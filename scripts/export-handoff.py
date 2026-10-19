#!/usr/bin/env python3
"""Export every Chronicle chapter as a standalone handoff file.

Each chapter of content/the-chronicle/*.md becomes handoff/<slug>.md with:
  - the title as an H1
  - a comment line naming the source file, dateline, and meta description
  - the prose body (dateline span converted to a plain line)
Style rules live in handoff/README.md.
"""
import pathlib, re

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / "content" / "the-chronicle"
OUT = ROOT / "handoff"

OUT.mkdir(exist_ok=True)

for path in sorted(SRC.glob("*.md")):
    if path.name == "index.md":
        continue
    text = path.read_text()
    m = re.match(r"^---\n(.*?)\n---\n(.*)$", text, flags=re.S)
    fm_raw, body = (m.group(1), m.group(2)) if m else ("", text)
    fm = {}
    for line in fm_raw.splitlines():
        if ":" in line:
            k, v = line.split(":", 1)
            fm[k.strip()] = v.strip().strip('"')

    dateline = re.search(r'<span class="dateline">(.*?)</span>', body, re.S)
    dateline_text = dateline.group(1).strip() if dateline else ""
    title_match = re.search(r"^# (.*)$", body, re.M)
    title = title_match.group(1).strip() if title_match else fm.get("title", path.stem)

    prose = body
    if dateline:
        prose = prose.replace(dateline.group(0), f"*{dateline_text}*")
    if title_match:
        prose = prose.replace(title_match.group(0), "", 1)

    # Navigation is site furniture, not prose.
    prose = re.sub(r'<nav class="chapter-nav".*?</nav>', '', prose, flags=re.S)

    out = [
        f"# {title}",
        "",
        f"<!-- source: content/the-chronicle/{path.name} | dateline: {dateline_text} | description: {fm.get('description', '')} -->",
        "",
        prose.strip(),
        "",
    ]
    (OUT / f"{path.stem}.md").write_text("\n".join(out))
    print(f"handoff/{path.stem}.md")

print("done")
