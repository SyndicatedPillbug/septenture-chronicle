<?php defined('BASEPATH') or exit('No direct script access allowed');$esc=static fn($v)=>htmlspecialchars(strip_tags(html_entity_decode((string)$v,ENT_QUOTES|ENT_HTML5,'UTF-8')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<link rel="stylesheet" href="/assets/writing-workspace.css?v=20261104a">
<article class="writing-hub" data-writing-hub>
<p class="writing-eyebrow">THE SEPTENTURE WRITING ROOM</p><h1>Pick up the thread.</h1>
<p class="writing-hub-intro">A new scene, a half-finished conversation, or a few notes while the dice are rolling. Your words have a place here.</p>
<nav class="writing-actions" aria-label="Start writing"><a class="primary" href="<?php echo site_url('write/missionpost'); ?>">＋ New story</a><a href="/index.php/play/notebook">My private game notes</a><a href="<?php echo $esc(rtrim(septenture_config('SITE_URL'),'/').'/fiction/'); ?>">Read the Exchange</a></nav>
<p><a href="/index.php/play/writing/notifications">Email preferences &amp; delivery</a></p><section class="writing-inbox"><h2>Your turn</h2><div data-writing-inbox role="status">Checking your scenes…</div></section>
<section><h2>Continue writing</h2><label class="writing-search">Find a draft<input type="search" data-draft-search placeholder="Title, character, or adventure"></label><div class="writing-drafts">
<?php $older=[];foreach(($posts_saved??[]) as $p):if(preg_match('/^(?:Import backup|Timeline, rules, notes)/i',strip_tags($p['title']))){$older[]=$p;continue;} ?>
<article class="writing-draft" data-draft-card><p class="writing-eyebrow">DRAFT<?php echo $p['locked']?' · IN USE':''; ?></p><h3><?php echo $esc($p['title']); ?></h3><p><?php echo $esc($p['authors']); ?><br><?php echo $esc($p['mission']); ?> · <?php echo $esc($p['date']); ?></p><a href="<?php echo site_url('write/missionpost/'.(int)$p['post_id']); ?>">Continue writing →</a><a href="<?php echo site_url('write/missionpost/'.(int)$p['post_id'].'/view'); ?>">Read draft</a></article>
<?php endforeach;if(empty($posts_saved)): ?><p>No drafts yet. Start a story whenever you’re ready.</p><?php endif; ?>
</div></section>
<?php if($older): ?><details><summary>Older import drafts (<?php echo count($older); ?>)</summary><ul><?php foreach($older as $p): ?><li><a href="<?php echo site_url('write/missionpost/'.(int)$p['post_id']); ?>"><?php echo $esc($p['title']); ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php if(!empty($logs_saved)): ?><details><summary>Existing journal drafts</summary><p>Journal entries can be published to the Exchange. Use My private game notes for notes only you can read.</p><ul><?php foreach($logs_saved as $l): ?><li><a href="<?php echo site_url('write/personallog/'.(int)$l['log_id']); ?>"><?php echo $esc($l['title']); ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php if(Auth::check_access('write/newsitem',false)): ?><details><summary>Announcements and publishing tools</summary><p><a href="<?php echo site_url('write/newsitem'); ?>">New announcement</a> · <a href="<?php echo site_url('write/personallog'); ?>">New published journal entry</a></p></details><?php endif; ?>
</article><script src="/assets/writing-workspace.js?v=notifications20261106"></script>
