#!/usr/bin/env python3
"""Ringkasan kemajuan kebutuhan dari docs/STATUS.md (tanpa tanggal/estimasi).

  python3 scripts/status.py          ringkasan lengkap
  python3 scripts/status.py --brief  ringkas (untuk awal sesi)
  python3 scripts/status.py --next   satu ID berikutnya sesuai urutan prioritas
"""
import os
import pathlib
import re
import sys

ROOT = pathlib.Path(os.environ.get("SIKG_ROOT") or pathlib.Path(__file__).resolve().parent.parent)
text = (ROOT / "docs" / "STATUS.md").read_text(encoding="utf-8")

section = None
sub = None
items = []  # (phase, prio, id, done, title)
for line in text.splitlines():
    if line.startswith("## Fase 1"):
        section = "Fase 1"
    elif line.startswith("## Fase 2"):
        section = "Fase 2"
    elif line.startswith("## Fase 3"):
        section = "Fase 3"
    elif line.startswith("### "):
        sub = line[4:].strip()
    m = re.match(r"- \[( |x)\] (\S+) \| (.*)$", line)
    if m and section:
        done = m.group(1) == "x"
        rest = m.group(3)
        prio = None
        pm = re.match(r"([MSC]) \| (.*)$", rest)
        if pm:
            prio, rest = pm.group(1), pm.group(2)
        items.append((section, prio, m.group(2), done, rest))


def pending(phase, prio=None):
    return [i for i in items if i[0] == phase and not i[3] and (prio is None or i[1] == prio)]


order = [("Fase 1", "M"), ("Fase 1", "S"), ("Fase 1", "C"), ("Fase 2", None), ("Fase 3", None)]


def next_item():
    for ph, pr in order:
        p = pending(ph, pr)
        if p:
            if ph != "Fase 1" and pending("Fase 1", "M"):
                return None
            return p[0]
    return None


if "--next" in sys.argv:
    n = next_item()
    print(f"{n[2]} | {n[4]}" if n else "Tidak ada kebutuhan berikutnya (atau Must Fase 1 belum selesai untuk Fase berikutnya).")
    sys.exit(0)

total = len(items)
done = sum(1 for i in items if i[3])
must = [i for i in items if i[0] == "Fase 1" and i[1] == "M"]
must_left = [i for i in must if not i[3]]
print(f"Kebutuhan selesai: {done}/{total}")
print(f"Must Fase 1: {len(must) - len(must_left)}/{len(must)} selesai")
if must_left:
    print("Must tersisa (urutan): " + ", ".join(i[2] for i in must_left[:8]) + (" ..." if len(must_left) > 8 else ""))
n = next_item()
print("Berikutnya: " + (f"{n[2]} ({n[4]})" if n else "-"))
if "--brief" not in sys.argv:
    for ph, pr in order:
        label = f"{ph}" + (f" {pr}" if pr else "")
        left = pending(ph, pr)
        print(f"- {label}: sisa {len(left)}")
