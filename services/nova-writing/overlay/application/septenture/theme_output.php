<?php
// Add the shared palette only to complete HTML pages; API bodies and headers are unchanged.
if(PHP_SAPI!=='cli')ob_start(static function(string $html): string {
 if(!preg_match('~^\s*(?:<!doctype\s+html|<html\b)~i',$html)||str_contains($html,'data-septenture-theme-assets'))return $html;
 foreach(headers_list() as $header)if(str_starts_with(strtolower($header),'content-type:')&&!str_contains(strtolower($header),'text/html'))return $html;
 $assets='<link data-septenture-theme-assets rel="stylesheet" href="/assets/site-theme.css?v=e358ca1101b3"><script src="/assets/site-theme.js?v=8927a0c55490"></script>';
 if(stripos($html,'</head>')!==false)return preg_replace('~</head>~i',$assets.'</head>',$html,1);
 return preg_replace('~(<!doctype[^>]*>)~i','$1'.$assets,$html,1);
});
