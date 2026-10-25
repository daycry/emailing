#!/usr/bin/env python3
"""Check the proposed MCP contract and synthetic fixtures without running the app.

Requires Python 3.10+ and jsonschema 4.x. Exit 0: consistent; 1: contract mismatch;
2: unreadable input or missing validator. This proves schema/reference-model
consistency only, never authorization, encryption, SDK or client interoperability.
"""
from __future__ import annotations

import argparse
from copy import deepcopy
import hashlib
import json
from pathlib import Path
import sys

try:
    from jsonschema import Draft4Validator, Draft202012Validator, FormatChecker
except ImportError:
    print('MCP contract check: install jsonschema 4.x in an isolated Python environment.', file=sys.stderr)
    raise SystemExit(2)


VERSION = '0.1.0'
REST_SHA256 = 'e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f'
CURSOR_ORDERS = {
    'activity_attempts': 'id_desc', 'activity_list': 'activity_desc',
    'campaigns_list': 'id_desc', 'contacts_list': 'id_desc',
    'imports_get': 'row_number_asc', 'imports_list': 'id_desc',
    'lists_list': 'id_desc', 'suppressions_list': 'id_desc',
    'templates_list': 'id_desc', 'connection_info': 'id_desc',
    'campaigns_get': 'recipient_id_asc',
}


