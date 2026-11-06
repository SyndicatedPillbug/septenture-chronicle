<?php
require_once __DIR__.'/../../nova/vendor/autoload.php';
final class Septenture_story_format {
    public const LIMIT=1048576;
    public static function validate($source,$format): string {
        if(!in_array($format,['legacy','markdown'],true))throw new InvalidArgumentException('Choose Markdown or legacy formatting.',400);
        if(!is_string($source)||!mb_check_encoding($source,'UTF-8'))throw new InvalidArgumentException('Story content must be UTF-8 text.',400);
        if($format==='markdown'&&strlen($source)>self::LIMIT)throw new LengthException('Markdown stories are limited to 1 MiB. Your text has not been saved or truncated.',413);
        return $source;
    }
    public static function render(string $source,string $format): string {
        self::validate($source,$format);
        $config=HTMLPurifier_Config::createDefault();$config->set('Cache.DefinitionImpl',null);
        $config->set('URI.AllowedSchemes',['http'=>true,'https'=>true,'mailto'=>true]);
        if($format==='markdown'){
            $environment=new League\CommonMark\Environment\Environment(['html_input'=>'escape','allow_unsafe_links'=>false,'max_nesting_level'=>100,'max_delimiters_per_line'=>1000]);
            $environment->addExtension(new League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
            $environment->addExtension(new League\CommonMark\Extension\Table\TableExtension());
            $environment->addExtension(new League\CommonMark\Extension\Strikethrough\StrikethroughExtension());
            // Milkdown uses an exact, attribute-free <br /> node for empty paragraphs.
            // Recognize that node only; all other HTML still uses the escaping renderer.
            $breakRenderer=new class implements League\CommonMark\Renderer\NodeRendererInterface {
                public function render(League\CommonMark\Node\Node $node,League\CommonMark\Renderer\ChildNodeRendererInterface $children) {
                    if(!preg_match('~^<br\s*/?\s*>$~i',trim($node->getLiteral())))return null;
                    return $node instanceof League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock ? '<p><br /></p>' : '<br />';
                }
            };
            $environment->addRenderer(League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock::class,$breakRenderer,100);
            $environment->addRenderer(League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline::class,$breakRenderer,100);
            $html=(string)(new League\CommonMark\MarkdownConverter($environment))->convert($source);
            $config->set('HTML.Allowed','p,br,hr,h1,h2,h3,h4,h5,h6,strong,em,del,ul,ol[start],li,blockquote,pre,code,a[href|title],img[src|alt|title],table,thead,tbody,tr,th[align],td[align]');
        }else{
            $html=nl2br($source);$config->set('Attr.EnableID',true);
            $config->set('HTML.DefinitionID','SeptentureStoryLegacy');$config->set('HTML.DefinitionRev',1);
            if($def=$config->maybeGetRawHTMLDefinition()){$def->addElement('center','Block','Flow','Common');$def->addElement('font','Inline','Inline','Common',['color'=>'Color']);}
        }
        return (new HTMLPurifier($config))->purify($html);
    }
    public static function words(string $source,string $format): int {
        $html=self::render($source,$format);
        $html=preg_replace('~</(?:p|div|h[1-6]|li|blockquote|pre|tr|td|th)>|<br\s*/?>|<hr\s*/?>~i',' ',$html);
        $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
        preg_match_all("/[\\p{L}\\p{N}]+(?:[’'\\-][\\p{L}\\p{N}]+)*/u",$text,$matches);
        return count($matches[0]);
    }
    public static function mail(string $source,string $format,string $type): string {
        $html=self::render($source,$format);
        // Nova adds nl2br to the surrounding email; protect renderer newlines from a second conversion.
        if($type==='html')return str_replace(["\r","\n"],['','&#10;'],$html);
        $text=preg_replace('~</(?:p|h[1-6]|li|blockquote|pre|tr)>|<br\s*/?>~i',"\n",$html);
        $text=preg_replace('~</t[dh]>~i',"\t",$text);
        return trim(html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
}
function septenture_story_format_for_id($id): string {
    if(!is_numeric($id)||(int)$id<1)return 'markdown';
    $ci=&get_instance();$ci->load->database();$row=$ci->db->select('post_content_format')->get_where('posts',['post_id'=>(int)$id])->row();
    return $row->post_content_format??'legacy';
}
function septenture_story_request_format($id) {return $_POST['post_content_format']??septenture_story_format_for_id($id);}
