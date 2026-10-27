<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/campaign.php';

function septenture_api(string $path, string $token): array {
    $ch = curl_init(rtrim(septenture_config('SUPABASE_URL'), '/').$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>12,
        CURLOPT_HTTPHEADER=>['apikey: '.septenture_config('SUPABASE_KEY'), 'Authorization: Bearer '.$token, 'Accept: application/json']]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 500 || $status === 0) throw new RuntimeException('The player account service is unavailable.', 503);
    if ($status !== 200) throw new RuntimeException('Please sign in again.', 401);
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Invalid account service response.', 503);
    return $data;
}
function septenture_identity(string $token): array {
    if (!preg_match('/^[A-Za-z0-9_.-]{30,8192}$/D', $token)) throw new RuntimeException('Please sign in again.', 401);
    $user = septenture_api('/auth/v1/user', $token);
    $uuid = $user['id'] ?? '';
    if (!preg_match('/^[a-f0-9-]{36}$/D', $uuid)) throw new RuntimeException('Invalid player account.', 401);
    // Campaign roles are protected records, never user metadata or a Nova rank.
    $campaign=septenture_campaign();
    $members=septenture_api('/rest/v1/campaign_members?select=campaign_id,user_id,role&campaign_id=eq.'.rawurlencode($campaign).'&user_id=eq.'.$uuid,$token);
    $role=septenture_campaign_role($members,$uuid,$campaign);
    if($role===null)throw new RuntimeException('Your account has not been invited to this campaign.',403);
    $owners=septenture_api('/rest/v1/character_owners?select=character_slug&user_id=eq.'.$uuid,$token);
    $profiles=septenture_api('/rest/v1/character_profiles?select=slug&campaign_id=eq.'.rawurlencode($campaign),$token);
    $slugs=array_values(array_intersect(array_column($owners,'character_slug'),array_column($profiles,'slug')));
    return ['uuid'=>$uuid,'email'=>$user['email']??'','admin'=>in_array($role,['owner','gm'],true),
        'site_admin'=>$role==='owner'&&hash_equals(septenture_config('PLAY_ADMIN_SUBJECT'),$uuid),
        'campaign'=>$campaign,'campaign_role'=>$role,'slugs'=>$slugs];
}
function septenture_provision(array $identity): int {
    $db = septenture_db();
    $bound=$db->query('SELECT campaign_id FROM nova_septenture_campaign WHERE singleton=1')->fetchColumn();
    if($bound!==septenture_campaign())throw new RuntimeException('Campaign database does not match this installation.',503);
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT userid FROM nova_septenture_identities WHERE subject=? FOR UPDATE');
        $q->execute([$identity['uuid']]);
        $id=$q->fetchColumn();
        $system=$identity['site_admin']??false;
        $role=$system?1:($identity['admin']?2:4);
        if (!$id) {
            // Nova never receives or stores the player's actual password.
            $q=$db->prepare('INSERT INTO nova_users (name,email,password,access_role,is_sysadmin,is_game_master,is_webmaster,is_firstlaunch,join_date,my_links) VALUES (?,?,?,?,?,?,?,\'n\',?,\'5\')');
            $q->execute([$identity['admin'] ? 'Game Master' : 'Player', substr($identity['email'],0,100), bin2hex(random_bytes(20)), $role,
                $system?'y':'n',$identity['admin']?'y':'n',$system?'y':'n',time()]);
            $id=(int)$db->lastInsertId();
            $q=$db->prepare('INSERT INTO nova_septenture_identities (subject,userid) VALUES (?,?)');
            $q->execute([$identity['uuid'],$id]);
        }
        $q=$db->prepare('UPDATE nova_users SET access_role=?,is_sysadmin=?,is_game_master=?,is_webmaster=?,email=? WHERE userid=?');
        $q->execute([$role,$system?'y':'n',$identity['admin']?'y':'n',$system?'y':'n',substr($identity['email'],0,100),$id]);
        // SSO bypasses Nova's join form, which normally creates these defaults.
        // Backfill missing preferences while preserving every explicit opt-out.
        $q=$db->prepare('INSERT INTO nova_user_prefs_values (prefvalue_user,prefvalue_key,prefvalue_value) SELECT ?,p.pref_key,p.pref_default FROM nova_user_prefs p LEFT JOIN nova_user_prefs_values v ON v.prefvalue_key=p.pref_key AND v.prefvalue_user=? WHERE v.prefvalue_id IS NULL');
        $q->execute([$id,$id]);
        // Website ownership remains authoritative. GM ownership of every public cast page
        // does not transfer PCs away from their players in Nova.
        if (!$identity['admin']) {
            $q=$db->prepare('UPDATE nova_characters c JOIN nova_septenture_cast m ON m.charid=c.charid SET c.user=? WHERE m.slug=?');
            foreach ($identity['slugs'] as $slug) $q->execute([$id,$slug]);
        }
        $q=$db->prepare('SELECT c.charid FROM nova_characters c JOIN nova_users u ON u.userid=c.user WHERE c.user=? ORDER BY (c.charid=u.main_char) DESC,c.charid LIMIT 1');
        $q->execute([$id]); $main=$q->fetchColumn();
        if (!$main && $identity['admin']) {
            $q=$db->prepare('INSERT INTO nova_characters (user,first_name,crew_type,date_activate,position_1) VALUES (?,\'Game Master\',\'active\',?,0)');
            $q->execute([$id,time()]); $main=$db->lastInsertId();
        }
        // DM is a protected campaign assignment. Only a dedicated, non-cast GM
        // identity may display it; ordinary PCs and NPCs keep character classes.
        $q=$db->prepare("SELECT c.charid FROM nova_characters c LEFT JOIN nova_septenture_cast m ON m.charid=c.charid WHERE c.user=? AND m.charid IS NULL AND c.first_name IN ('Game Master','Dungeon Master') ORDER BY c.charid LIMIT 1");
        $q->execute([$id]);$gmChar=$identity['admin']?$q->fetchColumn():false;
        if($gmChar){
            $q=$db->prepare('INSERT INTO nova_septenture_gm_cast(userid,charid) VALUES(?,?) ON DUPLICATE KEY UPDATE charid=VALUES(charid)');$q->execute([$id,$gmChar]);
        }else{$q=$db->prepare('DELETE FROM nova_septenture_gm_cast WHERE userid=?');$q->execute([$id]);}
        foreach(['position_1'=>'positions','position_2'=>'positions','rank'=>'ranks'] as $column=>$table){
            $key=$table==='ranks'?'rank_id':'pos_id';$group=$table==='ranks'?'rank_class':'pos_dept';
            $q=$db->prepare("UPDATE nova_characters c JOIN nova_$table r ON r.$key=c.$column SET c.$column=0 WHERE c.user=? AND r.$group=1 AND c.charid<>?");$q->execute([$id,$gmChar?:0]);
        }
        if($gmChar){
            $q=$db->prepare("UPDATE nova_characters SET position_1=COALESCE((SELECT pos_id FROM nova_positions WHERE pos_name='Dungeon Master' LIMIT 1),0),rank=(SELECT rank_id FROM nova_ranks WHERE rank_name='Dungeon Master' LIMIT 1) WHERE charid=?");$q->execute([$gmChar]);
        }
        if ($main) {
            $q=$db->prepare('UPDATE nova_users SET main_char=? WHERE userid=?');$q->execute([$main,$id]);
        }
        $db->commit();
        return (int)$id;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
