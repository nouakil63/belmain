#!/usr/bin/env python3
"""Pull a validated public GitHub revision; publish only the three Belmains components.

Install this script AND package-release.py outside sites/. The scheduler invokes this
fixed trusted copy, never a program from the candidate Git revision. No credentials,
WordPress bootstrap, database operations or writes to MU plugins are needed.
"""
from __future__ import annotations

import argparse
import contextlib
import datetime
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import signal
import subprocess
import sys
import tarfile
import tempfile
import time
import urllib.error
import urllib.request

REPOSITORY = 'nouakil63/belmain'
BRANCH = 'codex/version-3-bordeaux'
WORKFLOW = '.github/workflows/wordpress-checks.yml'
ORIGIN = 'https://github.com/' + REPOSITORY + '.git'
SITE_URL = 'https://belmains.com'
COMPONENTS = {'belmains': 'themes', 'belmains-commerce': 'plugins', 'belmains-crm': 'plugins'}
PHP = '/opt/php8.4/bin/php'
SHA = re.compile(r'[0-9a-f]{40}')
_spec = importlib.util.spec_from_file_location('belmains_package', Path(__file__).with_name('package-release.py'))
validator = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(validator)


class DeploymentError(RuntimeError):
    pass


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def manifest(root: Path) -> dict[str, str]:
    if root.is_symlink() or not root.is_dir():
        raise DeploymentError(f'Not an ordinary directory: {root}')
    result = {}
    for current, dirs, files in os.walk(root, followlinks=False):
        for name in dirs + files:
            path = Path(current) / name
            if path.is_symlink():
                raise DeploymentError(f'Symlink refused: {path}')
        for name in files:
            path = Path(current) / name
            if not path.is_file():
                raise DeploymentError(f'Non-regular file refused: {path}')
            result[path.relative_to(root).as_posix()] = digest(path)
    if not result:
        raise DeploymentError(f'Empty component refused: {root}')
    return result


def atomic_json(path: Path, value: dict) -> None:
    temp = path.with_suffix('.tmp')
    with temp.open('w', encoding='utf-8') as stream:
        json.dump(value, stream, sort_keys=True)
        stream.flush()
        os.fsync(stream.fileno())
    os.chmod(temp, 0o600)
    os.replace(temp, path)


def approved_run(run: dict, sha: str) -> bool:
    return all((run.get('head_sha') == sha, run.get('head_branch') == BRANCH,
                run.get('event') == 'push', run.get('path') == WORKFLOW,
                run.get('status') == 'completed', run.get('conclusion') == 'success'))


def request(url: str):
    return urllib.request.Request(url, headers={'User-Agent': 'Belmains-deployment/1.0',
                                               'Accept': 'application/vnd.github+json'})


def check_ci(sha: str) -> bool:
    url = (f'https://api.github.com/repos/{REPOSITORY}/actions/workflows/wordpress-checks.yml/runs'
           f'?branch={BRANCH}&event=push&head_sha={sha}&per_page=100')
    with urllib.request.urlopen(request(url), timeout=30) as response:
        runs = json.load(response).get('workflow_runs', [])
    exact = [r for r in runs if r.get('head_sha') == sha and r.get('head_branch') == BRANCH
             and r.get('event') == 'push' and r.get('path') == WORKFLOW]
    if not exact:
        return False
    latest = max(exact, key=lambda r: (r.get('id', 0), r.get('run_attempt', 0)))
    return approved_run(latest, sha)


def download_source(sha: str, destination: Path) -> Path:
    archive = destination / 'source.tar.gz'
    with urllib.request.urlopen(request(f'https://codeload.github.com/{REPOSITORY}/tar.gz/{sha}'), timeout=60) as response:
        with archive.open('wb') as output:
            size = 0
            while chunk := response.read(1024 * 1024):
                size += len(chunk)
                if size > 200 * 1024 * 1024:
                    raise DeploymentError('Source archive exceeds 200 MiB')
                output.write(chunk)
    source = destination / 'wordpress'
    source.mkdir()
    seen = set()
    total = 0
    with tarfile.open(archive, 'r:gz') as tar:
        for member in tar:
            parts = PurePosixPath(member.name).parts
            if len(parts) < 3 or parts[1] != 'wordpress' or parts[2] not in COMPONENTS:
                continue
            if member.isdir():
                continue
            if not member.isfile() or len(parts) < 4:
                raise DeploymentError(f'Unsafe archive entry: {member.name}')
            relative = PurePosixPath(*parts[3:])
            validator.validate_relative_path(parts[2], relative)
            key = (parts[2] + '/' + relative.as_posix()).casefold()
            if key in seen:
                raise DeploymentError('Duplicate archive entry')
            seen.add(key)
            total += member.size
            if member.size > 100 * 1024 * 1024 or total > 300 * 1024 * 1024:
                raise DeploymentError('Uncompressed archive exceeds limit')
            target = source / parts[2] / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            with tar.extractfile(member) as incoming, target.open('wb') as outgoing:
                shutil.copyfileobj(incoming, outgoing)
            os.chmod(target, 0o644)
    return source


