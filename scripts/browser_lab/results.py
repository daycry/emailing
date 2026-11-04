"""Verify the actual Playwright JSON report, not just the CLI exit code."""
import json
import re

from contract import require
from worker_io import ROOT


def titles(selection):
    require(selection in ('full', 'e2e08'), 'browser_selection_refused')
    path = ROOT / 'tests/e2e/mcp-google.spec.mjs'
    require(path.is_file() and not path.is_symlink() and path.stat().st_nlink == 1, 'browser_test_source_refused')
    names = re.findall(r"^test\('([^']+)'", path.read_text(encoding='utf-8'), re.M)
    require(len(names) == len(set(names)) == 9, 'browser_original_case_inventory_changed')
    if selection == 'e2e08':
        names = [name for name in names if name.startswith('MCP E2E-08 ')]
        require(len(names) == 1, 'browser_stability_case_missing')
    return names


def verify_report(raw, expected):
    require(isinstance(raw, bytes) and 0 < len(raw) <= 8 * 1048576, 'browser_report_limit')
    report = json.loads(raw)
    require(isinstance(report, dict) and report.get('errors') == [] and isinstance(report.get('stats'), dict), 'browser_report_invalid')
    for key, value in {'expected': len(expected), 'unexpected': 0, 'flaky': 0, 'skipped': 0}.items():
        require(type(report['stats'].get(key)) is int and report['stats'][key] == value, 'browser_report_not_green')
    config = report.get('config', {})
    projects = config.get('projects', [])
    require(type(config.get('workers')) is int and config['workers'] == 1 and len(projects) == 1
            and projects[0].get('retries') == 0 and projects[0].get('repeatEach') == 1
            and projects[0].get('timeout') == 60000, 'browser_report_configuration_changed')
    found, ids = [], set()

    def visit(suites):
        require(isinstance(suites, list), 'browser_report_suite_invalid')
        for suite in suites:
            require(isinstance(suite, dict), 'browser_report_suite_invalid')
            for spec in suite.get('specs', []):
                require(isinstance(spec, dict) and spec.get('file') == 'mcp-google.spec.mjs' and spec.get('ok') is True
                        and isinstance(spec.get('id'), str) and spec['id'] and spec['id'] not in ids,
                        'browser_report_case_invalid')
                ids.add(spec['id'])
                tests = spec.get('tests', [])
                require(isinstance(tests, list) and len(tests) == 1, 'browser_report_case_invalid')
                test = tests[0]
                results = test.get('results', [])
                require(test.get('status') == 'expected' and test.get('expectedStatus') == 'passed'
                        and test.get('timeout') == 60000 and len(results) == 1
                        and results[0].get('status') == 'passed' and results[0].get('retry') == 0
                        and results[0].get('errors') == [] and not results[0].get('error'), 'browser_report_case_not_passed')
                found.append(spec.get('title'))
            visit(suite.get('suites', []))

    visit(report.get('suites'))
    require(len(found) == len(set(found)) == len(expected) and set(found) == set(expected), 'browser_report_case_inventory_differs')
    return {'cases': len(found), 'passed': len(found), 'failed': 0, 'flaky': 0, 'skipped': 0, 'retries': 0}
