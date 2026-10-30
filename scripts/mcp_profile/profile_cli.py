"""Profile entry point: defaults to a credential-free dry-run."""
import argparse
from hashlib import sha256
import json
from pathlib import Path, PurePosixPath
from uuid import UUID, uuid4

from profile_endpoints import EndpointChecks
from profile_receipts import EvidenceError, ReceiptStore
from profile_schema import PhpSchemaValidator
from profile_session import ProtocolSession
from profile_smoke import ProfileSmoke, SmokeInputs
from profile_transport import BoundHttpClient


class SafeParser(argparse.ArgumentParser):
    def error(self, message):
        raise EvidenceError('invalid_cli_arguments')


def read_inputs(project_root, relative):
    try:
        if type(relative) is not str or '\\' in relative:
            raise EvidenceError('invalid_private_input_path')
        path = PurePosixPath(relative)
        if path.is_absolute() or path.parts[:5] != ('storage', 'app', 'private', 'mcp-profile-input', path.name):
            raise EvidenceError('invalid_private_input_path')
        if len(path.parts) != 5 or path.suffix != '.json' or str(UUID(path.stem)) != path.stem:
            raise EvidenceError('invalid_private_input_path')
        root = Path(project_root).resolve(strict=True)
        current = root
        for part in path.parts:
            current = current / part
            if current.is_symlink() or (hasattr(current, 'is_junction') and current.is_junction()):
                raise EvidenceError('unsafe_private_input_path')
        if current.resolve(strict=True) != current or not current.is_file():
            raise EvidenceError('unsafe_private_input_path')
        with current.open('rb') as stream:
            return SmokeInputs.from_bytes(stream.read(16385))
    except Exception:
        raise EvidenceError('private_input_unavailable') from None


def main(argv=None, *, project_root=None):
    parser = SafeParser(description='Check a laboratory MCP profile; default: dry-run.')
    parser.add_argument('--profile', choices=['laragon', 'docker'], required=True)
    parser.add_argument('--protocol', choices=['2025-11-25', '2026-07-28'], default='2026-07-28')
    parser.add_argument('--issuer', default='https://localhost:18443')
    parser.add_argument('--ca-file')
    parser.add_argument('--inputs')
    parser.add_argument('--execute', action='store_true')
    root = Path(project_root).resolve() if project_root else Path(__file__).resolve().parents[2]
    try:
        args = parser.parse_args(argv)
        if not args.execute:
            print(json.dumps({'mode': 'dry-run', 'profile': args.profile, 'protocol': args.protocol,
                              'credentials_loaded': False, 'network_executed': False,
                              'profile_qualified': False, 'native_client_qualified': False}))
            return 0
        if not args.inputs:
            raise EvidenceError('missing_private_input')
        inputs = read_inputs(root, args.inputs)
        client = BoundHttpClient(args.issuer, timeout=30, max_response_bytes=8388608,
                                 ca_file=args.ca_file)
        schema = PhpSchemaValidator(root)
        contract = (root / 'docs/api/mcp-tools.json').read_bytes()
        source_files = sorted(Path(__file__).parent.glob('profile_*.py'))
        source_files.append(Path(__file__).with_name('profile_schema_bridge.php'))
        source_files.append(root / 'scripts/mcp-profile-evidence.py')
        sources = {path.name: sha256(path.read_bytes()).hexdigest() for path in source_files}
        store = ReceiptStore(root, args.profile, str(uuid4()))
        references = []

        def record(receipt):
            reference = store.write(receipt)
            references.append({'relative_path': reference.relative_path, 'sha256': reference.sha256})

        endpoints = EndpointChecks(args.issuer, request=client.request, record=record)
        session = ProtocolSession(args.protocol, args.issuer, contract, token=inputs.token,
                                   request=client.request, record=record, schema_validator=schema)
        smoke = ProfileSmoke(args.issuer, endpoints=endpoints, session=session)
        proof = smoke.run(inputs)
        if any(sha256(path.read_bytes()).hexdigest() != sources[path.name] for path in source_files):
            raise EvidenceError('runner_sources_changed')
        print(json.dumps({'mode': 'execute', 'profile': args.profile, 'protocol': args.protocol,
                          'result': proof, 'contract_sha256': sha256(contract).hexdigest(),
                          'runner_sources': sources, 'observations': references,
                          'profile_qualified': False, 'native_client_qualified': False}))
        return 0
    except Exception:
        print(json.dumps({'error': 'profile_probe_failed', 'profile_qualified': False,
                          'native_client_qualified': False}))
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
