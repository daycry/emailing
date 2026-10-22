#!/usr/bin/env python3
"""Reject private machine paths, identity numbers and local secrets in public files."""

from __future__ import annotations

import argparse
from pathlib import Path
import re
import subprocess
import sys


for stream in (sys.stdout, sys.stderr):
    if hasattr(stream, "reconfigure"):
        stream.reconfigure(encoding="utf-8", errors="backslashreplace")


PRIVATE_PATH = re.compile(
    rb"(?i)(?:[a-z]:[/\\]+Users[/\\]+|/Users/|/home/)(?=[A-Za-z0-9_.-])[^/\\\r\n\t\"'<> ]+"
)
IDENTITY_NUMBER = re.compile(rb"(?<![A-Za-z0-9])(?:[0-9]{8}|[XYZ][0-9]{7})[A-Z](?![A-Za-z0-9])")
PRIVATE_KEY = re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----")
ACCESS_TOKEN = re.compile(rb"\bgh[pousr]_[A-Za-z0-9_]{20,}\b")
SECRET_NAME = re.compile(r"PASSWORD|SECRET|APP_KEY|API_KEY|ACCESS_TOKEN")


def git(root: Path, *args: str) -> bytes:
    return subprocess.check_output(["git", "-C", str(root), *args], stderr=subprocess.PIPE)


def local_secrets(root: Path) -> set[bytes]:
    values: set[bytes] = set()
    for relative in (".env", ".env.testing", "infra/.env"):
        file = root / relative
        if not file.is_file():
            continue
        for line in file.read_text(encoding="utf-8-sig").splitlines():
            if "=" not in line or line.lstrip().startswith("#"):
                continue
            name, value = line.split("=", 1)
            value = value.strip().strip("\"'")
            if SECRET_NAME.search(name) and len(value) >= 8:
                values.add(value.encode())
    return values


def inspect(name: str, body: bytes, secrets: set[bytes]) -> list[str]:
    reasons = []
    filename = Path(name).name
    public_content = name.encode("utf-8") + b"\n" + body
    if filename == ".env" or (filename.startswith(".env.") and not filename.endswith(".example")):
        reasons.append("private environment file")
    for pattern, label in (
        (PRIVATE_PATH, "private machine path"),
        (IDENTITY_NUMBER, "possible personal identity number"),
        (PRIVATE_KEY, "private key"),
        (ACCESS_TOKEN, "access token"),
    ):
        if pattern.search(public_content):
            reasons.append(label)
    if any(secret in public_content for secret in secrets):
        reasons.append("local secret value")
    return reasons


def indexed_files(root: Path) -> list[tuple[str, bytes]]:
    records = git(root, "ls-files", "--stage", "-z").split(b"\0")
    entries = []
    for record in filter(None, records):
        metadata, name = record.split(b"\t", 1)
        _, oid, stage = metadata.split()
        if stage != b"0":
            raise RuntimeError("Resolve merge conflicts before checking public data.")
        entries.append((name.decode("utf-8"), oid))
    if not entries:
        return []
    payload = b"\n".join(oid for _, oid in entries) + b"\n"
    result = subprocess.run(
        ["git", "-C", str(root), "cat-file", "--batch"], input=payload,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True,
    ).stdout
    offset = 0
    files = []
    for name, _ in entries:
        end = result.index(b"\n", offset)
        _, kind, size = result[offset:end].split()
        if kind != b"blob":
            raise RuntimeError("Unexpected indexed object; inspect the index.")
        offset = end + 1
        length = int(size)
        files.append((name, result[offset:offset + length]))
        offset += length + 1
    return files


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--staged", action="store_true", help="Inspect the exact Git index contents")
    args = parser.parse_args()
    root = Path(git(Path.cwd(), "rev-parse", "--show-toplevel").decode().strip())
    if args.staged:
        files = indexed_files(root)
    else:
        names = sorted(set(git(root, "ls-files", "--cached", "--others", "--exclude-standard", "-z").split(b"\0")))
        files = []
        for encoded in filter(None, names):
            name = encoded.decode("utf-8")
            file = root / name
            if file.is_symlink():
                files.append((name, str(file.readlink()).encode()))
            elif file.is_file():
                files.append((name, file.read_bytes()))
    secrets = local_secrets(root)
    findings = [(name, inspect(name, body, secrets)) for name, body in files]
    findings = [(name, reasons) for name, reasons in findings if reasons]
    print(f"Public data check: {len(files)} files, {len(findings)} findings")
    for name, reasons in findings:
        print(f"{name}: {', '.join(reasons)}")
    return int(bool(findings))


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Public data check could not complete: {type(error).__name__}", file=sys.stderr)
        raise SystemExit(2)
