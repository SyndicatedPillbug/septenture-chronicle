# Septenture writing workspace

This overlay extends the installed Nova writing service with a writing desk, studio controls, account-private notebooks and explicit scene turn notifications. It reuses the existing Markdown renderer, rich editor, account bridge, draft leases and revision checks.

Apply the additive migration before deploying the overlay to `fiction-release`. Mirror files under `overlay/assets` into `httpdocs/assets`; the PHP application and configuration stay outside the public webroot. Preserve the site-specific admin template and update its story-format/prose-editor asset fingerprints when those modules change. The deployed template here matches the verified live baseline.

## Access and publication

- Notebooks are keyed to the authenticated account UUID. The API takes no owner argument and provides no administrator/GM bypass. Notebook content has no feed, publication, mention, print, or Table-bridge route.
- Existing published journals remain separate Exchange entries.
- Collaborators explicitly added to a scene may read and edit its draft. Exact membership guards both editor HTML and draft APIs.
- Inserting an @name sends nothing and changes no access. Notify writer first saves the draft, then validates the recipient against the current collaborators and saved revision.
- The sender may pause their editing lease when notifying someone so the next writer can take over immediately. A unique sender/recipient/revision key suppresses duplicates. An inbox rechecks access after collaborators change. A bounded sender rate and CSRF/origin checks protect mutations.
- Notification emails contain a sign-in link and generic turn-taking text, never scene prose, notebook text or the scene title. Hosting SMTP uses the existing default sender and respects system email and saved-post email preferences.

## Verification

The isolated Nova fixtures passed 26 workspace API/privacy tests, 45 draft tests, 7 explicit-publication tests, 6 preview request tests, and targeted notebook/mention UI tests. A fake SMTP receiver verified the message without sending mail to players. The static-site frontend suite and build also passed. Live deployment and browser evidence are recorded in the Septenture project workspace.

## Draft recovery

Only unconfirmed edits create browser recovery records. Historical generation-zero records are ignored without deletion. Genuine unsaved writing requires an explicit recovery choice, shown in the editor status; starting a separate draft retains the old copy. IndexedDB opening and transactions have bounded waits so blocked recovery storage cannot leave the editor opening indefinitely.

Feature acceptance requires computer-use verification through the human interface: enter real test text, save, leave, reopen, and verify the saved values. Rendering controls and API checks alone do not establish completion. Use isolated accounts for sharing/notification tests, and never publish test prose or notify real players during a smoke test.

Collaborator sharing uses an in-page access confirmation, not a browser popup. Add presents the specific player and character, including read/edit access to existing text; Cancel leaves membership unchanged. Membership changes require an active editable draft and use the existing draft revision/autosave path.

## Targeted scene updates

Tag scene collaborators with @, then choose **Save & email tagged writers**. The recipient list is visible before sending. The action saves first and sends a separate generic inbox/email notification to each tagged collaborator, excluding the sender. Typing and autosave never send mail. The existing notification API validates current collaborator access and revision, respects email preferences, and deduplicates sender/recipient/revision. The optional manual handoff control remains separate; a targeted update keeps the sender's editing lease.

Legacy Nova story save/publication/delete broadcast emails are suppressed by the Write override. Journal and announcement behavior is unchanged. Rich-text tags use plain-text insertion so trailing spaces are retained instead of being consumed by Markdown parsing.

People, Setting and Topics are compact disclosures; manual handoff is collapsed by default. Word count stays in normal document flow with no inherited float clearance. Live rich/source geometry checks confirmed separation from the editor. UI-triggered targeted mail was captured locally, addressed only to a fake recipient and containing no story prose; repeating the action returned already-notified without a second email.

The @ picker searches all available linked writer characters by player or character name, including GM characters. Existing scene members are tagged immediately. Choosing a new character offers Add & tag with an explicit read/edit access explanation; confirmation updates the author selection and inserts the tag together through the existing autosave/revision path. Cancel changes neither text nor membership. Selection alone sends no notification.
