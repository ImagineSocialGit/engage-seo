"""Contract tests for the public SEO package.sh operator entrypoint.

The mutating implementation belongs to the existing local dev link helpers.
These tests use disposable helper stubs to verify correct, exact delegation.
"""

from __future__ import annotations

import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

SOURCE = Path(__file__).resolve().parents[2] / "package.sh"


class PackageOperatorCommandTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / "engage-seo"
        self.scripts = self.root / "scripts"
        self.scripts.mkdir(parents=True)
        (self.root / "artisan").write_text("fixture\n")
        shutil.copyfile(SOURCE, self.scripts / "package.sh")
        (self.scripts / "package.sh").chmod(0o755)
        (self.scripts / "operations/lib").mkdir(parents=True)
        for name, content in (
            ("dev-link-package.sh", '#!/usr/bin/env bash\nprintf "LINK:%s\\n" "$@"\n'),
            ("dev-unlink-package.sh", '#!/usr/bin/env bash\nprintf "UNLINK:%s\\n" "$@"\n'),
        ):
            path = self.scripts / name
            path.write_text(content)
            path.chmod(0o755)
        audit = self.scripts / "operations/lib/package_audit_workflow.py"
        audit.write_text('import json,sys\nprint(json.dumps(sys.argv[1:]))\n')
        self.source = Path(self.temp.name) / "package-checkout"
        self.source.mkdir()
        (self.source / "composer.json").write_text(json.dumps({"name": "example/provider"}))

    def execute(self, *args: str) -> subprocess.CompletedProcess:
        return subprocess.run(
            [str(self.scripts / "package.sh"), *args],
            cwd=self.source, capture_output=True, text=True, timeout=10, check=False,
        )

    def test_audit_delegates_client_and_flags_without_mutation(self) -> None:
        result = self.execute("audit", "client-one", "--json", "--source", str(self.source))
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(["client-one", "--json", "--source", str(self.source)], json.loads(result.stdout))

    def test_link_delegates_only_manifest_package_name_and_explicit_client(self) -> None:
        result = self.execute("link", "client-one", str(self.source))
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(
            ["LINK:example/provider", f"LINK:{self.source}", "LINK:client-one"],
            result.stdout.splitlines(),
        )

    def test_unlink_delegates_exact_package_and_client(self) -> None:
        result = self.execute("unlink", "client-one", "example/provider")
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(["UNLINK:example/provider", "UNLINK:client-one"], result.stdout.splitlines())

    def test_invalid_inputs_never_dispatch_helpers(self) -> None:
        for args in (
            ("link", "../other-client", str(self.source)),
            ("unlink", "../other-client", "example/provider"),
            ("unlink", "client-one", "other-package"),
            ("unlink", "client-one", "../bad"),
            ("link", "client-one", "relative-package"),
            ("link", "client-one"),
            ("unlink", "client-one"),
            ("update", "client-one", "example/provider"),
        ):
            with self.subTest(args=args):
                result = self.execute(*args)
                self.assertEqual(2, result.returncode)
                self.assertNotIn("LINK:", result.stdout)
                self.assertNotIn("UNLINK:", result.stdout)

    def test_invalid_or_symlinked_package_manifest_never_delegates(self) -> None:
        path = self.source / "composer.json"
        for value in ('not json', '{"name":"../bad"}', '[]'):
            with self.subTest(value=value):
                path.write_text(value)
                result = self.execute("link", "client-one", str(self.source))
                self.assertEqual(2, result.returncode)
                self.assertNotIn("LINK:", result.stdout)
        path.unlink()
        another = Path(self.temp.name) / "source.json"
        another.write_text('{"name":"example/provider"}')
        path.symlink_to(another)
        result = self.execute("link", "client-one", str(self.source))
        self.assertEqual(2, result.returncode)

    def test_help_describes_only_supported_workflows(self) -> None:
        result = self.execute("--help")
        self.assertEqual(0, result.returncode)
        self.assertIn("audit CLIENT_KEY", result.stdout)
        self.assertIn("link CLIENT_KEY", result.stdout)
        self.assertIn("unlink CLIENT_KEY", result.stdout)
        self.assertIn("package.sh update", result.stdout)
        self.assertIn("package.sh repair", result.stdout)
        self.assertIn("--confirm-plan", result.stdout)


if __name__ == "__main__":
    unittest.main()