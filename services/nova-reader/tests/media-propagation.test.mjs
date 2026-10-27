import assert from 'node:assert/strict'
import {characterRoster,characterOptions,canonicalCharacter,decorateProfile,profileHref,portraitUrl} from '../../../quartz/static/character-identity.js'
const profile={slug:'future-traveler',display_name:'A Future Traveler',portrait_path:'future-traveler/abc.png',background_path:'future-traveler/banner.webp',music_path:'future-traveler/song.mp3',updated_at:'2026-10-27T20:00:00Z',bio:'Public biography',theme:'moon'}
const config={url:'https://assets.example.test'}
const p=decorateProfile(profile,config),roster=characterRoster([p]),people=characterOptions([],roster)
assert.equal(people.find(c=>c.key===profile.slug).name,profile.display_name,'A future character appears without stories or a hard-coded roster entry')
assert.ok(portraitUrl(people.find(c=>c.key===profile.slug)).includes('/future-traveler/abc.png'))
const linked=canonicalCharacter({id:99,slug:profile.slug,name:'Old Name'},roster)
assert.equal(linked.id,99);assert.equal(linked.name,profile.display_name);assert.equal(linked.portrait_url,p.portrait_url)
assert.equal(profileHref(profile.slug,'https://site.example.test/chronicle/'),'https://site.example.test/chronicle/the-cast/character/?character=future-traveler')
assert.equal(profileHref('page','https://site.example.test/chronicle/'),'https://site.example.test/chronicle/the-cast/page/')
const replacement=decorateProfile({...profile,portrait_path:'future-traveler/new.png'},config)
assert.notEqual(replacement.portrait_url,p.portrait_url)
const revision=decorateProfile({...profile,updated_at:'2026-10-28T20:00:00Z'},config)
assert.notEqual(revision.portrait_url,p.portrait_url,'Replacing an asset at the same path cannot leave a cached image')
const removed=decorateProfile({...profile,portrait_path:null,background_path:null,music_path:null},config)
assert.equal(removed.portrait_url,null);assert.equal(removed.background_url,null);assert.equal(removed.music_url,null)
assert.ok(portraitUrl(removed).endsWith('/static/companions/default.svg'))
for(const path of ['other-character/photo.png','future-traveler/../photo.png','https://evil.example/photo.png','future-traveler/file.svg','future-traveler/file.html'])assert.equal(decorateProfile({...profile,portrait_path:path},config).portrait_url,null)
assert.equal(decorateProfile(profile,{url:'http://remote.example.test'}).portrait_url,null)
console.log('PASS future roster members, canonical media, replacement, cache revisions, reset, path checks and generic homepage links')
