"""Profile launcher with a hard wall-clock limit and owned-tree cleanup."""
import json
from pathlib import Path
import sys

from profile_process import GATE, run_bounded


def main(argv=None):
    arguments = list(sys.argv[1:] if argv is None else argv)
    if '--execute' not in arguments:
        from profile_cli import main as probe
        return probe(arguments)
    try:
        result = run_bounded([sys.executable, '-E', '-s', '-B', str(Path(__file__).resolve()),
                              '--owned-worker', *arguments],
                             cwd=Path(__file__).resolve().parents[2], timeout=180)
        if result.exit_code not in (0, 2):
            raise ValueError('unexpected_probe_exit')
        sys.stdout.buffer.write(result.stdout)
        sys.stdout.flush()
        return result.exit_code
    except Exception:
        print(json.dumps({'error': 'bounded_profile_probe_failed', 'profile_qualified': False,
                          'native_client_qualified': False}))
        return 2


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] == '--owned-worker':
        # Parent assigns the OS containment before sending the gate; no probe import before it.
        if sys.stdin.buffer.readline(64) != GATE:
            raise SystemExit(2)
        from profile_cli import main as probe
        raise SystemExit(probe(sys.argv[2:]))
    raise SystemExit(main())
