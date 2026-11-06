# Post-session audit and site index

This is the starting point for every played-session update. The central [surface catalog](surfaces.json) lists each place to review, where its authoritative content lives, and what changes belong there. The [Session 26 audit](sessions/26.json) is the first completed content review and records remaining human checks separately.

## Routine

1. Confirm the latest **played** session and review its recap/transcript. Record the played date and sources. Proposed sessions, private notebooks and GM prep are not public session facts. Attribute estimates, suspicions and disputed claims.
2. Copy the previous session JSON to `sessions/NUMBER.json`; update session, title, date, record, previous chapter, sources and affected characters. Change `currentSession` in `surfaces.json`. Reset every review and browser check; do not inherit verification from the previous session.
3. Work through every catalog surface. Record `updated`, `reviewed`, `no-change` or `not-applicable`, with a specific reason or evidence. Include conditional surfaces even when no change is needed. Preserve player-authored live profiles and game state.
4. Run `npm run test:session-audit` and `npm run audit:session`. Generate a readable report with `npm run audit:session -- --report /tmp/septenture-session-audit.html`. The report stays outside the public content tree. Fix consistency errors, then run the normal Quartz build and applicable existing tests. CI repeats the consistency checks before publishing.
5. Use the human browser UI on a preview: home → latest session → previous → next → contents. Open affected character scene references. Check narrow/wide layouts and both light/dark themes. Test search discoverability after building. Record only checks actually performed.
6. Publish, confirm the Pages deployment succeeds, repeat the key navigation checks on the live site, and record the release commit/run and observed results. A passing script does not count as a browser test. A content release can be deployed while post-deployment checks remain pending; do not call the audit complete until those are resolved.

## Source boundaries

- `content/` is the static Chronicle, homepage, character reference material and public lore. Quartz generates search, graph, sitemap and page output.
- Player-edited public profiles are stored separately in `character_profiles` and may override static profile seeds. A recap update must not silently replace player writing.
- Nova is authoritative for fiction, collaborators, journal metadata and private sheets. The Exchange mirrors those stories; it is not a second editable story database.
- Game notes remain private to their player. Table maps/tokens are live game state. Neither should be copied into a public recap by default.
- `handoff/` contains generated editorial copies. Refresh those only for an editorial handoff and label their date; they are not the live site source.
- Notification review is explicit: ordinary saves and legacy story publication do not broadcast mail. “Save & email tagged writers” sends a targeted turn notification, subject to preferences. If a message was expected, inspect the notification status and server delivery evidence, then confirm receipt. A previous successful test or SMTP acceptance alone does not establish current inbox delivery.

The catalog is a maintainer index, kept outside `content/` so operating instructions do not appear in the player-facing site. Add new surfaces here whenever a feature creates another place that may need a session update.
