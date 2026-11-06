import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, cp, readFile, writeFile, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { audit } from './session-audit.mjs'
const root = fileURLToPath(new URL('../',import.meta.url))
const catalog = JSON.parse(await readFile(resolve(root,'maintenance/surfaces.json')))
const session = JSON.parse(await readFile(resolve(root,`maintenance/sessions/${catalog.currentSession}.json`)))
async function fixture(t) {
  const dir = await mkdtemp(resolve(tmpdir(),'session-audit-'))
  await cp(resolve(root,'content'),resolve(dir,'content'),{recursive:true})
  t.after(()=>rm(dir,{recursive:true,force:true}))
  return dir
}
async function replace(root,path,from,to) {
  const p = resolve(root,'content',path)
  await writeFile(p,(await readFile(p,'utf8')).replaceAll(from,to))
}
test('current content passes consistency checks; unverified human checks remain explicit',async()=>{
  const result = await audit(root,catalog,session)
  assert.deepEqual(result.errors,[])
  const pending = structuredClone(session); pending.browserChecks['home-latest']='pending'
  assert.ok((await audit(root,catalog,pending)).pendingBrowserChecks.includes('home-latest'))
})
test('detects stale homepage, broken reading order and missing character reference',async t=>{
  const dir=await fixture(t)
  await replace(dir,'index.md',`href="${session.record}"`,'href="old-session"')
  await replace(dir,`${session.previous}.md`,'class="nav-next"','class="broken"')
  await replace(dir,'the-cast/source-notes.md',session.record,'the-chronicle/old-record')
  for(const character of session.affectedCharacters) await replace(dir,`the-cast/${character}/index.md`,session.record,'the-chronicle/old-record')
  const result=await audit(dir,catalog,session)
  assert.ok(result.errors.some(e=>e.includes('latest-session')))
  assert.ok(result.errors.some(e=>e.includes('link forward')))
  assert.ok(result.errors.some(e=>e.includes('No session reference')))
})
test('rejects omitted review evidence and unplayed source material',async()=>{
  const draft=structuredClone(session)
  draft.sources[0].kind='gm-prep'; draft.reviews.mail.evidence=''
  const result=await audit(root,catalog,draft)
  assert.ok(result.errors.some(e=>e.includes('played records')))
  assert.ok(result.errors.some(e=>e.includes('Notification intent')))
})
