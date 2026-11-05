# Table repairs — 5 November 2026

Dice form submission is intercepted before history loading, including Enter and history failures. Account-service outages deny requests without destroying the existing session; real 401/403 responses still revoke it. Nova verifies current account and campaign membership without unrelated public-profile lookups for each tool action.

Focus board enlarges the existing viewport within the page, keeps controls and Exit focus, and supports Escape. It preserves the iframe, map dimensions and coordinate origin. It is not a new zoom feature.

GM dialogs return to the tools home. Leave table uses a CSRF-protected POST, ends the Table session and returns to Chronicle Account.

NPC roster rows expose Assign player for the administrator. Private NPC writing assignments are concurrency-checked and revalidate campaign membership. Private sheet/control access does not silently move. Shared-profile NPCs retain the account service as ownership authority and cannot use this private-only shortcut.

Validation: local human UI focus/exit, Tokens/back, piece-control/home, dice click and Enter without navigation, private note format/save/reopen, and Leave table reaching Chronicle Account. Live focus and embedded sheets checked. Local role/CSRF/fog/ownership checks: 63/63; targeted outage check passes. No real NPC assignment, private notebook, public announcement or player notification was changed during verification.
