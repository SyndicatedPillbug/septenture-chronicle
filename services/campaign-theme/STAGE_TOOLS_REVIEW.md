# Manual Table tools — November 7, 2026

The Table adds manual measurement, exploration, reusable media and private session moments. These extend the play-kit dialogs and the existing native board; they do not calculate game rules.

## Play and preparation

**Measure** opens a ruler and circle, fixed 60° cone, or rectangle templates. Click two map points; Escape cancels. Distance uses the map's chosen distance per square and unit, with straight-line ruler measurements. Templates start private. Sharing is explicit, and owners can remove their own templates. Templates are scoped to the active map.

GM **Exploration** offers whole-map cover, reveal/conceal rectangles, shown/daylight/night sight, door state, and light state/radius without opening Map Workshop. Advanced geometry stays in Map Workshop. Player map pixels, piece records, resource requests, drawing checkpoints, event snapshots and location signals are checked on the server. Hidden geometry and shape-change library metadata are omitted while exploration is active. A token is visible only when its complete occupied footprint is visible. Player sight/light changes require the GM while exploration is active. A player with no visible character still gets a valid board.

Sight is conservative, using each player's own character vision plus native walls, doors and lights. This is a manual companion, not a rules engine. Concealment needs a local raster map no larger than 4096 pixels per side, 10 million pixels, or 20,000 grid cells. Unsupported sources fail closed. New cover, grid changes and quick map replacements validate these limits before committing. Exploration state belongs to the live map; scene snapshots do not reset cover, doors, lights or measurement templates.

**Align grid** accepts two printed intersections and the number of squares between them, then presents source pixels per square and offsets for review before Apply. Piece coordinates remain unchanged. Native geometry reloads after applying, with focus retained. Grid changes participate in the existing map recovery history.

GM **Media shelf** searches private image/audio titles and tags. Uploads and previews do not reveal anything. An image can prepare a token or rich handout, show an illustration, become a backdrop, or explicitly replace the current map. Map replacement retains the original protected file and records recovery. Shelf title/tags and reversible archives keep reusable assets organized. An external display choice clears stale saved-scene introduction metadata.

## Sound

Each device controls its own sound and volume. Rain, hearth and night presets remain available. Private MP3/Ogg/WAV loops under 2 MB can be uploaded, previewed on the GM device and explicitly selected for the scene. Only the currently selected loop is streamable by campaign members; unrevealed previews and the shelf remain GM-only. Streams support byte ranges and private, non-cacheable responses.

The sound panel distinguishes muted, loading, playing, failed and browser-paused playback. Enable sound creates/resumes the audio context from a user action. Consent is scoped to the account and current browser tab; a board reload attempts to resume it, but a browser that requires another gesture shows **Sound · paused**. Fade out & mute cancels a pending resume, so a late browser callback cannot turn sound back on. Personal volume, scene stop and return from a private preview are explicit controls. Saved scenes capture the uploaded audio selection; scene undo restores the preceding selection.

Browser tests confirmed running audio contexts and successful decoding/loop playback for GM and player views. This does not certify the user's speakers, output-device selection or audibility on every browser; those belong in the deferred group rehearsal.

## Private moments and writing

**Bookmark** immediately creates an owner-only moment for the current map. Its rich editor uses the shared prose ribbon. Revisions reject stale updates. Closing or a failed save retains a local draft for explicit recovery. Owners can archive and restore their moments. GM authority does not grant another player's bookmark access.

Select moments deliberately, then **Prepare writing prompt**. The editable prompt can be downloaded as Markdown or copied; a selectable text fallback handles blocked clipboard permission. Opening the writing desk does not transmit private notes in a URL or publish anything. Only selected moments enter the export. Canonical Nova notebooks and sheets keep their existing stores and owner policies; these bookmarks are a separate, deliberately selected session-to-writing aid.

## Acceptance

Isolated human UI checks covered two-point ruler, circle/cone/rectangle creation, removal, private/shared display, private bookmark save/selection/prompt preparation, a downloaded prompt containing only the selected moment, blocked clipboard fallback, a forced failed save and draft restoration, image/audio file pickers, tag search, image-to-token and image-to-handout preparation, backdrop/map display, door/light state, cover/reveal, daylight visibility, grid calibration/review/apply, GM/player sound enable, uploaded-loop preview/playback and fade/mute. Dark desktop, light player and 390×844 tool layouts were inspected visually.

The browser automation cannot retrieve a blob download inside an embedded frame. The same real download link was exercised in the native board opened as a top-level tab, and the resulting local file was checked. Embedded copy fallback was tested directly. Folder-to-browser OS dragging retains the previously tested drop handler; no OS file drag is claimed here.

Automated local checks: 61 new tools cases, 17 additional concealment/boundary cases, 63 existing membership/permission cases, 46 play-kit cases, 23 existing boundary cases, 23 scene/chat/map cases, 5 canonical-note checks, and the theme, dice, scene, overlay and audio-race Node regressions. The maintained new runners are `tests/stage-http.py`, `tests/stage-boundaries.py` and `tests/stage-sound.mjs`. HTTP runners target disposable localhost fixtures and must never run against the real campaign.

## Installation and recovery

Back up database, campaign resources and affected source, then apply additive `migrations/008_stage_tools.sql` before the matching overlay. Deploy PHP, JavaScript, CSS and both content-hashed XSL includes together. Existing account, notebook and sheet permissions do not change. No test data is copied to the live service. The existing database/resource backup schedule covers the new tables and protected map files.

Native model/controller overrides are based on pinned Cauldron revision `e7217c10a45916d703d962a894c8902f6e1c2402`, with existing integration patches retained. Review them when changing the pinned upstream. Cauldron-derived files retain its GPL v2 license; see `table-overlay/COPYING.cauldron`.
