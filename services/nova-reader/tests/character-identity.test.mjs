import assert from 'node:assert/strict'
import {characterRoster,characterOptions,resolveCharacter,applyCharacterNames,loadCharacterProfiles} from '../../../quartz/static/character-identity.js'
import {groupStories,selectStories} from '../../../quartz/static/fiction-reader.js'

const base={id:1,group:'story-1',displayTitle:'Letters',part:1,characters:[],writers:['Cendreveil / Finn'],tags:[],words:20,written:100,published:100,updated:100,search:'Original prose about The Gunslinger',excerpt:'Original prose',timeline:'',location:'',kind:'Fiction'}
const rows=[
  {...base,characters:[{id:7,slug:'the-gunslinger',name:'The Gunslinger'},{id:7,slug:'the-gunslinger',name:'Cendreveil'}]},
  {...base,id:2,part:2,characters:[{id:7,slug:'the-gunslinger',name:'Cendreveil'}]},
  {...base,id:3,group:'story-3',characters:[{id:5,slug:'simon',name:'Simon'}]},
  {...base,id:4,group:'story-4',characters:[{id:6,slug:'selim',name:'Selim'}]},
  {...base,id:5,group:'story-5',characters:[{id:20,slug:null,name:'A stranger'},{id:21,slug:null,name:'A stranger'}]},
]
const profiles=[{slug:'the-gunslinger',display_name:'Cendreveil'},{slug:'selim',display_name:'Selim Varek'}]
const roster=characterRoster(profiles),groups=groupStories(rows,roster),people=characterOptions(groups,roster)
assert.equal(groups[0].characters.length,1,'Same identity deduplicates across old names and chapters')
assert.equal(groups[0].characters[0].name,'Cendreveil')
assert.equal(rows[0].characters[0].name,'The Gunslinger','Do not mutate reader data or fiction')
assert.equal(people.filter(c=>c.slug==='the-gunslinger').length,1)
assert.equal(people.filter(c=>c.name==='The Gunslinger').length,0)
for(const alias of ['the-gunslinger','The Gunslinger','Gunslinger','Cendraveil','Cendreveil']){
  assert.equal(resolveCharacter(alias,people),'the-gunslinger')
  assert.deepEqual(selectStories(groups,{character:alias}).map(g=>g.key),['story-1'])
}
assert.equal(selectStories(groups,{query:'Cendreveil'}).length,1)
assert.equal(selectStories(groups,{query:'Gunslinger'}).length,4,'Historical prose remains searchable in all entries')
assert.equal(resolveCharacter('A stranger',people),'','Ambiguous name links must not silently select one NPC')
assert.equal(people.filter(c=>c.key==='nova:20'||c.key==='nova:21').length,2)
assert.equal(selectStories(groups,{character:'nova:20'}).length,1)
assert.equal(selectStories(groups,{character:'simon'}).length,1,'Same player is not the same character')
assert.equal(selectStories(groups,{character:'selim'}).length,1)
assert.equal(selectStories(groups,{writer:'Brendon'}).length,4)
const renamed=characterRoster([{slug:'the-gunslinger',display_name:'Future Name'}])
const renamedGroups=groupStories(rows,renamed),renamedPeople=characterOptions(renamedGroups,renamed)
assert.equal(renamedPeople.find(c=>c.key==='the-gunslinger').name,'Future Name')
assert.equal(resolveCharacter('the-gunslinger',renamedPeople),'the-gunslinger','Saved filter survives later rename')
assert.equal(resolveCharacter('Cendreveil',renamedPeople),'the-gunslinger')
assert.equal(selectStories(renamedGroups,{character:'the-gunslinger'}).length,1)

let calls=[]
globalThis.fetch=async(url,options)=>{
  calls.push({url:String(url),options})
  return {ok:true,json:async()=>String(url).endsWith('community-config.json')?{url:'https://example.test',publishableKey:'public-test-key',campaignId:'test-campaign'}:profiles}
}
assert.deepEqual(await loadCharacterProfiles(),profiles)
const query=new URL(calls[1].url)
assert.equal(query.searchParams.get('select'),'slug,display_name','Only public identity fields are fetched')
assert.equal(query.searchParams.get('campaign_id'),'eq.test-campaign')
assert.deepEqual(calls[1].options.headers,{apikey:'public-test-key'},'Public names never require a player token')
assert.deepEqual(await loadCharacterProfiles(),profiles)
assert.equal(calls.length,2,'Concurrent consumers share a short-lived public roster cache')
console.log('PASS stable identities, rename propagation, aliases, collisions, player separation, preserved prose and campaign-scoped public roster')