def read(path: Path):
    return json.loads(path.read_text(encoding='utf-8'))


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def cursor_decision(value, expected, now, validator) -> str:
    """Reference policy AFTER authenticated decryption; no cryptography proof."""
    if not validator.is_valid(value):
        return 'cursor_invalid'
    for name in ('resource_hash', 'account_id', 'user_id', 'grant_id',
                 'authorization_hash', 'filter_hash', 'operation', 'paged_projection'):
        if value[name] != expected[name]:
            return 'cursor_invalid'
    if value['issued_at'] > now + 30 or not 0 < value['expires_at'] - value['issued_at'] <= 900:
        return 'cursor_invalid'
    if value['expires_at'] <= now:
        return 'cursor_expired'
    if value['position']['kind'] != CURSOR_ORDERS[value['operation']]:
        return 'cursor_invalid'
    for name in ('account_id', 'user_id', 'snapshot_revision_id'):
        if value[name] is not None and int(value[name]) > 18446744073709551615:
            return 'cursor_invalid'
    if 'id' in value['position'] and int(value['position']['id']) > 18446744073709551615:
        return 'cursor_invalid'
    if value['operation'] == 'campaigns_get':
        if value['snapshot_revision_id'] is None:
            return 'cursor_invalid'
        if value['snapshot_revision_id'] != expected['snapshot_revision_id']:
            return 'version_conflict'
    elif value['snapshot_revision_id'] is not None:
        return 'cursor_invalid'
    if (value['operation'] == 'activity_list') != (value['resolved_window'] is not None):
        return 'cursor_invalid'
    return 'continue'


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--json', action='store_true', help='Print a bounded machine-readable summary')
    args = parser.parse_args()
    root = args.root.resolve()
    fixtures = root / 'tests/Contracts/fixtures' / ('mcp-v' + VERSION)
    bundle = read(root / 'docs/api/mcp-tools.json')
    oauth = read(root / 'docs/api/mcp-oauth.openapi.json')
    manifest = read(fixtures / 'manifest.json')
    failures: list[str] = []
    counts: dict[str, int] = {}

    def require(condition: bool, label: str) -> None:
        if not condition:
            failures.append(label)

    for name, expected in manifest['sha256'].items():
        # The manifest is versioned input, never an instruction to read outside
        # the declared public fixtures/contracts.
        relative = Path(name)
        require(not relative.is_absolute() and '..' not in relative.parts, 'manifest-relative-path')
        if relative.is_absolute() or '..' in relative.parts:
            continue
        require(digest(root / relative) == expected, 'manifest-hash:' + name)
    require(digest(root / 'docs/api/openapi.yaml') == REST_SHA256, 'accepted-rest-contract-changed')
    require(bundle['contract_version'] == VERSION == oauth['info']['version'], 'contract-version')
    require(manifest['contract_version'] == VERSION, 'fixture-manifest-version')
    names = [item['name'] for item in bundle['operations']]
    require(len(names) == 37 and len(set(names)) == 37, '37-unique-operations')
    tools = {item['name']: item for item in bundle['operations']}
    permissions = read(fixtures / 'permissions.json')
    require(set(names) == {item['name'] for item in permissions['operations']}, 'permission-operation-coverage')
    for expected in permissions['operations']:
        name = expected['name']
        actual = tools[name]
        require(actual['minimum_scopes'] == expected['minimum_scopes'], 'minimum-scopes:' + name)
        require(actual['annotations']['readOnlyHint'] == (not expected['mutating']), 'effect-annotation:' + name)
        require([item['scopes'] for item in actual['conditional_scopes']] ==
                [item['scopes'] for item in expected['conditional_scopes']], 'conditional-scopes:' + name)
    counts['permission_rows'] = len(permissions['operations'])
    validators = {}
    for name, tool in tools.items():
        for direction in ('inputSchema', 'outputSchema'):
            schema = tool[direction]
            Draft202012Validator.check_schema(schema)
            require(schema.get('type') == 'object' and schema.get('additionalProperties') is False,
                    'closed-root:' + name + ':' + direction)
            validators[name, direction] = Draft202012Validator(schema, format_checker=FormatChecker())
    for key, schema in [('error', bundle['errorSchema']), ('cursor', bundle['cursor']['decrypted_payload_schema'])]:
        Draft202012Validator.check_schema(schema)
        validators[key] = Draft202012Validator(schema, format_checker=FormatChecker())
    counts['embedded_tool_schemas'] = len(validators)
    coverage = {(name, direction, valid): 0 for name in names
                for direction in ('inputSchema', 'outputSchema') for valid in (False, True)}
    for case in read(fixtures / 'tools.json')['cases']:
        key = case['tool'], case['direction']
        observed = validators[key].is_valid(case['value'])
        require(observed == case['expected_valid'], 'tool-fixture:' + case['tool'] + ':' + case['case'])
        coverage[key[0], key[1], case['expected_valid']] += 1
    counts['tool_cases'] = sum(coverage.values())
    require(all(value > 0 for value in coverage.values()), 'positive-and-negative-input-output-for-each-tool')
    cursor_cases = read(fixtures / 'cursors.json')
    require(cursor_cases['contract_version'] == VERSION, 'fixture-version:cursors')
    for case in cursor_cases['cases']:
        require(cursor_decision(case['payload'], case['expected_context'], case['now'], validators['cursor'])
                == case['expected_decision'], 'cursor-model:' + case['case'])
    counts['cursor_reference_cases'] = len(cursor_cases['cases'])
    for family in ('errors', 'oauth'):
        cases = read(fixtures / (family + '.json'))
        require(cases['contract_version'] == VERSION, 'fixture-version:' + family)
        for case in cases['cases']:
            if family == 'errors':
                validator = validators['error']
            else:
                # OpenAPI 3.0 schemas use draft-04 constructs (not draft 2020-12).
                # Add components only for local #/components references.
                schema = deepcopy(oauth['components']['schemas'][case['schema']])
                schema['components'] = oauth['components']
                Draft4Validator.check_schema(schema)
                validator = Draft4Validator(schema, format_checker=FormatChecker())
            require(validator.is_valid(case['value']) == case['expected_valid'], family + ':' + case['case'])
        counts[family + '_cases'] = len(cases['cases'])
    require(oauth['x-admission-policy'] == bundle['admission'], 'shared-admission-policy')
    require(oauth['x-retention-policy'] == bundle['retention'], 'shared-retention-policy')
    require(oauth['x-key-lifecycle'] == bundle['key_lifecycle'], 'shared-key-lifecycle')
    require(oauth['x-binding-policy']['code_ttl_seconds'] == 300, 'code-ttl')
    require(oauth['x-binding-policy']['access_token_ttl_seconds'] == 3600, 'access-ttl')
    require(oauth['x-binding-policy']['refresh_token'] is False, 'no-refresh-profile')
    require(bundle['transport']['protocol_versions'] == ['2025-11-25', '2026-07-28'], 'target-protocol-profiles')
    require(all(item['runtime_qualified'] is False for item in bundle['transport']['profiles']), 'no-unproved-runtime-claim')
    report = {
        'classification': 'Static contract/schema consistency only; no app, SQL, SDK, cryptography, Google or client proof',
        'contract_version': VERSION, 'operations': len(tools), **counts,
        'failures': failures, 'passed': not failures,
    }
    if args.json:
        print(json.dumps(report, ensure_ascii=True))
    else:
        print(f"MCP contract {VERSION}: {len(tools)} operations, {counts['embedded_tool_schemas']} embedded schemas, "
              f"{counts['tool_cases']} tool / {counts['errors_cases']} error / {counts['oauth_cases']} OAuth cases; "
              f"{counts['cursor_reference_cases']} cursor reference cases; {len(failures)} failures")
        for failure in failures[:30]:
            print('- ' + failure)
        print('Static checks only; real transport, authorization and clients remain pending.')
    return int(bool(failures))


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except (OSError, ValueError, KeyError, TypeError) as error:
        print('MCP contract check could not complete: ' + type(error).__name__, file=sys.stderr)
        raise SystemExit(2)
