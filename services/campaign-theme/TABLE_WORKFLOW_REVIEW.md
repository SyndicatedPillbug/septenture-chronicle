# Table workflow review — November 7, 2026

The play surface keeps Dice, Sheets, Tokens and GM tools in dialogs, while Map Workshop remains a separate map-building page with return navigation.

## Changes

- Sketch tools now includes an explicit Draw on map / Stop drawing mode. A normal drag paints with the native terrain/color tools; Escape exits drawing. Opening another tool also exits drawing. Existing Ctrl/Shift/Alt shortcuts remain.
- Escape without an active spell-area overlay no longer emits a phantom sea_clear event. This fixes the false role-permission warning when leaving focus mode, without broadening server permissions.
- Dice and sheet dialogs name their destination: Back to GM tools or Back to Table tools.
- Map lists provide a native, keyboard-accessible open-map link instead of a mouse-only row handler.
- A failed tool-script load reports a recoverable connection status instead of calling an undefined function.

## Acceptance evidence

Actual browser UI was exercised against the live Odin session and an isolated disposable player/GM campaign. Live checks covered entry, a private dice roll without navigation, readable sheets, focus exit, drawing-mode toggling, GM return navigation, keyboard Workshop entry, and leaving to the Chronicle account without a redirect loop. No live map, token, sheet, or recorder state was edited.

Isolated UI checks covered player-only sheets and sheet save/reopen, private dice with explicit sharing, ordinary-drag terrain painting, token placement, NPC control grant/revoke, recorder publish/holding transitions, and leaving the table. The account fixtures contain synthetic data only.

All 63 disposable-account integration checks passed, including current membership/ownership, GM-only map and recorder actions, private assets, CSRF, ticket binding/replay, NPC grants, and canonical roster mapping. Existing dice-loading and sheet UI regressions also passed. The new Escape cleanup regression runs in CI alongside shared theme tests.

Eight source-only files were deployed with before/after hashes and a protected source backup. No database, configuration, or permission changes were deployed. Detailed local evidence is under outputs/table-review-20261107 in the project workspace; it is deliberately excluded from the public site.

## Limits

This was a focused acceptance pass, not a full-party, multi-hour session rehearsal. Physical mobile devices and OBS were not tested. The recorder currently excludes transient sketch marks. The live campaign still has its existing blank map and empty token library; this pass does not populate campaign assets or automate game rules.
