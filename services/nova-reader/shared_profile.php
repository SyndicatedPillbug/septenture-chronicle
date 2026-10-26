<?php
require_once __DIR__.'/identity.php';
const SEPTENTURE_SHARED_FIELDS=['gender','species','age','height','weight','hair_color','eye_color','physical_desc','personality','strengths','ambitions','hobbies','spouse','children','father','mother','brothers','sisters','other_family','service_record'];
const SEPTENTURE_PROFILE_FIELDS=['display_name'=>80,'tagline'=>160,'heading'=>160,'bio'=>5000,'mood'=>160,'location'=>160,'interests'=>1200,'music_title'=>160];
function septenture_shared_profile(string $slug,string $token): array {
    $rows=septenture_api('/rest/v1/character_profiles?slug=eq.'.rawurlencode($slug).'&campaign_id=eq.'.rawurlencode(septenture_campaign()).'&select=*',$token);
    if(count($rows)!==1)throw new RuntimeException('Character unavailable in this campaign.',404);
    return $rows[0];
}
function septenture_profile_values(array $input): array {
    $out=[];
    foreach(SEPTENTURE_PROFILE_FIELDS as $key=>$max){
        $v=$input[$key]??null;
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||($key==='display_name'&&trim($v)===''))throw new InvalidArgumentException('Check the length of '.$key.'.',400);
        $out[$key]=trim($v);
    }
    $details=$input['character_details']??[];
    if(!is_array($details)||array_diff(array_keys($details),SEPTENTURE_SHARED_FIELDS))throw new InvalidArgumentException('Unknown character detail.',400);
    foreach($details as $key=>$value)if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>5000)throw new InvalidArgumentException('Check the length of '.$key.'.',400);
    $out['character_details']=(object)$details;
    return $out;
}
function septenture_save_shared_profile(string $slug,string $token,array $input,string $revision): array {
    $values=septenture_profile_values($input);
    if(!preg_match('/^[0-9T:.+ Z-]{10,50}$/D',$revision))throw new InvalidArgumentException('Reload this profile before saving.',409);
    $url=rtrim(septenture_config('SUPABASE_URL'),'/').'/rest/v1/character_profiles?slug=eq.'.rawurlencode($slug).'&campaign_id=eq.'.rawurlencode(septenture_campaign()).'&updated_at=eq.'.rawurlencode($revision);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,
        CURLOPT_HTTPHEADER=>['apikey: '.septenture_config('SUPABASE_KEY'),'Authorization: Bearer '.$token,'Content-Type: application/json','Prefer: return=representation'],
        CURLOPT_POSTFIELDS=>json_encode($values,JSON_THROW_ON_ERROR)]);
    $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($body===false||$status>=500||$status===0)throw new RuntimeException('Save could not be confirmed. Reload and compare before retrying; your text is still in this form.',503);
    if($status!==200)throw new RuntimeException('Your profile editing access could not be verified.',403);
    $rows=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if(count($rows)!==1)throw new RuntimeException('This profile changed in another window, or your access changed. Your text is still in this form; copy it before reloading.',409);
    return $rows[0];
}
/** Refresh Nova's display cache FROM the shared record. Never publish Nova private data. */
function septenture_cache_profile(PDO $db,int $id,array $profile): void {
    $hash=hash('sha256',json_encode([$profile['display_name'],$profile['bio'],$profile['character_details']??[]],JSON_THROW_ON_ERROR));
    $q=$db->prepare('SELECT profile_hash FROM nova_septenture_profile_cache WHERE charid=? FOR UPDATE');$q->execute([$id]);
    $existing=$q->fetchColumn();if($existing===$hash)return;
    if($existing===false){
        $q=$db->prepare('SELECT * FROM nova_characters WHERE charid=?');$q->execute([$id]);$original=$q->fetch(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT * FROM nova_characters_data WHERE data_char=?');$q->execute([$id]);$fields=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare('INSERT IGNORE INTO nova_septenture_profile_cache(charid,original_character,original_fields,backed_up_at) VALUES(?,?,?,?)');
        $q->execute([$id,json_encode($original,JSON_THROW_ON_ERROR),json_encode($fields,JSON_THROW_ON_ERROR),time()]);
    }
    $q=$db->prepare("UPDATE nova_characters SET first_name=?,middle_name='',last_name='',suffix='' WHERE charid=?");$q->execute([htmlspecialchars($profile['display_name'],ENT_QUOTES,'UTF-8'),$id]);
    $details=$profile['character_details']??[];$details['history']=$profile['bio'];
    $fields=$db->query('SELECT field_id,field_name FROM nova_characters_fields')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($fields as $field=>$name){
        if(!in_array($name,array_merge(SEPTENTURE_SHARED_FIELDS,['history']),true)||!array_key_exists($name,$details)||!is_string($details[$name]))continue;
        // Existing private fields not explicitly shared remain untouched.
        $q=$db->prepare('SELECT data_id FROM nova_characters_data WHERE data_char=? AND data_field=?');$q->execute([$id,$field]);$exists=$q->fetchColumn();
        if($exists){$q=$db->prepare('UPDATE nova_characters_data SET data_value=?,data_updated=? WHERE data_char=? AND data_field=?');$q->execute([htmlspecialchars($details[$name],ENT_QUOTES,'UTF-8'),time(),$id,$field]);}
        else{$q=$db->prepare('INSERT INTO nova_characters_data(data_char,data_field,data_value,data_updated) VALUES(?,?,?,?)');$q->execute([$id,$field,htmlspecialchars($details[$name],ENT_QUOTES,'UTF-8'),time()]);}
    }
    $q=$db->prepare('UPDATE nova_septenture_profile_cache SET profile_hash=? WHERE charid=?');$q->execute([$hash,$id]);
}
function septenture_refresh_profiles(string $token): void {
    $profiles=septenture_api('/rest/v1/character_profiles?select=slug,display_name,bio,character_details&campaign_id=eq.'.rawurlencode(septenture_campaign()),$token);
    $db=septenture_db();$ids=$db->query('SELECT slug,charid FROM nova_septenture_cast')->fetchAll(PDO::FETCH_KEY_PAIR);
    $db->beginTransaction();
    try{foreach($profiles as $profile)if(isset($ids[$profile['slug']]))septenture_cache_profile($db,(int)$ids[$profile['slug']],$profile);$db->commit();}
    catch(Throwable $e){$db->rollBack();throw $e;}
}
