<?php
require_once __DIR__.'/play_state.php';
require_once __DIR__.'/inventory_rules.php';
require_once __DIR__.'/equipment_rules.php';
require_once __DIR__.'/play_store.php';
require_once __DIR__.'/manual_sheet.php';
require_once __DIR__.'/rules_core.php';
function septenture_play_integer(mixed $value,int $min=1,int $max=2147483647): int {
    if(!is_int($value)||$value<$min||$value>$max)throw new InvalidArgumentException('Invalid number.',400);return $value;
}
function septenture_play_text(mixed $value,int $max): string {
    if(!is_string($value)||strlen($value)>$max||!mb_check_encoding($value,'UTF-8'))throw new InvalidArgumentException('Invalid text.',400);return $value;
}
function septenture_play_room_state(array $input,array $old,Septenture_play_store $store): array {
    $round=septenture_play_integer($input['round']??null,1,10000);$turn=$input['turn']??null;
    $participants=$input['participants']??null;
    if(!is_array($participants)||!array_is_list($participants)||count($participants)>100)throw new InvalidArgumentException('Use at most 100 encounter participants.',400);
    $oldById=[];foreach($old['participants']??[] as $p)$oldById[$p['id']]=$p;
    $new=[];$ids=[];
    foreach($participants as $p) {
        if(!is_array($p))throw new InvalidArgumentException('Invalid participant.',400);
        $id=septenture_play_text($p['id']??null,40);
        if(!preg_match('/^[a-z0-9-]{1,40}$/D',$id)||isset($ids[$id]))throw new InvalidArgumentException('Participant IDs must be unique.',400);
        $ids[$id]=true;$conditions=$p['conditions']??[];$resources=$p['resources']??[];
        if(!is_array($conditions)||!array_is_list($conditions)||count($conditions)>20||!is_array($resources)||!array_is_list($resources)||count($resources)>50)throw new InvalidArgumentException('Too many conditions or resources.',400);
        $clean=['id'=>$id,'name'=>septenture_play_text($p['name']??null,80),'initiative'=>septenture_play_integer($p['initiative']??0,-100,1000),'character_id'=>isset($p['character_id'])?septenture_play_integer($p['character_id']):null,'hp'=>septenture_play_integer($p['hp']??0,0,100000),'max_hp'=>septenture_play_integer($p['max_hp']??0,0,100000),'temp_hp'=>septenture_play_integer($p['temp_hp']??0,0,100000),'conditions'=>[],'resources'=>[]];
        if($clean['hp']>$clean['max_hp'])throw new InvalidArgumentException('Current HP exceeds maximum HP.',400);
        foreach($conditions as $condition)$clean['conditions'][]=septenture_play_text($condition,80);
        foreach($resources as $resource) {
            if(!is_array($resource))throw new InvalidArgumentException('Invalid resource.',400);
            $r=['name'=>septenture_play_text($resource['name']??null,80),'current'=>septenture_play_integer($resource['current']??null,0,100000),'max'=>septenture_play_integer($resource['max']??null,0,100000)];
            if($r['current']>$r['max'])throw new InvalidArgumentException('Resource exceeds maximum.',400);$clean['resources'][]=$r;
        }
        $previous=$oldById[$id]??null;
        if($clean['character_id']!==null&&$clean!==$previous&&!$store->canControl($clean['character_id']))throw new RuntimeException('Control is required to share or change linked character stats.',403);
        if($previous&&$previous['character_id']!==null&&$clean['character_id']!==$previous['character_id']&&!$store->canControl((int)$previous['character_id']))throw new RuntimeException('Control is required to unlink another character.',403);
        $new[]=$clean;
    }
    foreach($oldById as $id=>$p)if(!isset($ids[$id])&&$p['character_id']!==null&&!$store->canControl((int)$p['character_id']))throw new RuntimeException('Control is required to remove a linked character.',403);
    if($turn!==null&&(!is_string($turn)||!isset($ids[$turn])))throw new InvalidArgumentException('Choose an encounter participant for the current turn.',400);
    return ['round'=>$round,'turn'=>$turn,'participants'=>$new];
}
/** Resolve only a saved ready build; state totals from requests never enter this path. */
function septenture_play_state_build(array $document): array {
    $build=$document['build']??[];if(($build['status']??'')!=='ready'||!is_array($build['input']??null))throw new RuntimeException('Finish and validate this build before using automatic play state.',409);
    $calculated=(new Septenture_rules_core())->calculate($build['input'],$document['rules_sources']??[]);
    if(($build['calculated']['source_digest']??null)!==$calculated['source_digest'])throw new RuntimeException('Rules changed. Review this build before using automatic play state.',409);
    if(!$calculated['validation']['ready'])throw new RuntimeException('Complete this character’s rules review before using automatic play state.',409);
    if(!is_array($document['inventory_state']??null))throw new RuntimeException('Record physical inventory and equipment before using automatic play state.',409);
    $runtime=Septenture_equipment_rules::calculate($build['input'],$document['inventory_state'],$document['rules_sources']??[]);
    if(!$runtime['validation']['ready'])throw new RuntimeException('The recorded loadout needs a complete rules review before automatic play.',409);
    return [$runtime,Septenture_play_state::buildKey($build['input'],$calculated['source_digest'])];
}
function septenture_play_api(string $route,string $method,array $identity,string $base): never {
    try {
        $admin=septenture_config('PLAY_ADMIN_SUBJECT');
        // Revoking the campaign role also revokes the pinned owner's play privilege.
        if(!($identity['admin']??false))$admin='00000000-0000-0000-0000-000000000000';
        $origin=parse_url($base,PHP_URL_SCHEME).'://'.parse_url($base,PHP_URL_HOST).(parse_url($base,PHP_URL_PORT)?':'.parse_url($base,PHP_URL_PORT):'');
        if(!defined('SEPTENTURE_TABLE_BRIDGE')&&isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!==$origin)throw new RuntimeException('Untrusted origin.',403);
        if(!in_array($method,['GET','POST'],true))throw new RuntimeException('Use GET or POST.',405);
        $input=[];
        if($method==='POST') {
            if(!defined('SEPTENTURE_TABLE_BRIDGE')&&((($_SERVER['HTTP_ORIGIN']??'')!==$origin)||!hash_equals($_SESSION['csrf']??'',$_SERVER['HTTP_X_PLAY_CSRF']??'')||empty($_SESSION['csrf'])))throw new RuntimeException('Reload the editor before making changes.',403);
            if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))throw new RuntimeException('Use JSON.',415);
            $bodyLimit=str_starts_with($route,'play/drafts')?7340032:($route==='play/formatting/preview'?6292480:262144);
            $raw=file_get_contents('php://input',false,null,0,$bodyLimit+1);
            if(strlen($raw)>$bodyLimit)throw new RuntimeException('Request too large.',413);
            try {$input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Invalid JSON.',400);}
            if(!is_array($input)||array_is_list($input))throw new InvalidArgumentException('Use a JSON object.',400);
        }
        $csrf=$_SESSION['csrf']??'';if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        if($route==='play/formatting/preview'){if($method!=='POST')throw new RuntimeException('Use POST.',405);if(array_diff(array_keys($input),['content','format']))throw new InvalidArgumentException('Provide only story content and format.',400);require_once __DIR__.'/story_format.php';$content=$input['content']??null;if(is_string($content)&&strlen($content)>Septenture_story_format::LIMIT)throw new LengthException('Preview text is limited to 1 MiB; your source has been kept.',413);$content=septenture_play_text($content,Septenture_story_format::LIMIT);$format=septenture_play_text($input['format']??null,8);septenture_json(['html'=>Septenture_story_format::render($content,$format)]);}
        if(in_array($route,['play/notebook','play/player-notes'],true)||str_starts_with($route,'play/writing/')){require_once __DIR__.'/writing_workspace.php';septenture_writing_api($route,$method,$identity,$input,$csrf);}
        if(str_starts_with($route,'play/drafts')){if((string)($_SERVER['HTTP_X_STORY_ACCOUNT']??'')!==(string)SEPTENTURE_PLAYER_ID)throw new RuntimeException('The signed-in account changed. Reload before recovering or saving.',409);require_once __DIR__.'/story_drafts.php';septenture_story_draft_api($route,$method,$input);}
        if($admin==='')throw new RuntimeException('Play tools are not configured yet.',503);
        $store=new Septenture_play_store(septenture_db(),$identity['uuid'],$admin);
        if(preg_match('~^play/spells/([a-z][a-z0-9_]{0,79})$~D',$route,$spellMatch)&&$method==='GET')septenture_json(['spell'=>(new Septenture_rules_core())->spell($spellMatch[1])]);
        if($route==='play/inventory-catalogue'&&$method==='GET')septenture_json(['catalogue'=>(new Septenture_inventory_rules())->catalogue()]);
        if($route==='play/catalogue'&&$method==='GET')septenture_json(['catalogue'=>(new Septenture_rules_core())->catalogue()]);
        if($route==='play/desk'&&$method==='GET') {
            header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');
            $site=htmlspecialchars(rtrim(septenture_config('SITE_URL'),'/').'/',ENT_QUOTES,'UTF-8');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="septenture-site" content="'.$site.'"><meta name="septenture-renew-csrf" content="'.htmlspecialchars($csrf,ENT_QUOTES,'UTF-8').'"><title>Private dice · Septenture</title><link rel="stylesheet" href="/assets/septenture.css"><main class="fiction-gate"><h1>Your private dice</h1><p>Roll here or use your own dice. Results stay private until you deliberately share them from a story draft.</p><section data-play-tools data-story-id="0" data-advanced-tools="false"><p data-play-status role="status"></p><div data-play-body hidden></div></section><p><a href="/index.php/write/index">Writing desk</a></p></main><script src="/assets/play.js?v='.substr(hash_file('sha256',__DIR__.'/../../assets/play.js'),0,12).'"></script><script src="/assets/septenture.js?v='.substr(hash_file('sha256',__DIR__.'/../../assets/septenture.js'),0,12).'"></script><script src="/assets/nova-session.js?v=20261104a"></script></html>';exit;
        }
        if($route==='play/roster'&&$method==='GET') {
            if($identity['uuid']!==$admin)throw new RuntimeException('Roster administration unavailable.',404);
            $rows=septenture_db()->query("SELECT c.charid AS character_id,m.slug,TRIM(CONCAT_WS(' ',c.first_name,c.last_name)) AS character_name,u.name AS writer,s.owner_subject,s.revision,s.updated_at FROM nova_septenture_cast m JOIN nova_characters c ON c.charid=m.charid LEFT JOIN nova_users u ON u.userid=c.user LEFT JOIN nova_play_sheets s ON s.character_id=c.charid ORDER BY m.slug")->fetchAll(PDO::FETCH_ASSOC);
            septenture_json(['characters'=>$rows,'note'=>'Profile editing, sheet ownership and table piece control are separate grants.']);
        }
        if($route==='play/bootstrap'&&$method==='GET')septenture_json(['account_subject'=>$identity['uuid'],'csrf'=>$csrf,'story_account'=>SEPTENTURE_PLAYER_ID,'is_administrator'=>$identity['uuid']===$admin,'sheets'=>$store->listSheets(),'players'=>$store->players()]);
        if($route==='play/sheets') {
            if($method==='GET')septenture_json(['sheets'=>$store->listSheets()]);
            $owner=$input['owner_subject']??null;if($owner!==null)$owner=septenture_play_text($owner,36);
            septenture_json(['sheet'=>$store->createSheet(septenture_play_integer($input['character_id']??null),$owner)],201);
        }
        if(preg_match('~^play/sheets/([1-9][0-9]*)(?:/(notes|manual|print|invitations|owner|build|preview|sources|state|state-review|inventory|loadout))?$~D',$route,$m)) {
            $id=(int)$m[1];$action=$m[2]??'';
            if($method==='GET') {
                if($action==='print')Septenture_manual_sheet::printPage($store->sheet($id),($_GET['notes']??'')==='1');
                if($action==='loadout'){$sheet=$store->calculationSheet($id);septenture_json(['preview'=>Septenture_equipment_rules::preview($sheet['document']),'revision'=>$sheet['revision']]);}
                if($action==='inventory'){$sheet=$store->sheet($id);$state=$sheet['document']['inventory_state']??null;septenture_json(['inventory'=>$state===null?null:(new Septenture_inventory_rules())->view($state),'revision'=>$sheet['revision'],'can_edit'=>$sheet['can_edit'],'can_control'=>$sheet['can_control']]);}
                if($action==='invitations')septenture_json(['invitations'=>$store->invitations($id)]);
                if($action==='')septenture_json(['sheet'=>$store->sheet($id)]);
            }
            if($method==='POST'&&$action==='inventory') {
                if(array_diff(array_keys($input),['revision','operation_id','command']))throw new InvalidArgumentException('Unknown inventory request fields.',400);
                if(!is_array($input['command']??null)||array_is_list($input['command']))throw new InvalidArgumentException('Provide a structured inventory operation.',400);
                $revision=septenture_play_integer($input['revision']??null);$operation=septenture_play_text($input['operation_id']??null,32);$command=$input['command'];$rules=new Septenture_inventory_rules();
                $sheet=$store->transitionInventory($id,$revision,$operation,$command,function($document)use($command,$rules){
                    $state=$document['inventory_state']??null;
                    if(($command['kind']??null)==='initialize') {
                        if(array_diff(array_keys($command),['kind','records','reason'])||!is_array($command['records']??null)||!is_string($command['reason']??null))throw new InvalidArgumentException('Provide physical inventory records and their source reason.',400);
                        if($state!==null)throw new RuntimeException('Inventory already exists. Use explicit acquisition, expenditure and movement records.',409);
                        return $rules->initialize($command['records'],$command['reason']);
                    }
                    if(!is_array($state))throw new RuntimeException('The owner must record initial inventory first.',409);
                    return $rules->step($state,$command);
                });
                septenture_json(['sheet'=>$sheet,'inventory'=>$rules->view($sheet['document']['inventory_state'])]);
            }
            if($method==='POST'&&$action==='state-review') {
                if(array_diff(array_keys($input),['revision']))throw new InvalidArgumentException('Unknown gameplay review fields.',400);
                $sheet=$store->sheet($id);if(!$sheet['can_edit'])throw new RuntimeException('Sheet unavailable.',404);$revision=septenture_play_integer($input['revision']??null);
                if($revision!==$sheet['revision'])throw new RuntimeException('Character changed. Refresh before reviewing gameplay migration.',409);
                [$calculated,$key]=septenture_play_state_build($sheet['document']);$state=$sheet['document']['play_state']??null;if(!is_array($state))throw new RuntimeException('There is no gameplay state to migrate.',409);
                $plan=Septenture_play_state_migration::plan($state,$calculated,$key,$id.':'.$revision);septenture_json(['review'=>$plan['review'],'revision'=>$revision]);
            }
            if($method==='POST'&&$action==='state') {
                if(array_diff(array_keys($input),['revision','operation_id','command']))throw new InvalidArgumentException('Unknown gameplay request fields.',400);
                if(!is_array($input['command']??null)||array_is_list($input['command']))throw new InvalidArgumentException('Provide a structured gameplay operation.',400);
                $revision=septenture_play_integer($input['revision']??null);$operation=septenture_play_text($input['operation_id']??null,32);$command=$input['command'];
                $sheet=$store->transitionPlayState($id,$revision,$operation,$command,function($document)use($command,$id,$revision){
                    [$calculated,$key]=septenture_play_state_build($document);$state=$document['play_state']??null;
                    if(($command['kind']??null)==='initialize'){if(array_diff(array_keys($command),['kind']))throw new InvalidArgumentException('Unknown initialization fields.',400);if($state!==null)throw new RuntimeException('Gameplay state already exists. Build changes require a reviewed migration.',409);return Septenture_play_state::initialize($calculated,$key);}
                    if(!is_array($state))throw new RuntimeException('Initialize this character’s gameplay state first.',409);
                    if(($command['kind']??null)==='migrate'){
                        if(array_diff(array_keys($command),['kind','review_digest','confirmed'])||!is_string($command['review_digest']??null)||!is_bool($command['confirmed']??null))throw new InvalidArgumentException('Provide a reviewed migration and explicit confirmation.',400);
                        return Septenture_play_state_migration::apply($state,$calculated,$key,$id.':'.$revision,$command['review_digest'],$command['confirmed']);
                    }
                    return Septenture_play_state::step($state,$calculated,$key,$command);
                },($command['kind']??null)==='migrate');septenture_json(['sheet'=>$sheet]);
            }
            if($method==='GET'&&$action==='print')Septenture_manual_sheet::printPage($store->sheet($id),($_GET['notes']??'')==='1');
            if($method==='POST'&&$action==='manual') {
                if(array_diff(array_keys($input),['revision','manual_sheet']))throw new InvalidArgumentException('Unknown manual sheet request fields.',400);
                $sheet=$store->sheet($id);$revision=septenture_play_integer($input['revision']??null);
                if($revision!==$sheet['revision'])throw new RuntimeException('Sheet changed. Your entries are kept here; compare or reload before saving.',409);
                $document=$sheet['document'];$document['manual_sheet']=Septenture_manual_sheet::validate($input['manual_sheet']??null);
                septenture_json(['sheet'=>$store->saveSheet($id,$revision,$document)]);
            }
            if($method==='POST'&&$action==='notes') {
                // Builder inputs get their own validated endpoint once the rules
                // engine is connected. Never accept client-calculated totals here.
                $sheet=$store->sheet($id);$revision=septenture_play_integer($input['revision']??null);
                if($revision!==$sheet['revision'])throw new RuntimeException('Sheet changed. Reload before saving.',409);
                $document=$sheet['document'];$document['notes']=septenture_play_text($input['notes']??null,16000);
                if(isset($input['notes_format'])){if(!in_array($input['notes_format'],['plain','markdown'],true))throw new InvalidArgumentException('Choose plain text or Markdown.',400);$document['notes_format']=$input['notes_format'];}
                septenture_json(['sheet'=>$store->saveSheet($id,$revision,$document)]);
            }
            if($method==='POST'&&$action==='sources') {
                $sheet=$store->sheet($id);if(!$sheet['can_edit'])throw new RuntimeException('Sheet unavailable.',404);
                $revision=septenture_play_integer($input['revision']??null);
                if(($sheet['document']['play_state']['combat_turn']['active']??false))throw new RuntimeException('End combat before changing character rule sources.',409);
                if($revision!==$sheet['revision'])throw new RuntimeException('Sheet changed. Reload before saving.',409);
                if(!is_array($input['source']??null)||array_is_list($input['source']))throw new InvalidArgumentException('Provide a structured rules source.',400);
                $snapshot=Septenture_rules_packages::snapshot($input['source']);$document=$sheet['document'];
                foreach($document['rules_sources']??[] as $existing)if($existing['source']['id']===$snapshot['source']['id']&&$existing['source']['version']===$snapshot['source']['version']) {
                    if(!hash_equals($existing['digest'],$snapshot['digest']))throw new RuntimeException('That source version is immutable. Increase its version to change its rules.',409);
                    septenture_json(['sheet'=>$sheet]);
                }
                $sources=$document['rules_sources']??[];if(count($sources)>=20)throw new InvalidArgumentException('Use at most 20 rules-source snapshots per sheet.',400);
                $sources[]=$snapshot;$document['rules_sources']=$sources;
                if(isset($document['build']))$document['build']['status']='draft';
                septenture_json(['sheet'=>$store->saveSheet($id,$revision,$document)]);
            }
            if($method==='POST'&&in_array($action,['build','preview'],true)) {
                $sheet=$store->sheet($id);
                if(!$sheet['can_edit'])throw new RuntimeException('Sheet unavailable.',404);
                if(!is_array($input['input']??null)||array_is_list($input['input']))throw new InvalidArgumentException('Provide structured builder choices.',400);
                $calculated=(new Septenture_rules_core())->calculate($input['input'],$sheet['document']['rules_sources']??[]);
                if($action==='preview')septenture_json(['calculated'=>$calculated]);
                if(($sheet['document']['play_state']['combat_turn']['active']??false))throw new RuntimeException('End combat before saving a changed character build.',409);
                $revision=septenture_play_integer($input['revision']??null);
                if($revision!==$sheet['revision'])throw new RuntimeException('Sheet changed. Reload before saving.',409);
                if(!is_bool($input['finalize']??null))throw new InvalidArgumentException('Choose draft or finalized build.',400);
                if($input['finalize']&&!$calculated['validation']['ready'])septenture_json(['error'=>'Complete required choices and rules coverage before using this build for play.','validation'=>$calculated['validation']],422);
                $document=$sheet['document'];$document['build']=['input'=>$input['input'],'calculated'=>$calculated,'status'=>$input['finalize']?'ready':'draft'];
                septenture_json(['sheet'=>$store->saveSheet($id,$revision,$document)]);
            }
            if($method==='POST'&&$action==='invitations') {
                if(!is_bool($input['enabled']??null))throw new InvalidArgumentException('Choose grant or revoke.',400);
                $store->share($id,septenture_play_text($input['viewer_subject']??null,36),$input['enabled'],septenture_play_text($input['type']??'view',12));septenture_json(['ok'=>true]);
            }
            if($method==='POST'&&$action==='owner') {
                $owner=$input['owner_subject']??null;if($owner!==null)$owner=septenture_play_text($owner,36);
                septenture_json(['sheet'=>$store->transfer($id,septenture_play_integer($input['revision']??null),$owner)]);
            }
        }
        if($route==='play/rolls') {
            if($method==='GET')septenture_json(['rolls'=>$store->privateRolls()]);
            $character=isset($input['character_id'])?septenture_play_integer($input['character_id']):null;
            septenture_json(['roll'=>$store->roll(septenture_play_text($input['expression']??null,120),septenture_play_text($input['mode']??'normal',12),$character)],201);
        }
        if($route==='play/actions'&&$method==='POST') {
            $character=septenture_play_integer($input['character_id']??null);if(!$store->canControl($character))throw new RuntimeException('Character control required.',403);
            if(array_diff(array_keys($input),['character_id','revision','action','mode','against_conditions','halfling_luck','use_minimum_die','use_damage_floor','condition_context']))throw new InvalidArgumentException('Submit action choices, not derived roll totals or critical-damage flags.',400);
            $sheet=$store->actionSheet($character);if(($sheet['document']['build']['status']??'')!=='ready')throw new RuntimeException('Finish and validate this character build before using its play actions.',409);
            $revision=septenture_play_integer($input['revision']??null);$key=septenture_play_text($input['action']??null,100);$choices=array_intersect_key($input,array_flip(['mode','against_conditions','halfling_luck','use_minimum_die','use_damage_floor','condition_context']));
            $roll=$store->resolvedRoll($character,$revision,function($document)use($key,$choices){
                $state=$document['play_state']??null;if(($state['combat_turn']['active']??false)&&preg_match('/^(attack|damage)\./D',$key))throw new RuntimeException('Resolve combat attacks through the combat target handler; standalone rolls cannot bypass attack budgets.',409);
                [$calculated,$buildKey]=septenture_play_state_build($document);
                if($state!==null){if(!is_array($state))throw new RuntimeException('Gameplay state needs review.',409);Septenture_play_state::validate($state,Septenture_play_state::limits($calculated),$buildKey);}
                return Septenture_roll_resolver::action($calculated,$key,$choices,$state);
            });septenture_json(['roll'=>$roll],201);
        }
        if(preg_match('~^play/rolls/([a-f0-9]{32})/insert$~D',$route,$m)&&$method==='POST')septenture_json(['result'=>$store->insertRoll($m[1],septenture_play_integer($input['story_id']??null))]);
        if(preg_match('~^play/rooms/([1-9][0-9]*)/combat/attacks/([a-f0-9]{32})/reactions/([1-9][0-9]*)/cancel$~D',$route,$cancelReaction)&&$method==='POST'){if(array_diff(array_keys($input),['operation_id']))throw new InvalidArgumentException('Cancel only the private reaction, without a refund.',400);septenture_json(['attack'=>$store->cancelPrivateReaction((int)$cancelReaction[1],$cancelReaction[2],(int)$cancelReaction[3],septenture_play_text($input['operation_id']??null,32))]);}
        if(preg_match('~^play/rooms/([1-9][0-9]*)/combat/attacks/([a-f0-9]{32})/cancel$~D',$route,$cancelMatch)&&$method==='POST'){if(array_diff(array_keys($input),['operation_id']))throw new InvalidArgumentException('Cancel only a private receipt, not its spent budget.',400);septenture_json(['attack'=>$store->cancelPrivateAttack((int)$cancelMatch[1],$cancelMatch[2],septenture_play_text($input['operation_id']??null,32))]);}
        if(preg_match('~^play/rooms/([1-9][0-9]*)/combat/attacks/([a-f0-9]{32})$~D',$route,$attackMatch)&&$method==='GET')septenture_json(['attack'=>$store->combatAttack((int)$attackMatch[1],$attackMatch[2])]);
        if(preg_match('~^play/rooms/([1-9][0-9]*)/combat$~D',$route,$m)) {
            $story=(int)$m[1];if($method==='GET')septenture_json(['combat'=>$store->combat($story)]);
            if(array_diff(array_keys($input),['revision','operation_id','character_id','character_revision','command'])||!is_array($input['command']??null)||array_is_list($input['command']))throw new InvalidArgumentException('Provide a structured combat operation, not derived budgets or actor statistics.',400);
            $command=$input['command'];$kind=$command['kind']??null;$allowed=match($kind){'start'=>['kind','order'],'set_target_context'=>['kind','attacker_id','target_id','context'],'set_defense_consent'=>['kind','allowed_attackers','share_outcomes'],'set_reaction_context'=>['kind','attack_id','reactor_id','context'],'roll_reaction'=>['kind','attack_id','feature'],'publish_attack','decline_reaction','resolve_attack','publish_reaction'=>['kind','attack_id'],'roll_attack'=>['kind','target_id','attack_action_id','weapon','attack_mode','halfling_luck','use_damage_floor','use_mastery'],'begin_attack'=>['kind','source'],'end','next_turn','action_surge','second_wind','decline_shift'=>['kind'],default=>throw new InvalidArgumentException('Choose an available combat operation.',400)};
            if(array_diff(array_keys($command),$allowed))throw new InvalidArgumentException('Unknown combat command fields.',400);
            $manage=in_array($kind,['start','end','set_target_context','set_reaction_context'],true);if($manage&&(isset($input['character_id'])||isset($input['character_revision'])))throw new InvalidArgumentException('Combat management selects a roster rather than one actor.',400);
            $character=$manage?null:septenture_play_integer($input['character_id']??null);$characterRevision=$manage?null:septenture_play_integer($input['character_revision']??null);
            $result=$store->transitionCombat($story,septenture_play_integer($input['revision']??null,0),septenture_play_text($input['operation_id']??null,32),$command,$character,$characterRevision,function($scene,$documents,$order,$metadata)use($command,$kind,$character){
                require_once __DIR__.'/reaction_rules.php';
                if($kind==='set_target_context'){if(!is_array($command['context']??null))throw new InvalidArgumentException('Declare target context.',400);return Septenture_attack_rules::setContext($scene,septenture_play_integer($command['attacker_id']??null),septenture_play_integer($command['target_id']??null),$command['context']);}
                if($kind==='set_reaction_context'){if(!is_array($command['context']??null))throw new InvalidArgumentException('Declare reaction context.',400);return Septenture_reaction_rules::context($scene,$command['attack_id'],$metadata['attack'],septenture_play_integer($command['reactor_id']??null),$command['context']);}
                if($kind==='set_defense_consent'){if(!is_array($command['allowed_attackers']??null))throw new InvalidArgumentException('Choose allowed combatants.',400);if(isset($command['share_outcomes'])&&!is_bool($command['share_outcomes']))throw new InvalidArgumentException('Choose whether outcomes may be shared.',400);return Septenture_attack_rules::consent($scene,$character,$metadata['owners'][$character],$command['allowed_attackers'],$command['share_outcomes']??false);}
                if($kind==='end'){$states=[];foreach($order as $id)$states[$id]=$documents[$id]['play_state']??[];return Septenture_combat_rules::end($scene,$states);}
                $actors=[];foreach($order as $id){[$calculated,$key]=septenture_play_state_build($documents[$id]);$state=$documents[$id]['play_state']??null;if(!is_array($state))throw new RuntimeException('Initialize every linked actor’s reviewed private play state first.',409);$actors[$id]=['character_id'=>$id,'calculated'=>$calculated,'build_key'=>$key,'state'=>$state,'owner_subject'=>$metadata['owners'][$id],'revision'=>$metadata['revisions'][$id]];}
                if($kind==='publish_attack')return Septenture_attack_lifecycle::publish($scene,$actors,$command['attack_id'],$metadata['attack'],$metadata['actor_subject'],$metadata['is_administrator']);
                if($kind==='decline_reaction')return Septenture_attack_lifecycle::decline($scene,$actors,$command['attack_id'],$metadata['attack'],$character,$metadata['actor_subject']);
                if($kind==='resolve_attack')return Septenture_attack_lifecycle::resolve($scene,$actors,$command['attack_id'],$metadata['attack'],$metadata['actor_subject'],$metadata['is_administrator']);
                if($kind==='roll_reaction')return Septenture_reaction_rules::roll($scene,$actors,$command['attack_id'],$metadata['attack'],$character,septenture_play_text($command['feature']??null,200),$metadata['actor_subject'],$metadata['is_administrator']);
                if($kind==='publish_reaction')return Septenture_reaction_rules::publish($scene,$actors,$command['attack_id'],$metadata['attack'],$character,$metadata['actor_subject'],$metadata['is_administrator']);
                if($kind==='roll_attack')return Septenture_attack_rules::stage($scene,$actors,$character,$command,$metadata['is_administrator']);
                return $kind==='start'?Septenture_combat_rules::start($order,$actors):Septenture_combat_rules::step($scene,$actors,$character,$command);
            });septenture_json(['combat'=>$result]);
        }
        if(preg_match('~^play/rooms/([1-9][0-9]*)$~D',$route,$m)) {
            $id=(int)$m[1];$room=$store->room($id);
            if($method==='GET')septenture_json(['room'=>$room,'events'=>$store->events($id)]);
            if(!is_array($input['document']??null)||array_is_list($input['document']))throw new InvalidArgumentException('Invalid encounter state.',400);
            $clean=septenture_play_room_state($input['document'],$room['document'],$store);
            septenture_json(['room'=>$store->saveRoom($id,septenture_play_integer($input['revision']??null,0),$clean)]);
        }
        throw new RuntimeException('Play tool route unavailable.',404);
    } catch(Throwable $e) {
        $status=in_array($e->getCode(),[400,401,403,404,405,409,413,415,429,503],true)?$e->getCode():503;
        septenture_json(['error'=>$status===503?'Play tools are temporarily unavailable. Your saved work is unchanged.':$e->getMessage()],$status);
    }
}
