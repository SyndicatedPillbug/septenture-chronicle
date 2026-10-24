import assert from 'node:assert/strict'
import {groupStories,selectStories} from '../../../quartz/static/fiction-reader.js'
const base={id:1,title:'An entry',displayTitle:'An entry',part:1,parts:1,characters:[{id:1,name:'Page',slug:'page'}],writers:['Player'],tags:[],words:20,written:100,published:100,updated:100,search:'A letter at midnight',excerpt:'A letter',timeline:'',location:''}
const groups=groupStories([{...base,group:'nova-1',entryType:'story',kind:'Fiction'},{...base,group:'journal-1',entryType:'journal',kind:'Journal'}])
assert.equal(groups.length,2,'Story and journal IDs must not collide')
assert.deepEqual(selectStories(groups,{kind:'Journal',character:'Page'}).map(g=>g.key),['journal-1'])
assert.deepEqual(selectStories(groups,{kind:'Stories',character:'Page'}).map(g=>g.key),['nova-1'])
assert.equal(selectStories(groups,{kind:'Journal',character:'Ceridwen'}).length,0)
assert.equal(selectStories(groups,{kind:'Journal',query:'midnight'}).length,1)
console.log('PASS journal/story namespaces, character and shelf filters, and journal full-text search')
