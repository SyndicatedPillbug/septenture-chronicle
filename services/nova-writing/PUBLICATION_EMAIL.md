# Fiction notification behavior

Apply `migrations/007_publication_mail.sql` before deploying the publication mail helper and controller hooks. The live deployment keeps a protected backup.

Newly published native stories and approved pending stories notify active linked player accounts whose `email_mission_posts` preference is `y`, while respecting `system_email`. Each recipient gets an individual message containing the story title and authenticated Exchange link; no prose, notebook text or other recipients are included. Ordinary draft saves, pending drafts, deletions and edits to already published stories stay silent. Explicit tagged-writer notifications continue separately. No historical stories are mailed during deployment.

`nova_publication_mail` records one attempt per story/normalized address. Repeated publish or approval requests cannot resend that notice. `sent` means SMTP accepted it; verify mailbox receipt separately. A `failed` or interrupted `pending` attempt needs operator review before a deliberate retry; do not blindly clear the ledger because delivery may already have occurred. No automatic retry worker is installed.

Check the live sender, mail-enabled setting and player preferences before attributing a missing notice to transport failure. Existing opt-outs must remain unchanged.
