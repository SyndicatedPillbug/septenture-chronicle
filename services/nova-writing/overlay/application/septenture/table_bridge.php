<?php
// Bearer-authenticated, narrowly scoped server-to-server access for the Table.
try {
    if(isset($_SERVER['HTTP_ORIGIN']))throw new RuntimeException('Use the Table tools.',403);
    $target=substr($route,strlen('table-api/'));
    $allowed=($method==='GET'&&preg_match('~^play/(?:bootstrap|rolls|sheets/[1-9][0-9]*(?:/print)?)$~D',$target))
        ||($method==='POST'&&preg_match('~^play/(?:rolls|formatting/preview|sheets/[1-9][0-9]*/(?:manual|notes))$~D',$target));
    if(!$allowed)throw new RuntimeException('Table tool unavailable.',404);
    $token=$_SERVER['HTTP_X_SEPTENTURE_PROOF']??'';
    if(!is_string($token))throw new RuntimeException('Please sign in again.',401);
    // Sheet authority depends on the verified subject and campaign membership,
    // not public profile decoration. Avoid two unrelated network requests per tool action.
    $identity=septenture_identity($token,false);
    $q=septenture_db()->prepare('SELECT userid FROM nova_septenture_identities WHERE subject=?');$q->execute([$identity['uuid']]);$player=$q->fetchColumn();
    if(!$player)$player=septenture_provision(septenture_identity($token));
    define('SEPTENTURE_PLAYER_ID',(int)$player);
    define('SEPTENTURE_PLAYER_SUBJECT',$identity['uuid']);
    define('SEPTENTURE_PLAYER_TOKEN',$token);
    define('SEPTENTURE_PLAYER_ADMIN',$identity['admin']);
    define('SEPTENTURE_TABLE_BRIDGE',true);
    require_once __DIR__.'/play_api.php';
    septenture_play_api($target,$method,$identity,$base);
} catch(Throwable $e) {
    $code=in_array($e->getCode(),[400,401,403,404,409,413,415,503],true)?$e->getCode():503;
    septenture_json(['error'=>$code===503?'Character tools are temporarily unavailable.':$e->getMessage()],$code);
}
