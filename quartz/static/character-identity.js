// A display name can change; the profile slug and Nova character ID cannot.
export const CAST=[
  ['page','Page','✧'],['ceridwen','Ceridwen','☽'],['coy','Coy','♧'],
  ['legion','Legion','❋'],['simon','Simon','⚔'],['selim','Selim Varek','◇'],
  ['the-gunslinger','Cendreveil','✦'],
].map(([slug,name,symbol])=>({slug,name,symbol,aliases:slug==='the-gunslinger'?['The Gunslinger','Gunslinger','Cendraveil','Cendreveil']:slug==='selim'?['Selim']:[]}))
const fold=value=>String(value||'').normalize('NFKD').toLocaleLowerCase().trim()
export const characterKey=c=>c.slug||`nova:${c.id}`
export function characterRoster(profiles=[]){
  const roster=new Map(CAST.map(c=>[c.slug,{...c}]))
  for(const p of profiles){
    if(!p.slug||!p.display_name?.trim())continue
    const previous=roster.get(p.slug)
    roster.set(p.slug,{...previous,slug:p.slug,name:p.display_name.trim(),symbol:previous?.symbol||'✧',aliases:[...new Set([...(previous?.aliases||[]),previous?.name].filter(Boolean))]})
  }
  return roster
}
export function canonicalCharacter(c,roster){
  const profile=c.slug&&roster.get(c.slug)
  return profile?{...c,name:profile.name,aliases:[...new Set([...(c.aliases||[]),c.name,...profile.aliases])]}:{...c}
}
export function characterOptions(groups,roster){
  const people=new Map(CAST.map(c=>[c.slug,roster.get(c.slug)||c]))
  for(const c of groups.flatMap(g=>g.characters))people.set(characterKey(c),{...people.get(characterKey(c)),...c})
  return [...people].map(([key,c])=>({...c,key})).sort((a,b)=>a.name.localeCompare(b.name))
}
export function resolveCharacter(value,people){
  if(value==='world')return 'world'
  const exact=people.find(c=>c.key===value)
  if(exact)return exact.key
  const matches=people.filter(c=>[c.name,...(c.aliases||[])].some(name=>fold(name)===fold(value)))
  return matches.length===1?matches[0].key:''
}
let pending,latest=[],loadedAt=0
export async function loadCharacterProfiles(){
  if(Date.now()-loadedAt<5000)return latest
  if(pending)return pending
  pending=(async()=>{
    const configResponse=await fetch(new URL('community-config.json',import.meta.url),{cache:'no-store',signal:AbortSignal.timeout(10000)})
    if(!configResponse.ok)throw new Error('Character configuration unavailable')
    const config=await configResponse.json()
    const url=new URL('/rest/v1/character_profiles',config.url)
    url.searchParams.set('select','slug,display_name')
    url.searchParams.set('campaign_id','eq.'+(config.campaignId||'septenture'))
    const response=await fetch(url,{headers:{apikey:config.publishableKey},credentials:'omit',cache:'no-store',signal:AbortSignal.timeout(10000)})
    if(!response.ok)throw new Error('Character names unavailable')
    const profiles=await response.json()
    if(!Array.isArray(profiles))throw new Error('Invalid character roster')
    latest=profiles;loadedAt=Date.now();return latest
  })().finally(()=>{pending=null})
  return pending
}
// Only update identity labels and cast navigation, never names inside fiction.
export function applyCharacterNames(scope,profiles){
  const roster=characterRoster(profiles)
  for(const link of scope.querySelectorAll('a[href]')){
    let url;try{url=new URL(link.getAttribute('href'),location.href)}catch{continue}
    if(url.origin!==location.origin)continue
    const match=url.pathname.match(/\/the-cast\/([^/]+)\/?$/)
    const person=match&&roster.get(match[1]);if(!person)continue
    const label=link.querySelector('.pc-directory-name')||(link.closest('.pc-friends')?link.querySelector('span:not([aria-hidden])'):null)
    if(label)label.textContent=person.name
    else if(link.children.length===0&&[person.name,...person.aliases].some(n=>fold(n)===fold(link.textContent)))link.textContent=person.name
    if(label){const img=link.querySelector('img');if(img)img.alt=person.name+'’s illustrated emblem'}
  }
  const root=scope.querySelector('[data-pc]'),person=root&&roster.get(root.dataset.pc)
  if(!person)return
  const heading=root.querySelector('.pc-identity h1');if(heading)heading.textContent=person.name
  const portrait=root.querySelector('.pc-identity img');if(portrait)portrait.alt=person.name+'’s illustrated emblem'
  const marquee=root.querySelector('.pc-marquee span')||root.querySelector('.pc-marquee');if(marquee)marquee.textContent='✧ welcome to '+person.name+'’s homepage ✧'
  const about=root.querySelector('.pc-main .pc-panel h2');if(about)about.textContent='ABOUT '+person.name.toUpperCase()
  document.title=person.name+' — The Cast'
}
if(typeof document!=='undefined'){
  let generation=0
  async function refresh(){
    const current=++generation
    applyCharacterNames(document,latest)
    try{const profiles=await loadCharacterProfiles();if(current===generation)applyCharacterNames(document,profiles)}catch{/* Keep usable static or last-known labels when offline. */}
  }
  document.addEventListener('nav',refresh)
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh()})
  window.addEventListener('septenture:profile-updated',event=>{
    const p=event.detail;if(p?.slug&&p.display_name){latest=[...latest.filter(c=>c.slug!==p.slug),{slug:p.slug,display_name:p.display_name}];loadedAt=0}
    refresh()
  })
  setInterval(()=>{if(!document.hidden)refresh()},60000)
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',refresh,{once:true});else refresh()
}
