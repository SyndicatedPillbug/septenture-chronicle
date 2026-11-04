# Septenture writing workspace

This overlay extends the installed Nova writing service with a writing desk, studio controls, account-private notebooks and explicit scene turn notifications. It reuses the existing Markdown renderer, rich editor, account bridge, draft leases and revision checks.

Apply the additive migration before deploying the overlay to `fiction-release`. Mirror files under `overlay/assets` into `httpdocs/assets`; the PHP application and configuration stay outside the public webroot. Preserve the site-specific admin template and update its story-format/prose-editor asset fingerprints when those modules change. The deployed template here matches the verified live baseline.

## Access and publication

- Notebooks are keyed to the authenticated account UUID. The API takes no owner argument and provides no administrator/GM bypass. Notebook content has no feed, publication, mention, print, or Table-bridge route.
- Existing published journals remain separate Exchange entries.
- Collaborators explicitly added to a scene may read and edit its draft. Exact membership guards both editor HTML and draft APIs.
- Inserting an @name sends nothing and changes no access. Notify writer first saves the draft, then validates the recipient against the current collaborators and saved revision.
- A unique sender/recipient/revision key suppresses duplicates. An inbox rechecks access after collaborators change. A bounded sender rate and CSRF/origin checks protect mutations.
- Notification emails contain a sign-in link and generic turn-taking text, never scene prose, notebook text or the scene title. Hosting SMTP uses the existing default sender and respects system email and saved-post email preferences.

## Verification

The isolated Nova fixtures passed 26 workspace API/privacy tests, 45 draft tests, 7 explicit-publication tests, 6 preview request tests, and targeted notebook/mention UI tests. A fake SMTP receiver verified the message without sending mail to players. The static-site frontend suite and build also passed. Live deployment and browser evidence are recorded in the Septenture project workspace.
