#!/usr/bin/env python3
"""Add (or refresh) previous / index / next navigation on every Chronicle chapter.

Reading order lives here, in ORDER. When a new chapter is written, add it to the
list and re-run this script. The nav block is stripped by scripts/export-handoff.py
so handoff files stay pure prose.
"""
import pathlib, re

ROOT = pathlib.Path(__file__).resolve().parent.parent
DIR = ROOT / "content" / "the-chronicle"

# Reading order: Part I -> Part II -> Part III.
ORDER = [
    ("c1-01-the-feylight-festival", "The Feylight Festival"),
    ("c1-02-the-clockwork-stall", "The Clockwork Stall"),
    ("c1-03-the-hag-in-the-tent", "The Hag in the Tent"),
    ("c1-04-moonfire-stew", "Moonfire Stew"),
    ("c1-05-wit-and-words", "Wit and Words"),
    ("c1-06-dark-dreams-and-propositions", "Dark Dreams and Propositions"),
    ("c1-07-the-cellar-door", "The Cellar Door"),
    ("c1-08-if-you-aint-wight", "If You Ain't Wight"),
    ("c1-09-the-helm-and-the-ring", "The Helm and the Ring"),
    ("c1-10-bad-omens", "Bad Omens"),
    ("c1-11-before-the-storm", "Before the Storm"),
    ("c1-12-the-siege-of-lobridge", "The Siege of Lobridge"),
    ("c1-13-the-victory-feast", "The Victory Feast"),
    ("c2-01-three-weeks-to-corium", "Three Weeks to Corium"),
    ("c2-02-the-salt-mares-fortune", "The Salt Mare's Fortune"),
    ("c3-05-the-bohun-fire", "The Bohun Fire"),
    ("c3-07-bill", "Bill"),
    ("c3-09-the-shrouded-in-the-alley", "The Shrouded in the Alley"),
    ("c3-10-the-bohun-estate", "The Bohun Estate"),
    ("c3-11-the-bohun-breakfast", "The Bohun Breakfast"),
    ("c3-12-stross", "Stross"),
    ("c3-13-the-loss-of-simon", "The Loss of Simon"),
    ("c3-14-the-cistern-house", "The Cistern House"),
    ("c3-15-who-am-i", "Who Am I"),
    ("c3-16-the-witness-in-the-basin", "The Witness in the Basin"),
    ("25-the-courtyard-and-the-wish", "The Courtyard and the Wish"),
    ("the-wrong-hunter", "The Wrong Hunter"),
    ("interlude-the-stone", "The Stone"),
]

NAV_RE = re.compile(r'\n*<nav class="chapter-nav".*?</nav>\n*', re.S)


def block(i: int) -> str:
    slug, title = ORDER[i]
    prev_link = (
        f'<a class="nav-prev" href="{ORDER[i - 1][0]}"><span class="nav-dir">← Previous</span>'
        f'<span class="nav-title">{ORDER[i - 1][1]}</span></a>'
        if i > 0
        else '<span class="nav-empty"></span>'
    )
    next_link = (
        f'<a class="nav-next" href="{ORDER[i + 1][0]}"><span class="nav-dir">Next →</span>'
        f'<span class="nav-title">{ORDER[i + 1][1]}</span></a>'
        if i < len(ORDER) - 1
        else '<span class="nav-empty"></span>'
    )
    return (
        '\n\n<nav class="chapter-nav" aria-label="Chapter navigation">\n'
        f'  {prev_link}\n'
        '  <a class="nav-index" href="/septenture-chronicle/the-chronicle/"><span class="nav-dir">↑</span> The Chronicle</a>\n'
        f'  {next_link}\n'
        '</nav>\n'
    )


changed = 0
for i, (slug, _title) in enumerate(ORDER):
    path = DIR / f"{slug}.md"
    if not path.exists():
        print(f"missing: {path.name}")
        continue
    text = NAV_RE.sub("\n", path.read_text()).rstrip() + block(i)
    path.write_text(text)
    changed += 1
print(f"navigation written to {changed} chapters")
