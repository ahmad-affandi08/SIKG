#!/usr/bin/env python3
"""Membuat ulang segel checksum file terlindungi (tooling/protected.sha256).

HANYA DIJALANKAN OLEH MANUSIA setelah sengaja mengubah CLAUDE.md, docs/rules, docs/PRD,
docs/operations.md, .claude, scripts, tooling, atau .github. Hook Claude Code memblokir
agen menjalankan skrip ini.
"""
import importlib.util
import pathlib

here = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("guard", here / "guard.py")
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)

files = guard.protected_files()
lines = [f"{guard.sha(p)}  {p}" for p in files]
guard.SEAL.parent.mkdir(parents=True, exist_ok=True)
guard.SEAL.write_text("\n".join(lines) + "\n")
print(f"Segel diperbarui: {len(files)} file.")
