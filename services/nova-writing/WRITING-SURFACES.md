# Writing surfaces — 5 November 2026

The shared ribbon covers paragraph styles, history, inline marks, lists, quotations, links, images and optional table commands. Titles, names, numbers, URLs, short profile facts and configuration remain purpose-specific.

| Surface | Storage and verification |
| --- | --- |
| Stories | Markdown with existing leases/revisions/recovery. Local UI: write, bold, save, reopen. |
| My game notes | Owner-only Markdown notebook. Local UI: write, bold, save, reload. Live ribbon and versioned asset URLs checked. |
| Journals | Existing explicit Markdown/legacy format and revision. Ten save/reopen, conflict and privacy checks pass. |
| Manual sheet prose | Existing per-section plain/Markdown source. Embedded Table UI and 21 access/print/revision checks pass. |
| Private sheet notes | Existing source plus optional private notes_format. Table UI: enable formatting, bold, save, reopen. Export still requires explicit note inclusion. |
| Messages | Existing Nova HTML storage/readers. Local UI: compose, italicize, send with email disabled, reopen with italics intact. |
| Announcements | Existing Nova HTML storage/readers. Local UI: compose, bold, save. No live announcement published. |
| Nova comments | Existing Nova HTML storage/readers. Shared adapter includes dynamic comment forms; individual browser rehearsal remains. |
| Public biography, prose details, guestbook | Markdown marker in existing public string; unmarked text remains literal. Safe DOM renderer; local public fixture write/bold/save/reload checked. No live profile mutated. |

Legacy HTML retains its exact original source until edited. Unsupported old formatting falls back to source rather than discarding it. HTML-backed native forms retain HTML source; modern Markdown-backed surfaces expose Markdown source. Private sheets and notebooks never populate public profile fields.

Hidden wiki, legacy administration/configuration and parked rules-engine surfaces are not newly exposed. Individual comment and public-field UI rehearsals remain before claiming exhaustive sitewide acceptance. Shared-component tests do not replace that inventory.
