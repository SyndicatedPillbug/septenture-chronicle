<?php
/** Scene art stays inside the authenticated campaign boundary, including its bytes. */
function tabletop_scene(TabletopService $svc,array $ctx,string $path): never {
    $campaign=$ctx['campaign_key'];$method=$_SERVER['REQUEST_METHOD'];
    if($path==='/session/chat'){
        if($method!=='GET')tabletop_response(['error'=>'Use GET.'],405);
        $rows=$svc->rows("SELECT id,payload FROM septenture_table_events WHERE campaign_key=? AND map_id=? AND (recipient_id IS NULL OR recipient_id=? OR sender_id=?) AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.action'))='say' ORDER BY id DESC LIMIT 60",[$campaign,$ctx['active_map_id']??($svc->rows('SELECT active_map_id FROM adventures WHERE id=?',[$ctx['cauldron_adventure_id']])[0]['active_map_id']),$ctx['cauldron_user_id'],$ctx['cauldron_user_id']]);
        $messages=[];foreach(array_reverse($rows) as $row){$p=json_decode($row['payload'],true);$messages[]=['id'=>$row['id'],'name'=>html_entity_decode($p['name']??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),'text'=>html_entity_decode($p['mesg']??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),'roll'=>isset($p['roll_id']),'private'=>isset($p['to_user_id'])];}
        tabletop_response(['messages'=>$messages]);
    }
    if($method==='POST'){
        if($ctx['purpose']!=='dm'||(int)$ctx['dm_id']!==(int)$ctx['cauldron_user_id'])tabletop_response(['error'=>'Only the GM can change the scene.'],403);
        if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))tabletop_response(['error'=>'Please reopen the Table.'],403);
        if($path!=='/session/scene')tabletop_response(['error'=>'Scene not found.'],404);
        $mode=$_POST['mode']??'';if(!in_array($mode,['scene','backdrop','board'],true))tabletop_response(['error'=>'Choose a scene display.'],400);
        if($mode==='board'){$svc->rows("UPDATE septenture_scene SET mode='board' WHERE campaign_key=?",[$campaign]);tabletop_response(['ok'=>true]);}
        $file=$_FILES['image']??null;
        if(!$file){$svc->rows('UPDATE septenture_scene SET mode=? WHERE campaign_key=?',[$mode,$campaign]);tabletop_response(['ok'=>true]);}
        if($file['error']!==UPLOAD_ERR_OK||$file['size']>2*1024*1024||!is_uploaded_file($file['tmp_name']))tabletop_response(['error'=>'Choose a PNG, JPEG or WebP image under 2 MB.'],400);
        $size=@getimagesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$size||!in_array($mime,['image/png','image/jpeg','image/webp'],true)||($size['mime']??'')!==$mime||$size[0]*$size[1]>24000000)tabletop_response(['error'=>'Choose a PNG, JPEG or WebP image up to 24 megapixels.'],400);
        $bytes=file_get_contents($file['tmp_name']);$revision=bin2hex(random_bytes(16));$title=mb_substr(pathinfo(basename($file['name']),PATHINFO_FILENAME),0,120);
        $svc->rows('INSERT INTO septenture_scene(campaign_key,revision,mode,title,mime,image) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),mode=VALUES(mode),title=VALUES(title),mime=VALUES(mime),image=VALUES(image)',[$campaign,$revision,$mode,$title,$mime,$bytes]);
        tabletop_response(['ok'=>true]);
    }
    if($method!=='GET')tabletop_response(['error'=>'Use GET or POST.'],405);
    if($path==='/session/scene/image'){
        $revision=$_GET['revision']??'';if(!is_string($revision)||!preg_match('/^[a-f0-9]{32}$/D',$revision))tabletop_response(['error'=>'Scene not found.'],404);
        $rows=$svc->rows('SELECT mime,image FROM septenture_scene WHERE campaign_key=? AND revision=?',[$campaign,$revision]);if(!$rows)tabletop_response(['error'=>'Scene not found.'],404);
        header('Content-Type: '.$rows[0]['mime']);header('Content-Length: '.strlen($rows[0]['image']));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');echo $rows[0]['image'];exit;
    }
    $row=$svc->rows('SELECT revision,mode,title FROM septenture_scene WHERE campaign_key=?',[$campaign]);
    tabletop_response(['scene'=>$row?($row[0]+['url'=>'/session/scene/image?revision='.$row[0]['revision']]):['mode'=>'board']]);
}
