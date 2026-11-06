<?php
// Carry this non-sensitive preference through server-side login redirects.
if(PHP_SAPI!=='cli'&&isset($_GET['theme'])&&is_string($_GET['theme'])&&in_array($_GET['theme'],['light','dark','system'],true))setcookie('septenture_theme',json_encode(['mode'=>$_GET['theme'],'updated'=>(int)round(microtime(true)*1000)]),['expires'=>time()+31536000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>false,'samesite'=>'Lax']);
// Add the shared palette only to complete HTML pages; API bodies and headers are unchanged.
if(PHP_SAPI!=='cli')ob_start(static function(string $html): string {
 if(!preg_match('~^\s*(?:<!doctype\s+html|<html\b)~i',$html)||str_contains($html,'data-septenture-theme-assets'))return $html;
 foreach(headers_list() as $header)if(str_starts_with(strtolower($header),'content-type:')&&!str_contains(strtolower($header),'text/html'))return $html;
 $assets='<link data-septenture-theme-assets rel="stylesheet" href="/js/site-theme.css?v=e7ec77bb74af"><script src="/js/site-theme.js?v=aacda04218d8"></script>';
 if(stripos($html,'</head>')!==false)return preg_replace('~</head>~i',$assets.'</head>',$html,1);
 return preg_replace('~(<!doctype[^>]*>)~i','$1'.$assets,$html,1);
});
