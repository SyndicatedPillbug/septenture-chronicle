<?php
/** Manual-play collaboration. All authority comes from the currently verified campaign context. */
final class TabletopPlayKit {
    private string $campaign;
    private bool $gm;
    public function __construct(private TabletopService $db, private array $ctx) {
        $this->campaign=$ctx['campaign_key'];
        $this->gm=$ctx['purpose']==='dm'&&(int)$ctx['cauldron_user_id']===(int)$ctx['dm_id'];
    }
    private function text(mixed $v,int $max=120): string {
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max)throw new RuntimeException('This text is too long or invalid.',400);
        return trim($v);
    }
    private function integer(mixed $v,int $min=0,int $max=100000): int {
        if(!is_int($v)||$v<$min||$v>$max)throw new RuntimeException('Choose a valid number.',400);return $v;
    }
    private function gm(): void {if(!$this->gm)throw new RuntimeException('Only the GM can change this.',403);}
    private function id(mixed $id): string {if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Choose an item from this Table.',400);return $id;}
    private function pack(mixed $value): string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    private function emptyState(): array {return ['scene'=>null,'ambience'=>'off','counters'=>[],'turns'=>['rows'=>[],'active'=>0,'round'=>1],'pause'=>null,'mapLocked'=>false,'pieceLocks'=>[],'drawingCursors'=>[],'drawingHashes'=>[],'drawings'=>[]];}
    private function shared(bool $lock=false): array {
        if($lock)$this->db->rows('INSERT IGNORE INTO septenture_kit_state(campaign_key,document) VALUES(?,?)',[$this->campaign,$this->pack($this->emptyState())]);
        $r=$this->db->rows('SELECT revision,document FROM septenture_kit_state WHERE campaign_key=?'.($lock?' FOR UPDATE':''),[$this->campaign]);
        return ['revision'=>(int)($r[0]['revision']??0),'state'=>$r?array_replace($this->emptyState(),json_decode($r[0]['document'],true)):$this->emptyState()];
    }
    private function saveState(array $state,int $revision): void {$this->db->rows('UPDATE septenture_kit_state SET document=?,revision=revision+1 WHERE campaign_key=? AND revision=?',[$this->pack($state),$this->campaign,$revision]);}
    public function active(): int {return (int)$this->db->rows('SELECT active_map_id FROM adventures WHERE id=?',[$this->ctx['cauldron_adventure_id']])[0]['active_map_id'];}
    private function media(string $id): array {
        $r=$this->db->rows('SELECT * FROM septenture_kit_media WHERE campaign_key=? AND id=?',[$this->campaign,$this->id($id)]);
        if(!$r)throw new RuntimeException('Image not found at this Table.',404);return $r[0];
    }
    private function newMedia(string $bytes,string $mime,string $title): string {
        $id=bin2hex(random_bytes(16));$this->db->rows('INSERT INTO septenture_kit_media(id,campaign_key,title,mime,image) VALUES(?,?,?,?,?)',[$id,$this->campaign,$title,$mime,$bytes]);return $id;
    }
    public function imageUpload(array $file): string {
        $this->gm();if(($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??PHP_INT_MAX)>2097152||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('Choose a PNG, JPEG or WebP image under 2 MB.',400);
        $bytes=file_get_contents($file['tmp_name']);$size=@getimagesizefromstring($bytes);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!$size||!in_array($mime,['image/png','image/jpeg','image/webp'],true)||$size['mime']!==$mime||$size[0]*$size[1]>24000000)throw new RuntimeException('Choose a raster image up to 24 megapixels.',400);
        return $this->newMedia($bytes,$mime,mb_substr(pathinfo(basename($file['name']),PATHINFO_FILENAME),0,120));
    }
    public function stream(string $id): never {
        $r=$this->media($id);$s=$this->shared()['state'];$scene=$s['scene']??[];
        $allowed=$this->gm||(($scene['mode']??'board')!=='board'&&($scene['image']??null)===$id)||($s['drawings'][(string)$this->active()]??null)===$id;
        if(!$allowed)$allowed=(bool)$this->db->rows('SELECT id FROM septenture_kit_handouts WHERE campaign_key=? AND media_id=? AND revealed=1 AND archived=0',[$this->campaign,$id]);
        if(!$allowed)throw new RuntimeException('This image has not been revealed.',404);
        if(!$this->gm&&($s['drawings'][(string)$this->active()]??null)===$id&&TabletopStage::$request?->sight()->active()){$r['image']=TabletopStage::$request->sight()->image($r['image'],true);$r['mime']='image/png';}header('Content-Type: '.$r['mime']);header('Content-Length: '.strlen($r['image']));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');echo $r['image'];exit;
    }
    // Called within the stage transaction after an explicit external display choice.
    public function clearSceneMetadata(): void {$saved=$this->shared(true);$state=$saved['state'];$state['scene']=null;$this->db->rows('UPDATE septenture_kit_state SET document=?,revision=revision+1 WHERE campaign_key=?',[$this->pack($state),$this->campaign]);}
    public function snapshot(int $map): array {
        $r=$this->db->rows('SELECT * FROM maps WHERE id=? AND adventure_id=?',[$map,$this->ctx['cauldron_adventure_id']]);if(!$r)throw new RuntimeException('Map not found at this Table.',404);
        return ['map'=>$r[0],'tokens'=>$this->db->rows('SELECT * FROM map_token WHERE map_id=? ORDER BY id',[$map]),'characters'=>$this->db->rows('SELECT * FROM map_character WHERE map_id=? ORDER BY id',[$map])];
    }
    private function restore(array $snapshot): void {
        $map=(int)$snapshot['map']['id'];$this->snapshot($map);
        $fields=['url','width','height','offset_x','offset_y','grid_size','show_grid','drag_character','fog_of_war','fow_distance','start_x','start_y'];
        $this->db->rows('UPDATE maps SET '.implode(',',array_map(fn($k)=>"$k=?",$fields)).' WHERE id=? AND adventure_id=?',[...array_map(fn($k)=>$snapshot['map'][$k],$fields),$map,$this->ctx['cauldron_adventure_id']]);
        foreach(['tokens'=>'map_token','characters'=>'map_character'] as $key=>$table){
            $this->db->rows("DELETE FROM $table WHERE map_id=?",[$map]);
            foreach($snapshot[$key] as $row){$fields=array_keys($row);$this->db->rows("INSERT INTO $table (".implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($row));}
        }
    }
    public function history(string $kind,string $target,string $label,array $before,array $after): void {
        $this->db->rows('INSERT INTO septenture_kit_history(campaign_key,subject,kind,target,label,before_json,after_json) VALUES(?,?,?,?,?,?,?)',[$this->campaign,$this->ctx['subject'],$kind,$target,$label,$this->pack($before),$this->pack($after)]);
    }
    private function reload(int $map): void {
        $payload=['action'=>'reload','map_id'=>$map,'adventure_id'=>(int)$this->ctx['cauldron_adventure_id']];
        // Server-only sender zero includes the initiating browser as well as other campaign members.
        $this->db->rows('INSERT INTO septenture_table_events(campaign_key,adventure_id,map_id,sender_id,payload) VALUES(?,?,?,?,?)',[$this->campaign,$this->ctx['cauldron_adventure_id'],$map,0,$this->pack($payload)]);
    }
    public function recordMapImage(array $before,array $after): void {
        $fields=array_flip(['url','width','height']);$this->history('map-image',(string)$after['map']['id'],'Replace map image',array_intersect_key($before['map'],$fields),array_intersect_key($after['map'],$fields));
        $shared=$this->shared(true);$shared['state']['scene']=null;$this->saveState($shared['state'],$shared['revision']);
    }
    private function eventCursor(): int {return (int)$this->db->rows('SELECT COALESCE(MAX(id),0) AS id FROM septenture_table_events WHERE campaign_key=?',[$this->campaign])[0]['id'];}
    public function useBoard(string $mode='board',bool $replace=false): void {$this->db->rows('START TRANSACTION');try{$shared=$this->shared(true);if($replace)$shared['state']['scene']=null;elseif($shared['state']['scene'])$shared['state']['scene']['mode']=$mode;$this->saveState($shared['state'],$shared['revision']);$this->db->rows('COMMIT');}catch(Throwable $e){$this->db->rows('ROLLBACK');throw $e;}}
    public function isLocked(string $instance): bool {return (bool)($this->shared()['state']['pieceLocks'][$instance]??false);}
    public function mapLocked(): bool {return (bool)$this->shared()['state']['mapLocked'];}
    public function recordNativeMove(string $instance): void {
        if(!preg_match('/^(token|character)([1-9][0-9]*)$/D',$instance,$m))return;
        $table=$m[1]==='token'?'map_token':'map_character';$r=$this->db->rows("SELECT i.id,i.map_id,i.pos_x,i.pos_y FROM $table i JOIN maps m ON m.id=i.map_id WHERE i.id=? AND m.adventure_id=?",[$m[2],$this->ctx['cauldron_adventure_id']]);if(!$r)return;$before=$r[0];
        register_shutdown_function(function()use($table,$instance,$before){try{$after=$this->db->rows("SELECT id,map_id,pos_x,pos_y FROM $table WHERE id=?",[$before['id']])[0]??null;if($after&&$after!==$before)$this->history('move',$instance,'Move piece',$before,$after);}catch(Throwable $e){error_log('Table move recovery could not be recorded.');}});
    }
    public function state(): array {
        $shared=$this->shared();$s=$shared['state'];if(!$this->gm&&$s['pause'])unset($s['pause']['by']);if(!$this->gm&&($s['scene']['mode']??'board')==='board'&&$s['scene'])$s['scene']['image']=null;
        // Piece locks and drawing media are public only for the active map's visible pieces.
        $visible=[];$pieces=[];$map=$this->active();
        foreach($this->db->rows('SELECT i.id,i.pos_x,i.pos_y,i.hidden,COALESCE(i.name,t.name) AS name,t.width,t.height,g.supabase_user_id AS controller FROM map_token i JOIN tokens t ON t.id=i.token_id LEFT JOIN septenture_token_control g ON g.map_token_instance_id=i.id AND g.campaign_key=? WHERE i.map_id=? ORDER BY i.id',[$this->campaign,$map]) as $r){$key='token'.$r['id'];if($this->gm||!$r['hidden']&&(TabletopStage::$request?->visible((float)($r['pos_x']??0),(float)($r['pos_y']??0),(float)($r['width']??1),(float)($r['height']??1))??true)){$visible[]=$key;$pieces[]=['key'=>$key,'name'=>$r['name'],'width'=>(int)$r['width'],'height'=>(int)$r['height'],'controller'=>$this->gm?($r['controller']??''):'','hidden'=>(bool)$r['hidden'],'control'=>tabletop_instance($this->db,$this->ctx,$key,true)];}}
        foreach($this->db->rows('SELECT i.id,i.pos_x,i.pos_y,i.hidden,c.name FROM map_character i JOIN characters c ON c.id=i.character_id WHERE i.map_id=?',[$map]) as $r){$key='character'.$r['id'];if($this->gm||!$r['hidden']&&(TabletopStage::$request?->visible((float)($r['pos_x']??0),(float)($r['pos_y']??0),(float)($r['width']??1),(float)($r['height']??1))??true)){$visible[]=$key;$pieces[]=['key'=>$key,'name'=>$r['name'],'control'=>tabletop_instance($this->db,$this->ctx,$key,true),'hidden'=>(bool)$r['hidden']];}}
        $s['pieceLocks']=array_intersect_key($s['pieceLocks']??[],array_flip($visible));unset($s['drawingHashes']);$s['drawingCursors']=isset($s['drawingCursors'][(string)$map])?[(string)$map=>$s['drawingCursors'][(string)$map]]:[];
        $s['drawings']=isset($s['drawings'][(string)$map])?[(string)$map=>$s['drawings'][(string)$map]]:[];
        $handouts=$this->db->rows('SELECT id,title,source,media_id,revealed,archived,revision FROM septenture_kit_handouts WHERE campaign_key=?'.($this->gm?'':' AND archived=0 AND revealed=1').' ORDER BY updated_at DESC LIMIT 100',[$this->campaign]);
        $prefs=$this->db->rows('SELECT revision,document FROM septenture_kit_preferences WHERE campaign_key=? AND subject=?',[$this->campaign,$this->ctx['subject']]);
        $signals=$this->db->rows("SELECT id,kind,document FROM septenture_kit_signals WHERE campaign_key=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 12 SECOND) ORDER BY id LIMIT 30",[$this->campaign]);
        $history=$this->db->rows('SELECT id,kind,label,created_at FROM septenture_kit_history WHERE campaign_key=? AND undone_at IS NULL'.($this->gm?'':' AND subject=? AND kind=\'move\'').' ORDER BY id DESC LIMIT 20',$this->gm?[$this->campaign]:[$this->campaign,$this->ctx['subject']]);
        return ['account'=>$this->ctx['subject'],'revision'=>$shared['revision'],'state'=>$s,'gm'=>$this->gm,'map'=>$map,'pieces'=>$pieces,'handouts'=>$handouts,'signals'=>array_values(array_filter(array_map(fn($r)=>['id'=>$r['id'],'kind'=>$r['kind']]+json_decode($r['document'],true),$signals),fn($r)=>(int)($r['map']??0)===$map&&($this->gm||$r['kind']!=='ping'||(TabletopStage::$request?->visible((float)$r['x'],(float)$r['y'])??true)))),'history'=>$history,'preferences'=>['revision'=>(int)($prefs[0]['revision']??0),'value'=>$prefs?json_decode($prefs[0]['document'],true):['follow'=>false,'favorites'=>[]]],'scenes'=>$this->gm?$this->db->rows('SELECT id,title,revision,archived FROM septenture_kit_scenes WHERE campaign_key=? ORDER BY updated_at DESC LIMIT 100',[$this->campaign]):[],'recoveries'=>$this->gm?$this->db->rows("SELECT id,label,created_at FROM septenture_kit_history WHERE campaign_key=? AND kind='map-image' AND undone_at IS NULL ORDER BY id DESC LIMIT 10",[$this->campaign]):[],'players'=>$this->gm?$this->db->rows('SELECT supabase_user_id AS subject,display_name AS name FROM septenture_users WHERE campaign_key=? ORDER BY display_name',[$this->campaign]):[]];
    }
    public function scene(string $id): array {$this->gm();$r=$this->db->rows('SELECT * FROM septenture_kit_scenes WHERE campaign_key=? AND id=?',[$this->campaign,$this->id($id)]);if(!$r)throw new RuntimeException('Scene not found.',404);$r=$r[0];return ['id'=>$r['id'],'title'=>$r['title'],'revision'=>(int)$r['revision'],'archived'=>(bool)$r['archived'],'document'=>json_decode($r['document'],true)];}
    private function assetToToken(string $asset,string $name,int $width,int $height): int {
        $r=$this->media($asset);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'][$r['mime']];
        $this->db->rows("INSERT INTO tokens(organisation_id,name,width,height,extension,type,armor_class,hitpoints,shape_change) VALUES(?,?,?,?,?,'portrait',0,0,0)",[$this->ctx['cauldron_org_id'],$name,$width,$height,$ext]);
        $id=(int)$this->db->rows('SELECT LAST_INSERT_ID() AS id')[0]['id'];$key=$this->db->rows('SELECT resources_key FROM organisations WHERE id=?',[$this->ctx['cauldron_org_id']])[0]['resources_key'];
        if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$key))throw new RuntimeException('Token storage is unavailable.',503);$dir=__DIR__.'/../public/resources/'.$key.'/tokens';if(!is_dir($dir))mkdir($dir,0755,true);
        if(!is_writable($dir)||file_put_contents($dir.'/'.$id.'.'.$ext,$r['image'],LOCK_EX)!==strlen($r['image']))throw new RuntimeException('Token image could not be saved.',503);return $id;
    }
    private function tokenAsset(array $token): string {
        $key=$this->db->rows('SELECT resources_key FROM organisations WHERE id=?',[$this->ctx['cauldron_org_id']])[0]['resources_key'];$file=__DIR__.'/../public/resources/'.$key.'/tokens/'.$token['token_id'].'.'.$token['extension'];
        if(!is_file($file))throw new RuntimeException('Token image is unavailable.',404);$mime=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'][$token['extension']]??'';
        return $this->newMedia(file_get_contents($file),$mime,$token['name']);
    }
    public function act(string $action,array $in): array {
        if($action==='preferences')return $this->preferences($in);
        if(in_array($action,['ping','reaction','spotlight'],true))return $this->signal($action,$in);
        $playerActions=['break','undo','counter-adjust'];if(!in_array($action,$playerActions,true))$this->gm();
        $this->db->rows('START TRANSACTION');try{
            $shared=$this->shared(true);$s=$shared['state'];
            // Requests are idempotent; they cannot dismiss a newer break request.
            if($action!=='break'&&$this->integer($in['revision']??null)!==$shared['revision'])throw new RuntimeException('The Table changed. Refresh these controls before trying again; your draft is kept.',409);
            $map=$this->active();
            switch($action){
                case 'break':$s['pause']=$s['pause']??['by'=>$this->ctx['display_name'],'requested'=>time()];break;
                case 'resume':$s['pause']=null;break;
                case 'lock-map':$s['mapLocked']=($in['locked']??false)===true;break;
                case 'ambience':$s['ambience']=$this->preset($in['preset']??'off');break;
                case 'counters':
                    if(!is_array($in['rows']??null)||count($in['rows'])>30)throw new RuntimeException('Use up to 30 manual counters.',400);$counters=[];
                    foreach($in['rows'] as $r){$owner=$r['owner']??'';if($owner!==''&&!$this->db->rows('SELECT supabase_user_id FROM septenture_users WHERE campaign_key=? AND supabase_user_id=?',[$this->campaign,$owner]))throw new RuntimeException('Choose a current player for this counter.',400);$counters[]=['id'=>isset($r['id'])?$this->id($r['id']):bin2hex(random_bytes(16)),'label'=>$this->text($r['label']??'',80),'value'=>$this->integer($r['value']??0,-999999,999999),'owner'=>$owner];}
                    $s['counters']=$counters;break;
                case 'counter-adjust':
                    $found=false;foreach($s['counters'] as &$counter){if($counter['id']!==($in['id']??null))continue;if(!$this->gm&&$counter['owner']!==$this->ctx['subject'])throw new RuntimeException('Only the assigned player or GM can change this counter.',403);$counter['value']=$this->integer($in['value']??null,-999999,999999);$found=true;}unset($counter);if(!$found)throw new RuntimeException('Counter not found.',404);break;
                case 'turns':
                    if(!is_array($in['rows']??null)||count($in['rows'])>50)throw new RuntimeException('Use up to 50 manual turns.',400);$rows=[];
                    foreach($in['rows'] as $r)$rows[]=['label'=>$this->text($r['label']??'',80),'counter'=>$this->integer($r['counter']??0,-999999,999999)];
                    $s['turns']=['rows'=>$rows,'active'=>$this->integer($in['active']??0,0,max(0,count($rows)-1)),'round'=>$this->integer($in['round']??1,1,9999)];break;
                case 'scene-save':
                    $title=$this->text($in['title']??'');if($title==='')throw new RuntimeException('Give the scene a title.',400);$oldScene=isset($in['id'])?$this->scene($this->id($in['id'])):null;
                    $image=isset($in['image'])&&$in['image']!==''?$this->id($in['image']):null;if($image)$this->media($image);
                    if(!$image&&($in['mode']??'board')!=='board'){$current=$this->db->rows("SELECT * FROM septenture_scene WHERE mode IN ('scene','backdrop') AND campaign_key=?",[$this->campaign]);if($current)$image=$this->newMedia($current[0]['image'],$current[0]['mime'],$current[0]['title']);}
                    $document=['audio'=>TabletopStage::$request?->audioSelection(),'intro'=>$this->text($in['intro']??'',20000),'image'=>$image,'mode'=>in_array($in['mode']??'', ['scene','backdrop','board'],true)?$in['mode']:'board','ambience'=>$this->preset($in['ambience']??'off'),'drawing'=>$oldScene&&!($in['capture']??false)?($oldScene['document']['drawing']??null):($s['drawings'][(string)$map]??null),'snapshot'=>$oldScene&&!($in['capture']??false)?$oldScene['document']['snapshot']:$this->snapshot($map)];
                    $id=isset($in['id'])?$this->id($in['id']):bin2hex(random_bytes(16));
                    if(isset($in['id'])){$old=$this->scene($id);if($this->integer($in['itemRevision']??null)!==$old['revision'])throw new RuntimeException('This scene changed in another tab. Your draft is kept.',409);$this->db->rows('UPDATE septenture_kit_scenes SET title=?,document=?,revision=revision+1 WHERE campaign_key=? AND id=?',[$title,$this->pack($document),$this->campaign,$id]);}
                    else $this->db->rows('INSERT INTO septenture_kit_scenes(id,campaign_key,title,document) VALUES(?,?,?,?)',[$id,$this->campaign,$title,$this->pack($document)]);break;
                case 'scene-archive':$scene=$this->scene($this->id($in['id']??null));$this->db->rows('UPDATE septenture_kit_scenes SET archived=?,revision=revision+1 WHERE campaign_key=? AND id=?',[($in['archived']??true)?1:0,$this->campaign,$scene['id']]);break;
                case 'scene-reveal':
                    $scene=$this->scene($this->id($in['id']??null));if($scene['archived'])throw new RuntimeException('Restore this scene before revealing it.',400);$d=$scene['document'];$target=(int)$d['snapshot']['map']['id'];
                    $before=['audio'=>TabletopStage::$request?->audioSelection(),'snapshot'=>$this->snapshot($target),'drawing'=>$s['drawings'][(string)$target]??null,'active'=>$map,'scene'=>$s['scene'],'ambience'=>$s['ambience']];$this->restore($d['snapshot']);$this->db->rows('UPDATE adventures SET active_map_id=? WHERE id=?',[$target,$this->ctx['cauldron_adventure_id']]);
                    $s['scene']=['id'=>$scene['id'],'title'=>$scene['title'],'intro'=>$d['intro'],'image'=>$d['image'],'mode'=>$d['mode'],'nonce'=>bin2hex(random_bytes(8))];$s['ambience']=$d['ambience'];
                    if($d['drawing']??null)$s['drawings'][(string)$target]=$d['drawing'];else unset($s['drawings'][(string)$target]);$s['drawingCursors'][(string)$target]=$this->eventCursor();
                    TabletopStage::$request?->restoreAudio($d['audio']??null);$this->setArt($s['scene']);$this->history('scene',(string)$target,'Reveal '.$scene['title'],$before,['snapshot'=>$this->snapshot($target),'drawing'=>$s['drawings'][(string)$target]??null,'active'=>$target,'scene'=>$s['scene'],'ambience'=>$s['ambience']]);$this->reload($target);break;
                case 'handout-save':
                    $title=$this->text($in['title']??'');if(!$title)throw new RuntimeException('Give the handout a title.',400);$source=$this->text($in['source']??'',30000);$image=($in['image']??'')!==''?$this->id($in['image']):null;if($image)$this->media($image);
                    if(isset($in['id'])){$id=$this->id($in['id']);$old=$this->db->rows('SELECT revision FROM septenture_kit_handouts WHERE campaign_key=? AND id=? FOR UPDATE',[$this->campaign,$id]);if(!$old)throw new RuntimeException('Handout not found.',404);if((int)$old[0]['revision']!==$this->integer($in['itemRevision']??null))throw new RuntimeException('This handout changed. Your draft is kept.',409);$this->db->rows('UPDATE septenture_kit_handouts SET title=?,source=?,media_id=?,revealed=0,revision=revision+1 WHERE campaign_key=? AND id=?',[$title,$source,$image,$this->campaign,$id]);}
                    else{$id=bin2hex(random_bytes(16));$this->db->rows('INSERT INTO septenture_kit_handouts(id,campaign_key,title,source,media_id) VALUES(?,?,?,?,?)',[$id,$this->campaign,$title,$source,$image]);}break;
                case 'handout-reveal':case 'handout-archive':
                    $id=$this->id($in['id']??null);$field=$action==='handout-reveal'?'revealed':'archived';$rows=$this->db->rows('SELECT id FROM septenture_kit_handouts WHERE campaign_key=? AND id=?',[$this->campaign,$id]);if(!$rows)throw new RuntimeException('Handout not found.',404);
                    $this->db->rows("UPDATE septenture_kit_handouts SET $field=?,revision=revision+1 WHERE campaign_key=? AND id=?",[($in['value']??true)?1:0,$this->campaign,$id]);break;
                case 'token-create':case 'token-edit':case 'token-duplicate':
                    $before=$this->snapshot($map);$instance=null;$old=null;
                    if($action!=='token-create'){$instance=$this->integer($in['id']??null,1);$old=$this->db->rows('SELECT i.*,COALESCE(i.name,t.name) AS name,t.width,t.height,t.extension FROM map_token i JOIN tokens t ON t.id=i.token_id WHERE i.id=? AND i.map_id=? AND t.organisation_id=?',[$instance,$map,$this->ctx['cauldron_org_id']])[0]??null;if(!$old)throw new RuntimeException('Choose a token on the active map.',404);}
                    $name=$this->text($in['name']??($old['name']??'Token'),50);$width=$this->integer($in['width']??(int)($old['width']??1),1,10);$height=$this->integer($in['height']??(int)($old['height']??1),1,10);
                    if($action==='token-create')$token=$this->assetToToken($this->id($in['image']??null),$name,$width,$height);
                    elseif($action==='token-duplicate'||$width!==(int)$old['width']||$height!==(int)$old['height'])$token=$this->assetToToken($this->tokenAsset($old),$name,$width,$height);
                    else $token=(int)$old['token_id'];
                    if($action==='token-edit')$this->db->rows('UPDATE map_token SET name=?,token_id=?,hidden=? WHERE id=?',[$name,$token,($in['hidden']??false)?1:0,$instance]);
                    else{$x=$this->integer($in['x']??min(65000,(int)($old['pos_x']??0)+1),0,65000);$y=$this->integer($in['y']??(int)($old['pos_y']??0),0,65000);$this->db->rows('INSERT INTO map_token(map_id,token_id,name,known,pos_x,pos_y,rotation,hidden,armor_class,hitpoints,damage,custom0,custom1) VALUES(?,?,?,1,?,?,0,?,0,0,0,0,0)',[$map,$token,$name,$x,$y,($in['hidden']??false)?1:0]);$instance=(int)$this->db->rows('SELECT LAST_INSERT_ID() AS id')[0]['id'];}
                    if(isset($in['controller'])&&$in['controller']!==''){$owner=$this->db->rows('SELECT supabase_user_id FROM septenture_users WHERE campaign_key=? AND supabase_user_id=?',[$this->campaign,$in['controller']]);if(!$owner)throw new RuntimeException('Choose a player from this Table.',400);$this->db->rows('INSERT INTO septenture_token_control(campaign_key,map_token_instance_id,supabase_user_id,granted_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE supabase_user_id=VALUES(supabase_user_id),granted_by=VALUES(granted_by)',[$this->campaign,$instance,$in['controller'],$this->ctx['subject']]);}
                    elseif(array_key_exists('controller',$in))$this->db->rows('DELETE FROM septenture_token_control WHERE campaign_key=? AND map_token_instance_id=?',[$this->campaign,$instance]);
                    $s['pieceLocks']['token'.$instance]=($in['locked']??false)===true;$this->history('map',(string)$map,$action==='token-create'?'Place '.$name:($action==='token-edit'?'Edit '.$name:'Duplicate '.$name),$before,$this->snapshot($map));$this->reload($map);break;
                case 'drawing':
                    $hash=hash('sha256',(string)($in['png']??''));if(($s['drawingHashes'][(string)$map]??null)===$hash)break;$s['drawingHashes'][(string)$map]=$hash;
                    $before=$s['drawings'][(string)$map]??null;$asset=$this->png($in['png']??null,'Drawing');
                    $baseline=$before??$this->png($in['before']??null,'Blank drawing');$s['drawings'][(string)$map]=$asset;$s['drawingCursors'][(string)$map]=$this->eventCursor();$this->history('drawing',(string)$map,'Sketch stroke',['image'=>$baseline],['image'=>$asset]);break;
                case 'undo':$this->undo($in,$s);break;
                default:throw new RuntimeException('This play tool is unavailable.',404);
            }
            $this->saveState($s,$shared['revision']);$this->db->rows('COMMIT');return $this->state();
        }catch(Throwable $e){$this->db->rows('ROLLBACK');throw $e;}
    }
    private function preset(mixed $v): string {if(!in_array($v,['off','rain','hearth','night'],true))throw new RuntimeException('Choose an ambience preset.',400);return $v;}
    private function setArt(?array $scene): void {
        if(!$scene||!$scene['image']||$scene['mode']==='board'){$this->db->rows("UPDATE septenture_scene SET mode='board' WHERE campaign_key=?",[$this->campaign]);return;}
        $m=$this->media($scene['image']);$this->db->rows('INSERT INTO septenture_scene(campaign_key,revision,mode,title,mime,image) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),mode=VALUES(mode),title=VALUES(title),mime=VALUES(mime),image=VALUES(image)',[$this->campaign,bin2hex(random_bytes(16)),$scene['mode'],$scene['title'],$m['mime'],$m['image']]);
    }
    private function png(mixed $data,string $title): string {
        if(!is_string($data)||!str_starts_with($data,'data:image/png;base64,')||strlen($data)>2800000)throw new RuntimeException('The drawing is too large to checkpoint.',413);$bytes=base64_decode(substr($data,22),true);$size=$bytes?@getimagesizefromstring($bytes):false;
        if(!$size||$size['mime']!=='image/png'||$size[0]*$size[1]>24000000)throw new RuntimeException('Invalid drawing checkpoint.',400);return $this->newMedia($bytes,'image/png',$title);
    }
    private function undo(array $in,array &$state): void {
        $id=$this->integer($in['id']??null,1);$r=$this->db->rows('SELECT * FROM septenture_kit_history WHERE campaign_key=? AND id=? AND undone_at IS NULL FOR UPDATE',[$this->campaign,$id])[0]??null;if(!$r)throw new RuntimeException('That action is no longer available to undo.',404);
        if(!$this->gm&&($r['subject']!==$this->ctx['subject']||$r['kind']!=='move'))throw new RuntimeException('You can undo only your own piece moves.',403);
        $before=json_decode($r['before_json'],true);$after=json_decode($r['after_json'],true);
        if($r['kind']==='move'){
            if(!$this->gm&&($state['pieceLocks'][$r['target']]??false))throw new RuntimeException('The GM has locked that piece.',403);
            if(!tabletop_instance($this->db,$this->ctx,$r['target'],true))throw new RuntimeException('You no longer control that piece.',403);
            preg_match('/^(token|character)([0-9]+)$/D',$r['target'],$m);$table=$m[1]==='token'?'map_token':'map_character';$now=$this->db->rows("SELECT id,map_id,pos_x,pos_y FROM $table WHERE id=?",[$m[2]])[0]??null;if($now!==$after)throw new RuntimeException('That piece moved again. Undo the newer move first.',409);
            $this->db->rows("UPDATE $table SET pos_x=?,pos_y=? WHERE id=?",[$before['pos_x'],$before['pos_y'],$before['id']]);
        }elseif($r['kind']==='drawing'){
            if(($state['drawings'][$r['target']]??null)!==$after['image'])throw new RuntimeException('Undo the newer sketch first.',409);$state['drawings'][$r['target']]=$before['image'];$state['drawingCursors'][$r['target']]=$this->eventCursor();unset($state['drawingHashes'][$r['target']]);
        }elseif($r['kind']==='map-image'){
            $now=array_intersect_key($this->snapshot((int)$r['target'])['map'],array_flip(['url','width','height']));if($now!==$after)throw new RuntimeException('The map image changed again. Recover the newer image first.',409);
            $this->db->rows('UPDATE maps SET url=?,width=?,height=? WHERE id=? AND adventure_id=?',[$before['url'],$before['width'],$before['height'],$r['target'],$this->ctx['cauldron_adventure_id']]);$this->reload((int)$r['target']);
        }elseif($r['kind']==='map'){
            if($this->snapshot((int)$r['target'])!==$after)throw new RuntimeException('The map changed again. Undo the newer change first.',409);$this->restore($before);$this->reload((int)$r['target']);
        }elseif($r['kind']==='scene'){
            if($this->snapshot((int)$r['target'])!==$after['snapshot']||$this->active()!==$after['active'])throw new RuntimeException('The scene changed again. Undo the newer changes first.',409);
            $this->restore($before['snapshot']);$this->db->rows('UPDATE adventures SET active_map_id=? WHERE id=?',[$before['active'],$this->ctx['cauldron_adventure_id']]);if($before['drawing']??null)$state['drawings'][$r['target']]=$before['drawing'];else unset($state['drawings'][$r['target']]);$state['drawingCursors'][$r['target']]=$this->eventCursor();$state['scene']=$before['scene'];$state['ambience']=$before['ambience'];TabletopStage::$request?->restoreAudio($before['audio']??null);$this->setArt($state['scene']);$this->reload((int)$before['active']);
        }else throw new RuntimeException('This action cannot be undone here.',400);
        $this->db->rows('UPDATE septenture_kit_history SET undone_at=UTC_TIMESTAMP() WHERE id=?',[$id]);
    }
    private function preferences(array $in): array {
        if(!is_array($in['favorites']??null)||count($in['favorites'])>16)throw new RuntimeException('Keep up to 16 favorite rolls.',400);$favorites=[];
        foreach($in['favorites'] as $f)$favorites[]=['name'=>$this->text($f['name']??'',80),'expression'=>$this->text($f['expression']??'',120),'mode'=>in_array($f['mode']??'normal',['normal','advantage','disadvantage'],true)?($f['mode']??'normal'):'normal','visibility'=>($f['visibility']??'private')==='public'?'public':'private'];
        $value=['follow'=>($in['follow']??false)===true,'volume'=>$this->integer($in['volume']??25,0,100),'favorites'=>$favorites];$this->db->rows('START TRANSACTION');try{
            $this->db->rows("INSERT IGNORE INTO septenture_kit_preferences(campaign_key,subject,document) VALUES(?,?,'{}')",[$this->campaign,$this->ctx['subject']]);$r=$this->db->rows('SELECT revision FROM septenture_kit_preferences WHERE campaign_key=? AND subject=? FOR UPDATE',[$this->campaign,$this->ctx['subject']]);if((int)$r[0]['revision']!==$this->integer($in['revision']??null))throw new RuntimeException('Your preferences changed in another tab. Reopen them to review.',409);
            $this->db->rows('UPDATE septenture_kit_preferences SET revision=revision+1,document=? WHERE campaign_key=? AND subject=?',[$this->pack($value),$this->campaign,$this->ctx['subject']]);$this->db->rows('COMMIT');return $this->state();
        }catch(Throwable $e){$this->db->rows('ROLLBACK');throw $e;}
    }
    private function signal(string $kind,array $in): array {
        $this->db->rows('DELETE FROM septenture_kit_signals WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
        $last=$this->db->rows('SELECT id FROM septenture_kit_signals WHERE campaign_key=? AND subject=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)',[$this->campaign,$this->ctx['subject']]);if($last)throw new RuntimeException('Give that signal a moment before sending another.',429);
        $d=['by'=>$this->ctx['display_name'],'at'=>time(),'map'=>$this->active(),'gm'=>$this->gm];
        if($kind==='reaction'){$d['emoji']=$in['emoji']??'';if(!in_array($d['emoji'],['♥','😂','✨','👏'],true))throw new RuntimeException('Choose a reaction.',400);}
        if($kind==='ping'){$d['x']=$this->integer($in['x']??null,0,65000);$d['y']=$this->integer($in['y']??null,0,65000);}
        if($kind==='spotlight'){$this->gm();$id=$this->id($in['id']??null);$r=$this->db->rows('SELECT id FROM septenture_kit_handouts WHERE campaign_key=? AND id=? AND archived=0',[$this->campaign,$id]);if(!$r)throw new RuntimeException('Handout not found.',404);$this->db->rows('UPDATE septenture_kit_handouts SET revealed=1,revision=revision+1 WHERE id=?',[$id]);$d['handout']=$id;}
        $this->db->rows('INSERT INTO septenture_kit_signals(campaign_key,subject,kind,document) VALUES(?,?,?,?)',[$this->campaign,$this->ctx['subject'],$kind,$this->pack($d)]);return ['ok'=>true];
    }
}
function tabletop_play_kit(TabletopService $db,array $ctx,string $route): never {
    $kit=new TabletopPlayKit($db,$ctx);$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'){
        if($route==='state')tabletop_response($kit->state());
        if($route==='media')$kit->stream((string)($_GET['id']??''));
        if($route==='scene')tabletop_response($kit->scene((string)($_GET['id']??'')));
        throw new RuntimeException('Play tool not found.',404);
    }
    if($method!=='POST')tabletop_response(['error'=>'Use GET or POST.'],405);
    if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))throw new RuntimeException('Reopen the Table before saving. Your draft is kept.',403);
    if($route==='upload')tabletop_response(['image'=>$kit->imageUpload($_FILES['image']??[])]);
    $raw=file_get_contents('php://input',false,null,0,6000001);if(strlen($raw)>6000000)throw new RuntimeException('This change is too large.',413);$in=json_decode($raw,true);if(!is_array($in)||array_is_list($in))throw new RuntimeException('Use a JSON object.',400);
    tabletop_response($kit->act($route,$in));
}
