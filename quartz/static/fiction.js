;(() => {
  if(window.__septentureFiction)return
  window.__septentureFiction=true
  async function boot() {
    const panel=document.querySelector('[data-fiction-login]')
    const enter=document.querySelector('[data-fiction-enter]')
    const character=document.querySelector('.pc-space[data-pc]')
    if(!panel&&!enter&&!character)return
    if(panel?.dataset.bound)return
    if(panel)panel.dataset.bound='true'
    const status=document.querySelector('[data-fiction-status]')
    const base=(document.body.dataset.basepath||'')+'/'
    try {
      const response=await fetch(base+'static/fiction-config.json',{cache:'no-store'})
      if(!response.ok)throw new Error('The writing room is not connected yet.')
      const config=await response.json()
      const nova=new URL(config.novaUrl)
      if(nova.protocol!=='https:'&&!(nova.protocol==='http:'&&['127.0.0.1','localhost'].includes(nova.hostname)))throw new Error('The writing room needs a secure connection.')
      if(character){
        if(!character.querySelector('[data-character-tools]')) {
          const nav=document.createElement('nav');nav.className='pc-browser';nav.dataset.characterTools='true';nav.setAttribute('aria-label','Character sections')
          const current=document.createElement('span');current.textContent='Public profile';current.setAttribute('aria-current','page');nav.append(current)
          for(const [label,view] of [['Character details','profile'],['Stories','stories'],['Journal','journal']]) {
            const link=document.createElement('a');link.textContent=label;link.dataset.routerIgnore='true'
            const destination=new URL('/index.php/auth/start',nova.origin);destination.searchParams.set('character',character.dataset.pc);destination.searchParams.set('view',view);link.href=destination.href;nav.append(link)
          }
          const banner=character.querySelector('.pc-marquee')||character.querySelector('.pc-browser');banner.after(nav)
        }
        return
      }
      if(enter){enter.href=nova.origin+'/index.php/auth/start';enter.dataset.routerIgnore='true';return}
      const params=new URLSearchParams(location.hash.slice(1))
      const state=params.get('state')
      history.replaceState(history.state,'',location.pathname+location.search)
      const accountResponse=await fetch(base+'static/community-config.json')
      if(!accountResponse.ok)throw new Error('Player accounts are temporarily unavailable.')
      const accountConfig=await accountResponse.json()
      const {createClient}=await import('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm')
      const client=createClient(accountConfig.url,accountConfig.publishableKey,{auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}})
      if(params.get('action')==='logout') {
        const {error}=await client.auth.signOut()
        if(error)throw new Error('The writing desk is closed, but the player account could not be signed out. Please retry from your character page.')
        status.textContent='You are signed out of Septenture.';return
      }
      if(!state||!/^[a-f0-9]{64}$/.test(state)) {location.assign(nova.origin+'/index.php/auth/start');return}
      async function handoff(session) {
        status.textContent='Opening your writing desk…'
        const response=await fetch(nova.origin+'/index.php/auth/ticket',{method:'POST',credentials:'omit',
          headers:{'Content-Type':'application/json'},body:JSON.stringify({state,token:session.access_token})})
        const result=await response.json()
        if(!response.ok)throw new Error(result.error||'The writing room could not be opened.')
        const destination=new URL('/index.php/auth/complete',nova.origin)
        destination.searchParams.set('ticket',result.ticket);destination.searchParams.set('state',state)
        location.replace(destination.href)
      }
      const {data,error}=await client.auth.getSession()
      if(error)throw error
      if(data.session){await handoff(data.session);return}
      const form=panel.querySelector('[data-fiction-form]');form.hidden=false;status.textContent='Sign in to continue.'
      form.addEventListener('submit',async event=>{
        event.preventDefault();const button=form.querySelector('button');button.disabled=true
        try {
          const fields=new FormData(form)
          const {data,error}=await client.auth.signInWithPassword({email:String(fields.get('email')),password:String(fields.get('password'))})
          form.elements.password.value=''
          if(error)throw new Error('That sign-in did not work. Check your email and password.')
          await handoff(data.session)
        }catch(error){status.textContent=error.message;button.disabled=false}
      })
    }catch(error){if(status)status.textContent=error.message||'The writing room is temporarily unavailable.'}
  }
  document.addEventListener('nav',boot)
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot()
})()