class Deploy:
    def __init__(self, site: Path, state: Path):
        if site.is_symlink() or state.is_symlink():
            raise DeploymentError('Site and state must not be symlinks')
        self.site = site.resolve()
        self.state = state.resolve()
        if self.state == self.site.parent or self.site.parent in self.state.parents:
            raise DeploymentError('State must be outside the sites directory')
        if not (self.site / 'wp-content').is_dir():
            raise DeploymentError('WordPress site directory does not exist')
        self.state.mkdir(parents=True, exist_ok=True, mode=0o700)
        os.chmod(self.state, 0o700)
        if self.state.stat().st_dev != self.site.stat().st_dev:
            raise DeploymentError('State and site must share a filesystem for atomic renames')
        self.guard = self.site / 'wp-content/mu-plugins/belmains-staging-guard.php'
        self.check_parents()
        self.current = self.state / 'current.json'
        self.pending = self.state / 'pending.json'

    def check_parents(self) -> None:
        for path in (self.site / 'wp-content', self.site / 'wp-content/themes',
                     self.site / 'wp-content/plugins', self.site / 'wp-content/mu-plugins'):
            if path.is_symlink() or not path.is_dir():
                raise DeploymentError(f'Runtime parent must be an ordinary directory: {path}')

    def live(self, component: str) -> Path:
        return self.site / 'wp-content' / COMPONENTS[component] / component

    def snapshot(self, revision: str) -> dict:
        self.check_parents()
        if not SHA.fullmatch(revision):
            raise DeploymentError('A full Git SHA is required')
        if self.guard.is_symlink() or not self.guard.is_file():
            raise DeploymentError('The closure MU plugin must exist as an ordinary file')
        return {'revision': revision, 'guard_sha256': digest(self.guard),
                'manifests': {c: manifest(self.live(c)) for c in COMPONENTS}}

    def initialize(self, revision: str) -> None:
        if self.current.exists():
            raise DeploymentError('Already initialized; refusing to overwrite the baseline')
        atomic_json(self.current, self.snapshot(revision))

    def baseline(self) -> dict:
        return json.loads(self.current.read_text())

    def verify(self, baseline: dict) -> None:
        if self.snapshot(baseline['revision']) != baseline:
            raise DeploymentError('Live files or closure guard changed outside deployment; reconcile first')

    def lint(self, source: Path) -> None:
        node = shutil.which('node')
        for component in COMPONENTS:
            files = manifest(source / component)
            for relative in files:
                validator.validate_relative_path(component, PurePosixPath(relative))
                path = source / component / relative
                command = [PHP, '-l', str(path)] if path.suffix == '.php' else (
                    [node, '--check', str(path)] if node and path.suffix == '.js' else None)
                if command:
                    result = subprocess.run(command, capture_output=True, text=True, timeout=30)
                    if result.returncode:
                        raise DeploymentError(f'Lint failed: {component}/{relative}: {result.stderr or result.stdout}')

    def smoke(self, revision: str) -> None:
        query = '?belmains_revision=' + revision
        with urllib.request.urlopen(request(SITE_URL + '/' + query), timeout=30) as response:
            body = response.read(5 * 1024 * 1024)
            if response.status != 200 or b'Belmains' not in body:
                raise DeploymentError('Homepage smoke check failed')
        try:
            with urllib.request.urlopen(request(SITE_URL + '/wp-json/' + query), timeout=30):
                raise DeploymentError('REST closure check failed: expected HTTP 503')
        except urllib.error.HTTPError as error:
            if error.code != 503 or b'belmains_preparation' not in error.read():
                raise DeploymentError('REST closure check failed') from error
        asset = self.live('belmains') / 'style.css'
        with urllib.request.urlopen(request(SITE_URL + '/wp-content/themes/belmains/style.css' + query), timeout=30) as response:
            if hashlib.sha256(response.read()).hexdigest() != digest(asset):
                raise DeploymentError('Public theme asset differs from deployed bytes')

    def restore(self, journal: dict) -> None:
        backup = Path(journal['backup']).resolve()
        if self.state / 'backups' not in backup.parents:
            raise DeploymentError('Invalid recovery backup location')
        with tempfile.TemporaryDirectory(prefix='rollback-', dir=self.state) as temp:
            temp = Path(temp)
            for c in COMPONENTS:
                if manifest(backup / c) != journal['baseline']['manifests'][c]:
                    raise DeploymentError('Recovery backup hash mismatch')
                shutil.copytree(backup / c, temp / c)
            for c in COMPONENTS:
                live = self.live(c)
                retired = temp / ('retired-' + c)
                if live.exists():
                    os.replace(live, retired)
                os.replace(temp / c, live)
            atomic_json(self.current, journal['baseline'])
        self.pending.unlink(missing_ok=True)

    def recover(self) -> None:
        if self.pending.exists():
            journal = json.loads(self.pending.read_text())
            if self.current.exists() and self.baseline()['revision'] == journal['revision']:
                self.verify(self.baseline())
                self.pending.unlink()
            else:
                self.restore(journal)

    def deploy(self, revision: str, source: Path) -> None:
        if not SHA.fullmatch(revision):
            raise DeploymentError('Invalid revision')
        baseline = self.baseline()
        self.verify(baseline)
        source = source.resolve()
        for c in COMPONENTS:
            validator.collect_files(source / c, c)
        self.lint(source)
        expected = {c: manifest(source / c) for c in COMPONENTS}
        backups = self.state / 'backups'
        backups.mkdir(exist_ok=True, mode=0o700)
        stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
        backup = backups / (stamp + '-' + revision[:12])
        backup.mkdir(mode=0o700)
        for c in COMPONENTS:
            shutil.copytree(self.live(c), backup / c)
            if manifest(backup / c) != baseline['manifests'][c]:
                raise DeploymentError('Live files changed while backing up')
        journal = {'revision': revision, 'backup': str(backup), 'baseline': baseline}
        with tempfile.TemporaryDirectory(prefix='release-', dir=self.state) as temp:
            temp = Path(temp)
            for c in COMPONENTS:
                shutil.copytree(source / c, temp / c)
                for current, dirs, files in os.walk(temp / c):
                    os.chmod(current, 0o755)
                    for name in files:
                        os.chmod(Path(current) / name, 0o644)
            self.verify(baseline)
            atomic_json(self.pending, journal)
            try:
                for c in COMPONENTS:
                    os.replace(self.live(c), temp / ('previous-' + c))
                    os.replace(temp / c, self.live(c))
                candidate = self.snapshot(revision)
                if candidate['manifests'] != expected or candidate['guard_sha256'] != baseline['guard_sha256']:
                    raise DeploymentError('Post-publication hash verification failed')
                self.smoke(revision)
                atomic_json(self.current, candidate)
                self.pending.unlink()
            except BaseException:
                self.restore(journal)
                raise
        print('DEPLOYED ' + revision, flush=True)

    def poll(self) -> None:
        self.recover()
        baseline = self.baseline()
        self.verify(baseline)
        result = subprocess.run(['git', 'ls-remote', '--exit-code', ORIGIN, 'refs/heads/' + BRANCH],
                                capture_output=True, text=True, timeout=45,
                                env={**os.environ, 'GIT_TERMINAL_PROMPT': '0'})
        if result.returncode:
            raise DeploymentError('Unable to read the publication branch')
        revision = result.stdout.split()[0]
        if not SHA.fullmatch(revision):
            raise DeploymentError('Unexpected branch revision')
        if revision == baseline['revision']:
            return
        retry_file = self.state / 'retry.json'
        retry = json.loads(retry_file.read_text()) if retry_file.exists() else {}
        if retry.get('revision') == revision and retry.get('after', 0) > time.time():
            raise DeploymentError('Validation retry is pending; retry the notification later')
        # Default backoff also bounds API calls for an unsuccessful or pending revision.
        atomic_json(retry_file, {'revision': revision, 'after': time.time() + 120})
        try:
            if not check_ci(revision):
                raise DeploymentError('Waiting for successful GitHub validation: ' + revision)
        except urllib.error.HTTPError as error:
            if error.code in (403, 429):
                delay = max(120, int(error.headers.get('Retry-After', '120')))
                reset = int(error.headers.get('X-RateLimit-Reset', '0'))
                atomic_json(retry_file, {'revision': revision, 'after': max(time.time() + delay, reset + 5)})
                raise DeploymentError('GitHub rate limit; retry the notification later') from error
            raise
        with tempfile.TemporaryDirectory(prefix='fetch-', dir=self.state) as temp:
            source = download_source(revision, Path(temp))
            self.deploy(revision, source)


def main() -> int:
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--site', type=Path, required=True)
    parser.add_argument('--state', type=Path, required=True)
    parser.add_argument('--initialize', action='store_true')
    parser.add_argument('--revision', help='Full SHA whose source was compared with live files')
    args = parser.parse_args()
    try:
        deployment = Deploy(args.site, args.state)
        with (deployment.state / 'lock').open('a') as lock:
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                return 0
            for sig in (signal.SIGINT, signal.SIGTERM):
                signal.signal(sig, lambda *_: (_ for _ in ()).throw(DeploymentError('Interrupted')))
            if args.initialize:
                if not args.revision:
                    raise DeploymentError('--initialize requires --revision after source comparison')
                deployment.initialize(args.revision)
                print('INITIALIZED ' + args.revision)
            else:
                deployment.poll()
        return 0
    except Exception as error:
        print(f'DEPLOYMENT_FAILED {type(error).__name__}: {error}', file=sys.stderr, flush=True)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
