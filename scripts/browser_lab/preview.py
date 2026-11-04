"""Show the browser Docker profile without executing Docker, SQL or a browser."""
import argparse
import json
import uuid

from compose import ROOT, compose
from contract import BrowserPlan, SERVICES, image_id, require


def main(arguments=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--run-id')
    parser.add_argument('--image', action='append', default=[], metavar='SERVICE=sha256:ID')
    args = parser.parse_args(arguments)
    plan = BrowserPlan(args.run_id or str(uuid.uuid4()))
    result = {'mode': 'preview-only', 'filesystem_written': False, 'docker_used': False, 'sql_used': False,
              'plan': plan.summary()}
    if args.image:
        images = {}
        for item in args.image:
            pair = item.split('=', 1)
            require(len(pair) == 2 and pair[0] in SERVICES and pair[0] not in images, 'browser_image_argument_refused')
            images[pair[0]] = image_id(pair[1])
        result['compose'] = compose(plan, images, ROOT / 'storage/mcp-google-docker' / plan.run)
    print(json.dumps(result, indent=2))
    return 0


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except ValueError as error:
        print(json.dumps({'outcome': 'refused', 'code': str(error), 'profile_accepted': False}))
        raise SystemExit(2)
