#!/usr/bin/env python3
"""Check tracked files without ever printing matched credentials."""
import pathlib
import re
import subprocess
import sys

paths = subprocess.check_output(["git", "ls-files", "-z"]).decode().split("\0")
patterns = [re.compile(rb"sk-or-v1-[A-Za-z0-9_-]{20,}"), re.compile(rb"gh[pousr]_[A-Za-z0-9_]{30,}")]
failures = []
for name in filter(None, paths):
    path = pathlib.Path(name)
    if path.name == ".env" or (path.name.startswith(".env.") and path.name != ".env.example") or path.name == "auth.json":
        failures.append(name)
    if path.is_file() and any(pattern.search(path.read_bytes()) for pattern in patterns):
        failures.append(name)
if failures:
    print("Credential check failed in: " + ", ".join(sorted(set(failures))))
    sys.exit(1)
print("Tracked-file credential check passed.")
