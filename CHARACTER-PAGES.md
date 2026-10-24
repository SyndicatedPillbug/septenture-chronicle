# The Septenture party webring

The Cast lives in `content/the-cast/`. Its styling is scoped in
`quartz/styles/companions.scss`. Audio and illustrated emblems live in
`quartz/static/companions/`.

## Shared profiles and accounts

Supabase project: `qjmeeupvjulrjedvitnk` (Septenture, us-east-1).
The project was created on the free plan at a quoted $0/month.

`quartz/static/community-config.json` contains the public project URL and
publishable key. It must never contain a service-role key. The account Edge
Function receives its service-role key from Supabase's server environment.

Public visitors can read profiles and guestbook notes. Assigned players can
sign guestbooks and edit their own profiles, including portrait, banner,
theme, accent, bio, interests, mood, location, and music. The site owner owns
all seven pages and can make player invitations. Postgres RLS and Storage
policies enforce these rules; hiding buttons is only a presentation choice.

Private, single-use invitation links expire after seven days. Their random
tokens are hashed in the database. The account function atomically claims
the invitation, creates an email/password account, and assigns its page.
The player chooses their password. No email is sent by this flow, and it
does not independently verify mailbox ownership. Invitations should go
directly to their intended recipients. Never put an invitation token in Git.

Existing accounts can receive additional page ownership through the
Supabase dashboard or an administrator SQL operation; a second signup
invitation cannot reuse an existing email address.

Players can change their password while signed in. Until an SMTP provider
and recovery flow are configured, forgotten-password recovery requires the
site owner to use Supabase's user administration. The default Supabase
email service is unsuitable for delivering recovery mail to the group.

Uploads become public website assets. Images are limited in the editor to
5 MB and music to 20 MB. The bucket also enforces a 20 MB maximum and a MIME
allowlist. Uploaded images support JPEG, PNG, and WebP. The free plan has
1 GB of file storage and 5 GB of egress; music should use compressed files.
Free projects can pause after a week of low activity. See
https://supabase.com/pricing and https://supabase.com/docs/guides/platform/free-project-pausing.

## Content and fallbacks

The static Markdown remains the readable fallback if Supabase is down.
The database profile overrides editable fields after loading. Initial profile
text and scene captions are compiled from the session records documented in
`content/the-cast/source-notes.md`. The From the Session Records panel and
source notes remain editorial content when a player edits their About panel
or pinboard. Themes and ambient music are decorative; they do not establish
character appearance, inventory, or musical taste. Emblems illustrate objects
rather than inventing player character likenesses. The checked content data
is retained in `data/character-session-facts.json`.

Before potentially breaking website or database changes, show Odin the exact
code diff, explain the effects, and wait for explicit approval before applying
or deploying it. This review preference was requested on October 23, 2026.

The visitor soundtrack picker previews a file for the current visit. To
make music permanent for everyone, log in, choose **Edit my page**, upload
the track, and save. The local guestbook demo is replaced by the shared
guestbook when the backend connects; old browser-local notes are never
silently published.

Original 24-second ambient loops were synthesized for this site. The Page
banner was generated with the built-in imagegen tool. Prompt: “A richly
detailed hand-painted 1990s fantasy adventure illustration of an ancient
bookbinder's workbench beneath tree roots, an open hand-sewn book, thread,
bone folder, brass scissors, candle, moths, and an arched window looking
onto a moonlit forest; indigo, moss green, old gold, worn parchment; wide
composition with dark space for a title; no people, text, or interface.”
Saved asset: `quartz/static/companions/bookbinder.png`.

## Verification

Build: `node quartz/bootstrap-cli.mjs build`.
Syntax: `node --check quartz/static/companions.js` and
`node --check quartz/static/companions-community.js`.

`supabase/tests/ownership.sql` runs live role tests in a transaction and
rolls everything back. It covers page ownership, protected columns,
guestbook posting and deletion, authorship spoofing, rate limiting,
media ownership, and anonymous access. It passed on the configured project.
The Supabase security advisor reported no findings after configuration.

Migrations are retained in `supabase/migrations/`; Edge Function source is
in `supabase/functions/character-accounts/index.ts`. The Edge Function
validates invitation secrets itself for account redemption and validates
an authenticated administrator for invitation creation, so gateway JWT
verification is intentionally disabled for this function.
