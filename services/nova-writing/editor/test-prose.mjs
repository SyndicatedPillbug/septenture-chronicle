import assert from 'node:assert/strict';
import {parseHTML} from 'linkedom';
import {renderMarkdown} from './src/prose-render.js';
const {document}=parseHTML('<html><body></body></html>');globalThis.document=document;
const allowed=(s,image)=>{try{return (image?['https:','http:']:['https:','http:','mailto:']).includes(new URL(s,'https://fixture.test').protocol)}catch{return false}};
const html=renderMarkdown('# Heading\n\n**Bold** and *italic*.\n\n| A | B |\n| - | - |\n| 1 | 2 |\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))\n\n![bad](data:text/html,x)',allowed);
const node=document.createElement('div');node.innerHTML=html;
assert.equal(node.querySelector('strong').textContent,'Bold');assert.equal(node.querySelector('em').textContent,'italic');assert.equal(node.querySelectorAll('table tr').length,2);assert.equal(node.querySelectorAll('script,[onclick],a[href^="javascript:"],img[src^="data:"]').length,0);assert.match(node.textContent,/<script>/);
console.log('PASS shared public Markdown renders formatting and tables, escapes raw HTML, and blocks unsafe link and image schemes');
