<?php
/** Present ONLY links already authorized by Nova's menu builder. No ACL changes. */
function septenture_navigation(string $authorizedHtml,string $type,string $current): string {
    $groups = [
        'Writing'=>[
            'write/index'=>'Writing desk','write/missionpost'=>'New story',
            'write/personallog'=>'Publish a journal entry','write/newsitem'=>'New announcement',
        ],
        'Characters'=>[
            'characters/index'=>'Character roster','characters/npcs'=>'NPC roster',
            'characters/create'=>'Create a character or NPC','characters/bio'=>'My character details',
            'user/characterlink'=>'Link my characters',
        ],
        'Messages'=>[
            'messages/index'=>'Inbox','messages/write'=>'New message','messages/sent'=>'Sent messages',
        ],
        'Your account'=>[
            'user/account'=>'Player account','play/writing/notifications'=>'Email preferences & delivery','user/options'=>'Other writing preferences',
            'user/status'=>'Availability',
        ],
        'Campaign management'=>[
            'manage/missions'=>'Adventures','manage/missiongroups'=>'Adventure collections',
            'manage/posts'=>'Manage stories','manage/logs'=>'Manage journals',
            'manage/news'=>'Announcements','manage/newscats'=>'Announcement categories',
            'manage/comments'=>'Comments','report/moderation'=>'Review submissions',
            'report/activity'=>'Player activity','report/posting'=>'Writing activity',
            'report/loa'=>'Player availability',
        ],
        'Administration'=>[
            'admin/index'=>'Administration overview','user/all'=>'Player directory',
            'site/settings'=>'Fiction settings','site/messages'=>'Page titles and messages',
            'site/roles'=>'Nova access roles','site/bans'=>'Blocked accounts',
            'site/menus'=>'Nova menu configuration','site/catalogueskins'=>'Appearance',
            'upload/index'=>'Upload images','upload/manage'=>'Manage uploads',
            'report/versions'=>'Software versions','report/stats'=>'Fiction statistics',
        ],
    ];
    $legacy = [
        'site/bioform','site/specsform','site/tourform','site/dockingform','site/simtypes',
        'site/catalogueranks','site/manifests','manage/depts','manage/positions','manage/ranks',
        'manage/specs','manage/tour','manage/decks','manage/docked','manage/awards',
        'characters/coc','characters/awards','user/nominate','report/awardnominations',
        'report/milestones','report/applications','personnel/coc','sim/awards',
        'sim/specs','sim/tour','sim/decks','sim/docked',
    ];
    $labels=[];$sections=[];
    foreach($groups as $group=>$routes) foreach($routes as $route=>$label) {$labels[$route]=$label;$sections[$route]=$group;}
    $labels += ['personnel/index'=>'Character directory','sim/listposts'=>'Stories',
        'sim/listlogs'=>'Journals','sim/missions'=>'Adventures','main/news'=>'Announcements'];
    preg_match_all('~<a\b[^>]*href="([^"]+)"[^>]*>.*?</a>~is',$authorizedHtml,$matches,PREG_SET_ORDER);
    $items=[];$advanced=[];$old=[];$plain=[];
    $activePath=parse_url($current,PHP_URL_PATH)??'';
    foreach($matches as $match) {
        $href=html_entity_decode($match[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
        $path=parse_url($href,PHP_URL_PATH)??'';
        $route=preg_replace('~^.*?/index\.php/~','',$path);
        $route=trim($route,'/');
        // Nova's own registration/recovery flow is replaced by the site account bridge.
        if(in_array($route,['main/join','login/register','login/reset_password'],true))continue;
        $link=$match[0];
        if(isset($labels[$route])) {
            $link=preg_replace('~(<a\b[^>]*>).*?(</a>)~is','$1<span>'.htmlspecialchars($labels[$route],ENT_QUOTES,'UTF-8').'</span>$2',$link);
        }
        if($path===$activePath) $link=preg_replace('~<a\b~','<a aria-current="page"',$link,1);
        if(in_array($route,$legacy,true)) {if($type==='adminsub')$old[]=$link;continue;}
        if($type!=='adminsub') {$plain[]=$link;continue;}
        if(isset($sections[$route]))$items[$sections[$route]][$route]=$link;
        else $advanced[]=$link; // Preserve authorized custom/unclassified links, outside daily tools.
    }
    $list=static function(array $links):string {return '<ul>'.implode('',array_map(static fn($a)=>'<li>'.$a.'</li>',$links)).'</ul>';};
    if($type!=='adminsub')return $list($plain);
    if(defined('SEPTENTURE_PLAYER_ID'))$items['Writing']['write/index'].='<br><a href="/index.php/play/notebook">My private game notes</a>';
    if(defined('SEPTENTURE_PLAYER_ID'))$items['Your account']['play/writing/notifications']='<a href="/index.php/play/writing/notifications">Email preferences &amp; delivery</a>';
    $hasSiteConfiguration=isset($items['Administration']['site/settings']);
    $out='<details class="mobile-writing-menu"><summary>Writing &amp; character tools</summary><div class="campaign-tools" aria-label="Writing and campaign tools">';
    foreach($groups as $group=>$routes) {
        if(empty($items[$group]))continue;
        $links=[];foreach($routes as $route=>$label)if(isset($items[$group][$route]))$links[]=$items[$group][$route];
        $heading=($group==='Campaign management'&&!$hasSiteConfiguration)?'Manage my writing':$group;
        $section='<h2 class="campaign-menu-heading">'.htmlspecialchars($heading,ENT_QUOTES,'UTF-8').'</h2>'.$list($links);
        if($group==='Administration')$out.='<details class="campaign-menu-disclosure"><summary>'.($hasSiteConfiguration?'Administration':'Additional writing tools').'</summary>'.$list($links).'</details>';
        elseif($group==='Campaign management')$out.='<details class="campaign-menu-disclosure"><summary>'.htmlspecialchars($heading,ENT_QUOTES,'UTF-8').'</summary>'.$list($links).'</details>';
        else $out.=$section;
    }
    if($advanced)$out.='<details class="campaign-menu-disclosure"><summary>Additional tools</summary>'.$list($advanced).'</details>';
    if($old&&$hasSiteConfiguration)$out.='<details class="campaign-menu-disclosure legacy-tools"><summary>Legacy Nova setup</summary><p>Older roster and setting tools. These do not manage D&amp;D classes, levels, or character sheets.</p>'.$list($old).'</details>';
    return $out.'</div></details><script>(function(d){var q=matchMedia("(max-width:700px)");function fit(){d.open=!q.matches}fit();q.addEventListener("change",fit)})(document.currentScript.previousElementSibling);</script>';
}
