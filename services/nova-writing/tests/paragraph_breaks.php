<?php
require '/var/www/fiction-release/application/septenture/story_format.php';
$s="First paragraph.\n\n<br />\n\nSecond paragraph.\n\n`<br />`\n\n```html\n<br />\n```\n\n<br onclick=\"alert(1)\">\n\n<script>alert(1)</script>";
$html=Septenture_story_format::render($s,'markdown');
if(substr_count($html,'<br />')!==1||substr_count($html,'&lt;br /&gt;')!==2||str_contains($html,'<script>')||str_contains($html,'<br onclick'))throw new Exception('Break safety regression: '.$html);
if(!str_contains($html,'<p>First paragraph.</p>')||!str_contains($html,'<p>Second paragraph.</p>'))throw new Exception('Paragraphs changed');
echo "PASS exact empty-paragraph breaks, preserved code examples, escaped scripts/attributes and paragraph boundaries\n";
