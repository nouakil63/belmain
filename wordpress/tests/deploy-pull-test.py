#!/usr/bin/env python3
"""Deployment safety checks. Temporary fake sites only; no production or network use."""
from __future__ import annotations

import importlib.util
import json
import shutil
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "tools" / "deploy-pull.py"
SPEC = importlib.util.spec_from_file_location("belmains_deploy_pull", SCRIPT)
assert SPEC and SPEC.loader
DEPLOY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(DEPLOY)

OLD = "1" * 40
NEW = "2" * 40


class PullDeploymentTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="belmains-deploy-test-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.site = self.root / "sites" / "example"
        self.state = self.root / "private-deploy"
        self.source = self.root / "candidate" / "wordpress"
        self.targets = {
            "belmains": self.site / "wp-content" / "themes" / "belmains",
            "belmains-commerce": self.site / "wp-content" / "plugins" / "belmains-commerce",
            "belmains-crm": self.site / "wp-content" / "plugins" / "belmains-crm",
        }
        for name, target in self.targets.items():
            self.make_component(target, name, "old")
            self.make_component(self.source / name, name, "new")
        self.guard = self.site / "wp-content" / "mu-plugins" / "belmains-staging-guard.php"
        self.guard.parent.mkdir(parents=True)
        self.guard.write_text("<?php // keep sales closed\n")
        self.upload = self.site / "wp-content" / "uploads" / "customer.txt"
        self.upload.parent.mkdir()
        self.upload.write_text("customer media")
        self.config = self.site / "wp-config.php"
        self.config.write_text("<?php // existing configuration\n")
        self.other_plugin = self.site / "wp-content" / "plugins" / "woocommerce" / "woocommerce.php"
        self.other_plugin.parent.mkdir()
        self.other_plugin.write_text("<?php // untouched third-party plugin\n")
        self.deployment = DEPLOY.Deploy(self.site, self.state)
        self.deployment.initialize(OLD)

    @staticmethod
    def make_component(root, name, content):
        root.mkdir(parents=True)
        if name == "belmains":
            (root / "style.css").write_text("/* Theme Name: Belmains\nVersion: 0.4.2\n*/\n/* " + content + " */\n")
            (root / "index.php").write_text("<?php // " + content + "\n")
        else:
            version = "0.3.0" if name == "belmains-crm" else "0.2.1"
            (root / (name + ".php")).write_text("<?php\n/* Plugin Name: " + name + "\nVersion: " + version + "\n*/\n// " + content + "\n")
        (root / "assets").mkdir()
        (root / "assets" / "admin.css").write_text("/* " + content + " */\n")

    def snapshot(self):
        return {name: DEPLOY.manifest(target) for name, target in self.targets.items()}

    def test_manifest_rejects_symlinks(self):
        (self.targets["belmains"] / "linked.php").symlink_to(self.config)
        with self.assertRaises((DEPLOY.DeploymentError, ValueError)):
            DEPLOY.manifest(self.targets["belmains"])

    def test_manifest_rejects_symlink_directory(self):
        (self.targets["belmains"] / "external").symlink_to(self.upload.parent, target_is_directory=True)
        with self.assertRaises((DEPLOY.DeploymentError, ValueError)):
            DEPLOY.manifest(self.targets["belmains"])

    def test_remote_drift_blocks_every_component(self):
        (self.targets["belmains-crm"] / "assets" / "admin.css").write_text("/* edited on server */\n")
        before = self.snapshot()
        with patch.object(self.deployment, "lint"), patch.object(self.deployment, "smoke"):
            with self.assertRaises(DEPLOY.DeploymentError):
                self.deployment.deploy(NEW, self.source)
        self.assertEqual(before, self.snapshot())

    def test_guard_drift_blocks_deployment(self):
        self.guard.write_text("<?php // locally changed guard\n")
        before = self.snapshot()
        with patch.object(self.deployment, "lint"), patch.object(self.deployment, "smoke"):
            with self.assertRaises(DEPLOY.DeploymentError):
                self.deployment.deploy(NEW, self.source)
        self.assertEqual(before, self.snapshot())
        self.assertEqual("<?php // locally changed guard\n", self.guard.read_text())

    def test_failed_lint_leaves_live_components_unchanged(self):
        before = self.snapshot()
        baseline = (self.state / "current.json").read_bytes()
        with patch.object(
            self.deployment, "lint", side_effect=DEPLOY.DeploymentError("simulated PHP parse error")
        ), patch.object(self.deployment, "smoke") as smoke:
            with self.assertRaises(DEPLOY.DeploymentError):
                self.deployment.deploy(NEW, self.source)
        self.assertEqual(before, self.snapshot())
        self.assertEqual(baseline, (self.state / "current.json").read_bytes())
        smoke.assert_not_called()

    def test_failed_smoke_restores_all_components_and_baseline(self):
        before = self.snapshot()
        baseline = (self.state / "current.json").read_bytes()
        with patch.object(self.deployment, "lint"), patch.object(
            self.deployment, "smoke", side_effect=DEPLOY.DeploymentError("simulated failed smoke")
        ):
            with self.assertRaises(DEPLOY.DeploymentError):
                self.deployment.deploy(NEW, self.source)
        self.assertEqual(before, self.snapshot())
        self.assertEqual(baseline, (self.state / "current.json").read_bytes())

    def test_pending_journal_recovers_interrupted_publication(self):
        before = self.snapshot()
        baseline = self.deployment.baseline()
        backup = self.state / "backups" / "simulated-interruption"
        backup.mkdir(parents=True)
        for name, target in self.targets.items():
            shutil.copytree(target, backup / name)
        DEPLOY.atomic_json(self.state / "pending.json", {
            "revision": NEW, "backup": str(backup), "baseline": baseline,
        })
        # Simulate a process dying after updating one directory and removing a second.
        shutil.rmtree(self.targets["belmains"])
        shutil.copytree(self.source / "belmains", self.targets["belmains"])
        shutil.rmtree(self.targets["belmains-commerce"])
        self.deployment.recover()
        self.assertEqual(before, self.snapshot())
        self.assertEqual(baseline, self.deployment.baseline())
        self.assertFalse((self.state / "pending.json").exists())

    def test_corrupt_recovery_backup_is_refused_before_writes(self):
        before = self.snapshot()
        baseline = self.deployment.baseline()
        backup = self.state / "backups" / "corrupt-recovery"
        backup.mkdir(parents=True)
        for name, target in self.targets.items():
            shutil.copytree(target, backup / name)
        (backup / "belmains-crm" / "assets" / "admin.css").write_text("corrupt")
        DEPLOY.atomic_json(self.state / "pending.json", {
            "revision": NEW, "backup": str(backup), "baseline": baseline,
        })
        with self.assertRaises(DEPLOY.DeploymentError):
            self.deployment.recover()
        self.assertEqual(before, self.snapshot())
        self.assertTrue((self.state / "pending.json").exists())

    def test_success_changes_only_allowlisted_code(self):
        guard = self.guard.read_bytes()
        before = self.snapshot()
        with patch.object(self.deployment, "lint"), patch.object(self.deployment, "smoke"):
            self.deployment.deploy(NEW, self.source)
        for name, target in self.targets.items():
            self.assertEqual(DEPLOY.manifest(self.source / name), DEPLOY.manifest(target))
            self.assertNotEqual(before[name], DEPLOY.manifest(target))
        self.assertEqual(guard, self.guard.read_bytes())
        self.assertEqual("customer media", self.upload.read_text())
        self.assertEqual("<?php // existing configuration\n", self.config.read_text())
        self.assertEqual("<?php // untouched third-party plugin\n", self.other_plugin.read_text())
        self.assertIn(NEW, (self.state / "current.json").read_text())

    def test_unexpected_source_path_blocks_deployment(self):
        (self.source / "belmains" / "wp-config.php").write_text("<?php // forbidden\n")
        before = self.snapshot()
        with patch.object(self.deployment, "lint"), patch.object(self.deployment, "smoke"):
            with self.assertRaises((DEPLOY.DeploymentError, ValueError)):
                self.deployment.deploy(NEW, self.source)
        self.assertEqual(before, self.snapshot())


class WorkflowApprovalTests(unittest.TestCase):
    def run_payload(self, **changes):
        value = {
            "head_sha": NEW,
            "head_branch": "codex/version-3-bordeaux",
            "event": "push",
            "path": ".github/workflows/wordpress-checks.yml",
            "status": "completed",
            "conclusion": "success",
        }
        value.update(changes)
        return value

    def test_exact_successful_push_is_approved(self):
        self.assertTrue(DEPLOY.approved_run(self.run_payload(), NEW))

    def test_each_approval_condition_is_required(self):
        for changes in (
            {"head_sha": OLD},
            {"head_branch": "main"},
            {"event": "pull_request"},
            {"path": ".github/workflows/other.yml"},
            {"status": "in_progress"},
            {"conclusion": "failure"},
            {"conclusion": "cancelled"},
        ):
            with self.subTest(changes=changes):
                self.assertFalse(DEPLOY.approved_run(self.run_payload(**changes), NEW))


if __name__ == "__main__":
    unittest.main(verbosity=2)
