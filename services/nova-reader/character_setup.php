<?php
require_once __DIR__.'/identity.php';
/** No service key or private character content is sent to the public profile API. */
function septenture_setup_rpc(string $token,string $action,array $input): mixed {
    if(!in_array($action,['create_shared_character','character_setup_members'],true))throw new LogicException('Unknown setup operation.');
    $ch=curl_init(rtrim(septenture_config('SUPABASE_URL'),'/').'/rest/v1/rpc/'.$action);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['apikey: '.septenture_config('SUPABASE_KEY'),'Authorization: Bearer '.$token,'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($input,JSON_THROW_ON_ERROR)]);
    $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($raw===false||$status===0||$status>=500)throw new RuntimeException('Character setup could not finish. Your setup was kept; retry this form to resume it.',503);
    if($status!==200)throw new RuntimeException('Your campaign or owning-player access changed. Check the owner and try again.',403);
    try{return json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Character setup could not finish. Retry this same form.',503);}
}
function septenture_setup_operation(): string {
    $hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}
final class Septenture_character_setup {
    private Closure $rpc;
    public function __construct(private PDO $db,private array $identity,callable $rpc){
        $this->rpc=Closure::fromCallable($rpc);
        if(!($identity['admin']??false)||!in_array($identity['campaign_role']??'', ['owner','gm'],true))throw new RuntimeException('Only the campaign owner or GM can create characters.',403);
        if($this->query('SELECT campaign_id FROM nova_septenture_campaign WHERE singleton=1')->fetchColumn()!==$identity['campaign'])throw new RuntimeException('Campaign database does not match this installation.',503);
    }
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    public function members(): array {
        $members=($this->rpc)('character_setup_members',['campaign'=>$this->identity['campaign']]);
        if(!is_array($members))throw new RuntimeException('The player list is unavailable. Please try again.',503);
        $names=$this->db->query("SELECT i.subject,COALESCE(NULLIF(TRIM(CONCAT_WS(' ',c.first_name,c.last_name)),''),u.name) AS name FROM nova_septenture_identities i JOIN nova_users u ON u.userid=i.userid LEFT JOIN nova_characters c ON c.charid=u.main_char")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($members as &$member)$member['label']=html_entity_decode($names[$member['subject']]??$member['label'],ENT_QUOTES,'UTF-8');
        return $members;
    }
    public function pending(?string $operation=null): array {
        $args=[$this->identity['uuid'],$this->identity['campaign']];$where='actor_subject=? AND campaign_id=?';
        if($operation!==null){$where.=' AND operation_id=?';$args[]=$operation;}else $where.=' AND completed_at IS NULL';
        return $this->query('SELECT operation_id,display_name,character_kind,owner_subject,character_id,profile_slug FROM nova_septenture_character_setup WHERE '.$where.' ORDER BY created_at DESC LIMIT 20',$args)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create(array $input): array {
        if(array_diff(array_keys($input),['operation','name','kind','owner']))throw new InvalidArgumentException('Unknown character setup fields.',400);
        $operation=$input['operation']??'';$name=$input['name']??'';$kind=$input['kind']??'';$owner=$input['owner']??'';
        if(!is_string($operation)||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$operation)||!is_string($owner)||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$owner))throw new InvalidArgumentException('Reload character setup before saving.',400);
        if(!is_string($name)||!mb_check_encoding($name,'UTF-8')||mb_strlen(trim($name))<1||mb_strlen(trim($name))>80||preg_match('/[\x00-\x1f\x7f]/u',$name))throw new InvalidArgumentException('Enter a character name of 1–80 characters.',400);
        if(!in_array($kind,['pc','npc','public_npc'],true))throw new InvalidArgumentException('Choose a character type.',400);
        $name=trim($name);$hash=hash('sha256',json_encode([$name,$kind,$owner],JSON_THROW_ON_ERROR));
        $lock='character-setup:'.substr(hash('sha256',$this->identity['campaign'].$operation),0,44);
        if((int)$this->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new RuntimeException('This setup is already running. Retry this same form in a moment.',409);
        try{
            $receipt=$this->query('SELECT * FROM nova_septenture_character_setup WHERE operation_id=?',[$operation])->fetch(PDO::FETCH_ASSOC);
            if($receipt&&($receipt['actor_subject']!==$this->identity['uuid']||$receipt['campaign_id']!==$this->identity['campaign']||!hash_equals($receipt['payload_hash'],$hash)))throw new RuntimeException('This request already belongs to another setup. Resume it unchanged or start a new character.',409);
            if($receipt&&$receipt['character_id'])return ['character_id'=>(int)$receipt['character_id'],'profile_slug'=>$receipt['profile_slug']];
            // Revalidate the target player on every attempt; no stale membership cache.
            if(!in_array($owner,array_column($this->members(),'subject'),true))throw new RuntimeException('Choose an owning player in this campaign.',403);
            if(!$receipt)$this->query('INSERT INTO nova_septenture_character_setup(operation_id,actor_subject,campaign_id,owner_subject,display_name,character_kind,payload_hash,created_at) VALUES(?,?,?,?,?,?,?,?)',[$operation,$this->identity['uuid'],$this->identity['campaign'],$owner,$name,$kind,$hash,time()]);
            $slug=null;
            if($kind!=='npc'){
                $slug=($this->rpc)('create_shared_character',['operation'=>$operation,'campaign'=>$this->identity['campaign'],'owner_id'=>$owner,'character_name'=>$name]);
                if(!is_string($slug)||!preg_match('/^pc-[a-p]{32}$/D',$slug))throw new RuntimeException('Profile setup could not be confirmed. Retry this same form.',503);
            }
            $this->db->beginTransaction();
            try{
                $q=$this->query('SELECT userid FROM nova_septenture_identities WHERE subject=? FOR UPDATE',[$owner]);$userid=$q->fetchColumn();
                if(!$userid){
                    // Invited players may not have visited Nova yet. SSO will fill in
                    // their verified email and permissions on their first visit.
                    $this->query("INSERT INTO nova_users(name,email,password,access_role,is_sysadmin,is_game_master,is_webmaster,is_firstlaunch,join_date,my_links) VALUES(?,?,?,4,'n','n','n','n',?,'5')",['Player','awaiting-'.$owner.'@example.invalid',bin2hex(random_bytes(20)),time()]);
                    $userid=(int)$this->db->lastInsertId();$this->query('INSERT INTO nova_septenture_identities(subject,userid) VALUES(?,?)',[$owner,$userid]);
                }
                $this->query("INSERT INTO nova_characters(user,first_name,crew_type,date_activate,position_1,position_2,rank) VALUES(?,?,?,?,0,NULL,NULL)",[$userid,htmlspecialchars($name,ENT_QUOTES,'UTF-8'),$kind==='pc'?'active':'npc',time()]);
                $character=(int)$this->db->lastInsertId();
                $this->query("INSERT INTO nova_characters_data(data_field,data_char,data_user,data_value,data_updated) SELECT field_id,?,?,'',? FROM nova_characters_fields WHERE field_display='y'",[$character,$userid,time()]);
                if($slug!==null)$this->query('INSERT INTO nova_septenture_cast(slug,charid) VALUES(?,?)',[$slug,$character]);
                $this->query('UPDATE nova_users SET main_char=? WHERE userid=? AND (main_char IS NULL OR main_char=0)',[$character,$userid]);
                $this->query('UPDATE nova_septenture_character_setup SET character_id=?,profile_slug=?,completed_at=? WHERE operation_id=?',[$character,$slug,time(),$operation]);
                $this->db->commit();
                return ['character_id'=>$character,'profile_slug'=>$slug];
            }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw new RuntimeException('Character setup could not finish. Your setup was kept; retry this same form to resume it.',503,$e);}
        }finally{$this->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
