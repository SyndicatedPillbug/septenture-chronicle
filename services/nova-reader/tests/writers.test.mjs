import assert from 'node:assert/strict'
import {groupStories,selectStories,writerName} from '../../../quartz/static/fiction-reader.js'
const base={id:1,title:'Test',displayTitle:'Test',part:1,parts:1,characters:[{id:1,name:'Page',slug:'page'}],tags:[],words:20,written:1,published:1,updated:1,search:'Page greets Ceridwen.',excerpt:'Page greets Ceridwen.',timeline:'',location:'',kind:'Fiction',group:'test'}
const rows=[{...base,writers:['Cendreveil / Finn','Simon','Odin']},{...base,id:2,part:2,writers:['Brendon','GM DM',"Lucas' Legion",'Ceridwen Glaive','Page']}]
const [group]=groupStories(rows)
assert.deepEqual(group.writers,['Brendon','Odin','Dan','Lucas','Kim','Chelsea'])
assert.equal(group.characters[0].name,'Page')
assert.equal(group.chapters[0].search,'Page greets Ceridwen.')
assert.equal(selectStories([group],{writer:'Brendon'}).length,1)
assert.equal(selectStories([group],{writer:'Cendreveil / Finn'}).length,1)
assert.equal(selectStories([group],{writer:'Chelsea',character:'Ceridwen'}).length,0)
for(const [alias,name] of [['Selim','Brendon'],['Cendraveil','Brendon'],['Game Master','Dan'],['Hervd Angantyr-Bohun','Lucas'],['Odin','Odin'],['Unknown guest','Unknown guest']])assert.equal(writerName(alias),name)
console.log('PASS player names, multi-character deduplication, preserved original GM credit, legacy writer filters, and unchanged character/prose fields')
