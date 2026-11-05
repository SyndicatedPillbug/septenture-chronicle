<?php
function tabletop_page(array $campaign,array $config,TabletopService $svc,array $ctx): void {
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('Referrer-Policy: strict-origin');
    $siteOrigin=parse_url($config['site_url'],PHP_URL_SCHEME).'://'.parse_url($config['site_url'],PHP_URL_HOST);
    header("Content-Security-Policy: default-src 'self'; frame-src 'self' $siteOrigin; style-src 'self' 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://*.supabase.co; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
    $esc=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $name=$esc($campaign['campaign_display_name']);$nova=$esc(rtrim($config['nova_url'],'/').'/index.php/auth/start');$site=$esc(rtrim(($config['chronicle_url']??$config['site_url']),'/'));
    $adventure=(int)$campaign['cauldron_adventure_id'];
    $dmTools='';
    $characters=$svc->rows('SELECT c.name,m.character_key FROM septenture_characters m JOIN characters c ON c.id=m.cauldron_character_id WHERE m.campaign_key=? AND c.user_id=? ORDER BY c.name',[$ctx['campaign_key'],$ctx['cauldron_user_id']]);
    $sheetLinks='';
    if($characters){
        $sheetLinks='<p class="hint">Your characters: ';
        foreach($characters as $character)$sheetLinks.='<a target="_blank" rel="noopener" href="'.$esc(rtrim($config['nova_url'],'/').'/index.php/auth/start?character='.rawurlencode($character['character_key'])).'">'.$esc($character['name']).'</a> · ';
        $sheetLinks=rtrim($sheetLinks,' · ').'</p>';
    }

    if($ctx['purpose']==='dm'){
        $players=$svc->rows('SELECT supabase_user_id,display_name FROM septenture_users WHERE campaign_key=? ORDER BY display_name',[$ctx['campaign_key']]);
        $pieces=$svc->rows('SELECT t.id,COALESCE(t.name,n.name) AS name FROM map_token t JOIN tokens n ON n.id=t.token_id JOIN maps m ON m.id=t.map_id WHERE m.adventure_id=? ORDER BY t.id',[$ctx['cauldron_adventure_id']]);
        $dmTools='<dialog id="piece-control" aria-labelledby="piece-title"><button type="button" data-close-control>Close</button><h2 id="piece-title">Piece control</h2><p>Choose which player can move an NPC piece. Private sheet access stays separate.</p><p role="status"></p>';
        if($players){
            $dmTools.='<form method="post" action="/session/control"><input type="hidden" name="csrf" value="'.$esc($_SESSION['tabletop_csrf']).'"><label>Piece <select name="token">';
            foreach($pieces as $piece)$dmTools.='<option value="'.(int)$piece['id'].'">'.$esc($piece['name']).'</option>';
            $dmTools.='</select></label> <label>Player <select name="subject">';
            foreach($players as $player)$dmTools.='<option value="'.$esc($player['supabase_user_id']).'">'.$esc($player['display_name']).'</option>';
            $dmTools.='</select></label> <button name="action" value="grant">Give control</button> <button name="action" value="revoke">Remove control</button></form><p>Piece control is separate from private character sheets.</p>';
        }
        if(!$pieces)$dmTools.='<p>No NPC pieces are on the maps yet. Use Tokens to place one first.</p>';
        $dmTools.='</dialog>';
    }
    $rev=substr(hash_file('sha256',__DIR__.'/../public/js/table-workflow.js'),0,12);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.$name.' · The Table</title><link rel="stylesheet" href="/css/table-workflow.css?v='.$rev.'"><body class="campaign-table-shell"><header class="table-shell-header"><h1>'.$name.' · The Table</h1><nav aria-label="Campaign"><details class="campaign-links"><summary>Campaign</summary><div><a href="'.$site.'" target="_blank" rel="noopener">Chronicle</a><a href="'.$site.'/fiction/" target="_blank" rel="noopener">Exchange</a><a href="'.$site.'/the-cast/" target="_blank" rel="noopener">Cast</a><a href="'.$site.'/account/" target="_blank" rel="noopener">Account</a><a href="'.$nova.'" target="_blank" rel="noopener">Writing desk</a><a href="/logout">Leave table</a></div></details></nav></header><iframe class="campaign-board" src="/adventure/'.$adventure.'?view='.$rev.'-'.time().'" title="'.$name.' tabletop" allow="fullscreen"></iframe>'.$dmTools.'<script src="/js/table-workflow.js?v='.$rev.'"></script><script type="module" src="/js/tabletop-session.js?v='.substr(hash_file('sha256',__DIR__.'/../public/js/tabletop-session.js'),0,16).'"></script></body></html>';
}
