---
title: The Fiction Exchange
description: The Septenture fiction archive. Stories, letters, and conversations from the days between adventures.
---

<link rel="stylesheet" href="../static/fiction-reader.css">
<section class="fiction-exchange" id="fiction-exchange" data-fiction-reader>
  <div class="fx-statusbar"><span>✶ SEPTENTURE / FRIENDS ONLY</span><span>YOUR STORIES HAVE A HOME HERE</span></div>
  <header class="fx-mast">
    <div><p class="fx-eyebrow">THE THINGS WE WRITE BETWEEN ADVENTURES</p><h1>The Fiction<br><em>Exchange</em><span aria-hidden="true">✧</span></h1><p class="fx-tagline">Letters. Loose ends. Very questionable company.</p></div>
    <aside class="fx-profile"><div class="fx-profile-seal" aria-hidden="true">✦</div><strong>Septenture</strong><span>“there is always more to the story”</span><p>Private corner of the internet<br>Current mood: <em>one more chapter</em></p><a data-fx-write href="./login/">✎ Open the writing desk</a></aside>
  </header>
  <div class="fx-ribbon"><span>✧ a place for the stories that wouldn’t fit around the table ✧</span><span class="fx-live" data-fx-live>PLAYER LOGIN REQUIRED</span></div>
  <div class="fx-gate" data-fx-gate>
    <div><span class="fx-eyebrow">YOU’RE ON THE LIST</span><h2>Come in. Pull up a chair.</h2><p>The shelves are open to Septenture players. Sign in with the account you use for your character page.</p></div>
    <form data-fx-login hidden><label>Email<input name="email" type="email" autocomplete="username" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button type="submit">Enter the Exchange →</button></form>
    <p data-fx-status role="status" aria-live="polite">Opening the door…</p>
    <p class="fx-small"><a data-fx-write href="./login/">Or open the writing desk ↗</a></p>
  </div>
  <div class="fx-layout" data-fx-library hidden>
    <aside class="fx-sidebar">
      <section class="fx-window"><h2>our circle <span>♥</span></h2><p class="fx-small">Choose someone to follow.</p><div class="fx-friends" data-fx-friends></div><button class="fx-text-button" data-fx-all>Show everybody’s stories →</button></section>
      <section class="fx-window fx-shelf-note"><h2>about this space</h2><p>Some stories begin after everyone else has gone to bed.</p><p>Others have been waiting years to be told.</p><p>They’re all welcome here.</p><div class="fx-stamps"><span>best viewed<br>after midnight</span><span>made with<br>too much lore</span></div></section>
      <section class="fx-window"><h2>the party webring</h2><a href="../the-cast/">Meet the companions ↗</a><button class="fx-text-button" data-fx-random>Take me somewhere unexpected ✧</button></section>
    </aside>
    <div class="fx-main">
      <section class="fx-controls" aria-label="Find a story">
        <div class="fx-controls-top"><h2>On the shelves <span data-fx-count></span></h2><button class="fx-text-button" data-fx-refresh>↻ Refresh</button></div>
        <label class="fx-search"><span class="fx-sr">Search stories</span><span aria-hidden="true">⌕</span><input type="search" data-fx-search placeholder="A name, a secret, a half-remembered line…"></label>
        <div class="fx-filter-row"><label>CHARACTER<select data-fx-character><option value="">Everybody</option></select></label><label>WRITER<select data-fx-writer><option value="">All writers</option></select></label><label>ON THE SHELF<select data-fx-kind><option value="">Everything</option><option>Fiction</option><option>Roleplay</option><option>Backstory</option><option>Reference</option></select></label><label>SORT<select data-fx-sort><option value="new">Newest writing</option><option value="old">Oldest writing</option><option value="short">A quick read</option><option value="long">Settle in</option><option value="az">A → Z</option></select></label></div>
        <div class="fx-results-line"><span data-fx-results role="status" aria-live="polite"></span><button class="fx-text-button" data-fx-reset>Clear filters</button></div>
      </section>
      <div class="fx-cards" data-fx-cards></div>
      <p class="fx-empty" data-fx-empty hidden>No stories on this shelf yet. Try another filter.</p>
      <footer class="fx-foot"><span>✧ FROM THE WRITING DESK TO YOUR LITTLE CORNER OF THE WEB</span><button class="fx-text-button" data-fx-signout>Sign out</button></footer>
    </div>
  </div>
  <section class="fx-reader" data-fx-reader-panel hidden role="dialog" aria-modal="true" aria-label="Story reader" tabindex="-1">
    <nav class="fx-reader-nav"><button data-fx-back>← Back to the Exchange</button><a data-fx-original target="_blank" rel="noopener noreferrer">Read at the writing desk ↗</a></nav>
    <header><p class="fx-eyebrow" data-fx-reader-kind></p><h2 data-fx-reader-title></h2><p data-fx-reader-byline></p><p class="fx-small" data-fx-reader-timeline></p></header>
    <div class="fx-reader-tools" aria-label="Reading preferences"><button data-fx-smaller aria-label="Smaller text">A−</button><button data-fx-larger aria-label="Larger text">A+</button><button data-fx-tone aria-pressed="false">Night paper</button><label><input type="checkbox" data-fx-credits checked> Writer credits</label><span data-fx-reader-progress></span></div>
    <div class="fx-story" data-fx-story role="status">Opening the story…</div>
    <nav class="fx-reader-bottom"><button data-fx-prev>← Previous story</button><button data-fx-next>Next story →</button></nav>
  </section>
</section>
