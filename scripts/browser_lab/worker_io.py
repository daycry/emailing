"""Bounded private command output and closed public receipts inside a native job."""
from pathlib import Path
import json
import os
import math
import re
import subprocess
import time
import uuid

from contract import require

ROOT = Path(__file__).resolve().parents[2]


def directory_for(plan):
    directory = ROOT / 'storage/mcp-google-docker' / plan.run
    current = ROOT
    for part in ('storage', 'mcp-google-docker', plan.run):
        current /= part
        require(current.is_dir() and not current.is_symlink() and not current.is_junction(), 'browser_directory_refused')
    require(directory.resolve() == directory and ',' not in str(directory), 'browser_directory_refused')
    return directory


class WorkerIO:
    def __init__(self, plan, phase):
        require(phase in ('exercise', 'cleanup'), 'browser_phase_refused')
        self.plan, self.phase = plan, phase
        self.directory = directory_for(plan)
        self.sequence = 0

    @staticmethod
    def private_bytes(path, limit):
        require(path.is_file() and not path.is_symlink() and not path.is_junction(), 'browser_output_link_refused')
        before = path.stat()
        require(before.st_nlink == 1 and before.st_size <= limit, 'browser_command_output_limit')
        with path.open('rb') as stream:
            opened = os.fstat(stream.fileno())
            require((opened.st_dev, opened.st_ino) == (before.st_dev, before.st_ino) and opened.st_nlink == 1,
                    'browser_output_identity_changed')
            body = stream.read(limit + 1)
        require(len(body) <= limit, 'browser_command_output_limit')
        return body

    def save(self, name, value):
        require(re.fullmatch('[a-z-]+[.]public[.]json', name) is not None, 'browser_receipt_name_refused')
        destination = self.directory / name
        require(not destination.is_symlink() and not destination.is_junction()
                and (not destination.exists() or destination.stat().st_nlink == 1), 'browser_receipt_link_refused')
        temporary = self.directory / ('.write-' + uuid.uuid4().hex)
        with temporary.open('xb') as stream:
            stream.write((json.dumps(value, indent=2) + '\n').encode('utf-8'))
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, destination)

    def public(self, name, *, optional=False):
        require(name in ('claim.public.json', 'mutation.public.json', 'source-manifest.public.json',
                         'exercise.public.json', 'cleanup.public.json', 'fixture-state.public.json'), 'browser_receipt_name_refused')
        path = self.directory / name
        if optional and not path.exists():
            require(not path.is_symlink(), 'browser_receipt_link_refused')
            return None
        require(path.is_file() and not path.is_symlink() and not path.is_junction() and path.stat().st_nlink == 1
                and 0 < path.stat().st_size <= 1048576, 'browser_receipt_refused')
        return json.loads(path.read_bytes())

    def command(self, label, argv, *, timeout=30, check=True, env=None, limit=1048576):
        require(re.fullmatch('[a-z][a-z0-9-]{0,70}', label) is not None, 'browser_command_label_refused')
        require(type(timeout) in (int, float) and math.isfinite(timeout) and 0 < timeout <= 1200
                and type(limit) is int and 0 < limit <= 8 * 1048576, 'browser_command_budget_refused')
        require(isinstance(argv, list) and argv and all(isinstance(arg, str) and arg and not any(c in arg for c in ('\0', '\r', '\n'))
                for arg in argv), 'browser_command_arguments_refused')
        self.sequence += 1
        stem = f'{self.phase}-{self.sequence:04d}-{label}'
        output = self.directory / (stem + '.stdout.private.txt')
        error = self.directory / (stem + '.stderr.private.txt')
        started = time.monotonic()
        exit_code, primary = None, None
        try:
            with output.open('xb') as out, error.open('xb') as err:
                child = subprocess.Popen(argv, cwd=ROOT, env=env, stdin=subprocess.DEVNULL, stdout=out, stderr=err,
                    creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
                child_primary = None
                try:
                    while child.poll() is None:
                        require(output.stat().st_size + error.stat().st_size <= limit, 'browser_command_output_limit')
                        require(time.monotonic() - started < timeout, 'browser_command_deadline')
                        time.sleep(0.02)
                    exit_code = child.returncode
                except BaseException as failure:
                    child_primary = failure
                    raise
                finally:
                    try:
                        if child.poll() is None:
                            child.kill()  # Only this newly created child; outer native job owns descendants.
                        child.wait(timeout=5)
                    except BaseException:
                        if child_primary is None:
                            raise
                        child_primary.add_note('browser_command_child_settlement_failed')
            require(output.stat().st_size + error.stat().st_size <= limit, 'browser_command_output_limit')
            result = subprocess.CompletedProcess(argv, exit_code, self.private_bytes(output, limit), self.private_bytes(error, limit))
            require(not check or exit_code == 0, 'browser_command_failed')
            return result
        except BaseException as failure:
            primary = failure
            raise
        finally:
            receipt = {'label': label, 'exit': exit_code, 'completed': primary is None,
                       'elapsed_ns': int((time.monotonic() - started) * 1e9)}
            if primary:
                receipt['failure_class'] = type(primary).__name__
            # A numbered receipt is exclusive; it never replaces another command.
            try:
                with (self.directory / (stem + '.receipt.public.json')).open('x', encoding='utf-8', newline='\n') as stream:
                    stream.write(json.dumps(receipt, indent=2) + '\n')
                    stream.flush()
                    os.fsync(stream.fileno())
            except BaseException:
                if primary is None:
                    raise
                primary.add_note('browser_command_receipt_unavailable')
