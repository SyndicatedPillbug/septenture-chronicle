<?php
require_once __DIR__.'/identity.php';

if (PHP_SAPI === 'cli') {
    $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='localhost';
    return;
}
require_once __DIR__.'/theme_output.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
$base=rtrim(septenture_config('NOVA_URL','http://127.0.0.1:8787'),'/');
$site=rtrim(septenture_config('SITE_URL','https://syndicatedpillbug.github.io/septenture-chronicle'),'/');
$route=trim(preg_replace('~^/index\.php(?:/|$)~','/',parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
$method=$_SERVER['REQUEST_METHOD'];
function septenture_fail(string $message, int $status=403): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta name="viewport" content="width=device-width"><title>Septenture Fiction</title><link rel="stylesheet" href="/assets/septenture.css"><main class="fiction-gate"><h1>Septenture Fiction</h1><p>'.htmlspecialchars($message).'</p><a href="/index.php/auth/start">Player login</a></main>';
    exit;
}
function septenture_json(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json'); echo json_encode($data);exit;
}
function septenture_go(string $url): never {header('Location: '.$url, true,303);exit;}
if ($route==='health') {
    septenture_json(['service'=>'septenture-fiction','php'=>PHP_VERSION,'uploadProgress'=>ini_get('session.upload_progress.enabled')]);
}
if (!str_starts_with($base,'https://') && !preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::\d+)?$~D',$base)) {
    septenture_fail('HTTPS must be configured before this service can be used.',503);
}
if(str_starts_with($route,'table-api/')) { require __DIR__.'/table_bridge.php'; exit; }
// A CORS POST carries the bearer token in the body, never in a URL. It does not
// depend on third-party cookies. A top-level navigation completes the handoff.
if ($route==='reader/stories') { require __DIR__.'/reader.php'; exit; }
if ($route==='auth/ticket') {
    $origin=parse_url($site,PHP_URL_SCHEME).'://'.parse_url($site,PHP_URL_HOST).(parse_url($site,PHP_URL_PORT)?':'.parse_url($site,PHP_URL_PORT):'');
    if (($_SERVER['HTTP_ORIGIN']??'')!==$origin) septenture_json(['error'=>'Untrusted origin.'],403);
    header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');
    if ($method==='OPTIONS') {http_response_code(204);exit;}
    if ($method!=='POST') septenture_json(['error'=>'Use POST.'],405);
    $body=file_get_contents('php://input',false,null,0,12289);
    if (strlen($body)>12288) septenture_json(['error'=>'Request too large.'],413);
    $input=json_decode($body,true);
    if (!is_array($input)||!preg_match('/^[a-f0-9]{64}$/D',$input['state']??'')) septenture_json(['error'=>'Invalid login request.'],400);
    try {
        $db=septenture_db();
        $q=$db->prepare('SELECT state_hash FROM nova_septenture_tickets WHERE state_hash=? AND expires_at>? AND ticket_hash IS NULL');
        $stateHash=hash('sha256',$input['state']);$q->execute([$stateHash,time()]);
        if (!$q->fetchColumn()) septenture_json(['error'=>'Login request expired. Start again.'],400);
        septenture_identity((string)($input['token']??''));
        $ticket=bin2hex(random_bytes(32));
        $q=$db->prepare('UPDATE nova_septenture_tickets SET ticket_hash=?,token=?,expires_at=? WHERE state_hash=? AND ticket_hash IS NULL AND expires_at>?');
        $q->execute([hash('sha256',$ticket),$input['token'],time()+60,$stateHash,time()]);
        if ($q->rowCount()!==1) septenture_json(['error'=>'Login request already used.'],400);
        septenture_json(['ticket'=>$ticket]);
    } catch (Throwable $e) {
        $status=in_array($e->getCode(),[401,403,503])?$e->getCode():503;
        septenture_json(['error'=>$status===503?'The fiction service is temporarily unavailable.':$e->getMessage()],$status);
    }
}
session_name('septenture_player');
session_save_path(septenture_config('SESSION_PATH','/var/lib/septenture'));
session_set_cookie_params(['httponly'=>true,'secure'=>str_starts_with($base,'https://'),'samesite'=>'Lax','path'=>'/']);
session_start();
try {
    if ($route==='auth/start'||str_starts_with($route,'login')) {
        if ($route==='login/logout') $route='auth/logout';
        else {
            $state=bin2hex(random_bytes(32));$_SESSION['state']=$state;
            $db=septenture_db();$db->exec('DELETE FROM nova_septenture_tickets WHERE expires_at<'.time());
            unset($_SESSION['return_to'],$_SESSION['compose_character']);
            if (isset($_GET['character'])) {
                $slug=$_GET['character'];$view=$_GET['view']??'profile';
                $views=['profile'=>'personnel/character/','stories'=>'personnel/viewposts/c/','journal'=>'personnel/viewlogs/c/'];
                if (!is_string($slug)||!preg_match('/^[a-z-]{1,41}$/D',$slug)||!is_string($view)||!isset($views[$view])) septenture_fail('Unknown character destination.',400);
                $q=$db->prepare('SELECT charid FROM nova_septenture_cast WHERE slug=?');$q->execute([$slug]);$character=$q->fetchColumn();
                if (!$character) septenture_fail('Character not found.',404);
                $_SESSION['return_to']='/index.php/'.$views[$view].(int)$character;
            }
            if(isset($_GET['draft'])){if(!is_string($_GET['draft'])||!ctype_digit($_GET['draft'])||(int)$_GET['draft']<1)septenture_fail('Unknown draft.',400);$_SESSION['return_to']='/index.php/write/missionpost/'.(int)$_GET['draft'];}
            if (isset($_GET['compose'])) {
                $compose=$_GET['compose'];
                $destinations=['story'=>'write/missionpost','journal'=>'write/personallog','dice'=>'play/desk','notes'=>'play/notebook'];
                if (!is_string($compose)||!isset($destinations[$compose])) septenture_fail('Unknown writing destination.',400);
                $_SESSION['return_to']='/index.php/'.$destinations[$compose];
                if(isset($character))$_SESSION['compose_character']=(int)$character;
            }
            $q=$db->prepare('INSERT INTO nova_septenture_tickets (state_hash,expires_at) VALUES (?,?)');
            $q->execute([hash('sha256',$state),time()+300]);
            $theme=$_GET['theme']??null;
            $themeQuery=is_string($theme)&&in_array($theme,['light','dark','system'],true)?'?theme='.$theme:'';
            septenture_go($site.'/fiction/login/'.$themeQuery.'#state='.$state);
        }
    }
    if ($route==='auth/complete') {
        $state=$_GET['state']??'';$ticket=$_GET['ticket']??'';
        if (!is_string($state)||!is_string($ticket)||!preg_match('/^[a-f0-9]{64}$/D',$ticket)||!hash_equals($_SESSION['state']??'', $state)||strlen($state)!==64) septenture_fail('That login link is invalid or belongs to another browser.',400);
        $db=septenture_db();$db->beginTransaction();
        $q=$db->prepare('SELECT token FROM nova_septenture_tickets WHERE state_hash=? AND ticket_hash=? AND expires_at>? FOR UPDATE');
        $q->execute([hash('sha256',$state),hash('sha256',$ticket),time()]);$token=$q->fetchColumn();
        if (!$token) {$db->rollBack();septenture_fail('That login link has expired or has already been used.',400);}
        $q=$db->prepare('DELETE FROM nova_septenture_tickets WHERE state_hash=?');$q->execute([hash('sha256',$state)]);$db->commit();
        $identity=septenture_identity($token);
        $returnTo=$_SESSION['return_to']??'/index.php/write/index';
        if (!preg_match('~^/index\.php/(?:play/(?:desk|notebook)|write/(?:index|missionpost(?:/[1-9][0-9]*)?|personallog)|personnel/(?:character/|viewposts/c/|viewlogs/c/)[1-9][0-9]*)$~D',$returnTo)) $returnTo='/index.php/write/index';
        $composeCharacter=$_SESSION['compose_character']??null;
        session_regenerate_id(true);$_SESSION=['token'=>$token,'subject'=>$identity['uuid'],'csrf'=>bin2hex(random_bytes(32))];
        if($composeCharacter)$_SESSION['compose_character']=$composeCharacter;
        septenture_go($base.$returnTo);
    }
    if ($route==='auth/renew') {
        $origin=parse_url($base,PHP_URL_SCHEME).'://'.parse_url($base,PHP_URL_HOST).(parse_url($base,PHP_URL_PORT)?':'.parse_url($base,PHP_URL_PORT):'');
        if($method!=='POST'||($_SERVER['HTTP_ORIGIN']??'')!==$origin||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_PLAY_CSRF']??''))septenture_json(['error'=>'Reopen your writing desk to renew this session.'],403);
        $raw=file_get_contents('php://input',false,null,0,12289);if(strlen($raw)>12288)septenture_json(['error'=>'Request too large.'],413);
        $input=json_decode($raw,true);if(!is_array($input)||!is_string($input['token']??null))septenture_json(['error'=>'Provide your player session proof.'],400);
        $expected=$_SESSION['subject']??null;
        if(!$expected&&isset($_SESSION['token']))$expected=septenture_identity($_SESSION['token'])['uuid'];
        $renewed=septenture_identity($input['token']);
        if(!$expected||!hash_equals($expected,$renewed['uuid']))septenture_json(['error'=>'The signed-in account changed. Reopen the writing desk; unsaved text remains in its current tab.'],409);
        $_SESSION['token']=$input['token'];$_SESSION['subject']=$expected;septenture_json(['ok'=>true]);
    }
    if ($route==='auth/logout') {
        if ($method==='POST' && hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'') && !empty($_SESSION['csrf'])) {
            $_SESSION=[];session_destroy();
            setcookie('septenture_player','',['expires'=>1,'path'=>'/','secure'=>str_starts_with($base,'https://'),'httponly'=>true,'samesite'=>'Lax']);
            septenture_go($site.'/fiction/login/#action=logout');
        }
        $_SESSION['csrf']??=bin2hex(random_bytes(32));
        echo '<!doctype html><title>Log out</title><link rel="stylesheet" href="/assets/septenture.css"><main class="fiction-gate"><h1>Leave the writing desk?</h1><form method="post"><input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'"><button>Log out of Septenture</button></form></main>';exit;
    }
    if (empty($_SESSION['token'])) septenture_fail('The writing room is open to Septenture players. Sign in with your existing player account.',401);
    $identity=septenture_identity($_SESSION['token']);
    $_SESSION['subject']=$identity['uuid'];
    $userid=septenture_provision($identity);
    define('SEPTENTURE_PLAYER_ID',$userid);
    define('SEPTENTURE_PLAYER_SUBJECT',$identity['uuid']);
    define('SEPTENTURE_PLAYER_TOKEN',$_SESSION['token']);
    define('SEPTENTURE_PLAYER_ADMIN',$identity['admin']);
    if($route==='owner/release-status'&&$method==='GET') {
        if(empty($identity['site_admin'])||!hash_equals(septenture_config('PLAY_ADMIN_SUBJECT'),$identity['uuid']))septenture_fail('Not found.',404);
        require __DIR__.'/owner_status.php';exit;
    }
    if(str_starts_with($route,'play/')) {
        require_once __DIR__.'/play_api.php';
        septenture_play_api($route,$method,$identity,$base);
    }
    require_once __DIR__.'/shared_profile.php';
    try{septenture_refresh_profiles($_SESSION['token']);}catch(Throwable $e){$GLOBALS['septenture_profile_refresh_failed']=true;}
    session_write_close();
    // The installer, updater and native account tools are CLI/SSO managed.
    if (preg_match('~^(?:install|update|upgrade|register|join|septenture_setup)(?:/|$)~i',$route)) septenture_fail('This function is managed by the site owner.',403);
    if ($route===''||$route==='index.php') septenture_go($base.'/index.php/write/index');
    if (str_starts_with($route,'application/assets/')) {
        $file=realpath(dirname(__DIR__,2).'/'.$route);
        $root=realpath(dirname(__DIR__).'/assets/images');
        if (!$file||!$root||!str_starts_with($file,$root.'/')||!preg_match('/\.(?:png|jpe?g|gif|webp)$/i',$file)) septenture_fail('File not found.',404);
        header('Content-Type: '.mime_content_type($file));readfile($file);exit;
    }
} catch (Throwable $e) {
    $status=in_array($e->getCode(),[401,403,503])?$e->getCode():503;
    if ($status===403) {$_SESSION=[];session_destroy();}
    septenture_fail($status===503?'The writing room is temporarily unavailable. Your saved work remains on the server.':$e->getMessage(),$status);
}
