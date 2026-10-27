;(()=>{
  async function boot(){
    const header=document.querySelector('.character-identity'),identity=document.querySelector('#septenture-character'),site=document.querySelector('meta[name="septenture-site"]')?.content
    if(!header||!identity||!site)return
    let person;try{person=JSON.parse(identity.textContent)}catch{return}
    try{
      const shared=await import(new URL('static/character-identity.js?v=3f0c0f7b3008',site).href)
      let busy=false
      async function refresh(){
        if(busy||document.hidden||!header.isConnected)return
        busy=true
        try{
          const p=(await shared.loadCharacterProfiles()).find(p=>p.slug===person.slug)
          if(!p)return
          header.querySelector('h1').textContent=p.display_name
          header.querySelector('p').textContent=p.tagline||p.heading||''
          shared.setPortrait(header.querySelector('img'),p)
          header.dataset.theme=p.theme||'moon'
          const banner=p.background_url||(p.theme==='moon'?new URL('static/companions/bookbinder.png',site).href:null)
          header.style.backgroundImage=banner?`linear-gradient(#10130fbb,#10130fee),url("${banner}")`:''
          if(/^#[a-f0-9]{6}$/i.test(p.accent||''))header.style.setProperty('--character-accent',p.accent);else header.style.removeProperty('--character-accent')
          const about=document.querySelector('.character-about')
          if(about){about.querySelector('h2').textContent='About '+p.display_name;about.querySelector('p').textContent=p.bio||'';about.querySelector('a').href=shared.profileHref(p.slug)}
          const tab=document.querySelector('.character-tabs a');if(tab)tab.href=shared.profileHref(p.slug)
        }catch{/* A temporary profile outage must not interrupt writing. */}finally{busy=false}
      }
      await refresh()
      const poll=setInterval(refresh,60000)
      document.addEventListener('visibilitychange',refresh)
      window.addEventListener('pagehide',()=>{clearInterval(poll);document.removeEventListener('visibilitychange',refresh)},{once:true})
    }catch{/* The server-rendered profile remains usable offline. */}
  }
  if(document.readyState==='complete')boot();else document.addEventListener('DOMContentLoaded',boot,{once:true})
})()
