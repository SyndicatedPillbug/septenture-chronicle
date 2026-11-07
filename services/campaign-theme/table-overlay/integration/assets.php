<?php
/** Direct resource paths never bypass authorization, including guessed filenames. */
function tabletop_asset(string $path,TabletopService $svc,$user,?array $context): never {
    $deny=static function(): never {tabletop_response(['error'=>'Asset not found.'],404);};
    $decoded=rawurldecode($path);
    if(str_contains($decoded,"\0")||str_contains($decoded,'\\')||preg_match('#(?:^|/)\.\.(?:/|$)#',$decoded))$deny();
    if(!$user->logged_in)$deny();
    $roles=$svc->rows('SELECT r.name FROM roles r JOIN user_role ur ON ur.role_id=r.id WHERE ur.user_id=?',[$user->id]);
    if(in_array('Recorder',array_column($roles,'name'),true))$deny();
    $org=$svc->rows('SELECT resources_key FROM organisations WHERE id=?',[$user->organisation_id]);
    $key=$org[0]['resources_key']??'';
    if($key==='')$deny();
    $allowed=preg_match('#^/files/(brushes|effects)/[a-zA-Z0-9_.-]+$#D',$decoded)===1;
    $dm=$svc->rows('SELECT id FROM adventures WHERE dm_id=?',[$user->id]);
    if(($user->is_admin||$dm)&&str_starts_with($decoded,'/resources/'.$key.'/'))$allowed=true;
    if(!$allowed&&$context){
        $adventure=(int)$context['cauldron_adventure_id'];
        $maps=$svc->rows('SELECT m.id,m.url FROM maps m JOIN adventures a ON a.active_map_id=m.id AND m.adventure_id=a.id WHERE a.id=?',[$adventure]);
        if(count($maps)===1){
            $map=$maps[0];
            if($decoded===$map['url']){if(TabletopStage::$request&&!TabletopStage::$request->gm&&TabletopStage::$request->sight()->active())TabletopStage::$request->mapImage();$allowed=true;}
            foreach($svc->rows('SELECT t.id,t.extension,t.width,t.height FROM tokens t JOIN map_token i ON i.token_id=t.id WHERE i.map_id=? AND i.hidden=0',[$map['id']]) as $t)
                if($decoded==='/resources/'.$key.'/tokens/'.$t['id'].'.'.$t['extension']){foreach($svc->rows('SELECT pos_x,pos_y FROM map_token WHERE map_id=? AND token_id=? AND hidden=0',[$map['id'],$t['id']]) as $pos)if(TabletopStage::$request?->visible((float)$pos['pos_x'],(float)$pos['pos_y'],(float)$t['width'],(float)$t['height'])??true)$allowed=true;}
            foreach($svc->rows('SELECT c.id,c.extension FROM characters c JOIN map_character i ON i.character_id=c.id WHERE i.map_id=? AND i.hidden=0',[$map['id']]) as $c)
                if($decoded==='/resources/'.$key.'/characters/'.$c['id'].'.'.$c['extension']){foreach($svc->rows('SELECT pos_x,pos_y FROM map_character WHERE map_id=? AND character_id=? AND hidden=0',[$map['id'],$c['id']]) as $pos)if(TabletopStage::$request?->visible((float)$pos['pos_x'],(float)$pos['pos_y'])??true)$allowed=true;}
        }
    }
    if(!$allowed)$deny();
    $public=realpath(__DIR__.'/../public');$file=realpath($public.$decoded);
    if(!$file||!str_starts_with($file,$public.'/')||!is_file($file))$deny();
    $mimes=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','mp4'=>'video/mp4','webm'=>'video/webm','mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/wav'];
    $mime=$mimes[strtolower(pathinfo($file,PATHINFO_EXTENSION))]??null;if(!$mime)$deny();
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');readfile($file);exit;
}
