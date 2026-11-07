# Manual play kit — November 7, 2026

The Table keeps the map central, with a compact player bar and separate GM preparation dialogs. It remains a manual companion for paper sheets and rulebooks.

## Player controls

- Sheets open the same canonical Nova sheet in a dialog; the player shortcut is called **My sheet**, while GM access is called **Sheets**.
- Named favorite rolls open Dice for review before rolling. Visibility defaults to private and is selectable before each roll. Public rolls and later sharing use the existing saved result; failed sharing never rolls again.
- **My notes** opens the existing owner-only Nova notebook with the shared rich-text ribbon, revision checks and draft recovery. There is no second notebook store in the Table and no GM bypass.
- Center a controlled piece, reopen revealed handouts, send a location ping, react briefly, request a break, view turns and adjust an assigned manual counter.
- Sound starts only after the player enables it, with personal volume and stop controls. Follow GM is optional and controls centering on GM pings and opening spotlights.

## GM preparation

Save the current map, placed pieces, drawing, art, opening and ambience as a private scene. Preview before revealing. Editing the opening keeps the saved board unless **Replace saved map and pieces with the current board** is explicitly selected. Scenes and handouts have reversible archives.

Handouts contain artwork and rich prose; editing a revealed handout withdraws it until explicitly revealed again. Spotlight reveals a handout and signals its availability without replacing the map.

Upload an image as a token, click to place it, then name, duplicate, resize, hide/reveal, lock or assign movement control. Assignment grants movement, never private sheet access. The normal image menu also offers Make a token, Show as scene, Use as backdrop and Use as map. Advanced native map creation remains in Map Workshop.

Undo records successful native moves, token changes, scene reveals and drawing checkpoints. Conflicting later edits must be undone first. Players can undo only their own moves while they still control an unlocked piece. Quick map replacement retains the prior image for recovery; background lock prevents an accidental replacement. Existing media files are preserved.

Manual turns have names, order, counters and a round number. Shared counters can be GM-only or assigned to a current player. No game rules, initiative, combat or character mechanics are calculated.

## Privacy and recovery

All new endpoints require an authenticated, current campaign member. GM drafts and saved map snapshots are GM-only. Players can read art only when it is revealed or is the active map drawing. Recorder access remains separate. Preferences and roll descriptions are scoped to the verified account. Writes require CSRF proof and revision checks. Raster uploads are bounded and kept within protected campaign storage. Reactions and pings are transient; old signals expire from the store.

Connection failures preserve prose drafts and show an explicit unconfirmed-action message. Local draft recovery restores text after closing a failed save. Native startup events neither announce another arrival nor erase drawing checkpoints. Focus survives server-driven scene/piece reloads; on small screens chat sits beneath the map and Exit focus remains visible.

## Verification

Actual isolated GM and player browser sessions exercised scene save/preview/reveal/edit, rich handouts and art upload, archive/restore, token placement/edit/duplicate/control, player movement and undo, first-stroke undo, map image recovery, background lock, named private/public rolls and later sharing, canonical private notes, readable sheets, pings/follow/spotlight, sound/volume/stop, reactions, break/resume, turns, assigned counters, and a forced outage with draft recovery. Light/dark and 390×844 focus/chat and handout layouts were inspected visually.

Automated coverage: 63 membership/permission checks, 46 play-kit HTTP checks, 23 additional boundary checks, 23 scene/chat/map checks, 5 canonical notebook isolation/conflict checks, plus the existing theme, scene interaction, sheet, dice loading, visibility, sharing-retry and overlay regressions. A favorite preset regression covers the public tool entry point.

The file picker was exercised through the actual browser. Folder-to-browser dragging is covered by the real drop-handler regression; the browser automation API cannot supply an operating-system file drag. No test image, scene, note, roll, message, or handout was published into the live campaign.

## Installation

Apply additive `migrations/007_play_kit.sql` before the overlay. Deploy the overlay PHP, JavaScript, CSS and both XSL layouts together. The layouts and lazy-loaded scripts use content-hash queries. The Nova table bridge must allow GET/POST `play/player-notes`, preserving its existing owner-only policy. Back up the database, campaign resources and affected source first; preserve all configurations and existing campaign state. The new media and scene state are covered by the existing database backup, and token/map files remain covered by campaign-resource backups.
