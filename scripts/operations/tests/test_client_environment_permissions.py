"""Local SEO permission contract tests; never read/write real credential values."""

from __future__ import annotations

import importlib.util
import os
from pathlib import Path
import pwd
import grp
import stat
import tempfile
from types import SimpleNamespace
from unittest import mock
import unittest

MODULE = Path(__file__).resolve().parents[1] / "lib" / "client_environment_permissions.py"
spec = importlib.util.spec_from_file_location("seo_permissions", MODULE)
permissions = importlib.util.module_from_spec(spec)
assert spec is not None and spec.loader is not None
spec.loader.exec_module(permissions)


class LocalSeoEnvironmentPermissionsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / "seo"
        self.client = self.root / "client" / "fixture-seo"
        self.client.mkdir(parents=True)
        self.root.chmod(0o755)
        (self.root / "client").chmod(0o755)
        self.root_env = self.root / ".env"
        self.client_env = self.client / ".env"
        self.root_env.write_text("APP_ENV=local\n")
        self.client_env.write_text("APP_URL=http://fixture-seo.test\n")
        self.owner_uid = os.getuid()
        self.runtime_user = pwd.getpwuid(self.owner_uid).pw_name
        self.runtime_group = grp.getgrgid(os.getgid()).gr_name
        self.root_env.chmod(0o664)
        self.client_env.chmod(0o600)
        self.client.chmod(0o750)

    def run_action(self, action, client=None):
        return permissions.run(
            action=action, root=self.root, client=client,
            group_name=self.runtime_group, runtime_user=self.runtime_user,
            operator_uid=self.owner_uid,
        )

    def test_prepare_corrects_only_selected_runtime_paths(self):
        unrelated = self.root / "unrelated"
        unrelated.write_text("do not modify")
        unrelated.chmod(0o666)
        original_root = self.root_env.read_bytes()
        original_client = self.client_env.read_bytes()
        self.run_action("prepare-client", "fixture-seo")
        self.assertEqual(0o640, stat.S_IMODE(self.root_env.stat().st_mode))
        self.assertEqual(0o640, stat.S_IMODE(self.client_env.stat().st_mode))
        self.assertEqual(0o2750, stat.S_IMODE(self.client.stat().st_mode))
        self.assertEqual(original_root, self.root_env.read_bytes())
        self.assertEqual(original_client, self.client_env.read_bytes())
        self.assertEqual(0o666, stat.S_IMODE(unrelated.stat().st_mode))

    def test_verify_refuses_bad_mode_without_modifying_it(self):
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("verify-root")
        self.assertEqual(0o664, stat.S_IMODE(self.root_env.stat().st_mode))

    def test_prepare_and_verify_retries_are_idempotent(self):
        self.run_action("prepare-client", "fixture-seo")
        stamp = (self.root_env.stat().st_mtime_ns, self.client_env.stat().st_mtime_ns)
        self.run_action("prepare-client", "fixture-seo")
        self.run_action("verify-client", "fixture-seo")
        self.assertEqual(stamp, (self.root_env.stat().st_mtime_ns, self.client_env.stat().st_mtime_ns))

    def test_new_client_env_can_be_missing_during_prepare_but_not_verify(self):
        self.client_env.unlink()
        self.run_action("prepare-client", "fixture-seo")
        self.assertFalse(self.client_env.exists())
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("verify-client", "fixture-seo")

    def test_refuses_symlinked_root_environment_without_touching_target(self):
        target = self.root / "target"
        target.write_text("not environment")
        self.root_env.unlink()
        self.root_env.symlink_to(target)
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("prepare-root")
        self.assertEqual("not environment", target.read_text())

    def test_preflight_rejects_bad_client_before_repairing_root(self):
        self.client_env.unlink()
        self.client_env.symlink_to(self.root / "missing-target")
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("prepare-client", "fixture-seo")
        self.assertEqual(0o664, stat.S_IMODE(self.root_env.stat().st_mode))

    def test_refuses_hardlinked_client_environment(self):
        self.root_env.chmod(0o640)
        os.link(self.client_env, self.root / "other")
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("prepare-client", "fixture-seo")

    def test_refuses_unowned_selected_file(self):
        self.root_env.chmod(0o640)
        with self.assertRaises(permissions.PermissionContractError):
            permissions.run(action="verify-root", root=self.root, client=None,
                group_name=self.runtime_group, runtime_user=self.runtime_user,
                operator_uid=self.owner_uid + 98765)

    def test_refuses_malformed_client_selection(self):
        with self.assertRaises(permissions.PermissionContractError):
            self.run_action("prepare-client", "../other")

    def test_nonmember_group_assignment_uses_bounded_sudo_fallback(self):
        # No privileged setup: simulate a mismatched group only at the initial
        # preparation stat; post-sudo inspection uses the real correct group.
        original_inspect = permissions._inspect
        calls = []
        inspected_root_env = 0

        def inspect(path, *, directory, operator_uid):
            nonlocal inspected_root_env
            details = original_inspect(path, directory=directory, operator_uid=operator_uid)
            if path == self.root_env:
                inspected_root_env += 1
                if inspected_root_env == 2:
                    return SimpleNamespace(st_gid=os.getgid() + 999,
                                           st_mode=details.st_mode,
                                           st_uid=details.st_uid,
                                           st_nlink=details.st_nlink,
                                           st_size=details.st_size)
            return details

        def fallback(command, **kwargs):
            calls.append(command)
            self.assertEqual(["sudo", "chgrp", "--", self.runtime_group, str(self.root_env)], command)
            return mock.Mock(returncode=0)

        with mock.patch.object(permissions, "_inspect", side_effect=inspect), \
             mock.patch.object(permissions.os, "chown", side_effect=PermissionError), \
             mock.patch.object(permissions.subprocess, "run", side_effect=fallback):
            self.run_action("prepare-root")
        self.assertEqual(1, len(calls))
        self.assertEqual(0o640, stat.S_IMODE(self.root_env.stat().st_mode))

    def test_client_setgid_fallback_is_verified_after_chmod(self):
        self.root_env.chmod(0o640)
        self.client_env.chmod(0o640)
        old = Path.chmod
        calls = []

        def chmod(path, mode, *args, **kwargs):
            if path == self.client and mode == 0o2750:
                return old(path, 0o750, *args, **kwargs)
            return old(path, mode, *args, **kwargs)

        def fallback(command, **kwargs):
            calls.append(command)
            self.assertEqual(["sudo", "chmod", "--", "2750", str(self.client)], command)
            old(self.client, 0o2750)
            return mock.Mock(returncode=0)

        with mock.patch.object(Path, "chmod", new=chmod), \
             mock.patch.object(permissions.subprocess, "run", side_effect=fallback):
            self.run_action("prepare-client", "fixture-seo")
        self.assertEqual(1, len(calls))
        self.assertEqual(0o2750, stat.S_IMODE(self.client.stat().st_mode))

    def test_script_calls_prepare_before_database_work_and_verifies_postwrite(self):
        bootstrap = (MODULE.parents[2] / "bootstrap-client-local.sh").read_text()
        self.assertLess(bootstrap.index('"$PERMISSIONS_HELPER" prepare-client'),
                        bootstrap.index('MYSQL_ADMIN=(sudo mysql'))
        self.assertLess(bootstrap.index('"$PERMISSIONS_HELPER" verify-client'),
                        bootstrap.index("MYSQL_GRANT_HOST="))
        creator = (MODULE.parents[2] / "create-client.sh").read_text()
        self.assertLess(creator.index('"$PERMISSIONS_HELPER" prepare-root'),
                        creator.index("gh auth status"))


if __name__ == "__main__":
    unittest.main()