<?php
// Read-only fiction feed. Called before Nova's session/HTML bootstrap.
require_once __DIR__.'/identity.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/json; charset=utf-8');
function fiction_reply(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    exit;
}
function fiction_writer_name(string $name): string {
    static $aliases=['kim'=>'Kim','ceridwen'=>'Kim','ceridwen glaive'=>'Kim','chelsea'=>'Chelsea','page'=>'Chelsea','brendon'=>'Brendon','cendraveil'=>'Brendon','cendreveil'=>'Brendon','cendreveil / finn'=>'Brendon','cendraveil / finn'=>'Brendon','finn'=>'Brendon','simon'=>'Brendon','lord simon devereux'=>'Brendon','selim'=>'Brendon','the gunslinger'=>'Brendon','dan'=>'Dan','coy'=>'Dan','coy qualdirriar'=>'Dan','gm'=>'Dan','gm dm'=>'Dan','game master'=>'Dan','lucas'=>'Lucas','lucas\' legion'=>'Lucas','legion'=>'Lucas','hervd'=>'Lucas','hervd angantyr-bohun'=>'Lucas'];
    return $aliases[strtolower(trim($name))] ?? trim($name);
}
$site=septenture_config('SITE_URL','https://syndicatedpillbug.github.io/septenture-chronicle');
$origin=parse_url($site,PHP_URL_SCHEME).'://'.parse_url($site,PHP_URL_HOST).(parse_url($site,PHP_URL_PORT)?':'.parse_url($site,PHP_URL_PORT):'');
if (($_SERVER['HTTP_ORIGIN']??'')!==$origin) fiction_reply(['error'=>'Untrusted origin.'],403);
header('Access-Control-Allow-Origin: '.$origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') {http_response_code(204);exit;}
if ($_SERVER['REQUEST_METHOD']!=='POST') fiction_reply(['error'=>'Use POST.'],405);
$raw=file_get_contents('php://input',false,null,0,12289);
if (strlen($raw)>12288) fiction_reply(['error'=>'Request too large.'],413);
$input=json_decode($raw,true);
if (!is_array($input)||!is_string($input['token']??null)) fiction_reply(['error'=>'Please sign in.'],401);
try {
    // This verifies the token with the account service and checks RLS-backed
    // game membership on EVERY request. No trust in client metadata or cookies.
    septenture_identity($input['token']);
    $db=septenture_db();
    $base=rtrim(septenture_config('NOVA_URL','https://septenture.helioho.st'),'/');
    $type=$input['type']??'story';
    if (!in_array($type,['story','journal'],true)) fiction_reply(['error'=>'Invalid entry type.'],400);
    if (isset($input['id'])) {
        if (!is_int($input['id'])||$input['id']<1) fiction_reply(['error'=>'Invalid story.'],400);
        $query=$type==='journal'
            ? "SELECT log_id AS post_id,log_title AS post_title,log_content AS post_content,log_last_update AS post_last_update FROM nova_personallogs WHERE log_id=? AND log_status='activated'"
            : "SELECT post_id,post_title,post_content,post_last_update FROM nova_posts WHERE post_id=? AND post_status='activated'";
        $q=$db->prepare($query);
        $q->execute([$input['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) fiction_reply(['error'=>'This entry is no longer published.'],404);
        fiction_reply(['id'=>(int)$row['post_id'],'title'=>$row['post_title'],'html'=>$row['post_content']??'',
            'revision'=>hash('sha256',(string)$row['post_content']),'novaUrl'=>$base.'/index.php/sim/'.($type==='journal'?'viewlog/':'viewpost/').$row['post_id']]);
    }
    $offset=$input['offset']??0;
    if (!is_int($offset)||$offset<0||$offset>100000) fiction_reply(['error'=>'Invalid page.'],400);
    $chars=[];
    foreach ($db->query('SELECT c.charid,c.first_name,c.middle_name,c.last_name,c.suffix,m.slug FROM nova_characters c LEFT JOIN nova_septenture_cast m ON m.charid=c.charid')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $chars[(int)$c['charid']]=['id'=>(int)$c['charid'],'name'=>trim(implode(' ',array_filter([$c['first_name'],$c['middle_name'],$c['last_name'],$c['suffix']]))),'slug'=>$c['slug']];
    }
    // Saved and pending posts never enter the catalogue, including for GMs.
    $rows=$db->query("SELECT * FROM (
        SELECT post_id,post_title,post_content,post_authors,post_tags,post_words,post_date,post_last_update,post_location,post_timeline,'story' AS entry_type FROM nova_posts WHERE post_status='activated'
        UNION ALL
        SELECT log_id,log_title,log_content,CAST(log_author_character AS CHAR),log_tags,log_words,log_date,log_last_update,'','','journal' AS entry_type FROM nova_personallogs WHERE log_status='activated'
    ) AS entries ORDER BY post_date DESC,entry_type,post_id DESC LIMIT 101 OFFSET ".(int)$offset)->fetchAll(PDO::FETCH_ASSOC);
    $more=count($rows)>100;$rows=array_slice($rows,0,100);$stories=[];
    foreach($rows as $r) {
        $isJournal=$r['entry_type']==='journal';
        $plain=html_entity_decode(strip_tags(preg_replace('~<(?:br|hr)\s*/?>~i',"\n",$r['post_content']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $writers=[];
        if (preg_match('/Original writers:\s*([^\r\n]+)/u',$plain,$m)) $writers=array_values(array_filter(array_map('trim',explode(',',$m[1]))));
        $characters=[];
        foreach(explode(',',$r['post_authors']??'') as $id) if(isset($chars[(int)$id])&&$chars[(int)$id]['name']!=='Game Master') $characters[]=$chars[(int)$id];
        if (!$writers) $writers=array_column($characters,'name');
        if (!$writers) $writers=['Game Master'];
        $writers=array_values(array_unique(array_filter(array_map('fiction_writer_name',$writers))));
        $title=$r['post_title'];$part=1;$parts=1;$display=$title;
        if(preg_match('/^(.*?)\s+[—–-]\s+Part (\d+) of (\d+)$/u',$title,$m)) {$display=$m[1];$part=(int)$m[2];$parts=(int)$m[3];}
        $tags=array_values(array_filter(array_map('trim',explode(',',$r['post_tags']??''))));
        $sourceId=null;foreach($tags as $tag) if(preg_match('/^discord-(\d+)$/D',$tag,$m)) $sourceId=$m[1];
        $kind=$isJournal?'Journal':(str_contains(strtolower($title),'backstory')?'Backstory':(str_contains(strtolower($title),'timeline, rules')?'Reference':(in_array('roleplay-archive',$tags,true)?'Roleplay':'Fiction')));
        $excerpt=preg_replace('/^Discord archive.*?Original (?:thread|channel)\s*/su','',$plain);
        $excerpt=preg_replace('/^Part \d+ of \d+;[^\n]*\n/u','',$excerpt);
        $excerpt=preg_replace('/[\p{L}\p{N}\x27\x{2019} \/]+ · \d{4}-\d{2}-\d{2}[^\n]*source\s*/u','',$excerpt);
        $excerpt=preg_replace('/\s+/u',' ',trim($excerpt));
        $written=(int)$r['post_date'];if(preg_match('/Discord archive\s*·\s*(\d{4}-\d{2}-\d{2})/u',$plain,$m)) $written=strtotime($m[1].'T12:00:00Z');
        $stories[]=['id'=>(int)$r['post_id'],'title'=>$title,'displayTitle'=>$display,'entryType'=>$r['entry_type'],'group'=>($isJournal?'journal-'.$r['post_id']:($sourceId?'discord-'.$sourceId:'nova-'.$r['post_id'])),
            'part'=>$part,'parts'=>$parts,'characters'=>$characters,'writers'=>$writers,'tags'=>$tags,'kind'=>$kind,
            'words'=>(int)$r['post_words'],'published'=>(int)$r['post_date'],'updated'=>(int)$r['post_last_update'],'written'=>$written,
            'timeline'=>$r['post_timeline'],'location'=>$r['post_location'],'excerpt'=>mb_substr($excerpt,0,260),
            'search'=>$plain,'revision'=>hash('sha256',(string)$r['post_content']),'novaUrl'=>$base.'/index.php/sim/'.($isJournal?'viewlog/':'viewpost/').$r['post_id']];
    }
    fiction_reply(['stories'=>$stories,'nextOffset'=>$more?$offset+100:null]);
} catch(Throwable $e) {
    $status=in_array($e->getCode(),[401,403,503],true)?$e->getCode():503;
    fiction_reply(['error'=>$status===503?'The fiction shelves are temporarily unavailable. Please try again.':$e->getMessage()],$status);
}
