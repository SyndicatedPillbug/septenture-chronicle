<?php
/** Optional, manually entered fields. This schema performs no game calculations. */
final class Septenture_manual_sheet {
    private static function text(mixed $value,int $limit): string {
        if(!is_string($value)||strlen($value)>$limit||!mb_check_encoding($value,'UTF-8'))throw new InvalidArgumentException('Sheet text is missing, too long, or is not UTF-8.',400);
        return $value;
    }
    private static function keys(array $value,array $allowed): void {if(array_diff(array_keys($value),$allowed))throw new InvalidArgumentException('Unknown manual sheet fields.',400);}
    private static function id(mixed $value,array &$seen): string {
        $id=self::text($value,64);if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$id)||isset($seen[$id]))throw new InvalidArgumentException('Use unique stable section and field IDs.',400);$seen[$id]=true;return $id;
    }
    public static function validate(mixed $input): array {
        if(!is_array($input))throw new InvalidArgumentException('Provide a manual sheet document.',400);
        self::keys($input,['version','sections']);
        if(($input['version']??null)!==1||!is_array($input['sections']??null)||!array_is_list($input['sections'])||count($input['sections'])>40)throw new InvalidArgumentException('Use manual sheet version 1 and at most 40 optional sections.',400);
        $ids=[];$sections=[];
        foreach($input['sections'] as $section){
            if(!is_array($section))throw new InvalidArgumentException('Invalid sheet section.',400);self::keys($section,['id','title','fields','prose']);
            $entry=['id'=>self::id($section['id']??null,$ids),'title'=>self::text($section['title']??null,160),'fields'=>[]];
            if(!is_array($section['fields']??null)||!array_is_list($section['fields'])||count($section['fields'])>100)throw new InvalidArgumentException('Use at most 100 fields per section.',400);
            foreach($section['fields'] as $field){if(!is_array($field))throw new InvalidArgumentException('Invalid sheet field.',400);self::keys($field,['id','label','value']);$entry['fields'][]=['id'=>self::id($field['id']??null,$ids),'label'=>self::text($field['label']??null,160),'value'=>self::text($field['value']??null,2000)];}
            $prose=$section['prose']??null;if(!is_array($prose))throw new InvalidArgumentException('Provide section prose with an explicit format.',400);self::keys($prose,['source','format']);
            if(!in_array($prose['format']??null,['plain','markdown'],true))throw new InvalidArgumentException('Choose plain text or Markdown.',400);
            $entry['prose']=['source'=>self::text($prose['source']??null,32000),'format'=>$prose['format']];$sections[]=$entry;
        }
        $result=['version'=>1,'sections'=>$sections];if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>200000)throw new LengthException('Manual sheets are limited to 200 KB; your work was not truncated.',413);return $result;
    }
    public static function render(array $sheet,bool $includeNotes=false): string {
        require_once __DIR__.'/story_format.php';$esc=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<h1>'.$esc($sheet['name']).'</h1><p class="revision">Saved revision '.(int)$sheet['revision'].' · '.$esc(gmdate('Y-m-d H:i T',(int)$sheet['updated_at'])).'</p>';
        foreach(self::validate($sheet['document']['manual_sheet']??['version'=>1,'sections'=>[]])['sections'] as $section){$html.='<section><h2>'.$esc($section['title']).'</h2><dl>';foreach($section['fields'] as $field)$html.='<div><dt>'.$esc($field['label']).'</dt><dd>'.$esc($field['value']).'</dd></div>';$html.='</dl><div class="prose">'.($section['prose']['format']==='markdown'?Septenture_story_format::render($section['prose']['source'],'markdown'):'<p>'.nl2br($esc($section['prose']['source'])).'</p>').'</div></section>';}
        if($includeNotes)$html.='<section><h2>Private notes · included by request</h2>'.(($sheet['document']['notes_format']??'plain')==='markdown'?Septenture_story_format::render($sheet['document']['notes']??'','markdown'):'<p>'.nl2br($esc($sheet['document']['notes']??'')).'</p>').'</section>';
        return $html;
    }
    public static function printPage(array $sheet,bool $includeNotes): never {
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Character sheet</title><link rel="stylesheet" href="/assets/manual-sheet.css"><main class="manual-print"><p class="print-help">Saved version only. Use your browser’s Print command to print or save as PDF.</p>'.self::render($sheet,$includeNotes).'</main></html>';exit;
    }
}
