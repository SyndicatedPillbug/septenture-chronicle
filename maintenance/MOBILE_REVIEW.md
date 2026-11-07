# Mobile workflow review — November 7, 2026

Scope: shared Chronicle navigation, character-to-Exchange links, authenticated Exchange filtering/reading, and Nova draft writing at 390px, with a 320px spot check and a desktop comparison. Table play remains outside this pass.

Changes:

- Shared navigation uses three columns of readable links with 44px minimum targets on phones.
- Reader credit checkboxes no longer inherit a negative margin that overlaps the theme control. Opening the reader locks the document scrollbar as well as its body.
- Nova's authorized tools menu is collapsible above the editor on phones and stays expanded on desktop. Its existing links and authorization remain unchanged.
- The writer picker retains its opaque panel, spacing and scroll bounds after moving into focus mode. Legacy collaborator widgets fit the available width.

Human browser verification is recorded in the local `outputs/mobile-audit-20261107/` report and captures. Draft typing, save/reopen, keyboard @, collaborator confirmation and focus exit are tested with fake local accounts. Real character profiles are read only; fiction is not published and notification actions are not used. Live navigation and reader behavior are checked after deployment.

Limits: viewport checks in a desktop browser do not establish touchscreen, software-keyboard, screen-reader or full accessibility compliance. Those need separate device and assistive-technology testing.
