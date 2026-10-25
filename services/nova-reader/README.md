# Private Nova reader

The static Fiction Exchange reads published Nova stories and journals through `/index.php/reader/stories`. Install `reader.php` alongside Nova’s existing private `application/septenture/identity.php`, then add the reader route from `entry.php` before Nova bootstraps. Preserve the remaining entry routes. No database or account configuration changes are needed.

Every request verifies the existing player identity and game membership. The feed returns only activated posts, uses no-store responses, and permits the configured static-site origin. Story content is never emitted into the static build. The browser refreshes its catalogue every minute while visible and when returning to the tab.

Do not add account credentials, production exports, private story fixtures, or test configuration to this repository.

Journal rows use `entryType: journal`, `group: journal-<id>`, and the Journal shelf. Detail requests include `type: journal`; omitted types retain compatibility with story links. Character links initialize the reader from `?character=<slug>&kind=Stories` or `kind=Journal`. Authoring starts through the existing session bridge with `compose=story` or `compose=journal`.

`identity.php` preserves an existing owned Nova main character during account synchronization. If that character is absent or belongs to another user, synchronization falls back to an owned character. Player credentials and roster handoff files are never stored in this repository.

The SSO account bridge fills missing Nova user preferences from the normal join-form defaults, including shared-draft email notifications. Existing preferences, including opt-outs, are preserved on subsequent logins. Delivery also requires Nova outgoing email to be enabled and an authenticated sender configured on the host; the account bridge does not enable transport or send messages.
