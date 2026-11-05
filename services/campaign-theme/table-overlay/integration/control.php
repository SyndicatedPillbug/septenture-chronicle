<?php
/** DM-only explicit NPC control; this never grants private sheet access. */
function tabletop_control(TabletopService $svc,array $ctx): never {
    if($ctx['purpose']==='dm'&&$_SERVER['REQUEST_METHOD']==='GET'){
        $pieces=$svc->rows('SELECT t.id,COALESCE(t.name,n.name) AS name FROM map_token t JOIN tokens n ON n.id=t.token_id JOIN maps m ON m.id=t.map_id WHERE m.adventure_id=? ORDER BY t.id',[$ctx['cauldron_adventure_id']]);
        tabletop_response(['pieces'=>$pieces]);
    }
    if($ctx['purpose']!=='dm'||$_SERVER['REQUEST_METHOD']!=='POST')tabletop_response(['error'=>'Only the DM can assign pieces.'],403);
    if(!hash_equals($_SESSION['tabletop_csrf']??'missing',(string)($_POST['csrf']??'')))tabletop_response(['error'=>'Please reopen the table.'],403);
    $token=filter_var($_POST['token']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$subject=$_POST['subject']??'';
    if(!$token||!is_string($subject)||!in_array($_POST['action']??null,['grant','revoke'],true))tabletop_response(['error'=>'Invalid control assignment.'],400);
    $piece=$svc->rows('SELECT t.id FROM map_token t JOIN maps m ON m.id=t.map_id WHERE t.id=? AND m.adventure_id=?',[$token,$ctx['cauldron_adventure_id']]);
    if(!$piece)tabletop_response(['error'=>'This piece is outside the campaign.'],403);
    if($_POST['action']==='grant'){
        $user=$svc->rows('SELECT cauldron_user_id FROM septenture_users WHERE campaign_key=? AND supabase_user_id=?',[$ctx['campaign_key'],$subject]);
        if(count($user)!==1)tabletop_response(['error'=>'This player is outside the campaign.'],403);
        $svc->rows('INSERT INTO septenture_token_control(campaign_key,map_token_instance_id,supabase_user_id,granted_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE supabase_user_id=VALUES(supabase_user_id),granted_by=VALUES(granted_by),granted_at=CURRENT_TIMESTAMP',[$ctx['campaign_key'],$token,$subject,$ctx['subject']]);
    }else $svc->rows('DELETE FROM septenture_token_control WHERE campaign_key=? AND map_token_instance_id=?',[$ctx['campaign_key'],$token]);
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))tabletop_response(['ok'=>true]);
    tabletop_redirect('/session/table/'.$ctx['campaign_key']);
}
