"""Bound an owned local HTTPS/browser test tree; never contacts real providers."""
from pathlib import Path
import argparse
import json
import os
import socket
import subprocess
import sys
import time
import uuid

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/mcp_profile'))
from profile_process import GATE, run_bounded


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--worker', action='store_true')
    parser.add_argument('--directory')
    parser.add_argument('--output', required=True)
    parser.add_argument('--inspect', action='store_true')
    args = parser.parse_args()
    out = Path(args.output).resolve()
    if not out.is_relative_to(ROOT) or out.is_symlink():
        return 2
    if not args.worker:
        directory = ROOT / 'storage/providers-email-20261030' / ('browser-' + str(uuid.uuid4()))
        directory.mkdir(parents=True)
        out.mkdir(parents=True, exist_ok=True)
        (out / 'raw').mkdir(exist_ok=True)
        (out / 'screenshots').mkdir(exist_ok=True)
        command = [sys.executable, '-E', '-s', '-B', str(Path(__file__).resolve()), '--worker', '--directory', str(directory), '--output', str(out)]
        if args.inspect:
            command.append('--inspect')
        result = run_bounded(command, cwd=ROOT, timeout=1200, max_output_bytes=1048576)
        (directory / 'bounded-stdout.private.txt').write_bytes(result.stdout)
        receipt = {'exit': result.exit_code, 'owned_tree_containment': True, 'directory': directory.relative_to(ROOT).as_posix(), 'elapsed_ns': result.elapsed_ns}
        (directory / 'bounded-receipt.public.json').write_text(json.dumps(receipt, indent=2), encoding='utf-8')
        print(json.dumps(receipt), flush=True)
        return result.exit_code
    if sys.stdin.buffer.readline(64) != GATE:
        return 2
    directory = Path(args.directory).resolve()
    if directory.parent != ROOT / 'storage/providers-email-20261030' or not directory.name.startswith('browser-') or not directory.is_dir():
        return 2
    env = os.environ.copy()
    env.update(APP_ENV='testing', DB_DATABASE='emailing_testing', DB_USERNAME='emailing_testing', DB_URL='',
               PROVIDER_BROWSER_TEST='1', PROVIDER_BROWSER_RUN=directory.name.removeprefix('browser-'),
               PROVIDER_BROWSER_DIRECTORY=str(directory), PROVIDER_BROWSER_OUTPUT=str(out), PYTHONDONTWRITEBYTECODE='1')
    exit_code = 1
    with (directory / 'launcher-stdout.private.txt').open('wb') as stdout, (directory / 'launcher-stderr.private.txt').open('wb') as stderr:
        server = subprocess.Popen(['node', 'tests/support/mail-provider-browser-launch.mjs'], cwd=ROOT, env=env, stdin=subprocess.PIPE, stdout=stdout, stderr=stderr)
        try:
            deadline = time.monotonic() + 120
            while not (directory / 'browser-ready.public.json').exists():
                if server.poll() is not None or time.monotonic() > deadline:
                    raise RuntimeError('Owned fixture readiness failed')
                time.sleep(.1)
            command = ['node', 'tests/support/mail-provider-browser-inspect.mjs'] if args.inspect else [
                'node', 'node_modules/@playwright/test/cli.js', 'test', '--config', 'tests/support/mail-provider-playwright.config.mjs']
            with (directory / 'browser-stdout.private.txt').open('wb') as test_stdout, (directory / 'browser-stderr.private.txt').open('wb') as test_stderr:
                exit_code = subprocess.run(command, cwd=ROOT, env=env, stdout=test_stdout, stderr=test_stderr, timeout=900).returncode
        finally:
            if server.poll() is None:
                server.stdin.write(b'stop\n')
                server.stdin.flush()
                server.stdin.close()
            try:
                server.wait(timeout=90)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait(timeout=10)
                exit_code = 1
            settlement = directory / 'browser-settlement.public.json'
            facts = json.loads(settlement.read_text(encoding='utf-8')) if settlement.exists() else {}
            clean = facts.get('sql_clean') is True and facts.get('owned_php_exited') is True and facts.get('owned_listeners_closed') is True
            for port in (18867, 18868, 18869):
                with socket.socket() as peer:
                    peer.settimeout(.5)
                    if peer.connect_ex(('127.0.0.1', port)) == 0:
                        clean = False
            if not clean or server.returncode != 0:
                exit_code = 1
            (out / 'raw' / 'cleanup.json').write_text(json.dumps({'owned_sql_removed': facts.get('sql_clean') is True,
                'owned_php_exited': facts.get('owned_php_exited') is True, 'owned_ports_closed': clean}, indent=2), encoding='utf-8')
    return exit_code


if __name__ == '__main__':
    raise SystemExit(main())
