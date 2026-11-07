<?php
/** Authenticated local transport. No group key grants authority. */
function tabletop_instance(TabletopService $svc,array $ctx,string $instance,bool $control): bool {
    if(!preg_match('/^(token|character)([1-9][0-9]{0,9})$/D',$instance,$m))return false;
    $isToken=$m[1]==='token';
    $sql=$isToken
        ? 'SELECT i.hidden,a.dm_id,a.active_map_id,m.id AS map_id,g.supabase_user_id AS owner FROM map_token i JOIN maps m ON m.id=i.map_id JOIN adventures a ON a.id=m.adventure_id LEFT JOIN septenture_token_control g ON g.campaign_key=? AND g.map_token_instance_id=i.id AND g.supabase_user_id=? WHERE i.id=? AND a.id=?'
        : 'SELECT i.character_id,i.hidden,a.dm_id,a.active_map_id,m.id AS map_id,c.user_id AS owner FROM map_character i JOIN maps m ON m.id=i.map_id JOIN adventures a ON a.id=m.adventure_id JOIN characters c ON c.id=i.character_id WHERE i.id=? AND a.id=?';
    $args=$isToken?[$ctx['campaign_key'],$ctx['subject'],$m[2],$ctx['cauldron_adventure_id']]:[$m[2],$ctx['cauldron_adventure_id']];
    $r=$svc->rows($sql,$args);if(count($r)!==1)return false;$r=$r[0];
    if((int)$r['dm_id']===(int)$ctx['cauldron_user_id'])return true;
    if($r['hidden']||(int)$r['active_map_id']!==(int)$r['map_id'])return false;
    if($control&&!$isToken){
        $keys=$svc->rows('SELECT character_key FROM septenture_characters WHERE campaign_key=? AND cauldron_character_id=?',[$ctx['campaign_key'],$r['character_id']]);
        if(count($keys)!==1||!in_array($keys[0]['character_key'],$ctx['owned_keys']??[],true))return false;
    }
    return !$control||($isToken?$r['owner']===$ctx['subject']:(int)$r['owner']===(int)$ctx['cauldron_user_id']);
}
function tabletop_events(TabletopService $svc,array $ctx): never {
    $uid=(int)$ctx['cauldron_user_id'];$dm=(int)$ctx['dm_id']===$uid;
    $adventure=(int)$ctx['cauldron_adventure_id'];$campaign=$ctx['campaign_key'];
    $maps=$svc->rows('SELECT active_map_id FROM adventures WHERE id=?',[$adventure]);$map=(int)$maps[0]['active_map_id'];
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))tabletop_response(['error'=>'Please reopen the table.'],403);
        $raw=file_get_contents('php://input',false,null,0,12289);if(strlen($raw)>12288)tabletop_response(['error'=>'Event too large.'],413);
        $data=json_decode($raw,true);if(!is_array($data))tabletop_response(['error'=>'Invalid event.'],400);
        if(!isset($data['action']))tabletop_response(['ok'=>true]); // upstream connection announcement
        $action=$data['action'];
        // Cauldron emits this on every frame load, not only a new human arrival.
        // A typed chat message has a name or no name key; startup explicitly sends null.
        if($action==='say'&&array_key_exists('name',$data)&&$data['name']===null&&is_string($data['mesg']??null)){
            $prefix=$ctx['display_name'].' entered the session, ';
            if($data['mesg']===$prefix.'being the Dungeon Master.'||str_starts_with($data['mesg'],$prefix.'playing '))tabletop_response(['ok'=>true]);
        }
        if($dm&&in_array($action,['create','delete','hide','show','known','shape','alternate','zone_create','zone_delete','light_create','light_delete'],true))$action='reload';
        $common=['say','move','rotate','marker','request_init','pong'];
        $dmActions=['pause','reload','draw_color','draw_width','draw_clear','draw_move','draw_line','draw_brush','fill_texture','night','ping','turn','door_state','window_state'];
        if(!in_array($action,$common,true)&&!($dm&&in_array($action,$dmActions,true)))tabletop_response(['error'=>'This event is not supported for your role.'],403);
        $instance=null;
        if(in_array($action,['move','rotate'],true)){
            $instance=$data['instance_id']??'';
            if(!is_string($instance)||!tabletop_instance($svc,$ctx,$instance,true))tabletop_response(['error'=>'You do not control that piece.'],403);
        }
        // Keep only fields used by the supported events. Identity is always server-derived.
        $out=['action'=>$action,'adventure_id'=>$adventure,'map_id'=>$map,'from_user_id'=>$uid];
        $schemas=[
            'say'=>['mesg'=>'text'], 'marker'=>['pos_x'=>'number','pos_y'=>'number'],
            'move'=>['instance_id'=>'id','pos_x'=>'number','pos_y'=>'number','speed'=>'number'],
            'rotate'=>['instance_id'=>'id','rotation'=>'number','speed'=>'number'],
            'pause'=>['pause'=>'bool'],'draw_color'=>['color'=>'color'],'draw_width'=>['width'=>'number'],
            'draw_brush'=>['brush'=>'brush'],'fill_texture'=>['src'=>'brush'],
            'draw_move'=>['draw_x'=>'number','draw_y'=>'number','width'=>'number','erase'=>'yesno'],
            'draw_line'=>['draw_x'=>'number','draw_y'=>'number','linecap'=>'linecap'],
            'night'=>['level'=>'number'],'turn'=>['char_id'=>'number'],
            'door_state'=>['door_id'=>'id','state'=>'door'], 'window_state'=>['window_id'=>'id','transparent'=>'yesno'],
            'request_init'=>['char_id'=>'id'], 'pong'=>[], 'ping'=>[], 'reload'=>[], 'draw_clear'=>[],
        ];
        if($action==='fill_texture'&&!isset($data['src']))tabletop_response(['error'=>'Choose a built-in terrain texture.'],400);
        if($action==='draw_brush'&&!isset($data['brush']))tabletop_response(['error'=>'Choose a built-in terrain brush.'],400);
        foreach($schemas[$action] as $field=>$type){
            if(!array_key_exists($field,$data))continue;$v=$data[$field];
            if($type==='number'){if(!is_numeric($v)||!is_finite((float)$v)||abs((float)$v)>100000) tableTop_response(['error'=>'Invalid number.'],400);$out[$field]=(float)$v;}
            elseif($type==='bool')$out[$field]=(bool)$v;
            else{
                if(!is_string($v)||strlen($v)>4096)tabletop_response(['error'=>'Invalid event field.'],400);
                if($type==='brush'){
                    if($action==='fill_texture'&&preg_match('~^https?://~',$v)){
                        if(strcasecmp((string)parse_url($v,PHP_URL_HOST),(string)parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST))!==0)tabletop_response(['error'=>'Choose a built-in terrain texture.'],400);
                        $v=(string)parse_url($v,PHP_URL_PATH);
                    }
                    if(!preg_match('#^/files/brushes/[a-zA-Z0-9_-]+\.(?:png|jpe?g|gif|webp)$#D',$v))tabletop_response(['error'=>'Choose a built-in terrain brush.'],400);
                    $brushRoot=realpath(__DIR__.'/../public/files/brushes');$brush=realpath(__DIR__.'/../public'.$v);
                    if(!$brushRoot||!$brush||!str_starts_with($brush,$brushRoot.'/')||!is_file($brush))tabletop_response(['error'=>'Terrain brush not found.'],400);
                }
                $patterns=['id'=>'/^[a-z]*[0-9]+$/D','color'=>'/^(#[a-fA-F0-9]{3,8}|rgba?\([0-9., %]+\))$/D','yesno'=>'/^(yes|no)$/D','linecap'=>'/^(round|butt|square)$/D','door'=>'/^(open|closed)$/D'];
                if(isset($patterns[$type])&&!preg_match($patterns[$type],$v))tabletop_response(['error'=>'Invalid event value.'],400);
                $out[$field]=$type==='text'?htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):$v;
            }
        }
        $out['name']=htmlspecialchars($ctx['display_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $out['user_id']=$uid;
        // Never trust client recipient IDs or impersonated sender fields.
        $recipient=null;
        if(isset($data['to_user_id'])){
            $target=$svc->rows('SELECT cauldron_user_id FROM septenture_users WHERE campaign_key=? AND cauldron_user_id=?',[$campaign,(int)$data['to_user_id']]);
            if(!$dm||count($target)!==1)tabletop_response(['error'=>'Invalid recipient.'],403);
            $recipient=(int)$target[0]['cauldron_user_id'];$out['to_user_id']=$recipient;
        }
        if(isset($data['to_char_id']))tabletop_response(['error'=>'Use a private writing message for this conversation.'],400);
        $svc->rows('DELETE FROM septenture_table_events WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
        $svc->rows('INSERT INTO septenture_table_events(campaign_key,adventure_id,map_id,sender_id,recipient_id,instance_id,payload) VALUES(?,?,?,?,?,?,?)',[$campaign,$adventure,$map,$uid,$recipient,$instance,json_encode($out,JSON_THROW_ON_ERROR)]);
        tabletop_response(['ok'=>true]);
    }
    if($_SERVER['REQUEST_METHOD']!=='GET')tabletop_response(['error'=>'Use GET or POST.'],405);
    $visible=$dm?'':' AND i.hidden=0';
    $snapshot=[];
    foreach($svc->rows('SELECT i.id,i.pos_x,i.pos_y,i.rotation,i.armor_class,i.hitpoints,i.damage,i.hidden FROM map_token i WHERE i.map_id=?'.$visible,[$map]) as $r)$snapshot[]=['id'=>'token'.$r['id']]+$r;
    foreach($svc->rows('SELECT i.id,i.pos_x,i.pos_y,i.rotation,c.armor_class,c.hitpoints,c.damage,i.hidden FROM map_character i JOIN characters c ON c.id=i.character_id WHERE i.map_id=?'.$visible,[$map]) as $r)$snapshot[]=['id'=>'character'.$r['id']]+$r;
    if(isset($_GET['init'])){
        $max=$svc->rows('SELECT COALESCE(MAX(id),0) AS event_cursor FROM septenture_table_events WHERE campaign_key=?',[$campaign]);
        tabletop_response(['csrf'=>$_SESSION['tabletop_csrf'],'cursor'=>(int)$max[0]['event_cursor'],'events'=>[],'mapId'=>$map,'pieces'=>$snapshot]);
    }
    $after=filter_var($_GET['after']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($after===false)tabletop_response(['error'=>'Invalid cursor.'],400);
    $rows=$svc->rows('SELECT * FROM septenture_table_events WHERE campaign_key=? AND id>? ORDER BY id LIMIT 100',[$campaign,$after]);
    $events=[];$cursor=$after;
    foreach($rows as $row){
        $cursor=(int)$row['id'];
        if((int)$row['sender_id']===$uid||($row['recipient_id']!==null&&(int)$row['recipient_id']!==$uid))continue;
        if((int)$row['map_id']!==$map)continue;
        if($row['instance_id']!==null&&!tabletop_instance($svc,$ctx,$row['instance_id'],false))continue;
        $events[]=json_decode($row['payload'],true);
    }
    tabletop_response(['cursor'=>$cursor,'events'=>$events,'mapId'=>$map,'pieces'=>$snapshot]);
}
