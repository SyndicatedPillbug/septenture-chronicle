# Shared campaign appearance

The canonical browser assets live in quartz/static/site-theme.js, site-theme.css and theme-sync.html. Copy JS/CSS to Nova assets and Table public/js; update the hash query in each HTML output hook. The hooks only modify HTML responses. APIs and private state are untouched.

Light: parchment, forest and brass. Dark: forest charcoal, parchment text and brass. The selector supports OS-following System mode. Preference sync stores only mode and timestamp on the Chronicle origin, validates source/origin, and falls back to explicit navigation parameters when cross-site storage is unavailable. It is browser-specific, not an account setting.

Run node --test services/campaign-theme/theme.test.mjs and verify all three services through the UI. Deploy the Chronicle bridge first, then the Nova and Table overlays. Keep server backups and do not overwrite service configurations.
