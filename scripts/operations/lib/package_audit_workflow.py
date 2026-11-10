#!/usr/bin/env python3
"""Read-only source/Composer evidence for an Engage SEO selected client.

This does not load client PHP configuration, bootstrap Laravel or repair packages.
The SEO ClientPackageLoader and FeatureManager remain the runtime authorities.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import re
import sys
from typing import Any

ROOT = Path(__file__).resolve().parents[3]
CLIENT_KEY_PATTERN = re.compile(r"^[a-z0-9][a-z0-9_-]*$")
PACKAGE_PATTERN = re.compile(r"^[a-z0-9_.-]+/[a-z0-9_.-]+$")
NOT_VERIFIED = [
    "Composer dependency solver and content-hash consistency",
    "Installed Composer revision, PSR-4 class loading and provider validity",
    "PHP configuration and selected-client package provider registration",
    "Feature/vertical activation, provider contributions and migration state",
    "Capability applicability, credential readiness and deployed runtime",
]


def finding(result: dict[str, Any], severity: str, code: str, detail: str) -> None:
    result["findings"].append({"severity": severity, "code": code, "detail": detail})


def read_object(path: Path, result: dict[str, Any], code: str) -> dict[str, Any] | None:
    try:
        document = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError) as exc:
        finding(result, "error", code, f"Cannot read valid JSON object at {path}: {exc}")
        return None
    if not isinstance(document, dict):
        finding(result, "error", code, f"Expected a JSON object at {path}.")
        return None
    return document


def is_platform_requirement(name: str) -> bool:
    return name in ("php", "hhvm") or name.startswith(("ext-", "lib-", "composer-"))


def requirements(document: dict[str, Any], result: dict[str, Any]) -> dict[str, str]:
    selected: dict[str, str] = {}
    for field in ("require", "require-dev"):
        values = document.get(field, {})
        if not isinstance(values, dict):
            finding(result, "error", "invalid_composer_requirements", f"composer.json {field} must be a JSON object.")
            continue
        for package, constraint in values.items():
            if not isinstance(package, str) or not isinstance(constraint, str) or not constraint.strip():
                finding(result, "error", "invalid_composer_requirement", f"composer.json {field} contains an invalid entry.")
                continue
            if is_platform_requirement(package):
                continue
            if PACKAGE_PATTERN.fullmatch(package) is None:
                finding(result, "error", "invalid_composer_package", f"Invalid Composer package name: {package}")
                continue
            if package in selected and selected[package] != constraint:
                finding(result, "error", "conflicting_requirement", f"Conflicting constraints for {package}.")
            else:
                selected[package] = constraint
    return dict(sorted(selected.items()))


def locked_packages(document: dict[str, Any], result: dict[str, Any]) -> dict[str, dict[str, Any]]:
    locked: dict[str, dict[str, Any]] = {}
    for field in ("packages", "packages-dev"):
        entries = document.get(field, [])
        if not isinstance(entries, list):
            finding(result, "error", "invalid_lock_packages", f"composer.lock {field} must be a list.")
            continue
        for package in entries:
            if not isinstance(package, dict) or not isinstance(package.get("name"), str):
                finding(result, "error", "invalid_lock_entry", f"composer.lock {field} contains an invalid package entry.")
                continue
            name = package["name"]
            if PACKAGE_PATTERN.fullmatch(name) is None or name in locked:
                finding(result, "error", "invalid_lock_entry", f"Invalid or duplicate locked package: {name}")
                continue
            version = package.get("version")
            source = package.get("source", {})
            reference = source.get("reference") if isinstance(source, dict) else None
            if not isinstance(version, str) or not version.strip():
                finding(result, "error", "invalid_lock_entry", f"Locked package {name} has no valid version.")
            locked[name] = {
                "version": version if isinstance(version, str) else None,
                "source_reference": reference if isinstance(reference, str) else None,
            }
    return dict(sorted(locked.items()))


def inspect_source(path: Path, result: dict[str, Any], required: dict[str, str]) -> dict[str, Any]:
    source: dict[str, Any] = {
        "path": str(path), "state": "missing", "package": None, "descriptor": "not_evaluated",
    }
    if not path.is_dir():
        finding(result, "error", "source_missing", f"Source checkout is not a directory: {path}")
        return source
    manifest = path / "composer.json"
    if not manifest.is_file():
        finding(result, "error", "source_composer_missing", f"Source checkout has no composer.json: {path}")
        return source
    composer = read_object(manifest, result, "invalid_source_composer")
    if composer is None:
        source["state"] = "invalid"
        return source
    name = composer.get("name")
    if not isinstance(name, str) or PACKAGE_PATTERN.fullmatch(name) is None:
        finding(result, "error", "invalid_source_name", f"Source package name must be vendor/package: {path}")
        source["state"] = "invalid"
        return source
    source["state"] = "valid"
    source["package"] = name
    source["selected_in_client"] = name in required
    if name not in required:
        finding(result, "warning", "source_not_selected", f"Source package {name} is not a direct client Composer requirement.")
    descriptor_path = path / "engage-package.json"
    if not descriptor_path.is_file():
        source["descriptor"] = "absent"
        finding(result, "warning", "source_descriptor_absent", f"Source package {name} has no engage-package.json; no registration metadata is inferred.")
        return source
    descriptor = read_object(descriptor_path, result, "invalid_source_descriptor")
    if descriptor is None:
        source["descriptor"] = "invalid"
        return source
    source["descriptor"] = "valid"
    source["kind"] = descriptor.get("kind")
    source["platform"] = descriptor.get("platform")
    if descriptor.get("schema") != 1:
        finding(result, "error", "source_descriptor_schema_invalid", f"Source {name} descriptor does not use supported schema 1.")
        source["descriptor"] = "invalid"
    if descriptor.get("name") != name:
        finding(result, "error", "source_descriptor_name_mismatch", f"Descriptor name differs from source Composer package {name}.")
        source["descriptor"] = "invalid"
    if descriptor.get("platform") != "seo":
        finding(result, "error", "source_descriptor_platform_mismatch", f"Source {name} descriptor is not declared for SEO.")
        source["descriptor"] = "invalid"
    if descriptor.get("kind") not in ("vertical", "integration", "library"):
        finding(result, "error", "source_descriptor_kind_invalid", f"Source {name} descriptor has an unsupported kind.")
        source["descriptor"] = "invalid"
    return source


def audit_client(root: Path, client_key: str, source_paths: list[Path] | None = None) -> dict[str, Any]:
    if CLIENT_KEY_PATTERN.fullmatch(client_key) is None:
        raise ValueError("Client key must use lowercase letters, numbers, hyphens or underscores.")

    client = root / "client" / client_key
    result: dict[str, Any] = {
        "schema": 1,
        "platform": "seo",
        "client": client_key,
        "mode": "read_only",
        "composer": {"state": "absent", "package_requirements": {}},
        "lock": {"state": "not_applicable", "locked_packages": {}},
        "autoload": "not_required",
        "package_config": "absent",
        "configuration": {"client": "absent", "features": "absent", "verticals": "absent"},
        "sources": [],
        "packages": [],
        "findings": [],
        "not_verified": list(NOT_VERIFIED),
    }
    if not client.is_dir():
        finding(result, "error", "client_missing", f"SEO client directory does not exist: client/{client_key}")
        return result
    if not (client / "config").is_dir():
        finding(result, "error", "client_config_directory_missing", f"SEO client config directory is missing: client/{client_key}/config")
    for key in result["configuration"]:
        if (client / "config" / f"{key}.php").is_file():
            result["configuration"][key] = "present_not_evaluated"
    if (client / "config" / "client_packages.php").is_file():
        result["package_config"] = "present_not_evaluated"
        finding(result, "info", "php_config_not_evaluated", "Selected-client PHP package provider and environment-key configuration was not evaluated.")

    composer_path = client / "composer.json"
    lock_path = client / "composer.lock"
    required: dict[str, str] = {}
    development_only: set[str] = set()
    locked: dict[str, dict[str, Any]] = {}
    if not composer_path.is_file():
        if lock_path.exists():
            result["lock"]["state"] = "orphaned"
            finding(result, "error", "orphaned_lock", "composer.lock exists without selected-client composer.json.")
        if (client / "vendor" / "autoload.php").exists():
            result["autoload"] = "unexpected"
            finding(result, "warning", "orphaned_autoload", "Client has vendor/autoload.php but no composer.json; SEO will not load it.")
    else:
        result["composer"]["state"] = "invalid"
        composer = read_object(composer_path, result, "invalid_client_composer")
        if composer is not None:
            result["composer"]["state"] = "valid"
            required = requirements(composer, result)
            production = composer.get("require", {})
            development = composer.get("require-dev", {})
            if isinstance(production, dict) and isinstance(development, dict):
                development_only = set(development) - set(production)
            result["composer"]["package_requirements"] = required
            vendor_dir = composer.get("config", {})
            if not isinstance(vendor_dir, dict):
                finding(result, "error", "invalid_composer_config", "composer.json config must be an object when supplied.")
            elif vendor_dir.get("vendor-dir", "vendor") != "vendor":
                finding(result, "error", "unsupported_vendor_directory", "SEO ClientPackageLoader requires the default client/vendor directory.")

        if not (client / "vendor" / "autoload.php").is_file():
            result["autoload"] = "missing"
            finding(result, "error", "missing_client_autoload", "Selected-client composer.json exists but vendor/autoload.php is missing.")
        else:
            result["autoload"] = "present"

        if not lock_path.is_file():
            result["lock"]["state"] = "missing"
            finding(result, "error", "missing_client_lock", "Selected-client composer.json has no composer.lock; no locked deployment revision is established.")
        else:
            result["lock"]["state"] = "invalid"
            lock = read_object(lock_path, result, "invalid_client_lock")
            if lock is not None:
                result["lock"]["state"] = "valid"
                locked = locked_packages(lock, result)
                result["lock"]["locked_packages"] = locked
                for name in required:
                    if name not in locked:
                        finding(result, "error", "missing_locked_requirement", f"Required package {name} has no composer.lock entry.")

    for name, constraint in required.items():
        target = client / "vendor" / name
        vendor_state = "missing"
        if target.is_symlink():
            vendor_state = "local_link" if target.exists() else "broken_local_link"
        elif target.is_dir():
            vendor_state = "present"
        if vendor_state in ("missing", "broken_local_link"):
            if name in development_only and vendor_state == "missing":
                finding(result, "info", "development_package_not_installed", f"Development-only package {name} is absent, as expected with composer install --no-dev.")
            else:
                finding(result, "error", "missing_installed_package", f"Composer requirement {name} is not present under client/vendor ({vendor_state}).")
        elif vendor_state == "local_link":
            finding(result, "info", "local_package_link", f"Composer requirement {name} is linked to a local source checkout; not a deployed revision.")
        result["packages"].append({
            "name": name,
            "constraint": constraint,
            "scope": "development" if name in development_only else "runtime",
            "lock": locked.get(name),
            "vendor": vendor_state,
        })

    for path in source_paths or []:
        result["sources"].append(inspect_source(path, result, required))
    return result


def print_human(result: dict[str, Any]) -> None:
    print(f"SEO selected-client package audit (read-only): {result['client']}")
    print(f"  Composer:  {result['composer']['state']} ({len(result['composer']['package_requirements'])} package requirements)")
    print(f"  Lock:      {result['lock']['state']} ({len(result['lock']['locked_packages'])} locked packages)")
    print(f"  Autoload:  {result['autoload']}")
    print(f"  Providers: {result['package_config']}")
    for package in result["packages"]:
        locked = "locked" if package["lock"] is not None else "not locked"
        print(f"  Package:   {package['name']} ({locked}; vendor: {package['vendor']})")
    for source in result["sources"]:
        print(f"  Source:    {source['path']} ({source['state']}; descriptor: {source['descriptor']})")
    for item in result["findings"]:
        print(f"  {item['severity'].upper()}: {item['code']}: {item['detail']}")
    print("  Not verified: provider config, PHP registration, feature/vertical selection, migrations, credentials or deployment readiness.")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Read-only Engage SEO selected-client package audit")
    parser.add_argument("client", help="Selected-client directory key (no .env selection required)")
    parser.add_argument("--source", action="append", default=[], metavar="PATH", help="Optional package source checkout; repeatable")
    parser.add_argument("--json", action="store_true", help="Print machine-readable JSON evidence")
    args = parser.parse_args(argv)
    try:
        result = audit_client(ROOT, args.client, [Path(path).resolve() for path in args.source])
    except ValueError as exc:
        parser.error(str(exc))
    if args.json:
        print(json.dumps(result, indent=2, ensure_ascii=False))
    else:
        print_human(result)
    return 1 if any(item["severity"] == "error" for item in result["findings"]) else 0


if __name__ == "__main__":
    sys.exit(main())