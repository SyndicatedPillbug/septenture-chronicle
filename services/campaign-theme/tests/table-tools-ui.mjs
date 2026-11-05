import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import vm from 'node:vm'
import {parseHTML} from '../../nova-writing/editor/node_modules/linkedom/esm/index.js'
const {document,window}=parseHTML('<html><body><button id="opener">Sheets</button></body></html>')
window.HTMLElement.prototype.showModal=function(){this.open=true}
window.HTMLElement.prototype.close=function(){this.open=false;this.dispatchEvent(new window.Event('close'))}
let conflict=false,shared=0,rollCount=0
let sheet={character_id:1,name:'Fixture Hero',revision:1,updated_at:1,can_edit:true,document:{notes:'Original notes',manual_sheet:{version:1,sections:[{id:'identity',title:'Identity',fields:[{id:'ac',label:'AC',value:'12'}],prose:{source:'Original prose',format:'plain'}}]}}}
const roll={id:'a'.repeat(32),result:{expression:'1d20',mode:'normal',total:17,terms:[{rolls:[17]}]}}
const context={document,window,crypto,structuredClone,TextEncoder,URL,location:{origin:'https://fixture.test'},confirm:()=>true,alert:()=>{},fetch:async(url,options)=>{let value,status=200;const data=options.body?JSON.parse(options.body):null
 if(url.endsWith('/bootstrap'))value={sheets:[structuredClone(sheet)]}
 else if(url.endsWith('/manual')){if(conflict){status=409;value={error:'Sheet changed. Your entries are kept.'}}else{sheet.document.manual_sheet=structuredClone(data.manual_sheet);sheet.revision++;value={sheet:structuredClone(sheet)}}}
 else if(url.endsWith('/notes')){sheet.document.notes=data.notes;sheet.revision++;value={sheet:structuredClone(sheet)}}
 else if(url.endsWith('/share-roll')){shared++;value={ok:true}}
 else if(url.endsWith('/rolls')){if(data){rollCount++;status=201;value={roll}}else value={rolls:[]}}
 else throw Error('Unexpected fixture request '+url)
 return {ok:status<400,status,json:async()=>value}
}}
for(const name of ['prose-editor.js','manual-sheet.js','table-tools.js'])vm.runInNewContext(readFileSync(new URL('../table-overlay/public/js/'+name,import.meta.url),'utf8'),context)
const tick=()=>new Promise(resolve=>setImmediate(resolve));const settle=async()=>{for(let i=0;i<5;i++)await tick()}
window.SeptentureTableTools.open('sheets',document.querySelector('#opener'));await settle()
const click=label=>[...document.querySelectorAll('button')].find(b=>b.textContent===label).click()
assert.match(document.querySelector('.manual-value').textContent,/12/);assert.equal(document.querySelectorAll('.manual-fields input').length,0);click('Edit character sheet')
const field=document.querySelectorAll('.manual-fields input')[0];assert.ok(field);field.value='17';field.dispatchEvent(new window.Event('input'))
const notes=document.querySelector('textarea[aria-label="Original private notes"]');notes.value='Unsaved private notes';notes.dispatchEvent(new window.Event('input'))
click('Close tools');window.SeptentureTableTools.open('sheets');assert.equal(document.querySelectorAll('.manual-fields input')[0].value,'17');assert.equal(notes.value,'Unsaved private notes')
conflict=true;click('Save character sheet');await settle();assert.equal(field.value,'17');assert.equal(notes.value,'Unsaved private notes');assert.match(document.querySelector('dialog [role=status]').textContent,/kept/)
conflict=false;click('Save character sheet');await settle();assert.equal(sheet.document.manual_sheet.sections[0].fields[0].value,'17');assert.equal(sheet.document.notes,'Unsaved private notes');assert.equal(sheet.revision,3)
click('Close tools');window.SeptentureTableTools.open('dice');await settle();assert.equal(shared,0)
const form=document.querySelector('dialog form');form.dispatchEvent(new window.Event('submit',{cancelable:true}));await settle();assert.equal(rollCount,1);assert.equal(shared,0);assert.match(document.querySelector('dialog ol').textContent,/17/)
click('Share with Table');await settle();assert.equal(shared,1)
console.log('PASS embedded sheets retain edits on close and conflict; section and notes saves use canonical revisions; dice remain private until deliberate sharing')
const host=document.createElement('div');document.body.append(host);const blank={version:1,sections:[]};let changes=0
const mounted=window.SeptentureManual.mount({container:host,sheet:{...sheet,name:'Blank Hero'},draft:blank,request:async()=>({html:''}),changed:()=>changes++,save:()=>{},isDirty:()=>true})
assert.equal(blank.sections.length,0);assert.match(host.textContent,/No character details/)
;[...host.querySelectorAll('button')].find(b=>b.textContent==='Start a blank character sheet').click()
assert.equal(blank.sections.length,8);assert.equal(changes,1);assert.equal(blank.sections[0].fields[0].value,'Blank Hero')
const fields=blank.sections.flatMap(s=>s.fields);assert.ok(fields.some(f=>f.label==='Armor class'));assert.ok(fields.some(f=>f.label==='Wisdom save'));assert.ok(fields.some(f=>f.label==='Spell slots / uses'));assert.equal(new Set([...blank.sections,...fields].map(x=>x.id)).size,blank.sections.length+fields.length)
assert.ok(fields.filter(f=>f.label!=='Character name').every(f=>f.value===''));assert.equal(host.querySelectorAll('.manual-fields input').length,fields.length)
;[...host.querySelectorAll('button')].find(b=>b.textContent==='Read character sheet').click();assert.equal(host.querySelectorAll('.manual-fields input').length,0);assert.match(host.textContent,/Blank Hero/);mounted.destroy()
console.log('PASS blank sheets are explicit unsaved templates; all standard manual fields present; no invented statistics; readable view hides editing controls')

const importHost=document.createElement('div');document.body.append(importHost);const importDraft={version:1,sections:[]};let importChanges=0
window.SeptentureManual.mount({container:importHost,sheet,draft:importDraft,request:async()=>({html:''}),changed:()=>importChanges++,save:()=>{},isDirty:()=>true})
const importFile=importHost.querySelector('input[type=file]');let payload={character_id:99,manual_sheet:sheet.document.manual_sheet};importFile.files=[{size:1000,text:async()=>JSON.stringify(payload)}]
importFile.dispatchEvent(new window.Event('change'));await settle();assert.equal(importDraft.sections.length,0);assert.match(importHost.textContent,/different character/)
payload={character_id:sheet.character_id,manual_sheet:sheet.document.manual_sheet};importFile.dispatchEvent(new window.Event('change'));await settle();assert.equal(importChanges,1);assert.equal(importDraft.sections[0].fields[0].value,'17');assert.match(importHost.textContent,/Imported into your draft/);assert.ok([...importHost.querySelectorAll('button')].some(b=>b.textContent==='Save character sheet'))
console.log('PASS imports are character-bound, start as unsaved drafts, retain source values, and require an explicit save')
