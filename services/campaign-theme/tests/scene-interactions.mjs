import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {parseHTML} from '../../nova-writing/editor/node_modules/linkedom/esm/index.js';
const source=readFileSync(new URL('../table-overlay/public/js/table-scene.js',import.meta.url),'utf8');
const flush=async()=>{for(let i=0;i<8;i++)await new Promise(r=>setImmediate(r))};
async function fixture(dm){
 const {document,window}=parseHTML(`<html><body class="live-board"><nav class="board-toolbar"><button>Chat &amp; rolls</button><button>Focus board</button></nav><div class="playarea" is_dm="${dm?'yes':'no'}"></div></body></html>`);
 window.HTMLElement.prototype.showModal=function(){this.open=true};window.HTMLElement.prototype.close=function(){this.open=false};
 const requests=[];let state={mode:'board'};const parent={postMessage(){}};
 class File {constructor(name,type='image/png'){this.name=name;this.type=type;this.size=100}}
 class FormData {constructor(){this.values=new Map}set(k,v){this.values.set(k,v)}}
 const context={document,window,parent,self:window,location:{origin:'https://fixture.test'},File,FormData,URL:{createObjectURL:()=> 'blob:fixture',revokeObjectURL(){}},requestAnimationFrame:f=>f(),addEventListener:window.addEventListener.bind(window),setInterval:()=>1,clearInterval(){},fetch:async(url,options={})=>{requests.push({url,...options});if(options.method==='POST'&&url==='/session/scene')state={mode:options.body.values.get('mode'),url:'/session/scene/image?revision='+'a'.repeat(32),title:'Fixture'};return {ok:true,json:async()=>url==='/session/chat'?{messages:[{name:'Fixture player',text:'<img src=x onerror=alert(1)>',roll:false}]}:{scene:state}}}};
 vm.runInNewContext(source,context);await flush();return {document,window,parent,File,requests};
}
const gm=await fixture(true);
assert.equal(gm.document.querySelectorAll('#scene-chat img').length,0,'Chat text must never create image markup');
assert.match(gm.document.querySelector('#scene-chat').textContent,/<img src=x/);
const drop=new gm.window.Event('drop',{cancelable:true});drop.dataTransfer={types:['Files'],files:[new gm.File('Road.png')]};gm.window.dispatchEvent(drop);assert.equal(drop.defaultPrevented,true);assert.equal(gm.document.querySelector('dialog').open,true);assert.match(gm.document.querySelector('dialog [role=status]').textContent,/Ready: Road.png/);assert.equal(gm.requests.filter(r=>r.method==='POST').length,0,'Dropping previews rather than publishing');
[...gm.document.querySelectorAll('dialog button')].find(b=>b.textContent==='Show as scene').click();await flush();assert.equal(gm.requests.filter(r=>r.method==='POST').length,1);assert.equal(gm.document.body.classList.contains('show-scene'),true);
const forged=new gm.window.Event('message');forged.origin='https://evil.invalid';forged.source=gm.parent;forged.data={type:'septenture-scene-drop',image:new gm.File('Forged.png')};gm.window.dispatchEvent(forged);assert.equal(gm.document.querySelector('dialog').open,false,'Foreign wrapper messages cannot open or change the scene');
const bad=new gm.window.Event('drop',{cancelable:true});bad.dataTransfer={types:['Files'],files:[new gm.File('unsafe.svg','image/svg+xml')]};gm.window.dispatchEvent(bad);assert.match(gm.document.querySelector('dialog [role=status]').textContent,/PNG, JPEG or WebP/);
const player=await fixture(false);assert.equal([...player.document.querySelectorAll('button')].some(b=>b.textContent==='Scene image'),false);const playerDrop=new player.window.Event('drop',{cancelable:true});playerDrop.dataTransfer={types:['Files'],files:[new player.File('No.png')]};player.window.dispatchEvent(playerDrop);await flush();assert.equal(playerDrop.defaultPrevented,true);assert.equal(player.requests.filter(r=>r.method==='POST').length,0);
assert.equal(gm.requests.some(r=>r.url.includes('rolls')),false,'Shared chat does not fetch a private dice ledger');
console.log('PASS scene drop previews before sharing, GM-only upload UI, rejected SVG and foreign messages, literal chat and private roll separation');
