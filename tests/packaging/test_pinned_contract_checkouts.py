"""Pinned inputs retain exact identities in Git and every checkout.

HEAD checks require Git support for ``check-attr --source``.
"""
import argparse
import ast
from hashlib import sha256
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import unittest

PROJECT = Path(__file__).resolve().parents[2]
SOURCE = 'head'
MANIFEST = 'tests/Contracts/fixtures/mcp-v0.2.0/manifest.json'


def object_name(path):
    return ('HEAD:' if SOURCE == 'head' else ':') + path


def git_bytes(*arguments):
    result = subprocess.run(['git', '-C', str(PROJECT), *arguments],
                            capture_output=True, timeout=20)
    if result.returncode:
        raise RuntimeError('Unable to inspect a pinned Git input') from None
    return result.stdout


def pinned_inputs():
    manifest = json.loads(git_bytes('cat-file', 'blob', object_name(MANIFEST)))
    if manifest.get('contract_version') != '0.2.0' or type(manifest.get('sha256')) is not dict:
        raise ValueError('Unsupported pinned input manifest')
    pins = dict(manifest['sha256'])
    checker = ast.parse(git_bytes('cat-file', 'blob', object_name('scripts/check-mcp-contract.py')))
    rest = [ast.literal_eval(node.value) for node in checker.body
            if isinstance(node, ast.Assign)
            and any(isinstance(target, ast.Name) and target.id == 'REST_SHA256'
                    for target in node.targets)]
    if len(rest) != 1 or pins.setdefault('docs/api/openapi.yaml', rest[0]) != rest[0]:
        raise ValueError('Inconsistent REST identity')
    runtime = git_bytes('cat-file', 'blob', object_name('app/Services/Mcp/ContractArguments.php'))
    runtime_pin = re.search(rb"hash\('sha256', \$bytes\) !== '([0-9a-f]{64})'", runtime)
    if not runtime_pin or pins.get('docs/api/mcp-tools.json') != runtime_pin.group(1).decode('ascii'):
        raise ValueError('Runtime catalogue identity differs from its manifest')
    # Treat manifest paths as data: only declared public contracts/fixtures are eligible.
    for name, expected in pins.items():
        if type(name) is not str or type(expected) is not str:
            raise ValueError('Invalid pinned input')
        path = PurePosixPath(name)
        if (not re.fullmatch(r'[A-Za-z0-9_./-]+', name)
                or path.is_absolute() or '..' in path.parts
                or not name.startswith(('docs/api/', 'tests/Contracts/fixtures/mcp-v0.2.0/'))
                or path.name.startswith('.env') or path.name.endswith('.env')
                or path.name in ('request.json', 'owner.json', 'process.json', 'settlement.json')
                or path.name.endswith('-request.json')
                or not re.fullmatch(r'[0-9a-f]{64}', expected)):
            raise ValueError('Invalid pinned input')
    return pins


class PinnedContractCheckoutTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.pins = pinned_inputs()

    def test_attributes_in_the_selected_git_source_disable_text_conversion(self):
        selection = ['--source=HEAD'] if SOURCE == 'head' else ['--cached']
        for name in self.pins:
            with self.subTest(path=name):
                observed = git_bytes('check-attr', *selection, '-z', 'text', '--', name)
                self.assertEqual([name.encode('utf-8'), b'text', b'unset', b''],
                                 observed.split(b'\0'))

    def test_shipped_git_objects_retain_declared_contract_identities(self):
        for name, expected in self.pins.items():
            with self.subTest(path=name):
                body = git_bytes('cat-file', 'blob', object_name(name))
                self.assertEqual(expected, sha256(body).hexdigest())

    def test_lf_crlf_and_input_checkouts_retain_declared_contract_identities(self):
        for name, expected in self.pins.items():
            for autocrlf in ('false', 'true', 'input'):
                with self.subTest(path=name, autocrlf=autocrlf):
                    body = git_bytes('-c', 'core.autocrlf=' + autocrlf, 'cat-file',
                                     '--filters', '--path=' + name, object_name(name))
                    self.assertEqual(expected, sha256(body).hexdigest())


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--project-root', type=Path, default=PROJECT)
    parser.add_argument('--source', choices=('head', 'index'), default='head')
    options, rest = parser.parse_known_args()
    PROJECT = options.project_root.resolve(strict=True)
    SOURCE = options.source
    unittest.main(argv=[sys.argv[0], *rest])
