<?php
/** Runs after the framework session is initialized, before routing or output. */
require_once __DIR__.'/theme_output.php';
require_once __DIR__.'/identity.php';
require_once __DIR__.'/service.php';
require_once __DIR__.'/assets.php';
require_once __DIR__.'/events.php';
require_once __DIR__.'/object-guard.php';
require_once __DIR__.'/control.php';
function tabletop_response(array $data,int $code=200): never {
    http_response_code($code);header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');echo json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP);exit;
}
function tabletop_redirect(string $url): never {
    header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('Location: '.$url,true,303);exit;
}
function tabletop_route($session,$user): void {
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    $configFile=__DIR__.'/config.php';
    if(!is_file($configFile))tabletop_response(['error'=>'The tabletop is not configured yet.'],503);
    $config=require $configFile;
    // The public hostname is a usable entry point; API and private routes stay guarded.
    if($path==='/'&&$_SERVER['REQUEST_METHOD']==='GET')tabletop_redirect('/session/connect');
    if($path==='/session/config')tabletop_response(['tabletopUrl'=>$config['tabletop_url'],'campaign'=>$config['campaign']??'septenture','novaUrl'=>$config['nova_url'],'manualPlayReady'=>true,'chronicleUrl'=>$config['site_url'],'account'=>['url'=>$config['supabase_url'],'publishableKey'=>$config['supabase_key']]]);
    if($path==='/session/connect'){
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('Referrer-Policy: strict-origin');
        echo str_replace('{{CHRONICLE}}',htmlspecialchars($config['site_url'],ENT_QUOTES,'UTF-8'),file_get_contents(__DIR__.'/connect.html'));exit;
    }
    $conn=new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE);$conn->set_charset('utf8mb4');
    $svc=new TabletopService($conn);
    $identityValidated=false;
    try{
        if($path==='/session/renew'){
            $p=parse_url($config['tabletop_url']??'');$origin=isset($p['scheme'],$p['host'])?$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:''):'';
            if($_SERVER['REQUEST_METHOD']!=='POST'||$origin===''||!hash_equals($origin,$_SERVER['HTTP_ORIGIN']??''))tabletop_response(['error'=>'Untrusted renewal.'],403);
            $grant=$_SESSION['tabletop_identity']??null;
            if(!$user->logged_in||!is_array($grant))tabletop_response(['error'=>'Please enter the table again.'],401);
            $raw=file_get_contents('php://input',false,null,0,12289);if(strlen($raw)>12288)tabletop_response(['error'=>'Request too large.'],413);
            $input=json_decode($raw,true);if(!is_string($input['token']??null))tabletop_response(['error'=>'Invalid account proof.'],400);
            $id=tabletop_account($config,$input['token'],$grant['campaign'],[$svc,'recorderAllowed']);$m=$svc->mapping($id,$grant['campaign']);
            if($id['subject']!==$grant['subject']||$id['purpose']!==$grant['purpose']||(int)$m['cauldron_user_id']!==(int)$user->id)throw new RuntimeException('Account access changed.',403);
            $_SESSION['tabletop_identity']['token']=$input['token'];tabletop_response(['ok'=>true]);
        }
        if($path==='/session/handoff'){
            $origin=$_SERVER['HTTP_ORIGIN']??'';
            $site=$config['site_url'];$p=parse_url($site);$allowed=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
            $own=parse_url($config['tabletop_url']??'');$ownOrigin=isset($own['scheme'],$own['host'])?$own['scheme'].'://'.$own['host'].(isset($own['port'])?':'.$own['port']:''):'';
            if(!in_array($origin,array_filter([$allowed,$ownOrigin]),true)||$_SERVER['REQUEST_METHOD']!=='POST')tabletop_response(['error'=>'Untrusted entry request.'],403);
            if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)tabletop_response(['error'=>'Request too large.'],413);
            $token=$_POST['token']??null;$campaign=$_POST['campaign']??null;
            if(!is_string($token)||!is_string($campaign))tabletop_response(['error'=>'Invalid sign-in request.'],400);
            $id=tabletop_account($config,$token,$campaign,[$svc,'recorderAllowed'],true);
            $svc->syncCharacters($id,$svc->mapping($id,$campaign));
            $binding=bin2hex(random_bytes(32));
            $ticket=$svc->issue($id,$campaign,$binding);
            $_SESSION['tabletop_pending']=['token'=>$token,'campaign'=>$campaign,'binding'=>$binding];
            tabletop_redirect('/session/enter?ticket='.$ticket);
        }
        if($path==='/session/enter'){
            $pending=$_SESSION['tabletop_pending']??null;unset($_SESSION['tabletop_pending']);
            if(!is_array($pending))tabletop_response(['error'=>'Please enter from the campaign website.'],401);
            $id=tabletop_account($config,$pending['token'],$pending['campaign'],[$svc,'recorderAllowed']);
            $m=$svc->consume((string)($_GET['ticket']??''),$pending['binding'],$id);
            // Rotate the framework session and login cookies before attaching the identity.
            $session->reset();
            if(!$session->set_user_id((int)$m['cauldron_user_id']))tabletop_response(['error'=>'Could not start your session.'],503);
            $_SESSION['tabletop_identity']=['token'=>$pending['token'],'campaign'=>$pending['campaign'],'subject'=>$id['subject'],'purpose'=>$id['purpose'],'owned_keys'=>$id['owned_keys']??[]];
            tabletop_redirect($id['purpose']==='recorder'?'/session/record?adventure='.(int)$m['cauldron_adventure_id']:'/session/table/'.$pending['campaign']);
        }
        $grant=$_SESSION['tabletop_identity']??null;
        if(is_array($grant)){
            $id=tabletop_account($config,$grant['token'],$grant['campaign'],[$svc,'recorderAllowed']);
            $m=$svc->mapping($id,$grant['campaign']);
            if($id['subject']!==$grant['subject']||$id['purpose']!==$grant['purpose']||(int)$m['cauldron_user_id']!==(int)$user->id)throw new RuntimeException('Your tabletop access changed. Please enter again.',403);
            $identityValidated=true;
            $_SESSION['tabletop_context']=$m+['subject'=>$id['subject'],'purpose'=>$id['purpose'],'owned_keys'=>$id['owned_keys']??[]];
            $_SESSION['tabletop_csrf']??=bin2hex(random_bytes(32));


            if($path==='/logout')return;
            if($id['purpose']==='recorder'){
                if($path==='/session/record'&&(string)($_GET['adventure']??'')===(string)$m['cauldron_adventure_id']&&$_SERVER['REQUEST_METHOD']==='GET')return;
                throw new RuntimeException('Recorder access is read-only.',403);
            }
            if(str_starts_with($path,'/session/tools/')){require_once __DIR__.'/tools.php';tabletop_tools($svc,$_SESSION['tabletop_context'],$config,$grant['token'],substr($path,strlen('/session/tools/')));}
            if($path==='/session/record'&&$id['purpose']==='dm'&&(string)($_GET['adventure']??'')===(string)$m['cauldron_adventure_id']){
                if($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals($_SESSION['tabletop_csrf'],$_SERVER['HTTP_X_TABLETOP_CSRF']??''))tabletop_response(['error'=>'Please reopen the table.'],403);
                return;
            }
            if(str_starts_with($path,'/object/'))tabletop_guard_object($svc,$_SESSION['tabletop_context'],$path);
            if($path==='/session/control')tabletop_control($svc,$_SESSION['tabletop_context']);
            if($path==='/session/events')tabletop_events($svc,$_SESSION['tabletop_context']);
            if(preg_match('#^/(resources|files)/#',$path))tabletop_asset($path,$svc,$user,$_SESSION['tabletop_context']);
            if(preg_match('#^/session/table/([a-z0-9-]+)$#D',$path,$match)){
                if($match[1]!==$grant['campaign'])throw new RuntimeException('This is another campaign.',403);
                require __DIR__.'/table.php';tabletop_page($m,$config,$svc,$_SESSION['tabletop_context']);exit;
            }
            // No spectator route, account management, arbitrary map selection or unrelated campaign routes.
            $allowed=['/logout','/object','/adventure/'.(int)$m['cauldron_adventure_id']];
            if($id['purpose']==='dm'&&preg_match('#^/vault/(map|token)(?:/(.*))?$#D',$path,$tool)){
                $kind=$tool[1];$tail=$tool[2]??'';$pieceId=null;
                $valid=in_array($tail,$kind==='map'?['','new','maps','audio']:['','new'],true);
                if(preg_match('#^(?:(?:grid|arrange)/)?([1-9][0-9]*)(?:/(?:grid|import|export))?$#D',$tail,$number)){$valid=true;$pieceId=$number[1];}
                if(!$valid)throw new RuntimeException('This tool is outside the current campaign.',403);
                $_SESSION['edit_adventure_id']=(int)$m['cauldron_adventure_id'];
                foreach(['adventure','adventure_id'] as $field)if(isset($_POST[$field])&&(string)$_POST[$field]!== (string)$m['cauldron_adventure_id'])throw new RuntimeException('This is another campaign.',403);
                if(isset($_POST['id'])&&ctype_digit((string)$_POST['id'])&&(int)$_POST['id']>0)$pieceId=(int)$_POST['id'];
                if($pieceId!==null){
                    $rows=$kind==='map'?$svc->rows('SELECT id FROM maps WHERE id=? AND adventure_id=?',[$pieceId,$m['cauldron_adventure_id']]):$svc->rows('SELECT id FROM tokens WHERE id=? AND organisation_id=?',[$pieceId,$m['cauldron_org_id']]);
                    if(count($rows)!==1)throw new RuntimeException('This belongs to another campaign.',403);
                }
                return;
            }
            if($id['purpose']==='dm'&&in_array($path,['/adventure/picture_directories','/adventure/pictures','/adventure/maps','/adventure/audio'],true))return;
            if(!in_array($path,$allowed,true)&&!preg_match('#^/object/[a-z_]+$#D',$path))throw new RuntimeException('This route is outside your tabletop session.',403);
        }elseif(preg_match('#^/(resources|files)/#',$path)){
            tabletop_asset($path,$svc,$user,null);
        }elseif(!($config['local_test_mode']??false)){
            // Fail closed for native logins on the integrated public installation.
            tabletop_response(['error'=>'Enter the table from the campaign website.'],401);
        }
    }catch(Throwable $e){
        if(!$identityValidated&&isset($_SESSION['tabletop_identity'])){
            unset($_SESSION['tabletop_identity'],$_SESSION['tabletop_context']);$user->logout();
        }
        $code=in_array($e->getCode(),[400,401,403,404,413,503],true)?$e->getCode():503;
        tabletop_response(['error'=>$code===503?'The tabletop account service is temporarily unavailable.':$e->getMessage()],$code);
    }
}
tabletop_route($_session,$_user);
