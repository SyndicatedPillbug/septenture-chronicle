<?php
function tabletop_guard_object(TabletopService $svc,array $ctx,string $path): void {
    if($_SERVER['REQUEST_METHOD']!=='POST')tabletop_response(['error'=>'Use POST.'],405);
    if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))tabletop_response(['error'=>'Please reopen the table.'],403);
    $action=substr($path,strlen('/object/'));
    if($ctx['purpose']==='player'){
        if(!in_array($action,['move','rotate','damage','hitpoints','armor_class','character_custom_value','token_custom_value','light','vision'],true))tabletop_response(['error'=>'This action needs the DM.'],403);
        if(in_array($action,['vision','light'],true)&&TabletopStage::$request?->sight()->active())tabletop_response(['error'=>'Ask the GM to change sight or light while exploration is active.'],403);
        $instance=$_POST['instance_id']??'';
        if(!is_string($instance)||!tabletop_instance($svc,$ctx,$instance,true))tabletop_response(['error'=>'You do not control that piece.'],403);
    }
    if(in_array($action,['move','rotate'],true)&&is_string($_POST['instance_id']??null)){
        if($action==='move'&&$ctx['purpose']==='player'&&isset($_POST['pos_x'],$_POST['pos_y'])&&!(TabletopStage::$request?->visible((float)$_POST['pos_x'],(float)$_POST['pos_y'])??true))tabletop_response(['error'=>'That area has not been revealed to you.'],403);
        $kit=new TabletopPlayKit($svc,$ctx);
        if($kit->isLocked($_POST['instance_id']))tabletop_response(['error'=>'This piece is locked. Ask the GM to unlock it.'],403);
        if($action==='move')$kit->recordNativeMove($_POST['instance_id']);
    }
    if(isset($_POST['instance_id'])&&is_string($_POST['instance_id'])&&preg_match('/^(token|character|light|door|wall|window|zone|blinder)([1-9][0-9]*)$/D',$_POST['instance_id'],$piece)){
        $tables=['token'=>'map_token','character'=>'map_character','light'=>'lights','door'=>'doors','wall'=>'walls','window'=>'walls','zone'=>'zones','blinder'=>'blinders'];$table=$tables[$piece[1]];
        if(count($svc->rows("SELECT i.id FROM $table i JOIN maps m ON m.id=i.map_id WHERE i.id=? AND m.adventure_id=?",[$piece[2],$ctx['cauldron_adventure_id']]))!==1)tabletop_response(['error'=>'That piece belongs to another table.'],403);
    }
    if(isset($_POST['token_id'])&&count($svc->rows('SELECT id FROM tokens WHERE id=? AND organisation_id=?',[(int)$_POST['token_id'],$ctx['cauldron_org_id']]))!==1)tabletop_response(['error'=>'That token belongs to another campaign.'],403);
    foreach(['adventure_id','map_id','door_id','light_id','wall_id','zone_id'] as $key){
        if(!isset($_POST[$key]))continue;
        $value=filter_var($_POST[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($value===false)tabletop_response(['error'=>'Invalid piece.'],400);
        if($key==='adventure_id')$matches=$value===(int)$ctx['cauldron_adventure_id'];
        else{
            $table=['map_id'=>'maps','door_id'=>'doors','light_id'=>'lights','wall_id'=>'walls','zone_id'=>'zones'][$key];
            $sql=$table==='maps'?'SELECT id FROM maps WHERE id=? AND adventure_id=?':"SELECT i.id FROM $table i JOIN maps m ON m.id=i.map_id WHERE i.id=? AND m.adventure_id=?";
            $matches=count($svc->rows($sql,[$value,$ctx['cauldron_adventure_id']]))===1;
        }
        if(!$matches)tabletop_response(['error'=>'That piece belongs to another table.'],403);
    }
}
