<?php
/** Canonical Nova sheets and private dice, presented inside the Table. */
function tabletop_nova_tool(array $config,string $token,string $route,string $method='GET',?string $body=null): array {
    $url=rtrim($config['nova_tools_url']??$config['nova_url'],'/').'/index.php/table-api/play/'.$route;
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['X-Septenture-Proof: '.$token,'Content-Type: application/json','Accept: application/json']]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($raw===false||$code===0||strlen($raw)>4194304)throw new RuntimeException('Character tools are temporarily unavailable.',503);
    return [$code,$raw];
}
function tabletop_tools(TabletopService $svc,array $ctx,array $config,string $token,string $route): never {
    $method=$_SERVER['REQUEST_METHOD'];
    $allowed=($method==='GET'&&preg_match('~^(?:bootstrap|rolls|sheets/[1-9][0-9]*(?:/print)?)$~D',$route))||($method==='POST'&&preg_match('~^(?:rolls|share-roll|formatting/preview|sheets/[1-9][0-9]*/(?:manual|notes))$~D',$route));
    if(!$allowed)tabletop_response(['error'=>'Tool unavailable.'],404);
    $body=null;
    if($method==='POST'){
        if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))tabletop_response(['error'=>'Reopen the Table tools before saving. Your entries are kept.'],403);
        $body=file_get_contents('php://input',false,null,0,262145);
        if(strlen($body)>262144)tabletop_response(['error'=>'Request too large.'],413);
        $input=json_decode($body,true);if(!is_array($input)||array_is_list($input))tabletop_response(['error'=>'Use a JSON object.'],400);
    }
    session_write_close();
    if($route==='share-roll'){
        if(!is_string($input['roll_id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$input['roll_id']))tabletop_response(['error'=>'Choose one of your private rolls.'],400);
        [$code,$raw]=tabletop_nova_tool($config,$token,'rolls');
        if($code!==200)tabletop_response(['error'=>'Your roll could not be checked. Please retry.'],503);
        $found=null;foreach(json_decode($raw,true)['rolls']??[] as $roll)if($roll['id']===$input['roll_id'])$found=$roll;
        if(!$found)tabletop_response(['error'=>'Private roll unavailable.'],404);
        $r=$found['result'];$text=$ctx['display_name'].' rolled '.$r['expression'].' ('.$r['mode'].'): '.$r['total'];
        $map=$svc->rows('SELECT active_map_id FROM adventures WHERE id=?',[$ctx['cauldron_adventure_id']])[0]['active_map_id'];
        $payload=['action'=>'say','adventure_id'=>(int)$ctx['cauldron_adventure_id'],'map_id'=>(int)$map,'from_user_id'=>(int)$ctx['cauldron_user_id'],'user_id'=>(int)$ctx['cauldron_user_id'],'name'=>htmlspecialchars($ctx['display_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),'mesg'=>htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')];
        $payload['roll_id']=$input['roll_id'];
        $lock='rollshare:'.substr(hash('sha256',$ctx['campaign_key'].':'.$ctx['subject'].':'.$input['roll_id']),0,48);
        $held=$svc->rows('SELECT GET_LOCK(?,2) AS held',[$lock]);
        if((int)($held[0]['held']??0)!==1)tabletop_response(['error'=>'This roll is being shared. Please retry shortly.'],409);
        try {
            $existing=$svc->rows("SELECT id FROM septenture_table_events WHERE campaign_key=? AND sender_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.roll_id'))=? LIMIT 1",[$ctx['campaign_key'],$ctx['cauldron_user_id'],$input['roll_id']]);
            if(!$existing){
        $svc->rows('INSERT INTO septenture_table_events(campaign_key,adventure_id,map_id,sender_id,recipient_id,instance_id,payload) VALUES(?,?,?,?,NULL,NULL,?)',[$ctx['campaign_key'],$ctx['cauldron_adventure_id'],$map,$ctx['cauldron_user_id'],json_encode($payload,JSON_THROW_ON_ERROR)]);
            }
        } finally {$svc->rows('SELECT RELEASE_LOCK(?) AS released',[$lock]);}
        tabletop_response(['ok'=>true]);
    }
    $printing=str_ends_with($route,'/print');
    if($printing&&($_GET['notes']??'')==='1')$route.='?notes=1';
    [$code,$raw]=tabletop_nova_tool($config,$token,$route,$method,$body);
    if(!$printing&&!is_array(json_decode($raw,true)))tabletop_response(['error'=>'Character tools could not load. Your Table remains open.'],503);
    http_response_code($code);header('Content-Type: '.($printing&&$code===200?'text/html':'application/json').'; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo $printing&&$code===200?str_replace('/assets/manual-sheet.css','/css/manual-sheet.css',$raw):$raw;exit;
}
