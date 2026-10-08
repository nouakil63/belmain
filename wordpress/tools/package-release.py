#!/usr/bin/env python3
"""Build the three installable Belmains ZIPs using only the Python standard library."""

from __future__ import annotations

import argparse
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import tempfile
import zipfile


WORDPRESS_ROOT = Path(__file__).resolve().parents[1]
COMPONENTS = (
    ("belmains", "style.css", "belmains-wordpress-theme"),
    ("belmains-commerce", "belmains-commerce.php", "belmains-commerce"),
    ("belmains-crm", "belmains-crm.php", "belmains-crm"),
)
TEXT_EXTENSIONS = {".php", ".css", ".js", ".md", ".txt", ".svg"}
ASSET_EXTENSIONS = {
    ".css", ".js", ".png", ".jpg", ".jpeg", ".webp", ".gif", ".avif",
    ".svg", ".ico", ".ttf", ".otf", ".woff", ".woff2", ".mp4", ".webm",
}
FORBIDDEN_PARTS = {
    "wp-config.php", "wp-config-sample.php", "wp-admin", "wp-includes", "wp-content",
    "uploads", "tests", "test", "tools", "private", "secrets", "credentials",
    "backup", "backups", "cache", "node_modules", "vendor", "__pycache__",
}
FORBIDDEN_FILENAME_WORDS = {
    "config", "private", "secret", "secrets", "credential", "credentials",
    "backup", "backups", "test", "tests", "dump", "export", "log",
}
FIXED_ZIP_DATE = (1980, 1, 1, 0, 0, 0)


def validate_relative_path(component: str, relative: PurePosixPath) -> None:
    """Fail closed on anything outside the runtime source/asset layout."""
    parts = relative.parts
    if not parts or relative.is_absolute() or any(
        part.startswith(".") or part.casefold() in FORBIDDEN_PARTS
        or ":" in part or "\\" in part or "\x00" in part
        for part in parts
    ):
        raise ValueError(f"Forbidden package path: {component}/{relative}")
    if set(re.split(r"[._-]+", relative.stem.casefold())) & FORBIDDEN_FILENAME_WORDS:
        raise ValueError(f"Private or development filename: {component}/{relative}")
    suffix = relative.suffix.casefold()
    if len(parts) == 1:
        allowed = suffix in {".php", ".css", ".js"} or relative.name.casefold() in {
            "readme.md", "readme.txt", "license", "license.md", "license.txt",
        }
    elif parts[0] == "assets":
        allowed = suffix in ASSET_EXTENSIONS
    elif component == "belmains" and parts[0] == "template-parts":
        allowed = suffix == ".php"
    elif component != "belmains" and parts[0] == "includes":
        allowed = suffix == ".php"
    else:
        allowed = False
    if not allowed:
        raise ValueError(f"Unexpected runtime file: {component}/{relative}")


def is_link(path: Path) -> bool:
    return path.is_symlink() or bool(getattr(path, "is_junction", lambda: False)())


def collect_files(root: Path, component: str) -> list[tuple[Path, PurePosixPath]]:
    if is_link(root) or not root.is_dir():
        raise ValueError(f"Package source must be an ordinary directory: {root}")
    files = []
    seen = set()
    for current, directories, filenames in os.walk(root, followlinks=False):
        for name in directories:
            directory = Path(current) / name
            relative = directory.relative_to(root)
            if is_link(directory) or any(
                part.startswith(".") or part.casefold() in FORBIDDEN_PARTS
                for part in relative.parts
            ):
                raise ValueError(f"Forbidden package directory: {component}/{relative}")
            if relative.parts[0] not in ({"assets", "template-parts"} if component == "belmains" else {"assets", "includes"}):
                raise ValueError(f"Unexpected package directory: {component}/{relative}")
        for name in filenames:
            path = Path(current) / name
            if is_link(path) or not path.is_file() or not path.resolve().is_relative_to(root):
                raise ValueError(f"Package source is not an ordinary contained file: {path}")
            relative = PurePosixPath(path.relative_to(root).as_posix())
            validate_relative_path(component, relative)
            key = relative.as_posix().casefold()
            if key in seen:
                raise ValueError(f"Duplicate package path: {component}/{relative}")
            seen.add(key)
            files.append((path, relative))
    if not files:
        raise ValueError(f"Empty package source: {root}")
    return sorted(files, key=lambda item: item[1].as_posix())


def read_version(path: Path) -> str:
    header = path.read_text(encoding="utf-8-sig")[:8192]
    matches = re.findall(r"^[ \t]*(?:\*[ \t]*)?Version:[ \t]*([^\r\n]+)", header, re.MULTILINE)
    if len(matches) != 1:
        raise ValueError(f"Exactly one Version header is required in {path}")
    version = matches[0].strip()
    if not re.fullmatch(r"[0-9]+(?:\.[0-9]+){1,3}(?:-[A-Za-z0-9]+(?:[.-][A-Za-z0-9]+)*)?", version):
        raise ValueError(f"Invalid package version in {path}")
    return version


def verify_archive(path: Path, component: str, expected: list[str]) -> None:
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        if names != expected or len(names) != len(set(names)):
            raise ValueError(f"Archive manifest differs from source: {path.name}")
        for name in names:
            member = PurePosixPath(name)
            if member.parts[0] != component or len(member.parts) < 2:
                raise ValueError(f"Archive has an invalid top-level folder: {name}")
            validate_relative_path(component, PurePosixPath(*member.parts[1:]))
        bad_file = archive.testzip()
        if bad_file:
            raise ValueError(f"Archive CRC validation failed: {bad_file}")


def build(output: Path) -> list[Path]:
    output = output.resolve()
    if output.is_relative_to(WORDPRESS_ROOT):
        raise ValueError("The output directory must be outside the wordpress source directory.")
    plans = []
    for component, metadata, prefix in COMPONENTS:
        root = WORDPRESS_ROOT / component
        files = collect_files(root, component)
        version = read_version(root / metadata)
        plans.append((component, f"{prefix}-{version}.zip", files))

    output.mkdir(parents=True, exist_ok=True)
    results = []
    checksums = []
    with tempfile.TemporaryDirectory(prefix=".belmains-package-", dir=output) as staging:
        for component, filename, files in plans:
            archive_path = Path(staging) / filename
            expected = []
            with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
                for source, relative in files:
                    name = f"{component}/{relative.as_posix()}"
                    expected.append(name)
                    data = source.read_bytes()
                    if source.suffix.casefold() in TEXT_EXTENSIONS:
                        data = data.replace(b"\r\n", b"\n")
                    info = zipfile.ZipInfo(name, FIXED_ZIP_DATE)
                    info.create_system = 3
                    info.external_attr = (stat.S_IFREG | 0o644) << 16
                    info.compress_type = zipfile.ZIP_DEFLATED
                    archive.writestr(info, data, compresslevel=9)
            verify_archive(archive_path, component, expected)
            digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
            checksums.append(f"{digest}  {filename}\n")
        (Path(staging) / "SHA256SUMS").write_text("".join(checksums), encoding="ascii", newline="\n")
        for filename in [plan[1] for plan in plans] + ["SHA256SUMS"]:
            destination = output / filename
            (Path(staging) / filename).replace(destination)
            results.append(destination)
    return results


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", required=True, type=Path, help="Destination outside the wordpress source directory.")
    arguments = parser.parse_args()
    try:
        for path in build(arguments.output_dir):
            print(f"Created {path.name} ({path.stat().st_size:,} bytes)")
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        print(f"Packaging failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
