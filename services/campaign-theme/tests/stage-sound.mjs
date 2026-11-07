import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {parseHTML} from '../../nova-writing/editor/node_modules/linkedom/esm/index.js';
const source=readFileSync(new URL('../table-overlay/public/js/table-stage.js',import.meta.url),'utf8');
const flush=async()=>{for(let i=0;i<6;i++)await new Promise(r=>setImmediate(r))};
async function fixture(){
 const {document,window}=parseHTML('<html><body class="live-board"><nav class="board-toolbar"><button>Focus board</button></nav><main class="playarea" grid_cell_size="50"><div id="map_background"></div></main><footer class="kit-quickbar"></footer></body></html>');
 window.HTMLElement.prototype.showModal=function(){this.open=true};window.HTMLElement.prototype.close=function(){this.open=false};
 const values=new Map(),contexts=[],timers=[];let starts=0;
 const parameter=()=>({value:0,setTargetAtTime(){},cancelScheduledValues(){}});
 class AudioContext {constructor(){this.state='suspended';this.currentTime=0;this.sampleRate=10;contexts.push(this)}resume(){if(this.state==='running')return Promise.resolve();return new Promise(resolve=>this.resumed=()=>{this.state='running';resolve()})}createGain(){return {gain:parameter(),connect(){}}}addEventListener(){}createBuffer(){return {getChannelData:()=>new Float32Array(40)}}createBufferSource(){return {connect(){},start(){starts++},stop(){},disconnect(){}}}createBiquadFilter(){return {frequency:parameter(),Q:parameter(),connect(){},disconnect(){}}}close(){this.state='closed';return Promise.resolve()}}
 window.AudioContext=AudioContext;
 const state={account:'owner',gm:false,revision:0,map:{id:1,title:'Fixture',scale:5,unit:'ft'},templates:[],bookmarks:[],audio:null,vision:null};
 window.SeptenturePlayKit={getState:()=>({account:'owner',state:{ambience:'rain'},preferences:{revision:0,value:{volume:25}}}),close(){}};
 const storage={getItem:k=>values.get(k),setItem:(k,v)=>values.set(k,v),removeItem:k=>values.delete(k)};
 vm.runInNewContext(source,{document,window,addEventListener:window.addEventListener.bind(window),AbortController,sessionStorage:storage,localStorage:storage,Event:window.Event,setInterval:()=>1,clearInterval(){},setTimeout:(fn,ms)=>{timers.push({fn,ms});return timers.length},clearTimeout(){},fetch:async()=>({ok:true,json:async()=>state})});
 await flush();await window.SeptentureStage.open('sound');const click=name=>[...document.querySelectorAll('button')].find(b=>b.textContent===name).click();return {document,click,contexts,timers,values,starts:()=>starts};
}
const normal=await fixture();normal.click('Enable sound');await flush();normal.contexts[0].resumed();await flush();assert.equal(normal.starts(),1);assert.equal(normal.document.querySelector('.stage-sound-status').dataset.playback,'playing');assert.match(normal.document.querySelector('.stage-sound-status').textContent,/Playing: Rain/);normal.click('Fade out & mute');await flush();assert.equal(normal.document.querySelector('.stage-sound-status').dataset.playback,'muted');assert.equal(normal.values.get('septenture-stage-sound:owner'),undefined);
const race=await fixture();race.click('Enable sound');await flush();race.click('Fade out & mute');await flush();race.contexts[0].resumed();await flush();assert.equal(race.starts(),0,'A late browser resume must never undo a newer mute');assert.equal(race.document.querySelector('.stage-sound-status').dataset.playback,'muted');
const blocked=await fixture();blocked.click('Enable sound');await flush();blocked.timers.find(t=>t.ms===1500).fn();await flush();assert.equal(blocked.document.querySelector('.stage-sound-status').dataset.playback,'blocked');assert.match(blocked.document.querySelector('.stage-sound-status').textContent,/Browser paused sound/);assert.equal(blocked.starts(),0);
console.log('PASS enabled audio starts, mute stays muted across late resume, blocked autoplay is explicit, and consent is account-scoped');
