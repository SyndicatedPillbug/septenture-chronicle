import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import vm from 'node:vm'
let requests=0,creates=0,fail=true
const code=readFileSync(new URL('../../../quartz/static/campaign-account.js',import.meta.url),'utf8').replaceAll('export ','').replaceAll('import.meta.url',JSON.stringify('https://site.test/campaign/static/campaign-account.js')).replace('await import(SDK)','({createClient:mockCreateClient})')+'\nthis.getAccount=getAccount'
const client={auth:{}},context={URL,fetch:async()=>{requests++;return{ok:!fail,json:async()=>({url:'https://project.supabase.co',publishableKey:'public'})}},mockCreateClient:(_url,_key,options)=>{creates++;assert.equal(options.auth.flowType,'pkce');assert.equal(options.auth.autoRefreshToken,true);return client}}
vm.runInNewContext(code,context)
await assert.rejects(context.getAccount());fail=false
const [one,two]=await Promise.all([context.getAccount(),context.getAccount()])
assert.equal(one.client,client);assert.equal(one,two);assert.equal(creates,1);assert.equal(requests,2)
assert.equal(await context.getAccount(),one)
console.log('PASS one shared session client across concurrent consumers; failed configuration can retry')
