"""Check an internal MCP profile; credentials and network remain opt-in."""
from pathlib import Path
import sys


def main():
    components = Path(__file__).resolve().parent / 'mcp_profile'
    sys.path.insert(0, str(components))
    from profile_bounded import main as bounded_main
    return bounded_main()


if __name__ == '__main__':
    raise SystemExit(main())
