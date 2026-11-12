#!/usr/bin/env python3
"""Narrow local SEO root/client environment permission preparation and verification.

Only the exact selected root/client .env and selected-client directory are in
scope. No recursive checkout changes and no environment contents are read.
"""

from __future__ import annotations

import argparse
import grp
import os
from pathlib import Path
import pwd
import re
import stat
import subprocess
import sys

_CLIENT_KEY = re.compile(r"[a-z0-9][a-z0-9_-]{0,127}\Z")
_MAX_ENV = 128 * 1024


class PermissionContractError(Exception):
    pass


def _fail(reason: str) -> None:
    raise PermissionContractError(reason)


def _inspect(path: Path, *, directory: bool, operator_uid: int) -> os.stat_result:
    try:
        info = path.lstat()
    except OSError:
        _fail(f"Required runtime path is missing or inaccessible: {path}")
    if ((not stat.S_ISDIR(info.st_mode) if directory else not stat.S_ISREG(info.st_mode))
            or info.st_uid != operator_uid
            or (not directory and (info.st_nlink != 1 or info.st_size > _MAX_ENV))):
        _fail(f"Unsafe runtime path, type, owner, link count, or size: {path}")
    return info


def _identity(group_name: str, runtime_user: str) -> int:
    try:
        group = grp.getgrnam(group_name)
        user = pwd.getpwnam(runtime_user)
        memberships = set(os.getgrouplist(runtime_user, user.pw_gid))
    except (KeyError, OSError):
        _fail("The selected local runtime identity or group does not exist")
    if group.gr_gid not in memberships:
        _fail("Runtime user is not a member of the authorized group")
    return group.gr_gid


def _prepare(path: Path, *, directory: bool, operator_uid: int,
             group_name: str, group_gid: int) -> None:
    current = _inspect(path, directory=directory, operator_uid=operator_uid)
    expected = 0o2750 if directory else 0o640
    # Close excessive file access *before* changing its group, so a legacy
    # world-readable/group-writable .env is narrowed immediately. Directories
    # take the opposite order so chmod can establish setgid on the final group.
    if not directory and stat.S_IMODE(current.st_mode) != expected:
        try:
            path.chmod(expected)
        except OSError:
            _fail(f"Cannot set safe runtime permissions: {path}")
    if current.st_gid != group_gid:
        try:
            os.chown(path, -1, group_gid, follow_symlinks=False)
        except PermissionError:
            try:
                subprocess.run(["sudo", "chgrp", "--", group_name, str(path)],
                               check=True, stdout=subprocess.DEVNULL)
            except (OSError, subprocess.CalledProcessError):
                _fail(f"Cannot assign the authorized runtime group: {path}")
    if directory and stat.S_IMODE(_inspect(path, directory=True, operator_uid=operator_uid).st_mode) != expected:
        try:
            path.chmod(expected)
        except OSError:
            _fail(f"Cannot set safe runtime permissions: {path}")
        if stat.S_IMODE(path.lstat().st_mode) != expected:
            # On Linux, a nonmember owner can silently lose setgid on chmod.
            try:
                subprocess.run(["sudo", "chmod", "--", "2750", str(path)],
                               check=True, stdout=subprocess.DEVNULL)
            except (OSError, subprocess.CalledProcessError):
                _fail(f"Cannot set client directory group inheritance: {path}")
    _verify(path, directory=directory, operator_uid=operator_uid, group_gid=group_gid)


def _verify(path: Path, *, directory: bool, operator_uid: int, group_gid: int) -> None:
    info = _inspect(path, directory=directory, operator_uid=operator_uid)
    expected = 0o2750 if directory else 0o640
    if info.st_gid != group_gid or stat.S_IMODE(info.st_mode) != expected:
        _fail(f"Runtime group/mode mismatch for {path}; expected {expected:04o} and selected runtime group")


def run(*, action: str, root: Path, client: str | None,
        group_name: str, runtime_user: str,
        operator_uid: int | None = None) -> None:
    if action not in ("prepare-root", "verify-root", "prepare-client", "verify-client"):
        _fail("Unknown permissions action")
    if not root.is_absolute() or root == Path("/") or root.is_symlink():
        _fail("Expected an absolute, non-symlinked SEO root")
    if client is not None and not _CLIENT_KEY.fullmatch(client):
        _fail("Invalid selected client key")
    if action.endswith("client") and not client:
        _fail("Client environment permissions require a selected client")
    owner = os.getuid() if operator_uid is None else operator_uid
    if operator_uid is None and os.geteuid() == 0:
        _fail("Do not run the developer lifecycle as root")
    group_gid = _identity(group_name, runtime_user)
    # Inspect the entire selected scope before any permission change. A wrong
    # owner, symlink or hardlink must not produce a partial "repair".
    _inspect(root, directory=True, operator_uid=owner)
    root_env = root / ".env"
    _inspect(root_env, directory=False, operator_uid=owner)
    selected: Path | None = None
    selected_env: Path | None = None
    client_env_exists = False
    if action.endswith("client"):
        assert client is not None
        parent = root / "client"
        parent_info = _inspect(parent, directory=True, operator_uid=owner)
        if parent_info.st_mode & stat.S_IWOTH:
            _fail("Client parent must not be world-writable")
        selected = parent / client
        _inspect(selected, directory=True, operator_uid=owner)
        selected_env = selected / ".env"
        if selected_env.is_symlink():
            _fail("Refusing a symlinked selected-client environment")
        client_env_exists = selected_env.exists()
        if client_env_exists:
            _inspect(selected_env, directory=False, operator_uid=owner)
        elif action == "verify-client":
            _fail("Selected-client .env is missing; run platform-owned bootstrap")

    prepare = action.startswith("prepare")
    if prepare:
        _prepare(root_env, directory=False, operator_uid=owner,
                 group_name=group_name, group_gid=group_gid)
    else:
        _verify(root_env, directory=False, operator_uid=owner, group_gid=group_gid)
    if action.endswith("root"):
        return
    assert selected is not None and selected_env is not None
    if prepare:
        _prepare(selected, directory=True, operator_uid=owner,
                 group_name=group_name, group_gid=group_gid)
    else:
        _verify(selected, directory=True, operator_uid=owner, group_gid=group_gid)
    if client_env_exists:
        if prepare:
            _prepare(selected_env, directory=False, operator_uid=owner,
                     group_name=group_name, group_gid=group_gid)
        else:
            _verify(selected_env, directory=False, operator_uid=owner, group_gid=group_gid)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Prepare or verify local SEO runtime environment permissions")
    parser.add_argument("action", choices=("prepare-root", "verify-root", "prepare-client", "verify-client"))
    parser.add_argument("--root", type=Path, required=True)
    parser.add_argument("--client")
    parser.add_argument("--runtime-group", default="www-data")
    parser.add_argument("--runtime-user", default="www-data")
    args = parser.parse_args(argv)
    try:
        run(action=args.action, root=args.root, client=args.client,
            group_name=args.runtime_group, runtime_user=args.runtime_user)
    except PermissionContractError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())