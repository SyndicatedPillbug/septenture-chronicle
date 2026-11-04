<?php
require_once __DIR__.'/story_format.php';
/** All prose mutations lock the same post row before checking revision and tab lease. */
final class Septenture_story_drafts {
    public function __construct(private PDO $db,private int $user,private bool $admin=false){}
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    private function transaction(callable $fn): mixed {$this->db->beginTransaction();try{$v=$fn();$this->db->commit();return $v;}catch(Throwable $e){$this->db->rollBack();throw $e;}}
    public function row(int $id,bool $lock=false): array {
        $row=$this->query('SELECT * FROM nova_posts WHERE post_id=?'.($lock?' FOR UPDATE':''),[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$row||(!$this->admin&&!in_array((string)$this->user,explode(',',(string)$row['post_authors_users']),true)))throw new RuntimeException('Story unavailable.',404);
        return $row;
    }
    public static function uuid($v): string {if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$v))throw new InvalidArgumentException('Invalid draft or operation ID.',400);return $v;}
    public static function token($v): string {if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new InvalidArgumentException('Resume editing in this tab.',409);return hash('sha256',$v);}
    public function guard(array $row,array $input): void {
        if(!isset($input['revision'])||(!is_int($input['revision'])&&!is_string($input['revision']))||!ctype_digit((string)$input['revision'])||(string)$row['post_revision']!==(string)$input['revision'])throw new RuntimeException('This story changed. Compare your unsaved text before reloading.',409);
        $hash=self::token($input['tab']??null);
        $lease=$this->query('SELECT * FROM nova_story_leases WHERE story_id=?',[$row['post_id']])->fetch(PDO::FETCH_ASSOC);
        if(!$lease||(int)$lease['user_id']!==$this->user||!hash_equals($lease['tab_hash'],$hash)||(int)$lease['expires_at']<=time())throw new RuntimeException('The editing lease expired or belongs to another tab. Resume editing before saving.',409);
    }
    public function read(int $id): array {
        $r=$this->row($id);return ['id'=>(int)$r['post_id'],'revision'=>(int)$r['post_revision'],'saved_at'=>(int)$r['post_date'],'content'=>$r['post_content'],'format'=>$r['post_content_format'],'title'=>$r['post_title'],'authors'=>array_map('intval',array_filter(explode(',',(string)$r['post_authors']))),'mission'=>(int)$r['post_mission'],'location'=>$r['post_location'],'timeline'=>$r['post_timeline'],'tags'=>$r['post_tags'],'status'=>$r['post_status']];
    }
    public function lease(int $id,array $in,string $action): array {return $this->transaction(function()use($id,$in,$action){
        $r=$this->row($id,true);$hash=self::token($in['tab']??null);
        $l=$this->query('SELECT * FROM nova_story_leases WHERE story_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        $ours=$l&&(int)$l['user_id']===$this->user&&hash_equals($l['tab_hash'],$hash);
        if($action==='release') {if($ours){$this->query('DELETE FROM nova_story_leases WHERE story_id=?',[$id]);$this->query('UPDATE nova_posts SET post_lock_user=0,post_lock_date=0 WHERE post_id=?',[$id]);}return ['released'=>true];}
        if((int)($in['revision']??0)!==(int)$r['post_revision'])throw new RuntimeException('The story changed. Compare or reload before resuming.',409);
        if($l&&!$ours&&(int)$l['expires_at']>time())throw new RuntimeException('Another writer or tab is editing this story.',409);
        if($action==='renew'&&(!$ours||(int)$l['expires_at']<=time()))throw new RuntimeException('The editing lease expired. Resume editing.',409);
        if(!$l&&(int)$r['post_lock_user']!==0&&(int)$r['post_lock_user']!==$this->user&&(int)$r['post_lock_date']>time()-300)throw new RuntimeException('Another writer is editing this story.',409);
        $expiry=time()+90;$this->query('REPLACE INTO nova_story_leases(story_id,user_id,tab_hash,expires_at) VALUES(?,?,?,?)',[$id,$this->user,$hash,$expiry]);
        $this->query('UPDATE nova_posts SET post_lock_user=?,post_lock_date=? WHERE post_id=?',[$this->user,time(),$id]);return ['revision'=>(int)$r['post_revision'],'expires_at'=>$expiry];
    });}
    public function create(array $in): array {
        $client=self::uuid($in['client_id']??null);self::token($in['tab']??null);
        return $this->transaction(function()use($client,$in){
            $this->query('INSERT IGNORE INTO nova_story_creations(user_id,client_id) VALUES(?,?)',[$this->user,$client]);
            $id=$this->query('SELECT story_id FROM nova_story_creations WHERE user_id=? AND client_id=? FOR UPDATE',[$this->user,$client])->fetchColumn();
            if($id)return $this->read((int)$id);
            $char=$this->query('SELECT c.charid FROM nova_characters c JOIN nova_users u ON u.userid=c.user WHERE c.user=? ORDER BY(c.charid=u.main_char) DESC,c.charid LIMIT 1',[$this->user])->fetchColumn();
            if(isset($in['character_id'])){
                if(!is_int($in['character_id'])||$in['character_id']<1)throw new InvalidArgumentException('Choose a character.',422);
                $preferred=$this->query('SELECT charid FROM nova_characters WHERE charid=? AND (user=? OR ?=1)',[$in['character_id'],$this->user,$this->admin?1:0])->fetchColumn();
                if(!$preferred)throw new RuntimeException('Choose your own character when starting a story.',403);$char=$preferred;
            }
            if(!$char)throw new RuntimeException('Assign your default character before starting a story.',422);
            $mission=$this->query("SELECT mission_id FROM nova_missions WHERE mission_status='current' ORDER BY mission_id DESC LIMIT 1")->fetchColumn();
            if(!$mission)throw new RuntimeException('An active adventure is required.',422);
            $this->query("INSERT INTO nova_posts(post_title,post_content,post_content_format,post_authors,post_authors_users,post_mission,post_date,post_status,post_saved,post_participants,post_revision,post_lock_user,post_lock_date) VALUES('Untitled story','','markdown',?,?,?,?, 'saved',?,?,1,?,?)",[(string)$char,(string)$this->user,$mission,time(),$char,(string)$this->user,$this->user,time()]);
            $id=(int)$this->db->lastInsertId();$this->query('UPDATE nova_story_creations SET story_id=? WHERE user_id=? AND client_id=?',[$id,$this->user,$client]);
            $this->query('INSERT INTO nova_story_leases(story_id,user_id,tab_hash,expires_at) VALUES(?,?,?,?)',[$id,$this->user,self::token($in['tab']),time()+90]);return $this->read($id);
        });
    }
    private function fields(array $in,array $r): array {
        $source=$in['content']??null;$format=$in['format']??null;Septenture_story_format::validate($source,$format);
        if(strlen($source)>Septenture_story_format::LIMIT)throw new LengthException('Drafts are limited to 1 MiB. Your text has been kept.',413);
        if($format!==$r['post_content_format'])throw new RuntimeException('Use the reviewed conversion to change story format.',409);
        $data=['post_content'=>$source,'post_content_format'=>$format];
        foreach(['title'=>255,'location'=>255,'timeline'=>255,'tags'=>65535] as $key=>$max){$v=$in[$key]??'';if(!is_string($v)||mb_strlen($v)>$max)throw new InvalidArgumentException('Invalid '.$key.'.',422);$data['post_'.$key]=$key==='title'&&trim($v)===''?'Untitled story':$v;}
        $mission=$in['mission']??null;if(!is_int($mission)||!$this->query('SELECT mission_id FROM nova_missions WHERE mission_id=?',[$mission])->fetchColumn())throw new InvalidArgumentException('Choose an adventure.',422);$data['post_mission']=$mission;
        $authors=$in['authors']??null;if(!is_array($authors)||!array_is_list($authors)||!count($authors)||count($authors)>100)throw new InvalidArgumentException('Choose story authors.',422);
        $ids=[];$users=[];foreach($authors as $id){if(!is_int($id)||$id<1)throw new InvalidArgumentException('Invalid author.',422);$character=$this->query('SELECT user FROM nova_characters WHERE charid=?',[$id])->fetch(PDO::FETCH_ASSOC);if(!$character)throw new InvalidArgumentException('Unknown character author.',422);$ids[$id]=$id;if((int)$character['user']>0)$users[(int)$character['user']]=(int)$character['user'];}
        if(!isset($users[$this->user])&&!$this->admin)throw new RuntimeException('Keep your character among this draft’s authors.',403);
        $data['post_authors']=implode(',',$ids);$data['post_authors_users']=implode(',',$users);$data['post_words']=Septenture_story_format::words($source,$format);return $data;
    }
    public function save(int $id,array $in): array {return $this->transaction(function()use($id,$in){
        $r=$this->row($id,true);$operation=self::uuid($in['operation_id']??null);$hash=hash('sha256',json_encode($in));
        $old=$this->query('SELECT * FROM nova_story_operations WHERE story_id=? AND operation_id=?',[$id,$operation])->fetch(PDO::FETCH_ASSOC);
        if($old){if((int)$old['revision']!==(int)$r['post_revision'])throw new RuntimeException('The story changed after that save. Compare before continuing.',409);if((int)$old['user_id']!==$this->user||!hash_equals($old['request_hash'],$hash))throw new RuntimeException('Operation ID reused.',409);return ['revision'=>(int)$old['revision'],'saved_at'=>(int)$old['saved_at'],'id'=>$id];}
        $this->guard($r,$in);if($r['post_status']!=='saved')throw new RuntimeException('Only unpublished drafts autosave.',409);
        $data=$this->fields($in,$r);$changed=false;foreach($data as $key=>$v)if((string)($r[$key]??'')!==(string)$v)$changed=true;
        $revision=(int)$r['post_revision']+($changed?1:0);$now=time();
        if($changed){$data['post_revision']=$revision;$data['post_date']=$now;$this->query('UPDATE nova_posts SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($data))).' WHERE post_id=?',[...array_values($data),$id]);}
        $this->query('INSERT INTO nova_story_operations(story_id,operation_id,user_id,request_hash,revision,saved_at) VALUES(?,?,?,?,?,?)',[$id,$operation,$this->user,$hash,$revision,$now]);
        return ['id'=>$id,'revision'=>$revision,'saved_at'=>$now];
    });}
    public static function convert(string $source): array {
        $safe=Septenture_story_format::render($source,'legacy');
        $converter=new League\HTMLToMarkdown\HtmlConverter(['strip_tags'=>true,'hard_break'=>true]);$converter->getEnvironment()->addConverter(new League\HTMLToMarkdown\Converter\TableConverter());
        $markdown=$converter->convert($safe);Septenture_story_format::validate($markdown,'markdown');
        $warnings=[];if(preg_match('~<(?:font|center|span|div|u|sup|sub|iframe|video|audio)|style=|colspan=|rowspan=~i',$source))$warnings[]='Colors, alignment, styling, merged cells or unsupported elements may change. Compare both renderings before accepting.';
        return ['content'=>$markdown,'original_html'=>$safe,'html'=>Septenture_story_format::render($markdown,'markdown'),'warnings'=>$warnings];
    }
    public function conversion(int $id,array $in,string $action): array {return $this->transaction(function()use($id,$in,$action){
        $r=$this->row($id,true);$this->guard($r,$in);
        if($r['post_status']!=='saved')throw new RuntimeException('Convert unpublished drafts only.',409);
        if($action==='review'){if($r['post_content_format']!=='legacy')throw new RuntimeException('This story already uses Markdown.',409);return self::convert($r['post_content']);}
        if($action==='restore-review'||$action==='restore'){
            $backup=$this->query('SELECT * FROM nova_story_conversions WHERE id=? AND story_id=?',[(int)($in['backup_id']??0),$id])->fetch(PDO::FETCH_ASSOC);
            if(!$backup)throw new RuntimeException('Conversion backup unavailable.',404);
            if($action==='restore-review')return ['content'=>$backup['original_source'],'format'=>$backup['original_format'],'html'=>Septenture_story_format::render($backup['original_source'],$backup['original_format']),'warnings'=>['Restoring replaces the current draft. Its exact source will also be retained as a private backup.']];
            $source=$backup['original_source'];$format=$backup['original_format'];
        }elseif($action==='commit'){
            if($r['post_content_format']!=='legacy')throw new RuntimeException('This story already uses Markdown.',409);
            $converted=self::convert($r['post_content']);$source=$converted['content'];$format='markdown';
            if(!hash_equals(hash('sha256',$source),(string)($in['review_hash']??'')))throw new RuntimeException('Conversion changed. Review again.',409);
        }else throw new RuntimeException('Unknown conversion action.',404);
        $revision=(int)$r['post_revision']+1;
        $this->query('INSERT INTO nova_story_conversions(story_id,user_id,original_source,original_format,converted_hash,revision,created_at) VALUES(?,?,?,?,?,?,?)',[$id,$this->user,$r['post_content'],$r['post_content_format'],hash('sha256',$source),$revision,time()]);$backupId=(int)$this->db->lastInsertId();
        $this->query('UPDATE nova_posts SET post_content=?,post_content_format=?,post_revision=?,post_date=?,post_words=? WHERE post_id=?',[$source,$format,$revision,time(),Septenture_story_format::words($source,$format),$id]);return [...$this->read($id),'backup_id'=>$backupId];
    });}
    public function backups(int $id): array {$this->row($id);return $this->query('SELECT id,original_format,revision,created_at FROM nova_story_conversions WHERE story_id=? ORDER BY id DESC',[$id])->fetchAll(PDO::FETCH_ASSOC);}
}
function septenture_story_draft_api(string $route,string $method,array $input): never {
    $s=new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN);
    if($route==='play/drafts'&&$method==='POST')septenture_json($s->create($input),201);
    if(preg_match('~^play/drafts/([1-9][0-9]*)(?:/(lease|save|conversion|backups))?$~D',$route,$m)){
        $id=(int)$m[1];$action=$m[2]??'';
        if($method==='GET'&&$action==='')septenture_json($s->read($id));
        if($method==='GET'&&$action==='backups')septenture_json(['backups'=>$s->backups($id)]);
        if($method==='POST'&&$action==='save')septenture_json($s->save($id,$input));
        if($method==='POST'&&$action==='lease'){if(!in_array($input['action']??'',['acquire','renew','release'],true))throw new InvalidArgumentException('Invalid lease action.',400);septenture_json($s->lease($id,$input,$input['action']));}
        if($method==='POST'&&$action==='conversion'){$result=$s->conversion($id,$input,$input['action']??'');if(($input['action']??'')==='review')$result['review_hash']=hash('sha256',$result['content']);septenture_json($result);}
    }
    throw new RuntimeException('Draft endpoint unavailable.',404);
}
