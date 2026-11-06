# Notification preferences and delivery

`/index.php/play/writing/notifications` is linked from the writing desk, editor and authenticated Nova navigation. It edits the existing Nova `email_mission_posts` (new publications) and `email_mission_posts_save` (deliberate tagged updates) preferences, never a second account store. Existing choices are preserved on deployment. Missing publication preferences default off; missing tag preferences retain the legacy on behavior. Disabling mail does not disable desk notifications.

The delivery view contains only tagged updates requested by the signed-in sender while both players remain in the scene, and publication receipts for stories the signed-in player currently coauthors. It returns player names, story titles and statuses, never email addresses or prose. Anonymous reads fail; writes use the existing session, CSRF and same-origin protections. Notebook permissions are unchanged.

## Status and retry rules

- `sent`: SMTP accepted the message; this does not confirm inbox arrival. No retry.
- `disabled`: site email or the recipient's preference prevented sending. No retry.
- `retryable`: the sender was invalid before transport, or SMTP could not connect/resolve its host. No message could have been accepted. A deliberate retry is allowed.
- `pending`: attempt claimed but not completed. A crash may have happened after acceptance. No automatic expiry or retry.
- `uncertain`: transport failed after connection or threw an exception; acceptance cannot be ruled out. No retry.
- Historical `failed`: existing records do not establish whether SMTP accepted mail. Preserve them and block retry.

Each retry locks the delivery and scene, checks the current sender's access and recipient's active linked identity, rechecks preferences and system email, and commits `pending` before making any network call. Tagged retries also require the same saved revision and a still-private draft. A 30-second cooldown and three total attempts apply. Successful, concurrent or repeated requests cannot claim the same delivery again. No new desk notification is created by retrying an email. Initial sends retain the unique per-revision/per-recipient claims.

`008_notification_center.sql` adds retry counters and the tagged attempt timestamp. The deployment installer checks each column before adding it. Existing statuses, content and preferences remain unchanged. No backlog mail, automatic retry worker, new access grant or public data endpoint is introduced.

Mail uses Nova's already-supported loopback SMTP/cURL transport for both publication and tag notices. UTF-8 headers and bodies are encoded, recipients are individual, and authenticated links lead to the Exchange or draft. Story prose and private notes are never included.

## Verification

The isolated fixture tests check account boundaries, CSRF/origin, preference persistence, draft revision/access, opt-out rechecking, accepted/pending/uncertain/historical-failure retry blocks, cooldown and attempt limit. Browser verification saved and reopened preferences, produced a real connection refusal, retried through the UI into a fake SMTP receiver, confirmed exactly one message with no prose, and verified light/dark and narrow-screen layout. Fixture changes must never be deployed as production content.
