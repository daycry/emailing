"""Runtime source inventory comparator. Compare runtime files with an independently approved artifact.

No Laravel, PHP, SQL, Docker or network access. Expected manifests must come from
accepted artifacts, never be copied from the runtime observation being checked.
"""

import argparse
import hashlib
import json
import re
from pathlib import Path, PurePosixPath


class InvalidInventory(ValueError):
    pass


SHA = re.compile(r"[0-9a-f]{64}\Z")
SCALAR_FIELDS = ("php_version", "platform", "reporter_sha256")
REQUIRED_FILES = {
    "bootstrap/app.php", "bootstrap/providers.php", "public/index.php",
    "artisan", "composer.json", "composer.lock", "package.json", "package-lock.json", "vite.config.js", "vendor/composer/installed.json",
}
REQUIRED_TREES = (
    "app/", "config/", "database/migrations/", "routes/",
    "resources/views/", "lang/", "public/build/", "vendor/",
)
ALLOWED_ROOTS = REQUIRED_TREES + ("bootstrap/",)


def reject_duplicates(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise InvalidInventory("duplicate_key")
        result[key] = value
    return result


def read_json(path):
    try:
        return parse_json(Path(path).read_bytes())
    except OSError:
        raise InvalidInventory("unreadable_json") from None


def parse_json(data):
    try:
        return json.loads(data.decode("utf-8-sig"), object_pairs_hook=reject_duplicates)
    except (UnicodeError, json.JSONDecodeError):
        raise InvalidInventory("unreadable_json") from None


def validate(document, kind):
    if not isinstance(document, dict) or document.get("schema_version") != 1:
        raise InvalidInventory("invalid_schema")
    if type(document["schema_version"]) is not int or document.get("kind") != kind:
        raise InvalidInventory("invalid_kind")
    for field in SCALAR_FIELDS:
        if not isinstance(document.get(field), str) or not document[field]:
            raise InvalidInventory("invalid_metadata")
    if not SHA.fullmatch(document["reporter_sha256"]):
        raise InvalidInventory("invalid_reporter_hash")
    files = document.get("files")
    if not isinstance(files, dict) or not files:
        raise InvalidInventory("invalid_files")
    folded = set()
    for name, digest in files.items():
        if not isinstance(name, str) or not name:
            raise InvalidInventory("invalid_path")
        parts = name.split("/")
        if (PurePosixPath(name).is_absolute() or "\\" in name or ":" in name
                or any(part in ("", ".", "..") for part in parts)
                or any(ord(char) < 32 or ord(char) == 127 for char in name)
                or any(part.casefold().startswith(".env") for part in parts)):
            raise InvalidInventory("invalid_path")
        if name not in REQUIRED_FILES and not name.startswith(ALLOWED_ROOTS):
            raise InvalidInventory("path_outside_allowlist")
        if name.casefold() in folded:
            raise InvalidInventory("case_colliding_paths")
        folded.add(name.casefold())
        if not isinstance(digest, str) or not SHA.fullmatch(digest):
            raise InvalidInventory("invalid_file_hash")
    if not REQUIRED_FILES.issubset(files):
        raise InvalidInventory("required_file_missing")
    if any(not any(name.startswith(prefix) for name in files) for prefix in REQUIRED_TREES):
        raise InvalidInventory("required_tree_missing")
    return files


def compare(expected, actual):
    expected_files = validate(expected, "accepted-runtime-source-manifest")
    actual_files = validate(actual, "runtime-visible-source-inventory")
    missing = sorted(expected_files.keys() - actual_files.keys())
    unexpected = sorted(actual_files.keys() - expected_files.keys())
    changed = sorted(name for name in expected_files.keys() & actual_files.keys()
                     if expected_files[name] != actual_files[name])
    metadata = [field for field in SCALAR_FIELDS if expected[field] != actual[field]]
    return {
        "schema_version": 1,
        "kind": "runtime-source-comparison",
        "passed": not (missing or unexpected or changed or metadata),
        "expected_count": len(expected_files),
        "observed_count": len(actual_files),
        "missing": missing,
        "unexpected": unexpected,
        "changed": changed,
        "metadata_mismatch": metadata,
    }


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--expected", required=True)
    parser.add_argument("--actual", required=True)
    args = parser.parse_args(argv)
    try:
        expected_bytes = Path(args.expected).read_bytes()
        actual_bytes = Path(args.actual).read_bytes()
        result = compare(parse_json(expected_bytes), parse_json(actual_bytes))
        result["expected_sha256"] = hashlib.sha256(expected_bytes).hexdigest()
        result["actual_sha256"] = hashlib.sha256(actual_bytes).hexdigest()
    except (InvalidInventory, OSError) as error:
        code = str(error) if isinstance(error, InvalidInventory) else "unreadable_json"
        print(json.dumps({"error": "qa_inventory_comparison_failed", "reason": code}))
        return 2
    print(json.dumps(result, sort_keys=True))
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
