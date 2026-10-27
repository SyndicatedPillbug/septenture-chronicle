import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import {runInNewContext} from 'node:vm'
const source=readFileSync(new URL('../profile-live.js',import.meta.url),'utf8')
function run(readyState,initialHeader){
  let header=initialHeader?{dataset:{}}:null,listener,observer,disconnected=false
  const document={readyState,body:{},querySelector(selector){return selector==='.character-identity'?header:selector==='#septenture-character'?{textContent:'invalid fixture JSON'}:selector==='meta[name="septenture-site"]'?{content:'https://site.example.test/'}:null},addEventListener(event,fn){if(event==='DOMContentLoaded')listener=fn}}
  runInNewContext(source,{document,setTimeout(){},MutationObserver:class{constructor(fn){observer=fn}observe(){}disconnect(){disconnected=true}}})
  return {get header(){return header},start(){listener?.()},render(){header={dataset:{}};observer?.()},get disconnected(){return disconnected}}
}
const late=run('interactive',true);assert.equal(late.header.dataset.sharedProfileSync,'connecting','A late deferred script must initialize after DOMContentLoaded')
const delayed=run('complete',false);delayed.render();assert.equal(delayed.header.dataset.sharedProfileSync,'connecting','Nova can add its header after the script starts');assert.equal(delayed.disconnected,true)
const loading=run('loading',true);assert.equal(loading.header.dataset.sharedProfileSync,undefined);loading.start();assert.equal(loading.header.dataset.sharedProfileSync,'connecting')
console.log('PASS Nova startup before, during and after page rendering')
