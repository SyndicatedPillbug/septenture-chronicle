// The public key identifies the project. Postgres and Storage enforce ownership.
const here = new URL('.', import.meta.url)
let clientPromise
async function getClient() {
  if (!clientPromise) clientPromise = (async () => {
    const response = await fetch(new URL('community-config.json', here), {cache:'no-store'})
    if (!response.ok) throw new Error('Account configuration could not load.')
    const config = await response.json()
    if (!config.url || !config.publishableKey) return null
    const {createClient} = await import('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm')
    return createClient(config.url, config.publishableKey, {auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}})
  })().catch(error => { clientPromise=undefined; throw error })
  return clientPromise
}
const make = (tag, text, className) => {
  const el=document.createElement(tag)
  if (text !== undefined) el.textContent=text
  if (className) el.className=className
  return el
}
const themes=['moon','candle','water','hive','rose','watch','powder']
const fileTypes={'image/jpeg':'jpg','image/png':'png','image/webp':'webp','audio/mpeg':'mp3','audio/ogg':'ogg','audio/wav':'wav','audio/x-wav':'wav','audio/mp4':'m4a','audio/aac':'aac'}

export async function mount(root, signal) {
  const on=(el,type,fn)=>el.addEventListener(type,fn,{signal})
  const slug=root.dataset.pc
  let client, session, owned=false, admin=false, profile
  let pending=[]
  let saving=false
  const bar=make('div',undefined,'pc-account-bar')
  const accountStatus=make('span','Opening the guestbook…')
  const login=make('button','Player login')
  login.type='button'
  const edit=make('button','✎ Edit my page'); edit.type='button'; edit.hidden=true
  const invite=make('button','Invite a player'); invite.type='button'; invite.hidden=true
  const password=make('button','Change password');password.type='button';password.hidden=true
  const write=make('a','✎ Write new content','pc-write-new');write.hidden=true;write.dataset.routerIgnore='true'
  bar.append(accountStatus,write,login,edit,invite,password)
  root.querySelector('.pc-browser').after(bar)
  const dialog=make('dialog',undefined,'pc-account-dialog')
  const heading=make('h2')
  const close=make('button','Close ×');close.type='button'
  const body=make('div')
  const status=make('p');status.setAttribute('role','status')
  const header=make('div',undefined,'pc-dialog-header');header.append(heading,close)
  dialog.append(header,body,status);root.append(dialog)
  on(close,'click',()=>dialog.close())
  on(dialog,'cancel',event=>{if(saving)event.preventDefault()})
  on(dialog,'close',()=> {if(!saving && client && pending.length){const paths=pending.splice(0);client.storage.from('character-media').remove(paths)}})
  signal.addEventListener('abort',()=>{dialog.close();dialog.remove();bar.remove()},{once:true})
  function open(title) {body.replaceChildren();status.textContent='';heading.textContent=title;dialog.showModal()}
  function field(form,label,name,value='',kind='text',max=160) {
    const wrapper=make('label',label)
    const input=make(kind==='textarea'?'textarea':'input')
    if(kind!=='textarea')input.type=kind
    else input.rows=5
    input.name=name;input.value=value ?? '';input.maxLength=max
    wrapper.append(input);form.append(wrapper);return input
  }
  function button(form,text,fn) {const b=make('button',text);b.type='button';on(b,'click',fn);form.append(b);return b}
  function publicUrl(path) {return client.storage.from('character-media').getPublicUrl(path).data.publicUrl}
  function apply(p) {
    profile=p
    const set=(selector,text)=>{const el=root.querySelector(selector);if(el)el.textContent=text}
    set('.pc-identity h1',p.display_name)
    set('.pc-banner .pc-eyebrow',p.tagline)
    set('.pc-banner-title',p.heading)
    set('.pc-status','✦ '+p.mood)
    set('.pc-facts dd',p.location)
    set('.pc-main .pc-panel h2','ABOUT '+p.display_name.toUpperCase())
    const about=root.querySelector('.pc-main .pc-panel-body')
    about.querySelectorAll(':scope > p')[1].textContent=p.bio
    const interests=root.querySelector('.pc-likes');interests.replaceChildren()
    for(const item of p.interests.split('\n').filter(Boolean))interests.append(make('li',item))
    for(const theme of themes)root.classList.toggle('pc-'+theme,theme===p.theme)
    if(p.accent)root.style.setProperty('--pc-accent',p.accent)
    else root.style.removeProperty('--pc-accent')
    if(p.portrait_path)root.querySelector('.pc-identity img').src=publicUrl(p.portrait_path)
    const banner=root.querySelector('.pc-banner')
    if(p.background_path)banner.style.backgroundImage=`linear-gradient(#0006,#0006),url("${publicUrl(p.background_path)}")`
    if(p.music_path) {
      const audio=root.querySelector('[data-pc-audio]')
      const url=publicUrl(p.music_path)
      if(audio.src!==url){audio.pause();audio.src=url}
      set('[data-track-title]',p.music_title || 'The player’s chosen track')
      set('[data-track-credit]','Player soundtrack · shared with all visitors')
      root.querySelector('[data-play]').setAttribute('aria-label','Play '+(p.music_title || 'the player’s chosen track'))
    }
  }
  function loginDialog(token) {
    open(token?'Your invitation to the party':'Player login')
    if(!client){body.append(make('p','Player accounts are temporarily unavailable. Please try again later.'));return}
    const form=make('form')
    body.append(form)
    if(token)form.append(make('p','Choose your own email and password. This private invitation can be used once.'))
    const email=field(form,'Email','email','','email',254);email.required=true;email.autocomplete='email'
    const pass=field(form,token?'Choose a password (12–128 characters)':'Password','password','','password',128)
    pass.required=true;pass.autocomplete=token?'new-password':'current-password';if(token)pass.minLength=12
    const submit=make('button',token?'Create my account':'Log in');submit.type='submit';form.append(submit)
    on(form,'submit',async event=>{
      event.preventDefault();submit.disabled=true;status.textContent=token?'Creating your account…':'Logging in…'
      try {
        if(token){
          const {data,error}=await client.functions.invoke('character-accounts',{body:{action:'redeem',token,email:email.value,password:pass.value}})
          if(error || data?.error)throw new Error(data?.error || 'Account setup failed. Your invitation may have expired, or the email may already have an account.')
        }
        const {error}=await client.auth.signInWithPassword({email:email.value,password:pass.value})
        if(error)throw error
        pass.value='';dialog.close();await refreshAuth();await loadNotes()
      }catch(error){status.textContent=error.message || 'Login failed.'}finally{submit.disabled=false}
    })
    if(!token)form.append(make('p','Accounts come through a private invitation from your DM. For password recovery, contact the DM.'))
  }
  on(login,'click',async()=>{
    if(session){await client.auth.signOut();await refreshAuth();await loadNotes()}
    else loginDialog()
  })
  on(password,'click',()=>{
    open('Change your password')
    const form=make('form');body.append(form)
    const pass=field(form,'New password (12–128 characters)','newPassword','','password',128);pass.required=true;pass.minLength=12;pass.autocomplete='new-password'
    const submit=make('button','Save new password');submit.type='submit';form.append(submit)
    on(form,'submit',async event=>{event.preventDefault();submit.disabled=true;try{const{error}=await client.auth.updateUser({password:pass.value});if(error)throw error;pass.value='';status.textContent='Password changed.'}catch(error){status.textContent=error.message}finally{submit.disabled=false}})
  })
  on(edit,'click',()=>{
    if(!owned || !profile)return
    open('Make this corner yours')
    const form=make('form');body.append(form)
    const fields={}
    for(const [name,label,kind,max] of [['display_name','Display name','text',80],['tagline','Small banner text','text',160],['heading','Banner title','text',160],['mood','Status / player update','text',160],['location','Currently / location','text',160],['bio','About me','textarea',5000],['interests','Player pinboard (one per line)','textarea',1200],['music_title','Track title','text',160]]){
      fields[name]=field(form,label,name,profile[name],kind,max)
      if(['display_name','heading','bio'].includes(name))fields[name].required=true
    }
    const themeLabel=make('label','Your theme');const theme=make('select');theme.name='theme'
    for(const [value,text] of [['moon','Moonlit bookbinder'],['candle','Candle & bone'],['water','Green water'],['hive','Honeycomb'],['rose','Pressed rose'],['watch','White & gold'],['powder','Violet smoke']]){const option=make('option',text);option.value=value;theme.append(option)}
    theme.value=profile.theme;themeLabel.append(theme);form.append(themeLabel)
    const accent=field(form,'Accent color','accent',profile.accent || getComputedStyle(root).getPropertyValue('--pc-accent').trim(),'color')
    const media={}; const reset={}
    for(const [key,label,accept] of [['portrait_path','Portrait','image/jpeg,image/png,image/webp'],['background_path','Banner image','image/jpeg,image/png,image/webp'],['music_path','Music track','audio/mpeg,audio/ogg,audio/wav,audio/mp4,audio/aac']]) {
      media[key]=field(form,label,key,'','file');media[key].accept=accept
      const l=make('label','Use the original '+label.toLowerCase());const c=make('input');c.type='checkbox';reset[key]=c;l.prepend(c);form.append(l)
    }
    form.append(make('p','Images: JPG, PNG, or WebP, up to 5 MB. Audio: MP3, OGG, WAV, M4A, or AAC, up to 20 MB. These uploads become part of your public page.'))
    const submit=make('button','Save for everyone');submit.type='submit';form.append(submit)
    on(form,'submit',async event=>{
      event.preventDefault();saving=true;submit.disabled=true;close.disabled=true;status.textContent='Saving your page…'
      let committed=false
      try {
        const update={theme:theme.value,accent:accent.value}
        for(const[name,input]of Object.entries(fields))update[name]=input.value.trim()
        for(const[key,input]of Object.entries(media)){
          const file=input.files?.[0]
          if(reset[key].checked){update[key]=null;continue}
          if(!file)continue
          const ext=fileTypes[file.type]
          const isAudio=key==='music_path'
          if(!ext || (isAudio?!file.type.startsWith('audio/'):!file.type.startsWith('image/')))throw new Error('Choose a supported '+(isAudio?'audio':'image')+' file.')
          if(file.size>(isAudio?20:5)*1024*1024)throw new Error('That file is too large.')
          const path=`${slug}/${crypto.randomUUID()}.${ext}`
          const {error}=await client.storage.from('character-media').upload(path,file,{contentType:file.type,cacheControl:'3600',upsert:false})
          if(error)throw error
          pending.push(path);update[key]=path
        }
        const {data,error}=await client.from('character_profiles').update(update).eq('slug',slug).eq('updated_at',profile.updated_at).select().maybeSingle()
        if(error)throw error
        if(!data)throw new Error('This page changed in another window, or your access changed. Close the editor and reload before trying again.')
        committed=true;pending=[]
        const oldPaths=Object.keys(media).filter(key=>Object.hasOwn(update,key)&&profile[key]).map(key=>profile[key])
        apply(data)
        // Reset media can restore the static defaults without leaving stale audio.
        accountStatus.textContent='Saved for everyone. Reloading your finished page…'
        if(oldPaths.length)await client.storage.from('character-media').remove(oldPaths)
        dialog.close();if(!signal.aborted)location.reload()
      }catch(error){
        if(!committed && pending.length){await client.storage.from('character-media').remove(pending);pending=[]}
        status.textContent=error.message || 'The page could not be saved.'
      }finally{saving=false;submit.disabled=false;close.disabled=false}
    })
  })
  on(invite,'click',()=>{
    open('Invite a player')
    body.append(make('p','Make a private, one-use invitation for a character. Share it directly with that player. It expires in seven days.'))
    const select=make('select')
    for(const id of ['page','ceridwen','coy','legion','simon','selim','the-gunslinger']){const option=make('option',id);option.value=id;select.append(option)}
    body.append(select)
    button(body,'Create private invitation',async()=>{
      status.textContent='Creating invitation…'
      const{data,error}=await client.functions.invoke('character-accounts',{body:{action:'invite',slug:select.value}})
      if(error || data?.error){status.textContent=data?.error || 'Could not create invitation.';return}
      const url=new URL(`../${select.value}/`,location.href);url.hash='invite='+data.token
      const link=make('input');link.value=url.href;link.readOnly=true;link.setAttribute('aria-label','Private player invitation link');body.append(link)
      status.textContent='Share this link privately. It grants editing access to this character.'
    })
  })
  let bookForm,bookStatus,notes,displayName,approved=false
  async function refreshAuth() {
    if(signal.aborted)return
    const{data}=await client.auth.getSession();session=data.session
    owned=false;admin=false;approved=false
    if(session){
      const{data:ownership,error}=await client.from('character_owners').select('character_slug').eq('user_id',session.user.id)
      if(error)throw error
      approved=Boolean(ownership?.length);owned=ownership?.some(o=>o.character_slug===slug) || false
      const{data:admins}=await client.from('community_admins').select('user_id').eq('user_id',session.user.id)
      admin=Boolean(admins?.length)
    }
    if(signal.aborted)return
    write.hidden=!(session&&(approved||admin)&&write.hasAttribute('href'));
    login.textContent=session?'Log out':'Player login';edit.hidden=!owned;invite.hidden=!admin;password.hidden=!session
    accountStatus.textContent=session?(approved?'Welcome back.':'Signed in. Ask your DM to assign your character.'):'The party guestbook.'
    if(bookForm){bookForm.querySelector('button[type=submit]').disabled=!approved;bookStatus.textContent=approved?'Your note will appear for everyone.':'Log in with your player account to sign the shared guestbook.'}
  }
  async function loadNotes() {
    if(!client || !notes)return
    const fields=session?'id,name,message,created_at,author_id':'id,name,message,created_at'
    const{data,error}=await client.from('character_guestbook').select(fields).eq('character_slug',slug).order('created_at',{ascending:false}).limit(50)
    if(error){bookStatus.textContent='The shared guestbook could not load. Please try again later.';return}
    if(signal.aborted)return
    notes.replaceChildren()
    if(!data.length)notes.append(make('p','The first page is still blank.','pc-fine'))
    for(const note of data){
      const el=make('div',undefined,'pc-guest-note');el.append(make('strong',note.name),make('p',note.message))
      if(session && (owned || note.author_id===session.user.id))button(el,'Remove note',async()=>{
        const{error}=await client.from('character_guestbook').delete().eq('id',note.id)
        if(error)bookStatus.textContent='That note could not be removed.';else await loadNotes()
      })
      notes.append(el)
    }
  }
  try{
    const writingConfig=await fetch(new URL('fiction-config.json',here),{cache:'no-store'}).then(r=>r.ok?r.json():null).catch(()=>null)
    if(writingConfig?.novaUrl){const url=new URL('/index.php/auth/start',writingConfig.novaUrl);if(url.protocol==='https:'||['127.0.0.1','localhost'].includes(url.hostname)){url.searchParams.set('compose','story');write.href=url.href}}
    client=await getClient()
    if(signal.aborted)return
    if(!client){accountStatus.textContent='Player accounts are temporarily unavailable.';return}
    const{data,error}=await client.from('character_profiles').select().eq('slug',slug).single()
    if(error)throw error
    if(signal.aborted)return
    apply(data)
    // Replacing the form detaches the static demo's browser-local handlers.
    const localForm=root.querySelector('[data-guest-form]')
    bookForm=localForm.cloneNode(true);localForm.replaceWith(bookForm)
    bookForm.querySelector('button[type=submit]').disabled=true
    bookStatus=bookForm.querySelector('[data-guest-status]')
    bookForm.querySelector('button[type=submit]').textContent='✎ Sign the guestbook'
    displayName=bookForm.querySelector('[name=guest]');displayName.value=''
    notes=root.querySelector('[data-guest-entries]')
    root.querySelector('#sign-the-guestbook').closest('.pc-panel').querySelector('.pc-fine').textContent='The shared guestbook. Everyone can read it; players can sign it.'
    on(bookForm,'submit',async event=>{
      event.preventDefault();if(!approved)return
      const submit=bookForm.querySelector('button[type=submit]');submit.disabled=true
      const values=new FormData(bookForm)
      const name=String(values.get('guest')||'').trim();const message=String(values.get('message')||'').trim()
      if(!name || !message){bookStatus.textContent='Add a name and a message.';submit.disabled=false;return}
      try{
        const{error}=await client.from('character_guestbook').insert({character_slug:slug,name,message})
        if(error)throw error
        bookForm.querySelector('[name=message]').value='';await loadNotes();bookStatus.textContent='Signed! Your note is saved for everyone.'
      }catch(error){bookStatus.textContent=error.message || 'Your note could not be saved.'}finally{submit.disabled=!approved}
    })
    await refreshAuth();await loadNotes()
    const{data:{subscription}}=client.auth.onAuthStateChange(()=>{setTimeout(()=>{if(!signal.aborted)refreshAuth().then(loadNotes).catch(()=>{accountStatus.textContent='Please log in again.'})},0)})
    signal.addEventListener('abort',()=>subscription.unsubscribe(),{once:true})
    const hash=new URLSearchParams(location.hash.slice(1));const token=hash.get('invite')
    if(token){history.replaceState(null,'',location.pathname+location.search);if(/^[a-f0-9]{64}$/.test(token))loginDialog(token)}
  }catch(error){
    if(!signal.aborted)accountStatus.textContent='The guestbook is temporarily unavailable.'
  }
}
