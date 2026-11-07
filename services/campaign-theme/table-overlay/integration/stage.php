<?php
/** Manual exploration, reusable media and owner-only session bookmarks. */
require_once __DIR__.'/visibility.php';
final class TabletopStage {
    public static ?self $request=null;
    public bool $gm;
    private string $campaign;
    private ?StageSight $vision=null;
    public function __construct(public TabletopService $db,public array $ctx){$this->campaign=$ctx['campaign_key'];$this->gm=$ctx['purpose']==='dm'&&(int)$ctx['cauldron_user_id']===(int)$ctx['dm_id'];}
    private function gm(): void {if(!$this->gm)throw new RuntimeException('Only the GM can change this.',403);}
    private function id(mixed $v): string {if(!is_string($v)||!preg_match('/^[a-f0-9]{32}$/D',$v))throw new RuntimeException('Choose an item from this Table.',400);return $v;}
    private function text(mixed $v,int $max=120): string {if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max)throw new RuntimeException('Use shorter, valid text.',400);return trim($v);}
    private function number(mixed $v,float $min=0,float $max=65000): float {if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max)throw new RuntimeException('Choose a valid number.',400);return (float)$v;}
    private function pack(mixed $v): string {return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    public function map(): array {return $this->db->rows('SELECT m.* FROM maps m JOIN adventures a ON a.active_map_id=m.id WHERE a.id=? AND m.adventure_id=a.id',[$this->ctx['cauldron_adventure_id']])[0]??throw new RuntimeException('Choose an active map first.',404);}
    private function stored(bool $lock=false): array {
        if($lock)$this->db->rows("INSERT IGNORE INTO septenture_stage_state(campaign_key,document) VALUES(?,'{}')",[$this->campaign]);
        $r=$this->db->rows('SELECT revision,document FROM septenture_stage_state WHERE campaign_key=?'.($lock?' FOR UPDATE':''),[$this->campaign]);
        return ['revision'=>(int)($r[0]['revision']??0),'value'=>array_replace(['audio'=>null,'maps'=>[],'templates'=>[]],$r?json_decode($r[0]['document'],true):[])];
    }
    public function sight(): StageSight {
        if($this->vision)return $this->vision;$m=$this->map();$s=$this->stored()['value'];$options=$s['maps'][(string)$m['id']]??[];
        $chars=$this->db->rows('SELECT i.*,c.vision FROM map_character i JOIN characters c ON c.id=i.character_id WHERE i.map_id=? AND i.hidden=0 AND c.user_id=?',[$m['id'],$this->ctx['cauldron_user_id']]);
        return $this->vision=new StageSight($m,$options,$chars,$this->db->rows('SELECT * FROM walls WHERE map_id=?',[$m['id']]),$this->db->rows('SELECT * FROM doors WHERE map_id=?',[$m['id']]),$this->db->rows('SELECT * FROM lights WHERE map_id=?',[$m['id']]),$this->db->rows('SELECT * FROM blinders WHERE map_id=?',[$m['id']]));
    }
    public function visible(float $x,float $y,float $w=1,float $h=1): bool {
        if($this->gm)return true;$v=$this->sight();if(!$v->active())return true;
        for($dy=0;$dy<max(1,ceil($h));$dy++)for($dx=0;$dx<max(1,ceil($w));$dx++)if(!$v->allows($x+$dx,$y+$dy))return false;return true;
    }
    public static function filter(array|false $rows,string $kind): array|false {
        $self=self::$request;if(!$rows||!$self||$self->gm)return $rows;$sight=$self->sight();if(!$sight->active())return $rows;
        // Hidden geometry is unnecessary in a client whose map pixels are masked on the server.
        if(in_array($kind,['walls','doors','lights','zones','blinders'],true))return [];
        return array_values(array_filter($rows,fn($r)=>$self->visible((float)$r['pos_x'],(float)$r['pos_y'],(float)($r['width']??1),(float)($r['height']??1))));
    }
    public function state(): array {
        $saved=$this->stored();$s=$saved['value'];$map=$this->map();$options=$s['maps'][(string)$map['id']]??[];$sight=$this->sight();
        $templates=array_values(array_filter($s['templates'],fn($r)=>(int)$r['map']===(int)$map['id']&&($r['shared']||$r['subject']===$this->ctx['subject'])&&($this->gm||$this->visible($r['x'],$r['y'])&&$this->visible($r['ex'],$r['ey']))));
        // Account identifiers are never needed for other players' templates.
        foreach($templates as &$t){$t['mine']=$t['subject']===$this->ctx['subject'];unset($t['subject']);}unset($t);
        $bookmarks=$this->db->rows('SELECT id,revision,map_title,title,source,archived,created_at FROM septenture_stage_bookmarks WHERE campaign_key=? AND subject=? ORDER BY created_at DESC LIMIT 200',[$this->campaign,$this->ctx['subject']]);
        $audio=$s['audio'];if($audio){$m=$this->media($audio['id']);$audio['title']=$m['title'];$audio['url']='/session/stage/audio?id='.$m['id'];}
        return ['account'=>$this->ctx['subject'],'revision'=>$saved['revision'],'gm'=>$this->gm,'map'=>['id'=>(int)$map['id'],'title'=>$map['title'],'grid'=>(float)$map['grid_size'],'offsetX'=>(int)$map['offset_x'],'offsetY'=>(int)$map['offset_y'],'showGrid'=>(bool)$map['show_grid'],'scale'=>$options['scale']??5,'unit'=>$options['unit']??'ft'],'audio'=>$audio,'templates'=>$templates,'bookmarks'=>$bookmarks,'vision'=>$this->gm?null:['active'=>$sight->active(),'revision'=>$sight->revision()], 'exploration'=>$this->gm?['mode'=>(int)$map['fog_of_war'],'manual'=>$options['manual']??false,'base'=>$options['base']??'shown','areas'=>$options['areas']??[],'doors'=>$this->db->rows('SELECT * FROM doors WHERE map_id=? ORDER BY id',[$map['id']]),'lights'=>$this->db->rows('SELECT * FROM lights WHERE map_id=? ORDER BY id',[$map['id']])]:null];
    }
    public function mapImage(): never {
        $map=$this->map();$root=realpath(__DIR__.'/../public');$path=realpath($root.$map['url']);if(!$path||!str_starts_with($path,$root.'/')||!is_file($path))throw new RuntimeException('Map image unavailable.',404);$bytes=file_get_contents($path);if(!$this->gm&&$this->sight()->active())$bytes=$this->sight()->image($bytes);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!str_starts_with($mime,'image/'))throw new RuntimeException('Use a raster map for exploration.',400);header('Content-Type: '.$mime);header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo $bytes;exit;
    }
    public function audioSelection(): ?array {return $this->stored()['value']['audio'];}
    public function restoreAudio(?array $audio): void {$saved=$this->stored(true);$s=$saved['value'];if($audio){$m=$this->media($audio['id']);if(!str_starts_with($m['mime'],'audio/'))throw new RuntimeException('Saved scene audio is unavailable.',404);$audio['nonce']=bin2hex(random_bytes(8));}$s['audio']=$audio;$this->db->rows('UPDATE septenture_stage_state SET document=?,revision=revision+1 WHERE campaign_key=?',[$this->pack($s),$this->campaign]);}
    private function media(string $id): array {return $this->db->rows('SELECT * FROM septenture_kit_media WHERE campaign_key=? AND id=?',[$this->campaign,$this->id($id)])[0]??throw new RuntimeException('Media not found.',404);}
    public function shelf(): array {
        $this->gm();$search=$this->text($_GET['q']??'',120);$kind=($_GET['kind']??'')==='audio'?'audio':'image';$pattern='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
        return ['items'=>$this->db->rows("SELECT m.id,m.title,m.mime,COALESCE(s.tags,'') AS tags,COALESCE(s.archived,0) AS archived FROM septenture_kit_media m LEFT JOIN septenture_stage_media s ON s.media_id=m.id AND s.campaign_key=m.campaign_key WHERE m.campaign_key=? AND m.mime LIKE ? AND (m.title LIKE ? OR s.tags LIKE ?) AND m.title NOT IN ('Drawing','Blank drawing') ORDER BY m.created_at DESC,m.id LIMIT 100",[$this->campaign,$kind==='audio'?'audio/%':'image/%',$pattern,$pattern])];
    }
    public function upload(array $file): array {
        $this->gm();if(($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??PHP_INT_MAX)>2097152||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('Choose an audio loop under 2 MB.',400);
        $bytes=file_get_contents($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$mime=['application/ogg'=>'audio/ogg','audio/x-wav'=>'audio/wav','audio/wave'=>'audio/wav'][$mime]??$mime;
        if(!in_array($mime,['audio/mpeg','audio/ogg','audio/wav'],true))throw new RuntimeException('Choose an MP3, Ogg or WAV audio file.',400);
        $id=bin2hex(random_bytes(16));$title=$this->text(mb_substr(pathinfo(basename($file['name']),PATHINFO_FILENAME),0,120));
        $this->db->rows('INSERT INTO septenture_kit_media(id,campaign_key,title,mime,image) VALUES(?,?,?,?,?)',[$id,$this->campaign,$title,$mime,$bytes]);$this->db->rows("INSERT INTO septenture_stage_media(media_id,campaign_key,kind) VALUES(?,?,'audio')",[$id,$this->campaign]);return ['id'=>$id,'title'=>$title];
    }
    public function stream(string $id,bool $preview=false): never {
        $m=$this->media($id);$s=$this->stored()['value'];if($preview)$this->gm();elseif(($s['audio']['id']??'')!==$id||!str_starts_with($m['mime'],'audio/'))throw new RuntimeException('This audio is not playing in the scene.',404);
        $bytes=$m['image'];$length=strlen($bytes);$start=0;$end=$length-1;
        if(isset($_SERVER['HTTP_RANGE'])){if(!preg_match('/^bytes=(\d+)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$range)){http_response_code(416);exit;}$start=(int)$range[1];$end=$range[2]===''?$end:min($end,(int)$range[2]);if($start>$end){http_response_code(416);header('Content-Range: bytes */'.$length);exit;}http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$length);}
        header('Content-Type: '.$m['mime']);header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');echo substr($bytes,$start,$end-$start+1);exit;
    }
    private function notify(int $map,bool $includeGM=true): void {$this->db->rows('INSERT INTO septenture_table_events(campaign_key,adventure_id,map_id,sender_id,payload) VALUES(?,?,?,?,?)',[$this->campaign,$this->ctx['cauldron_adventure_id'],$map,$includeGM?0:$this->ctx['cauldron_user_id'],$this->pack(['action'=>'reload','map_id'=>$map,'adventure_id'=>(int)$this->ctx['cauldron_adventure_id']])]);}
    public function validateActiveMap(): void {$map=$this->map();$options=$this->stored()['value']['maps'][(string)$map['id']]??[];if((int)$map['fog_of_war']!==0||($options['manual']??false))$this->validateMask($map);}
    private function validateMask(array $map): void {
        $grid=(float)$map['grid_size'];if($grid<=0||ceil($map['width']/$grid)*ceil($map['height']/$grid)>20000)throw new RuntimeException('Use a larger grid size before enabling concealment on this map.',400);
        $root=realpath(__DIR__.'/../public');$path=realpath($root.$map['url']);$size=$path&&str_starts_with($path,$root.'/')?@getimagesize($path):false;
        if(!$size||!in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_GIF,IMAGETYPE_WEBP],true)||$size[0]>4096||$size[1]>4096||$size[0]*$size[1]>10000000)throw new RuntimeException('Exploration needs a local raster map up to 4096 pixels per side and 10 million pixels.',400);
    }
    public function act(string $action,array $in): array {
        if(str_starts_with($action,'bookmark-'))return $this->bookmark($action,$in);
        if(!in_array($action,['template-save','template-remove'],true))$this->gm();
        $createdPath=null;$this->db->rows('START TRANSACTION');try{
            $saved=$this->stored(true);if(($in['revision']??null)!==$saved['revision'])throw new RuntimeException('These controls changed. Reopen them before saving; your entries are kept.',409);$s=$saved['value'];$map=$this->map();$key=(string)$map['id'];$options=$s['maps'][$key]??[];
            if(in_array($action,['cover-all','area'],true)||$action==='sight-mode'&&(int)($in['mode']??0)!==0)$this->validateMask($map);
            switch($action){
                case 'template-save':
                    if(count($s['templates'])>=100)throw new RuntimeException('Remove an old template first.',400);$shape=$in['shape']??'';if(!in_array($shape,['ruler','circle','cone','rectangle'],true))throw new RuntimeException('Choose a shape.',400);
                    foreach(['x','y','ex','ey'] as $coordinate)$in[$coordinate]=$this->number($in[$coordinate]??null);if(hypot($in['ex']-$in['x'],$in['ey']-$in['y'])<.05)throw new RuntimeException('Choose two different map points.',400);$s['templates'][]=['id'=>bin2hex(random_bytes(16)),'map'=>(int)$map['id'],'subject'=>$this->ctx['subject'],'by'=>$this->ctx['display_name'],'shared'=>($in['shared']??false)===true,'shape'=>$shape,'label'=>$this->text($in['label']??'',80),'x'=>$this->number($in['x']??null),'y'=>$this->number($in['y']??null),'ex'=>$this->number($in['ex']??null),'ey'=>$this->number($in['ey']??null),'color'=>in_array($in['color']??'', ['gold','blue','red','green'],true)?$in['color']:'gold'];break;
                case 'template-remove':
                    $id=$this->id($in['id']??null);$found=false;foreach($s['templates'] as $t)if($t['id']===$id){if(!$this->gm&&$t['subject']!==$this->ctx['subject'])throw new RuntimeException('You can remove only your own templates.',403);$found=true;}if(!$found)throw new RuntimeException('Template not found.',404);$s['templates']=array_values(array_filter($s['templates'],fn($t)=>$t['id']!==$id));break;
                case 'audio-set':
                    $id=($in['id']??'')!==''?$this->id($in['id']):null;if($id){$m=$this->media($id);if(!str_starts_with($m['mime'],'audio/'))throw new RuntimeException('Choose an audio loop.',400);}$s['audio']=$id?['id'=>$id,'nonce'=>bin2hex(random_bytes(8))]:null;break;
                case 'media-edit':
                    $m=$this->media($this->id($in['id']??null));$title=$this->text($in['title']??$m['title']);if(!$title)throw new RuntimeException('Give this media a title.',400);$this->db->rows('UPDATE septenture_kit_media SET title=? WHERE campaign_key=? AND id=?',[$title,$this->campaign,$m['id']]);$this->db->rows('INSERT INTO septenture_stage_media(media_id,campaign_key,kind,tags,archived) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE tags=VALUES(tags),archived=VALUES(archived)',[$m['id'],$this->campaign,str_starts_with($m['mime'],'audio/')?'audio':'image',$this->text($in['tags']??'',240),($in['archived']??false)?1:0]);break;
                case 'media-display':
                    $m=$this->media($this->id($in['id']??null));$mode=$in['mode']??'';if(!str_starts_with($m['mime'],'image/')||!in_array($mode,['scene','backdrop','map'],true))throw new RuntimeException('Choose image art and a display option.',400);
                    if($mode==='map'){
                        $kit=new TabletopPlayKit($this->db,$this->ctx);if($kit->mapLocked())throw new RuntimeException('Unlock the background before replacing it.',409);$before=$kit->snapshot((int)$map['id']);$size=getimagesizefromstring($m['image']);$org=$this->db->rows('SELECT resources_key FROM organisations WHERE id=?',[$this->ctx['cauldron_org_id']])[0]['resources_key'];if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$org))throw new RuntimeException('Map storage is unavailable.',503);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$m['mime']]??throw new RuntimeException('Use PNG, JPEG or WebP for map art.',400);$url='/resources/'.$org.'/maps/shelf-'.bin2hex(random_bytes(16)).'.'.$ext;$path=__DIR__.'/../public'.$url;$createdPath=$path;if(file_put_contents($path,$m['image'],LOCK_EX)!==strlen($m['image']))throw new RuntimeException('Map art could not be saved.',503);$this->db->rows('UPDATE maps SET url=?,width=?,height=? WHERE id=?',[$url,$size[0],$size[1],$map['id']]);if((int)$map['fog_of_war']!==0||($options['manual']??false))$this->validateMask($this->map());$kit->recordMapImage($before,$kit->snapshot((int)$map['id']));$this->db->rows("UPDATE septenture_scene SET mode='board' WHERE campaign_key=?",[$this->campaign]);$this->notify((int)$map['id']);
                    }else{$this->db->rows('INSERT INTO septenture_scene(campaign_key,revision,mode,title,mime,image) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),mode=VALUES(mode),title=VALUES(title),mime=VALUES(mime),image=VALUES(image)',[$this->campaign,bin2hex(random_bytes(16)),$mode,$m['title'],$m['mime'],$m['image']]);}
                    (new TabletopPlayKit($this->db,$this->ctx))->clearSceneMetadata();break;
                case 'grid':
                    $before=(new TabletopPlayKit($this->db,$this->ctx))->snapshot((int)$map['id']);$grid=round($this->number($in['grid']??null,4,999),2);$ox=(int)$this->number($in['offsetX']??0,0,255);$oy=(int)$this->number($in['offsetY']??0,0,255);if((int)$map['fog_of_war']!==0||($options['manual']??false))$this->validateMask(array_replace($map,['grid_size'=>$grid]));$this->db->rows('UPDATE maps SET grid_size=?,offset_x=?,offset_y=?,show_grid=? WHERE id=?',[$grid,$ox,$oy,($in['showGrid']??true)?1:0,$map['id']]);$options['scale']=$this->number($in['scale']??5,.01,10000);$options['unit']=$this->text($in['unit']??'ft',16);$kit=new TabletopPlayKit($this->db,$this->ctx);$kit->history('map',$key,'Align map grid',$before,$kit->snapshot((int)$map['id']));$this->notify((int)$map['id']);break;
                case 'sight-mode':
                    $mode=(int)$this->number($in['mode']??null,0,4);if(!in_array($mode,[0,2,4],true))throw new RuntimeException('Choose shown, daylight or night sight.',400);$this->db->rows('UPDATE maps SET fog_of_war=? WHERE id=?',[$mode,$map['id']]);$this->notify((int)$map['id'],false);break;
                case 'cover-all':$options['manual']=true;$options['base']='covered';$options['areas']=[];$this->notify((int)$map['id'],false);break;
                case 'reveal-all':$options['manual']=false;$options['areas']=[];$this->notify((int)$map['id'],false);break;
                case 'area':
                    $rect=[];foreach(['x','y','ex','ey'] as $f)$rect[$f]=(int)$this->number($in[$f]??null);if($rect['ex']<=$rect['x']||$rect['ey']<=$rect['y'])throw new RuntimeException('Choose two different map corners.',400);if(count($options['areas']??[])>=200)throw new RuntimeException('Reset manual cover before adding more areas.',400);$options['manual']=true;$options['base']??='shown';$options['areas'][]=$rect+['reveal'=>($in['reveal']??false)===true];$this->notify((int)$map['id'],false);break;
                case 'door':case 'light':
                    $table=$action==='door'?'doors':'lights';$id=(int)$this->number($in['id']??null,1);$old=$this->db->rows("SELECT id FROM $table WHERE id=? AND map_id=?",[$id,$map['id']]);if(!$old)throw new RuntimeException('This object is not on the active map.',404);$values=$action==='door'?['open','closed']:['on','off'];if(!in_array($in['state']??'', $values,true))throw new RuntimeException('Choose a valid state.',400);$this->db->rows("UPDATE $table SET state=? WHERE id=?",[$in['state'],$id]);if($action==='light'&&isset($in['radius']))$this->db->rows('UPDATE lights SET radius=? WHERE id=?',[(int)$this->number($in['radius'],1,255),$id]);$this->notify((int)$map['id'],false);break;
                default:throw new RuntimeException('Play tool not found.',404);
            }
            $s['maps'][$key]=$options;$this->db->rows('UPDATE septenture_stage_state SET document=?,revision=revision+1 WHERE campaign_key=? AND revision=?',[$this->pack($s),$this->campaign,$saved['revision']]);$this->db->rows('COMMIT');return $this->state();
        }catch(Throwable $e){$this->db->rows('ROLLBACK');if($createdPath&&is_file($createdPath))unlink($createdPath);throw $e;}
    }
    private function bookmark(string $action,array $in): array {
        if($action==='bookmark-save'){
            $title=$this->text($in['title']??'');if(!$title)throw new RuntimeException('Give this moment a title.',400);$source=$this->text($in['source']??'',30000);
            if(isset($in['id'])){$id=$this->id($in['id']);$this->db->rows('START TRANSACTION');try{$r=$this->db->rows('SELECT revision FROM septenture_stage_bookmarks WHERE campaign_key=? AND subject=? AND id=? FOR UPDATE',[$this->campaign,$this->ctx['subject'],$id]);if(!$r)throw new RuntimeException('Bookmark not found.',404);if((int)$r[0]['revision']!==($in['itemRevision']??null))throw new RuntimeException('This bookmark changed. Your draft is kept.',409);$this->db->rows('UPDATE septenture_stage_bookmarks SET title=?,source=?,revision=revision+1 WHERE campaign_key=? AND subject=? AND id=?',[$title,$source,$this->campaign,$this->ctx['subject'],$id]);$this->db->rows('COMMIT');}catch(Throwable $e){$this->db->rows('ROLLBACK');throw $e;}}
            else {$id=bin2hex(random_bytes(16));$this->db->rows('INSERT INTO septenture_stage_bookmarks(id,campaign_key,subject,map_title,title,source) VALUES(?,?,?,?,?,?)',[$id,$this->campaign,$this->ctx['subject'],$this->map()['title'],$title,$source]);}
        }elseif($action==='bookmark-archive'){$id=$this->id($in['id']??null);$r=$this->db->rows('SELECT id FROM septenture_stage_bookmarks WHERE id=? AND campaign_key=? AND subject=?',[$id,$this->campaign,$this->ctx['subject']]);if(!$r)throw new RuntimeException('Bookmark not found.',404);$this->db->rows('UPDATE septenture_stage_bookmarks SET archived=?,revision=revision+1 WHERE id=? AND subject=?',[($in['archived']??true)?1:0,$id,$this->ctx['subject']]);}
        else throw new RuntimeException('Bookmark tool not found.',404);return $this->state()+['bookmarkId'=>$id];
    }
}
final class StageSight {
    private ?RecorderVisibility $native=null;
    public function __construct(public array $map,private array $options,array $chars,array $walls,array $doors,array $lights,array $blinders){if((int)$map['fog_of_war']!==0)$this->native=new RecorderVisibility($map,$chars,$walls,$doors,$lights,$blinders);}
    public function active(): bool {return (int)$this->map['fog_of_war']!==0||($this->options['manual']??false);}
    public function revision(): string {return hash('sha256',($this->native?->revision()??json_encode($this->map)).json_encode($this->options));}
    public function allows(float $x,float $y): bool {
        if($this->native&&!$this->native->allows($x,$y))return false;if(!($this->options['manual']??false))return true;$yes=($this->options['base']??'shown')==='shown';
        foreach($this->options['areas']??[] as $r)if(floor($x)>=$r['x']&&floor($y)>=$r['y']&&floor($x)+1<=$r['ex']&&floor($y)+1<=$r['ey'])$yes=$r['reveal'];return $yes;
    }
    public function image(string $bytes,bool $drawing=false): string {
        $size=@getimagesizefromstring($bytes);if(!$size||$size[0]*$size[1]>10000000||$size[0]>4096||$size[1]>4096)throw new RuntimeException('This map cannot be safely masked. Use a raster map up to 4096 pixels per side.',400);$source=@imagecreatefromstring($bytes);if(!$source)throw new RuntimeException('Map image unavailable.',404);
        $w=imagesx($source);$h=imagesy($source);$target=imagecreatetruecolor($w,$h);imagefill($target,0,0,imagecolorallocate($target,20,25,22));$g=(float)$this->map['grid_size']*($drawing?$w/$this->map['width']:1);$ox=$drawing?0:(int)$this->map['offset_x'];$oy=$drawing?0:(int)$this->map['offset_y'];
        for($y=0;$y<ceil($h/$g);$y++)for($x=0;$x<ceil($w/$g);$x++)if($this->allows($x,$y)){$left=(int)floor($x*$g+$ox);$top=(int)floor($y*$g+$oy);$cw=min((int)ceil($g),$w-$left);$ch=min((int)ceil($g),$h-$top);if($cw>0&&$ch>0)imagecopy($target,$source,$left,$top,$left,$top,$cw,$ch);}
        ob_start();imagepng($target);$result=ob_get_clean();imagedestroy($source);imagedestroy($target);return $result;
    }
}
function tabletop_stage(TabletopService $db,array $ctx,string $route): never {
    $stage=new TabletopStage($db,$ctx);$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'){if($route==='state')tabletop_response($stage->state());if($route==='map-image')$stage->mapImage();if($route==='shelf')tabletop_response($stage->shelf());if($route==='audio'||$route==='preview')$stage->stream((string)($_GET['id']??''),$route==='preview');throw new RuntimeException('Play tool not found.',404);}
    if($method!=='POST')throw new RuntimeException('Use GET or POST.',405);
    if(!hash_equals($_SESSION['tabletop_csrf']??'missing',$_SERVER['HTTP_X_TABLETOP_CSRF']??''))throw new RuntimeException('Reopen the Table before saving. Your entries are kept.',403);
    if($route==='upload-audio')tabletop_response($stage->upload($_FILES['audio']??[]));
    $raw=file_get_contents('php://input',false,null,0,100001);if(strlen($raw)>100000)throw new RuntimeException('This entry is too large.',413);$input=json_decode($raw,true);if(!is_array($input)||array_is_list($input))throw new RuntimeException('Use a JSON object.',400);tabletop_response($stage->act($route,$input));
}
