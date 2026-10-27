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
    roster.set(p.slug,{...previous,...p,slug:p.slug,name:p.display_name.trim(),symbol:previous?.symbol||'✧',aliases:[...new Set([...(previous?.aliases||[]),previous?.name].filter(Boolean))]})
  }
  return roster
}
export function canonicalCharacter(c,roster){
  const profile=c.slug&&roster.get(c.slug)
  return profile?{...c,...profile,id:c.id,name:profile.name,aliases:[...new Set([...(c.aliases||[]),c.name,...profile.aliases])]}:{...c}
}
export function characterOptions(groups,roster){
  const people=new Map(roster)
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
    url.searchParams.set('select','slug,display_name,tagline,heading,bio,mood,location,interests,theme,accent,music_title,portrait_path,background_path,music_path,character_details,updated_at')
    url.searchParams.set('order','slug')
    url.searchParams.set('campaign_id','eq.'+(config.campaignId||'septenture'))
    url.searchParams.set('limit','200')
    const profiles=[]
    for(let page=0;page<100;page++){
      url.searchParams.set('offset',String(page*200))
      const response=await fetch(url,{headers:{apikey:config.publishableKey},credentials:'omit',cache:'no-store',signal:AbortSignal.timeout(10000)})
      if(!response.ok)throw new Error('Character profiles unavailable')
      const batch=await response.json()
      if(!Array.isArray(batch))throw new Error('Invalid character roster')
      profiles.push(...batch)
      if(batch.length<200)break
      if(page===99)throw new Error('Character roster is too large')
    }
    latest=profiles.map(p=>decorateProfile(p,config));loadedAt=Date.now();return latest
  })().finally(()=>{pending=null})
  return pending
}
const SITE=new URL('../',import.meta.url).href
const detailNames=['gender','species','age','height','weight','hair_color','eye_color','physical_desc','personality','strengths','ambitions','hobbies','spouse','children','father','mother','brothers','sisters','other_family','service_record']
const themes=['moon','candle','water','hive','rose','watch','powder']
export function profileHref(slug,base=SITE){
  return new URL(CAST.some(c=>c.slug===slug)?`the-cast/${slug}/`:`the-cast/character/?character=${encodeURIComponent(slug)}`,base).href
}
export function mediaUrl(profile,key,config){
  const path=profile[key]
  if(typeof path!=='string'||!path.startsWith(profile.slug+'/')||path.split('/').some(s=>!s||s==='.'||s==='..')||!new RegExp(key==='music_path'?'\\.(mp3|ogg|wav|m4a|aac)$':'\\.(jpg|jpeg|png|webp)$','i').test(path))return null
  const url=new URL('/storage/v1/object/public/character-media/'+path.split('/').map(encodeURIComponent).join('/'),config.url)
  if(url.protocol!=='https:'&&!['localhost','127.0.0.1'].includes(url.hostname))return null
  if(profile.updated_at)url.searchParams.set('v',profile.updated_at)
  return url.href
}
export function decorateProfile(p,config){return {...p,portrait_url:mediaUrl(p,'portrait_path',config),background_url:mediaUrl(p,'background_path',config),music_url:mediaUrl(p,'music_path',config)}}
export function portraitUrl(p,base=SITE){return p.portrait_url||new URL('static/companions/'+(CAST.some(c=>c.slug===p.slug)?p.slug:'default')+'.svg',base).href}
export function setPortrait(img,p){
  const url=portraitUrl(p)
  img.alt=(p.name||p.display_name||'Character')+'’s portrait'
  if(img.dataset.profileImage===url)return
  img.dataset.profileImage=url
  img.onerror=()=>{img.onerror=null;img.src=portraitUrl({...p,portrait_url:null})}
  img.src=url;img.alt=(p.name||p.display_name||'Character')+'’s portrait'
}
const defaults=new WeakMap()
export function applyProfile(root,p){
  const name=p.display_name||p.name
  const set=(selector,value)=>{const e=root.querySelector(selector);if(e&&value!==undefined)e.textContent=value??''}
  if(!defaults.has(root)){
    const audio=root.querySelector('[data-pc-audio]')
    defaults.set(root,{background:root.querySelector('.pc-banner')?.style.backgroundImage||'',audio:audio?.getAttribute('src')||'',title:root.querySelector('[data-track-title]')?.textContent||'',credit:root.querySelector('[data-track-credit]')?.textContent||'',music:undefined})
  }
  const original=defaults.get(root)
  set('.pc-identity h1',name);set('.pc-banner .pc-eyebrow',p.tagline);set('.pc-banner-title',p.heading)
  set('.pc-status',p.mood===undefined?undefined:'✦ '+p.mood);set('.pc-facts dd',p.location)
  set('.pc-main .pc-panel h2','ABOUT '+name.toUpperCase());set('.pc-marquee span','✧ welcome to '+name+'’s homepage ✧')
  const bio=root.querySelectorAll('.pc-main .pc-panel-body > p')[1];if(bio&&p.bio!==undefined)bio.textContent=p.bio
  const interests=root.querySelector('.pc-likes')
  if(interests&&p.interests!==undefined){interests.replaceChildren();for(const text of String(p.interests||'').split('\n').filter(Boolean)){const li=document.createElement('li');li.textContent=text;interests.append(li)}}
  if(p.character_details){
    let panel=root.querySelector('[data-shared-character-details]')
    const about=root.querySelector('.pc-main .pc-panel')
    if(!panel&&about){panel=document.createElement('section');panel.className='pc-panel';panel.dataset.sharedCharacterDetails='';about.after(panel)}
    if(panel){panel.replaceChildren();const dl=document.createElement('dl');for(const key of detailNames){const value=p.character_details[key];if(typeof value==='string'&&value.trim()){const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=key.replaceAll('_',' ');dd.textContent=value;dl.append(dt,dd)}}panel.hidden=!dl.children.length;if(dl.children.length){const h=document.createElement('h2');h.textContent='Character details';panel.append(h,dl)}}
  }
  if(p.theme)for(const theme of themes)root.classList.toggle('pc-'+theme,theme===p.theme)
  if(p.accent!==undefined){if(/^#[0-9a-f]{6}$/i.test(p.accent||''))root.style.setProperty('--pc-accent',p.accent);else root.style.removeProperty('--pc-accent')}
  const portrait=root.querySelector('.pc-identity img');if(portrait)setPortrait(portrait,p)
  const banner=root.querySelector('.pc-banner');if(banner&&p.background_path!==undefined)banner.style.backgroundImage=p.background_url?`linear-gradient(#0006,#0006),url("${p.background_url}")`:original.background
  const music=p.music_url||null,audio=root.querySelector('[data-pc-audio]')
  if(audio&&p.music_path!==undefined&&(original.music!==music||original.musicTitle!==p.music_title)){
    if(original.music!==music){audio.pause();audio.src=music||original.audio}
    set('[data-track-title]',music?(p.music_title||'The player’s chosen track'):original.title)
    set('[data-track-credit]',music?'Player soundtrack · shared with all visitors':original.credit)
    root.querySelector('[data-play]')?.setAttribute('aria-label','Play '+(music?(p.music_title||'the player’s chosen track'):original.title))
    original.music=music;original.musicTitle=p.music_title
  }
  document.title=name+' — The Cast'
}
function syncDirectory(scope,profiles){
  const grid=scope.querySelector('.pc-directory-grid');if(!grid)return
  const roster=characterRoster(profiles)
  for(const p of roster.values()){
    let card=[...grid.querySelectorAll('a')].find(a=>a.dataset.characterSlug===p.slug||new URL(a.getAttribute('href'),location.href).pathname.endsWith('/'+p.slug+'/'))
    if(!card){card=document.createElement('a');card.className='pc-directory-card';const img=document.createElement('img');img.width=240;img.height=240;img.loading='lazy';const name=document.createElement('strong');name.className='pc-directory-name';const note=document.createElement('p');note.dataset.profileTagline='';const action=document.createElement('b');action.textContent='Visit homepage ↗';card.append(img,name,note,action);grid.append(card)}
    card.dataset.characterSlug=p.slug;card.href=profileHref(p.slug);card.querySelector('.pc-directory-name').textContent=p.name
    const img=card.querySelector('img');if(img)setPortrait(img,p)
    let note=card.querySelector('[data-profile-tagline]');if(!note){note=card.querySelector('p');if(note)note.dataset.profileTagline=''}
    if(note&&p.tagline!==undefined)note.textContent=p.tagline||p.heading||''
    for(const theme of themes)card.classList.toggle('pc-'+theme,theme===(p.theme||'moon'))
    if(/^#[0-9a-f]{6}$/i.test(p.accent||''))card.style.setProperty('--pc-accent',p.accent);else card.style.removeProperty('--pc-accent')
  }
}
function syncFriends(scope,profiles){
  const roster=characterRoster(profiles)
  for(const list of scope.querySelectorAll('.pc-friends')){
    const current=list.closest('[data-pc]')?.dataset.pc
    for(const p of roster.values()){
      if(p.slug===current)continue
      let link=[...list.querySelectorAll('a')].find(a=>a.dataset.characterSlug===p.slug||new URL(a.getAttribute('href'),location.href).pathname.endsWith('/'+p.slug+'/'))
      if(!link){link=document.createElement('a');const img=document.createElement('img');img.width=40;img.height=40;img.loading='lazy';const label=document.createElement('span');link.append(img,label);list.append(link)}
      link.dataset.characterSlug=p.slug;link.href=profileHref(p.slug)
      const label=link.querySelector('span:not([aria-hidden])');if(label)label.textContent=p.name
      let img=link.querySelector('img');if(!img){img=document.createElement('img');img.width=40;img.height=40;link.prepend(img)}setPortrait(img,p)
    }
  }
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
    if(label){const img=link.querySelector('img');if(img){img.alt=person.name+'’s portrait';setPortrait(img,person)}}
  }
  syncDirectory(scope,profiles)
  syncFriends(scope,profiles)
  const root=scope.querySelector('[data-pc]'),person=root&&roster.get(root.dataset.pc)
  if(person)applyProfile(root,person)
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
    const p=event.detail;if(p?.slug&&p.display_name){latest=[...latest.filter(c=>c.slug!==p.slug),p];loadedAt=0}
    refresh()
  })
  setInterval(()=>{if(!document.hidden)refresh()},60000)
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',refresh,{once:true});else refresh()
}
