"""Engage SEO package audit tests using disposable source fixtures only."""

from __future__ import annotations

import contextlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest import mock

MODULE = Path(__file__).resolve().parents[1] / "lib" / "package_audit_workflow.py"
SPEC = importlib.util.spec_from_file_location("seo_package_audit_workflow", MODULE)
workflow = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(workflow)


class SeoSelectedClientPackageAuditTest(unittest.TestCase):
    def setUp(self) -> None:
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.root = Path(temp.name) / "engage-seo"
        self.client = self.root / "client" / "fixture"
        (self.client / "config").mkdir(parents=True)
        (self.root / "artisan").write_text("placeholder\n")
        (self.client / "config" / "client.php").write_text("<?php return [];\n")
        self.patch_root = mock.patch.object(workflow, "ROOT", self.root)
        self.patch_root.start()
        self.addCleanup(self.patch_root.stop)

    def write_json(self, path: Path, data: dict) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(data, indent=2) + "\n")

    def prepare_package(self, name: str = "example/package") -> None:
        self.write_json(self.client / "composer.json", {
            "name": "example/client",
            "require": {"php": "^8.3", name: "dev-main"},
        })
        self.write_json(self.client / "composer.lock", {
            "packages": [{"name": name, "version": "dev-main", "source": {"reference": "abc123"}}],
            "packages-dev": [],
            "content-hash": "opaque-not-checked",
        })
        (self.client / "vendor" / name).mkdir(parents=True)
        (self.client / "vendor" / "autoload.php").write_text("<?php // fixture\n")

    @staticmethod
    def codes(result: dict, severity: str = "error") -> set[str]:
        return {entry["code"] for entry in result["findings"] if entry["severity"] == severity}

    def test_no_package_client_has_no_composer_or_vendor_requirement(self) -> None:
        result = workflow.audit_client(self.root, "fixture")
        self.assertEqual("absent", result["composer"]["state"])
        self.assertEqual("not_applicable", result["lock"]["state"])
        self.assertEqual("not_required", result["autoload"])
        self.assertEqual([], result["packages"])
        self.assertFalse(self.codes(result))

    def test_valid_lock_and_provider_config_are_not_mistaken_for_activation(self) -> None:
        self.prepare_package()
        provider = self.client / "config" / "client_packages.php"
        provider.write_text("<?php throw new Exception('must not execute');\n")
        (self.client / "config" / "features.php").write_text("<?php throw new Exception('must not execute');\n")
        (self.client / "config" / "verticals.php").write_text("<?php throw new Exception('must not execute');\n")
        (self.client / ".env").write_text("INVALID=must_not_read\n")
        (self.root / ".env").write_text("INVALID=must_not_read\n")
        before = {p: (p.read_bytes(), p.stat().st_mtime_ns) for p in (
            provider, self.client / "composer.json", self.client / "composer.lock",
            self.client / "config" / "features.php", self.client / ".env", self.root / ".env",
        )}
        original = Path.read_text

        def safe_read(path: Path, *args, **kwargs):
            if path.name.startswith(".env") or path.suffix == ".php":
                raise AssertionError(f"Audit tried to evaluate or read runtime configuration: {path}")
            return original(path, *args, **kwargs)

        with mock.patch.object(Path, "read_text", safe_read):
            result = workflow.audit_client(self.root, "fixture")
        self.assertFalse(self.codes(result))
        self.assertEqual("present_not_evaluated", result["package_config"])
        self.assertEqual("present_not_evaluated", result["configuration"]["features"])
        self.assertEqual("present_not_evaluated", result["configuration"]["verticals"])
        self.assertEqual("abc123", result["packages"][0]["lock"]["source_reference"])
        self.assertEqual("present", result["packages"][0]["vendor"])
        self.assertEqual(before, {p: (p.read_bytes(), p.stat().st_mtime_ns) for p in before})

    def test_missing_autoload_and_missing_lock_are_errors_not_repairs(self) -> None:
        self.prepare_package()
        (self.client / "vendor" / "autoload.php").unlink()
        (self.client / "composer.lock").unlink()
        result = workflow.audit_client(self.root, "fixture")
        self.assertEqual({"missing_client_autoload", "missing_client_lock"}, self.codes(result))
        self.assertFalse((self.client / "composer.lock").exists())

    def test_lock_entries_must_match_direct_requirements(self) -> None:
        self.prepare_package()
        self.write_json(self.client / "composer.lock", {"packages": [], "packages-dev": []})
        result = workflow.audit_client(self.root, "fixture")
        self.assertIn("missing_locked_requirement", self.codes(result))
        self.assertIsNone(result["packages"][0]["lock"])

    def test_missing_installed_package_is_not_reported_as_installed(self) -> None:
        self.prepare_package()
        (self.client / "vendor" / "example" / "package").rmdir()
        result = workflow.audit_client(self.root, "fixture")
        self.assertIn("missing_installed_package", self.codes(result))
        self.assertEqual("missing", result["packages"][0]["vendor"])

    def test_source_descriptor_valid_and_legacy_descriptor_absent(self) -> None:
        self.prepare_package()
        source = self.root.parent / "package-source"
        source.mkdir()
        self.write_json(source / "composer.json", {"name": "example/package"})
        self.write_json(source / "engage-package.json", {
            "name": "example/package", "platform": "seo", "kind": "vertical", "schema": 1,
        })
        report = workflow.audit_client(self.root, "fixture", [source])
        self.assertEqual("valid", report["sources"][0]["descriptor"])
        self.assertFalse(self.codes(report))
        (source / "engage-package.json").unlink()
        report = workflow.audit_client(self.root, "fixture", [source])
        self.assertEqual("absent", report["sources"][0]["descriptor"])
        self.assertIn("source_descriptor_absent", self.codes(report, "warning"))
        self.assertFalse(self.codes(report))

    def test_source_platform_mismatch_is_reported(self) -> None:
        source = self.root.parent / "other-host-package"
        source.mkdir()
        self.write_json(source / "composer.json", {"name": "example/package"})
        self.write_json(source / "engage-package.json", {
            "name": "example/package", "platform": "core", "kind": "vertical", "schema": 1,
        })
        report = workflow.audit_client(self.root, "fixture", [source])
        self.assertIn("source_descriptor_platform_mismatch", self.codes(report))

    def test_runtime_vendor_path_contract_rejects_custom_composer_vendor_directory(self) -> None:
        self.prepare_package()
        self.write_json(self.client / "composer.json", {
            "require": {"example/package": "dev-main"},
            "config": {"vendor-dir": "dependencies"},
        })
        report = workflow.audit_client(self.root, "fixture")
        self.assertIn("unsupported_vendor_directory", self.codes(report))

    def test_development_only_package_may_be_absent_from_no_dev_vendor(self) -> None:
        self.prepare_package()
        self.write_json(self.client / "composer.json", {
            "require": {"php": "^8.3", "example/package": "dev-main"},
            "require-dev": {"example/testing-library": "^1.0"},
        })
        self.write_json(self.client / "composer.lock", {
            "packages": [{"name": "example/package", "version": "dev-main"}],
            "packages-dev": [{"name": "example/testing-library", "version": "1.0.0"}],
        })
        report = workflow.audit_client(self.root, "fixture")
        self.assertFalse(self.codes(report))
        self.assertEqual("development", report["packages"][1]["scope"])
        self.assertIn("development_package_not_installed", self.codes(report, "info"))

    def test_invalid_source_descriptor_schema_is_rejected(self) -> None:
        self.prepare_package()
        source = self.root.parent / "package-source"
        source.mkdir()
        self.write_json(source / "composer.json", {"name": "example/package"})
        self.write_json(source / "engage-package.json", {
            "name": "example/package", "platform": "seo", "kind": "vertical", "schema": 5,
        })
        report = workflow.audit_client(self.root, "fixture", [source])
        self.assertIn("source_descriptor_schema_invalid", self.codes(report))

    def test_composer_config_is_not_interpreted_as_package_provider_registration(self) -> None:
        self.prepare_package()
        result = workflow.audit_client(self.root, "fixture")
        self.assertEqual("absent", result["package_config"])
        self.assertFalse(any("registered" in str(package) for package in result["packages"]))

    def test_no_client_and_invalid_client_key_are_rejected(self) -> None:
        report = workflow.audit_client(self.root, "missing")
        self.assertIn("client_missing", self.codes(report))
        with self.assertRaises(ValueError):
            workflow.audit_client(self.root, "../fixture")

    def test_json_cli_returns_nonzero_for_structural_failure(self) -> None:
        self.prepare_package()
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            self.assertEqual(0, workflow.main(["fixture", "--json"]))
        data = json.loads(out.getvalue())
        self.assertEqual("seo", data["platform"])
        self.assertEqual("valid", data["lock"]["state"])
        (self.client / "composer.lock").unlink()
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            self.assertEqual(1, workflow.main(["fixture", "--json"]))
        self.assertIn("missing_client_lock", self.codes(json.loads(out.getvalue())))


if __name__ == "__main__":
    unittest.main()