# Manual Table tools — November 7, 2026

The Table adds manual measurement, exploration, reusable media and private session moments. These extend the play-kit dialogs and the existing native board; they do not calculate game rules.

## Play and preparation

**Ruler** starts a two-point measurement directly from the top bar. **Templates** opens a compact circle, fixed 60° cone, or rectangle picker. Click two map points; visible Cancel tool or Escape cancels. Distance uses the map's chosen distance per square and unit, with straight-line ruler measurements. Templates start private. Sharing is explicit, and owners can remove their own templates. Templates are scoped to the active map.

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

## Navigation and range-label follow-up — November 7

The compact play bar keeps Dice, Ruler, Templates, Sheets and Chat together. Tools opens a searchable, grouped menu that preserves native Sketch, tokens and advanced preparation. Submenus return to the same menu. On narrow screens, the play actions scroll while Tools and Exit focus stay pinned. Active drawing exposes Stop drawing. Selecting another tool cancels pending placement/drawing rather than leaving two map actions armed.

Escape closes dialogs or cancels active tools; it never exits board focus. Exit focus is an explicit, visible action. Pending ruler, ping and token placement have visible Cancel tool controls.

Every measurement label includes feet and an approximate Daggerheart fictional distance band: up to 5 feet Melee, 10 Very Close, 30 Close, 100 Far, 300 Very Far, then Out of Range. The 5-foot Melee boundary is a practical estimate for touching distance. These are approximate fictional ranges, not the optional defined-grid rules; the GM adjudicates context. Circle labels describe radius, cone labels reach, and rectangle labels both dimensions. Metric and yard scales convert to feet; unrecognized units require a feet scale rather than inventing a conversion. Existing saved templates receive labels without rewriting stored data.

Source: [official Daggerheart SRD 2.0, page 51](https://www.daggerheart.com/wp-content/uploads/2026/08/DH_SRD_2_2026_08_25.pdf).

Human UI checks used isolated GM and player accounts, dark/light desktop and 390×844 layouts. They exercised direct ruler creation, circle/cone/rectangle selection, cone and rectangle creation, cancellation, repeated Escape retaining focus, explicit focus exit, tool search, role filtering, drawing-to-ruler transition, ping/token cancellation, and submenu return. Automated tests cover range boundaries, metric conversion, actual SVG labels, private defaults and focus behavior. This follow-up changes client navigation and presentation only; no migration or account/sheet/notebook permission changes.

## Context-menu and marker repair — November 7

Native **Measure distance** now anchors the first point at the original right-click and uses the same private saved ruler as the toolbar, including visible feet/bands and reload persistence. The legacy area-template submenu now opens the supported Templates picker instead of sending rejected spell-overlay events. Context **Manage measurements** and the Templates panel expose saved measurements and owner/GM removal directly, with explicit success feedback. The server permission boundary is unchanged.

**Set marker** uses the original context-click in scrolled map coordinates for both the local marker and shared event. The pin tip is anchored at that point. The transient marker image cannot initiate a browser image drag, and internal board drags are excluded from the scene file-drop handlers in both board and wrapper. Genuine external file drops retain their preview-before-sharing flow.

Isolated GM/player human UI checks exercised context-menu ruler creation, reload persistence, owner-only removal, supported circle creation through the context menu, marker placement and dragging across the marker without opening the scene uploader. Unit regressions verify origin coordinates, shared/private defaults, removal refresh, legacy-callback bypass, marker coordinates, and internal/external drop separation. The earlier live private ruler was removed at the user's request through the existing management UI before deployment.
