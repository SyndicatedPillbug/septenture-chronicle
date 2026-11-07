# Shared campaign appearance

The canonical browser assets live in quartz/static/site-theme.js, site-theme.css and theme-sync.html. Copy JS/CSS to Nova assets and Table public/js; update the hash query in each HTML output hook. The hooks only modify HTML responses. APIs and private state are untouched.

Light: parchment, forest and brass. Dark: forest charcoal, parchment text and brass. The selector supports OS-following System mode. Preference sync stores only mode and timestamp on the Chronicle origin, validates source/origin, and falls back to explicit navigation parameters when cross-site storage is unavailable. It is browser-specific, not an account setting.

Run node --test services/campaign-theme/theme.test.mjs and verify all three services through the UI. Deploy the Chronicle bridge first, then the Nova and Table overlays. Keep server backups and do not overwrite service configurations.

The Table overlay also includes the play/workshop separation: a viewport-filling wrapper, compact play controls, native modal token selection, a current DM-only NPC-control list with CSRF-protected in-place saves, optional Activity/Sketch panels, and a separate Map Workshop with return navigation. `table-workflow.js/css` must be deployed together with both XSL layout includes and `integration/table.php` / `control.php`. Asset query values are content hashes. Token creation and movement still use the native guarded object routes; no permission or private-sheet policy changes are introduced.

See [Table workflow review](TABLE_WORKFLOW_REVIEW.md) for the November 7 acceptance scope, fixes, and remaining limits.
