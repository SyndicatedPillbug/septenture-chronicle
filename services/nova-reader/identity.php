<?php
require_once __DIR__.'/config.php';

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
    // Membership and administrator status come from RLS-protected tables, never user_metadata.
    $owners = septenture_api('/rest/v1/character_owners?select=character_slug&user_id=eq.'.$uuid, $token);
    $admins = septenture_api('/rest/v1/community_admins?select=user_id&user_id=eq.'.$uuid, $token);
    if (!$owners && !$admins) throw new RuntimeException('Your account has not been assigned to the game. Ask the GM for an invitation.', 403);
    return ['uuid'=>$uuid, 'email'=>$user['email'] ?? '', 'admin'=>count($admins)>0,
        'slugs'=>array_column($owners, 'character_slug')];
}
function septenture_provision(array $identity): int {
    $db = septenture_db();
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT userid FROM nova_septenture_identities WHERE subject=? FOR UPDATE');
        $q->execute([$identity['uuid']]);
        $id=$q->fetchColumn();
        $role=$identity['admin'] ? 1 : 4;
        if (!$id) {
            // Nova never receives or stores the player's actual password.
            $q=$db->prepare('INSERT INTO nova_users (name,email,password,access_role,is_sysadmin,is_game_master,is_webmaster,is_firstlaunch,join_date,my_links) VALUES (?,?,?,?,?,?,?,\'n\',?,\'5\')');
            $q->execute([$identity['admin'] ? 'Game Master' : 'Player', substr($identity['email'],0,100), bin2hex(random_bytes(20)), $role,
                $identity['admin']?'y':'n',$identity['admin']?'y':'n',$identity['admin']?'y':'n',time()]);
            $id=(int)$db->lastInsertId();
            $q=$db->prepare('INSERT INTO nova_septenture_identities (subject,userid) VALUES (?,?)');
            $q->execute([$identity['uuid'],$id]);
        }
        $q=$db->prepare('UPDATE nova_users SET access_role=?,is_sysadmin=?,is_game_master=?,is_webmaster=? WHERE userid=?');
        $q->execute([$role,$identity['admin']?'y':'n',$identity['admin']?'y':'n',$identity['admin']?'y':'n',$id]);
        // Website ownership remains authoritative. GM ownership of every public cast page
        // does not transfer PCs away from their players in Nova.
        if (!$identity['admin']) {
            $q=$db->prepare('UPDATE nova_characters c JOIN nova_septenture_cast m ON m.charid=c.charid SET c.user=? WHERE m.slug=?');
            foreach ($identity['slugs'] as $slug) $q->execute([$id,$slug]);
        }
        $q=$db->prepare('SELECT c.charid FROM nova_characters c JOIN nova_users u ON u.userid=c.user WHERE c.user=? ORDER BY (c.charid=u.main_char) DESC,c.charid LIMIT 1');
        $q->execute([$id]); $main=$q->fetchColumn();
        if (!$main && $identity['admin']) {
            $q=$db->prepare('INSERT INTO nova_characters (user,first_name,crew_type,date_activate) VALUES (?,\'Game Master\',\'active\',?)');
            $q->execute([$id,time()]); $main=$db->lastInsertId();
        }
        if ($main) {
            $q=$db->prepare('UPDATE nova_users SET main_char=? WHERE userid=?');$q->execute([$main,$id]);
        }
        $db->commit();
        return (int)$id;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
