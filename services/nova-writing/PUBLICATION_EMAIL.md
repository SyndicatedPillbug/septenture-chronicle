# Fiction notification behavior

Apply `migrations/007_publication_mail.sql` before deploying the publication mail helper and controller hooks. The live deployment keeps a protected backup.

Newly published native stories and approved pending stories notify active linked player accounts whose `email_mission_posts` preference is `y`, while respecting `system_email`. Each recipient gets an individual message containing the story title and authenticated Exchange link; no prose, notebook text or other recipients are included. Ordinary draft saves, pending drafts, deletions and edits to already published stories stay silent. Explicit tagged-writer notifications continue separately. No historical stories are mailed during deployment.

`nova_publication_mail` records one notice per story/normalized address. Repeated publish or approval requests cannot resend that notice. `sent` means SMTP accepted it; verify mailbox receipt separately. Historical `failed`, `uncertain`, and interrupted `pending` attempts cannot be retried through the site because delivery may already have occurred. Only definite preconnection failures (`retryable`) have a deliberate retry option; do not clear the ledger. No automatic retry worker is installed.

Check the live sender, mail-enabled setting and player preferences before attributing a missing notice to transport failure. Existing opt-outs must remain unchanged.

## Delivery center update

The notification center now exposes account email preferences and author-scoped delivery receipts. The shared SMTP transport classifies definite preconnection failures as `retryable` and other failures as `uncertain`; historical `failed` and unresolved `pending` records remain blocked from retry. See [NOTIFICATION_CENTER.md](NOTIFICATION_CENTER.md) for the current state machine and additive schema.
