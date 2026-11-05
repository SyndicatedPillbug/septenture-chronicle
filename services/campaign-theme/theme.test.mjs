import {test} from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const code=readFileSync(new URL('./site-theme.js',import.meta.url),'utf8');
function app(saved=null,blocked=false){
 const events={},domEvents={},attrs={},data={},storage=new Map(saved?[['septenture-theme-v1',JSON.stringify(saved)]]:[]),media={matches:false,addEventListener(type,fn){this.change=fn}};
 const root={dataset:data,style:{},setAttribute(k,v){attrs[k]=v}};
 const doc={documentElement:root,readyState:'loading',querySelectorAll:()=>[],addEventListener(k,v){domEvents[k]=v},dispatchEvent(){}};
 const win={matchMedia:()=>media};
 const ctx={window:win,document:doc,location:{href:'https://septenture.helioho.st/index.php/write/index',origin:'https://septenture.helioho.st',hostname:'septenture.helioho.st'},localStorage:{getItem(k){if(blocked)throw Error();return storage.get(k)},setItem(k,v){if(blocked)throw Error();storage.set(k,v)}},URL,CustomEvent:class{},addEventListener(k,v){events[k]=v},parent:{},Date};
 vm.runInNewContext(code,ctx);return {api:win.SeptentureTheme,events,media,data,attrs,storage};
}
test('system theme follows OS while explicit selection remains fixed',()=>{const a=app();assert.equal(a.data.septentureTheme,'light');a.media.matches=true;a.media.change();assert.equal(a.data.septentureTheme,'dark');a.api.set('light');a.media.change();assert.equal(a.data.septentureTheme,'light');});
test('malformed preferences and inaccessible storage safely fall back',()=>{for(const saved of [{mode:'invalid',updated:1},{mode:'dark',updated:Date.now()+600000},{mode:'dark',updated:-1}])assert.equal(app(saved).api.get(),'system');const a=app(null,true);a.api.set('dark');assert.equal(a.data.septentureTheme,'dark');});
test('untrusted messages cannot change appearance or persist arbitrary data',()=>{const a=app();a.events.message({source:{},origin:'https://evil.invalid',data:{type:'septenture-theme-state',record:{mode:'dark',updated:Date.now()}}});assert.equal(a.api.get(),'system');a.api.set('garbage');assert.equal(a.api.get(),'system');});
test('valid same-origin storage updates sync while older state is ignored',()=>{const a=app();a.storage.set('septenture-theme-v1',JSON.stringify({mode:'dark',updated:100}));a.events.storage({key:'septenture-theme-v1'});assert.equal(a.api.get(),'dark');a.storage.set('septenture-theme-v1',JSON.stringify({mode:'light',updated:50}));a.events.storage({key:'septenture-theme-v1'});assert.equal(a.api.get(),'dark');});
