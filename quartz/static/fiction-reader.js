const {CAST,characterKey,characterRoster,canonicalCharacter,characterOptions,resolveCharacter,loadCharacterProfiles,portraitUrl}=await import(new URL('./character-identity.js'+new URL(import.meta.url).search,import.meta.url).href)
const SDK='https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm'
const fold=value=>String(value||'').normalize('NFKD').toLocaleLowerCase()
const WRITER_ALIASES=new Map([
  ['Kim',['Kim','Ceridwen','Ceridwen Glaive']],
  ['Chelsea',['Chelsea','Page']],
  ['Brendon',['Brendon','Cendraveil','Cendreveil','Cendreveil / Finn','Cendraveil / Finn','Finn','Simon','Lord Simon Devereux','Selim','The Gunslinger']],
  ['Dan',['Dan','Coy','Coy Qualdirriar','GM','GM DM','Game Master']],
  ['Lucas',['Lucas',"Lucas' Legion",'Legion','Hervd','Hervd Angantyr-Bohun']],
].flatMap(([player,aliases])=>aliases.map(alias=>[fold(alias).trim(),player])))
export const writerName=value=>WRITER_ALIASES.get(fold(value).trim())||String(value||'').trim()
export function groupStories(rows,roster=characterRoster()){
  const grouped=new Map()
  for(const original of rows){
    const row={...original,characters:original.characters.map(c=>canonicalCharacter(c,roster))}
    const key=String(row.group);let group=grouped.get(key)
    if(!group){group={...row,key,title:row.displayTitle,chapters:[],characters:[],writers:[],tags:[],words:0,written:row.written,search:''};grouped.set(key,group)}
    group.chapters.push(row);group.words+=Number(row.words)||0;group.written=Math.min(group.written,row.written);group.updated=Math.max(group.updated,row.updated,row.published)
    for(const c of row.characters)if(!group.characters.some(x=>characterKey(x)===characterKey(c)))group.characters.push(c)
    for(const alias of row.writers){const w=writerName(alias);if(w&&!group.writers.includes(w))group.writers.push(w)}
    for(const t of row.tags)if(!group.tags.includes(t))group.tags.push(t)
    group.search+=' '+row.search
  }
  return [...grouped.values()].map(g=>{const chapters=g.chapters.sort((a,b)=>a.part-b.part);return {...g,chapters,excerpt:chapters[0].excerpt,timeline:chapters[0].timeline,location:chapters[0].location,search:fold([g.title,g.timeline,g.location,...g.writers,...g.characters.flatMap(c=>[c.name,...(c.aliases||[])]),...g.tags,g.search].join(' '))}})
}
export function selectStories(groups,{query='',character='',writer='',kind='',sort='new'}={}){
  const terms=fold(query).trim().split(/\s+/).filter(Boolean)
  const stableKey=CAST.some(c=>c.slug===character)||character.startsWith('nova:')||groups.some(g=>g.characters.some(c=>characterKey(c)===character))
  const matchesCharacter=c=>stableKey?characterKey(c)===character:[c.name,...(c.aliases||[])].some(name=>fold(name)===fold(character))
  const found=groups.filter(g=>(!character||(character==='world'?g.characters.length===0:g.characters.some(matchesCharacter)))&&(!writer||g.writers.includes(writerName(writer)))&&(!kind||(kind==='Stories'?g.kind!=='Journal':g.kind===kind))&&terms.every(t=>g.search.includes(t)))
  found.sort((a,b)=>sort==='old'?a.written-b.written:sort==='short'?a.words-b.words:sort==='long'?b.words-a.words:sort==='az'?a.title.localeCompare(b.title):b.written-a.written||b.id-a.id)
  return found
}
// Never insert Nova HTML directly into the live document. Rebuild a small safe
// subset, with URL checks and no event handlers, styles, embeds or executable nodes.
export function safeStory(html){
  const source=new DOMParser().parseFromString(html,'text/html'),fragment=document.createDocumentFragment()
  const allowed=new Set(['P','BR','HR','EM','I','STRONG','B','U','S','BLOCKQUOTE','UL','OL','LI','CODE','PRE','H2','H3','H4','A','SPAN','DIV'])
  const forbidden=new Set(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','MATH','FORM','INPUT','BUTTON','TEXTAREA','SELECT','TEMPLATE','LINK','META','IMG','AUDIO','VIDEO','SOURCE'])
  function copy(node,parent){
    if(node.nodeType===Node.TEXT_NODE){const text=node.textContent.replace(/\r\n?/g,'\n');if(!text.trim()){parent.append(document.createTextNode(' '));return}const lines=text.split('\n');lines.forEach((line,i)=>{if(i)parent.append(document.createElement('br'));parent.append(document.createTextNode(line))});return}
    if(node.nodeType!==Node.ELEMENT_NODE||forbidden.has(node.tagName))return
    if(!allowed.has(node.tagName)){for(const child of node.childNodes)copy(child,parent);return}
    const element=document.createElement(node.tagName.toLowerCase())
    if(node.tagName==='A'){
      try{const url=new URL(node.getAttribute('href')||'',location.origin);if(!['https:','http:'].includes(url.protocol))throw Error();element.href=url.href;element.target='_blank';element.rel='noopener noreferrer';element.referrerPolicy='no-referrer'}catch{element.removeAttribute('href')}
    }
    for(const child of node.childNodes)copy(child,element)
    if(node.tagName==='STRONG'&&/ · \d{4}-\d{2}-\d{2}/.test(element.textContent)){
      element.className='fx-credit'
      element.textContent=element.textContent.replace(/^(.*?)((?: · )\d{4}-\d{2}-\d{2}.*)$/s,(_,name,date)=>writerName(name)+date)
    }
    if(node.tagName==='A'&&element.textContent.trim()==='source')element.className='fx-source-link'
    parent.append(element)
  }
  for(const node of source.body.childNodes)copy(node,fragment)
  if(fragment.firstElementChild?.tagName==='STRONG'&&fragment.firstElementChild.textContent==='Discord archive'&&[...fragment.querySelectorAll('a')].some(a=>/^Original (thread|channel)$/.test(a.textContent))){while(fragment.firstChild){const node=fragment.firstChild,done=node.nodeName==='HR';node.remove();if(done)break}}
  return formatStoryParagraphs(fragment)
}
// Imported Discord prose uses single line breaks where an editor would use
// paragraphs. Give those lines semantic paragraphs without changing the text,
// inline emphasis, author credits, lists, or preformatted passages.
export function formatStoryParagraphs(container){
  const blocks=new Set(['P','DIV','BLOCKQUOTE','UL','OL','LI','PRE','H2','H3','H4','HR'])
  const output=document.createDocumentFragment();let paragraph=null
  function flush(){
    if(paragraph?.textContent.trim()){
      const prose=[...paragraph.childNodes].some(n=>n.nodeType===Node.TEXT_NODE?n.textContent.trim():!n.classList.contains('fx-credit')&&!n.classList.contains('fx-source-link'))
      paragraph.className=prose?'fx-prose':'fx-credit-line';output.append(paragraph)
    }
    paragraph=null
  }
  for(const node of [...container.childNodes]){
    if(node.nodeName==='BR'){flush();continue}
    if(blocks.has(node.nodeName)){
      flush()
      if(node.nodeName==='P')output.append(formatStoryParagraphs(node))
      else{if(['DIV','BLOCKQUOTE'].includes(node.nodeName))node.replaceChildren(formatStoryParagraphs(node));output.append(node)}
    }else{if(!paragraph)paragraph=document.createElement('p');paragraph.append(node)}
  }
  flush();return output
}
const el=(tag,text,className)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(className)node.className=className;return node}
export async function mount(root,base){
  if(root.dataset.fxMounted)return
  root.dataset.fxMounted='true'
  const initialFilters=new URLSearchParams(location.search);let filtersInitialized=false
  const $=name=>root.querySelector('[data-fx-'+name+']')
  let active=true,client,config,groups=[],visible=[],current=null,controller=null,refreshing=false,poll=null,readerRequest=null,readerSize=19,roster=characterRoster(),people=[]
  const gate=$('gate'),library=$('library'),status=$('status'),login=$('login'),panel=$('reader-panel'),story=$('story')
  const handlers=[],listen=(target,event,fn)=>{target.addEventListener(event,fn);handlers.push(()=>target.removeEventListener(event,fn))}
  function setBackgroundInert(value){for(const child of root.children)if(child!==panel)child.inert=value}
  function closeReader(updateHash=true){setBackgroundInert(false);current=null;readerRequest?.abort();panel.hidden=true;document.body.style.overflow='';if(updateHash&&location.hash.startsWith('#story='))history.replaceState(history.state,'',location.pathname+location.search);$('search').focus({preventScroll:true})}
  function clearPrivate(message){setBackgroundInert(false);groups=[];visible=[];current=null;readerRequest?.abort();controller?.abort();$('cards').replaceChildren();$('friends').replaceChildren();story.replaceChildren();panel.hidden=true;document.body.style.overflow='';library.hidden=true;gate.hidden=false;login.hidden=false;$('compose').hidden=true;status.textContent=message;$('live').textContent='PLAYER LOGIN REQUIRED'}
  async function request(payload,signal){
    const {data,error}=await client.auth.getSession()
    if(error||!data.session){const e=new Error('Sign in to open the shelves.');e.status=401;throw e}
    const response=await fetch(config.readerUrl||new URL('/index.php/reader/stories',config.novaUrl).href,{method:'POST',credentials:'omit',cache:'no-store',referrerPolicy:'no-referrer',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload,token:data.session.access_token}),signal})
    let body;try{body=await response.json()}catch{throw new Error('The reading shelves are temporarily unavailable. Please try again.')}
    if(!response.ok){const e=new Error(body.error||'The entry could not be opened.');e.status=response.status;throw e}
    return body
  }
  function fillOptions(select,values,all){const previous=select.value;select.replaceChildren();const first=el('option',all);first.value='';select.append(first);for(const value of values){const option=el('option',value);option.value=value;select.append(option)}select.value=values.includes(previous)?previous:''}
  function renderFriends(){
    $('friends').replaceChildren()
    for(const person of people){
      const {name,slug,key}=person,button=el('button',undefined,'fx-friend');button.type='button';button.setAttribute('aria-label','Filter by '+name);button.setAttribute('aria-pressed',String($('character').value===key))
      const face=el('span'),img=el('img');img.src=portraitUrl(person);img.onerror=()=>{img.onerror=null;img.src=portraitUrl({...person,portrait_url:null})};img.alt='';img.loading='lazy';face.append(img)
      button.append(face,el('div',name));button.addEventListener('click',()=>{$('character').value=$('character').value===key?'':key;render()});$('friends').append(button)
    }
  }
  function render(){
    const params=new URLSearchParams(location.search)
    for(const [key,value] of [['q',$('search').value],['character',$('character').value],['writer',$('writer').value],['kind',$('kind').value],['sort',$('sort').value==='new'?'':$('sort').value]]){if(value)params.set(key,value);else params.delete(key)}
    const query=params.toString();history.replaceState(history.state,'',location.pathname+(query?'?'+query:'')+location.hash)
    visible=selectStories(groups,{query:$('search').value,character:$('character').value,writer:$('writer').value,kind:$('kind').value,sort:$('sort').value})
    $('cards').replaceChildren();$('count').textContent='('+groups.length+')';$('results').textContent=visible.length+' of '+groups.length+' entries & collections';$('empty').hidden=visible.length!==0
    for(const g of visible){
      const card=el('article',undefined,'fx-card'),meta=el('div',undefined,'fx-card-meta');meta.append(el('span',g.kind),el('span',Math.max(1,Math.ceil(g.words/220))+' min read'));const heading=el('h3'),title=el('button',g.title,'fx-story-title');title.type='button';title.addEventListener('click',()=>openStory(g));heading.append(title)
      const chars=el('div',undefined,'fx-card-chars');for(const c of g.characters)chars.append(el('span',c.name));if(!g.characters.length)chars.append(el('span','World & NPC stories'))
      const bottom=el('div',undefined,'fx-card-bottom');bottom.append(el('span',new Intl.DateTimeFormat('en-US',{month:'short',day:'numeric',year:'numeric'}).format(new Date(g.written*1000))),el('span',g.chapters.length>1?g.chapters.length+' chapters · read together':g.kind==='Journal'?'OPEN JOURNAL ↗':'OPEN STORY ↗'))
      card.append(meta,heading,el('p',g.writers.join(' · '),'fx-card-byline'),el('p',g.excerpt,'fx-card-excerpt'),chars,bottom);$('cards').append(card)
    }
    renderFriends()
  }
  async function openStory(group,preserve=false){
    readerRequest?.abort();readerRequest=new AbortController();const signal=readerRequest.signal,oldTop=panel.scrollTop
    current=group.key;panel.hidden=false;setBackgroundInert(true);document.body.style.overflow='hidden';$('reader-title').textContent=group.title;$('reader-kind').textContent=group.kind+' / '+Math.max(1,Math.ceil(group.words/220))+' MIN READ';$('reader-byline').textContent=group.writers.join(' · ');$('reader-timeline').textContent=[group.timeline,group.location].filter(Boolean).join(' · ');$('original').href=group.chapters[0].novaUrl;story.replaceChildren(el('p','Opening the entry…'));history.replaceState(history.state,'',location.pathname+location.search+'#story='+encodeURIComponent(group.key));panel.focus({preventScroll:true});panel.scrollTop=preserve?oldTop:0
    try{
      const loaded=[]
      for(const chapter of group.chapters){const data=await request({id:chapter.id,type:chapter.entryType||'story'},signal);if(!active||signal.aborted||current!==group.key)return;loaded.push(data)}
      const content=document.createDocumentFragment()
      for(let i=0;i<loaded.length;i++){if(i>0){content.append(el('hr'));content.append(el('p','Continued', 'fx-credit'))}content.append(safeStory(loaded[i].html))}
      story.replaceChildren(content);story.removeAttribute('role');if(preserve)panel.scrollTop=oldTop;updateProgress()
    }catch(error){if(error.name==='AbortError')return;if([401,403].includes(error.status)){clearPrivate(error.message);return}story.replaceChildren(el('p',error.message,'fx-story-error'))}
  }
  function updateProgress(){const max=panel.scrollHeight-panel.clientHeight;$('reader-progress').textContent=(max>0?Math.min(100,Math.round(panel.scrollTop/max*100)):100)+'% read'}
  async function refresh(){
    if(!active||refreshing||!root.isConnected)return
    refreshing=true;$('refresh').disabled=true;controller=new AbortController();const timeout=setTimeout(()=>controller?.abort(),40000)
    try{
      let rows=[],offset=0
      for(let page=0;page<200;page++){const result=await request({offset},controller.signal);rows.push(...result.stories);if(result.nextOffset===null){offset=null;break}offset=result.nextOffset}
      if(offset!==null)throw new Error('This archive is too large to open at once. Please contact the GM.')
      if(!active)return
      try{roster=characterRoster(await loadCharacterProfiles())}catch{/* Preserve the last-known roster during an outage. */}
      if(!active||controller.signal.aborted)return
      const before=current?groups.find(g=>g.key===current):null;groups=groupStories(rows,roster)
      people=characterOptions(groups,roster)
      const previous=$('character').value;fillOptions($('character'),[],'Everybody')
      for(const person of people){const option=el('option',person.name);option.value=person.key;$('character').append(option)}
      const world=el('option','World & NPC stories');world.value='world';$('character').append(world);$('character').value=resolveCharacter(previous,people)
      fillOptions($('writer'),[...new Set(groups.flatMap(g=>g.writers))].sort(),'All writers')
      if(!filtersInitialized){
        $('character').value=resolveCharacter(initialFilters.get('character')||'',people)
        $('search').value=initialFilters.get('q')||''
        for(const name of ['writer','kind','sort']){const raw=initialFilters.get(name),value=name==='writer'?writerName(raw):raw;if(value&&[...$(name).options].some(o=>o.value===value))$(name).value=value}
        filtersInitialized=true
      }
      gate.hidden=true;library.hidden=false;$('compose').hidden=false;status.textContent='';$('live').textContent='● '+groups.length+' ENTRIES / LIVE FROM NOVA';render()
      if(current){const now=groups.find(g=>g.key===current);if(!now){closeReader();status.textContent='That story is no longer published.'}else if(before&&now.chapters.map(c=>c.revision).join()!==before.chapters.map(c=>c.revision).join())await openStory(now,true)}
      else if(location.hash.startsWith('#story=')){const key=decodeURIComponent(location.hash.slice(7)),found=groups.find(g=>g.key===key);if(found)await openStory(found)}
    }catch(error){if(active){clearPrivate(error.name==='AbortError'?'The shelves took too long to respond. Please try again.':error.message)}}finally{clearTimeout(timeout);refreshing=false;if(active)$('refresh').disabled=false}
  }
  for(const field of ['character','writer','kind','sort'])listen($(field),'change',render)
  let searchTimer;listen($('search'),'input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(render,120)})
  listen($('reset'),'click',()=>{$('search').value='';for(const n of ['character','writer','kind'])$(n).value='';render()});listen($('all'),'click',()=>{$('character').value='';render()})
  listen($('refresh'),'click',refresh);listen($('random'),'click',()=>{if(visible.length)openStory(visible[Math.floor(Math.random()*visible.length)])})
  listen($('back'),'click',()=>closeReader());listen(panel,'keydown',e=>{if(e.key==='Escape')closeReader();if(e.key==='Tab'){const nodes=[...panel.querySelectorAll('button:not(:disabled),a[href],input')].filter(n=>n.getClientRects().length);const first=nodes[0],last=nodes.at(-1);if(e.shiftKey&&(document.activeElement===first||document.activeElement===panel)){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}});listen(panel,'scroll',updateProgress)
  for(const [name,delta]of[['smaller',-1],['larger',1]])listen($(name),'click',()=>{readerSize=Math.max(15,Math.min(28,readerSize+delta));story.style.setProperty('--fx-reading-size',readerSize+'px')})
  listen($('tone'),'click',()=>{const dark=story.classList.toggle('fx-night');$('tone').setAttribute('aria-pressed',String(dark));$('tone').textContent=dark?'Day paper':'Night paper'})
  listen($('credits'),'change',()=>story.classList.toggle('fx-hide-credits',!$('credits').checked))
  for(const [name,delta]of[['prev',-1],['next',1]])listen($(name),'click',()=>{const list=visible.length?visible:groups,index=list.findIndex(g=>g.key===current);if(list.length)openStory(list[(index+delta+list.length)%list.length])})
  listen(document,'visibilitychange',()=>{if(!document.hidden)refresh()})
  let subscription
  listen(document,'nav',()=>{if(!root.isConnected){active=false;controller?.abort();readerRequest?.abort();clearInterval(poll);clearTimeout(searchTimer);subscription?.unsubscribe();for(const off of handlers)off();document.body.style.overflow=''}})
  try{
    const responses=await Promise.all([fetch(base+'static/fiction-config.json',{cache:'no-store'}),fetch(base+'static/community-config.json',{cache:'no-store'})]);if(responses.some(r=>!r.ok))throw new Error('The Exchange is not connected yet. Please try again later.')
    config=await responses[0].json();const account=await responses[1].json();const nova=new URL(config.novaUrl);if(nova.protocol!=='https:'&&!['127.0.0.1','localhost'].includes(nova.hostname))throw new Error('The writing room needs a secure connection.');config.novaUrl=nova.origin
    if(config.readerUrl&&new URL(config.readerUrl).origin!==nova.origin)throw new Error('Invalid reader service configuration.')
    for(const link of root.querySelectorAll('[data-fx-write]')){const url=new URL('/index.php/auth/start',nova.origin);if(link.dataset.fxWrite)url.searchParams.set('compose',link.dataset.fxWrite);link.href=url.href;link.dataset.routerIgnore='true'}
    const {createClient}=await import(SDK);client=createClient(account.url,account.publishableKey,{auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}})
    subscription=client.auth.onAuthStateChange(event=>{if(event==='SIGNED_OUT')clearPrivate('You are signed out. Sign in to return to the shelves.');else if(event==='SIGNED_IN')setTimeout(refresh,0)}).data.subscription
    listen(login,'submit',async e=>{e.preventDefault();const button=login.querySelector('button');button.disabled=true;status.textContent='Checking the guest list…';try{const fields=new FormData(login),{error}=await client.auth.signInWithPassword({email:String(fields.get('email')),password:String(fields.get('password'))});login.elements.password.value='';if(error)throw new Error('That sign-in did not work. Check your email and password.');await refresh()}catch(error){status.textContent=error.message}finally{button.disabled=false}})
    listen($('signout'),'click',async()=>{clearPrivate('Signing out…');const {error}=await client.auth.signOut();status.textContent=error?'Sign-out did not complete. Please retry.':'You are signed out.'})
    await refresh();poll=setInterval(()=>{if(!document.hidden)refresh()},60000)
  }catch(error){clearPrivate(error.message);if(!client)login.hidden=true}
}

if(typeof document!=='undefined'){
  const boot=()=>{const root=document.querySelector('[data-fiction-reader]');if(root)mount(root,(document.body.dataset.basepath||'')+'/')}
  document.addEventListener('nav',boot)
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot()
}

