<?php
// A read-only bridge to the public profile. Private character-sheet data never
// belongs in this payload, the public profile table, or the public media bucket.
require_once __DIR__.'/campaign.php';
echo '<meta name="septenture-site" content="'.htmlspecialchars(rtrim(septenture_config('SITE_URL'),'/').'/',ENT_QUOTES,'UTF-8').'">';
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if (!preg_match('~/(?:personnel/character|personnel/viewposts/c|personnel/viewlogs/c)/(\d+)(?:/|$)~D',$path,$match)) return;
try {
    $q=septenture_db()->prepare('SELECT m.slug,c.first_name FROM nova_septenture_cast m JOIN nova_characters c ON c.charid=m.charid WHERE m.charid=?');
    $q->execute([(int)$match[1]]);$cast=$q->fetch(PDO::FETCH_ASSOC);
    if (!$cast) return;
    $slug=$cast['slug'];$profile=[];
    $url=rtrim(septenture_config('SUPABASE_URL'),'/').'/rest/v1/character_profiles?slug=eq.'.rawurlencode($slug).'&campaign_id=eq.'.rawurlencode(septenture_campaign()).'&select=display_name,tagline,heading,bio,theme,accent,portrait_path,background_path,updated_at';
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>3,
        CURLOPT_HTTPHEADER=>['apikey: '.septenture_config('SUPABASE_KEY'),'Accept: application/json']]);
    $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if ($body!==false && $status===200) $profile=json_decode($body,true,32,JSON_THROW_ON_ERROR)[0]??[];
    $site=rtrim(septenture_config('SITE_URL'),'/');
    $legacy=in_array($slug,['page','ceridwen','coy','legion','simon','selim','the-gunslinger'],true);
    $media=static function($path) use($slug,$profile) {
        if (!is_string($path)||!preg_match('~^'.preg_quote($slug,'~').'/[a-f0-9-]+\.(?:jpg|png|webp)$~D',$path)) return null;
        return rtrim(septenture_config('SUPABASE_URL'),'/').'/storage/v1/object/public/character-media/'.$path.'?v='.rawurlencode($profile['updated_at']??'');
    };
    $payload=['id'=>(int)$match[1],'slug'=>$slug,'name'=>$profile['display_name']??$cast['first_name'],
        'bio'=>$profile['bio']??'',
        'heading'=>$profile['heading']??'','tagline'=>$profile['tagline']??'',
        'portrait'=>$media($profile['portrait_path']??null)??$site.'/static/companions/'.($legacy?$slug:'default').'.svg',
        'theme'=>in_array($profile['theme']??'', ['moon','candle','water','hive','rose','watch','powder'],true)?$profile['theme']:'moon',
        'banner'=>$media($profile['background_path']??null)??(($profile['theme']??'moon')==='moon'?$site.'/static/companions/bookbinder.png':null),
        'accent'=>preg_match('/^#[a-f0-9]{6}$/i',$profile['accent']??'')?$profile['accent']:null,
        'publicUrl'=>$legacy?$site.'/the-cast/'.$slug.'/':$site.'/the-cast/character/?character='.rawurlencode($slug)];
    echo '<script type="application/json" id="septenture-character">'.json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).'</script>';
    echo '<script defer src="/assets/profile-live.js?v=20261027b"></script>';
} catch (Throwable $e) {
    // Profile decoration may fail without taking away the writing tools.
    // Do not print API responses, tokens, database errors, or other user data.
}
