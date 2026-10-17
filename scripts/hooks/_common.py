"""Utilitas bersama hook Claude Code untuk SIKG."""
import importlib.util
import json
import os
import pathlib
import sys

ROOT = pathlib.Path(os.environ.get("CLAUDE_PROJECT_DIR") or pathlib.Path(__file__).resolve().parents[2]).resolve()
os.environ.setdefault("SIKG_ROOT", str(ROOT))


def load_input():
    try:
        return json.load(sys.stdin)
    except Exception:
        return {}


def guard_module():
    spec = importlib.util.spec_from_file_location("guard", ROOT / "scripts" / "guard.py")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def block(msg):
    """Blokir aksi: pesan ke Claude lewat stderr, exit 2."""
    sys.stderr.write("DIBLOKIR oleh aturan SIKG: " + msg + "\n")
    sys.exit(2)


def rel_path(p):
    try:
        return str(pathlib.Path(p).resolve().relative_to(ROOT)).replace(os.sep, "/")
    except Exception:
        return None
