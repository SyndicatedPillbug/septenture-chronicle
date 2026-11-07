# Shared campaign appearance

The canonical browser assets live in quartz/static/site-theme.js, site-theme.css and theme-sync.html. Copy JS/CSS to Nova assets and Table public/js; update the hash query in each HTML output hook. The hooks only modify HTML responses. APIs and private state are untouched.

Light: parchment, forest and brass. Dark: forest charcoal, parchment text and brass. The selector supports OS-following System mode. Preference sync stores only mode and timestamp on the Chronicle origin, validates source/origin, and falls back to explicit navigation parameters when cross-site storage is unavailable. It is browser-specific, not an account setting.

Run node --test services/campaign-theme/theme.test.mjs and verify all three services through the UI. Deploy the Chronicle bridge first, then the Nova and Table overlays. Keep server backups and do not overwrite service configurations.

The Table overlay also includes the play/workshop separation: a viewport-filling wrapper, compact play controls, native modal token selection, a current DM-only NPC-control list with CSRF-protected in-place saves, optional Activity/Sketch panels, and a separate Map Workshop with return navigation. `table-workflow.js/css` must be deployed together with both XSL layout includes and `integration/table.php` / `control.php`. Asset query values are content hashes. Token creation and movement still use the native guarded object routes; no permission or private-sheet policy changes are introduced.

See [Table workflow review](TABLE_WORKFLOW_REVIEW.md) for the November 7 acceptance scope, fixes, and remaining limits.

## Immersive scenes and conversation

Apply `migrations/006_scene.sql` before deploying the scene overlay. Deploy `integration/scene.php`, the updated `integration/router.php`, `public/js/table-scene.js`, `table-workflow.js/css`, and both XSL includes together. Scene images are raster-only blobs in the private campaign database, covered by the existing database backup. No public upload directory or external image URL is used. Current membership is checked before metadata, art bytes or chat are returned; only the campaign GM can change a scene, with CSRF validation. Recorder access remains separate and does not include this chat or scene-art endpoint.

Focus frames the existing board as a near-full-screen overlay with shaded controls and an optional Chat & rolls panel. GM scene art can replace the board visually or serve as a backdrop. Return to board restores the existing map; it retains the last scene image for reuse. Art is shared with current players after an explicit scene/backdrop choice. Dragging a file previews that choice; the file picker provides the same path. Large inputs are resized in-browser when needed. PNG/JPEG/WebP sources up to 25 MB are accepted by the picker, with a 2 MB / 24-megapixel server upload limit. Source files are not altered.

The side panel renders conversation as plain text and includes only rolls explicitly shared with the Table. Private dice ledgers and character notes are never queried by the shared panel. The panel retains the latest 60 authorized messages on the current map from the existing short-lived event store; it is not a permanent campaign archive. Scene changes and conversation refresh every two seconds while the page is visible.

November 7 acceptance: browser UI covered GM file-picker preview/upload, scene display, player focus/chat Enter submission, dice rolling and explicit sharing, readable sheets over the scene, and contrast. Existing 63 permission checks plus 16 scene/chat HTTP checks passed. Native folder-to-browser file dragging could not be confirmed with the browser control API; the actual drop handler is covered by the scene interaction regression, including preview-before-publish, player denial, foreign-message denial and SVG rejection. Recorder output still shows the map, not this scene artwork.
