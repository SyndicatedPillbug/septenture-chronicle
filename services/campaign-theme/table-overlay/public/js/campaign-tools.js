// Campaign links use Nova's canonical writing, sheet and private dice services.
(()=>{
 const banner=document.createElement('div');banner.className='campaign-connection';banner.setAttribute('role','status');banner.hidden=true
 const message=document.createElement('span'),reconnect=document.createElement('a');reconnect.href='/session/connect';reconnect.target='_top';reconnect.textContent='Reopen Table';const dismiss=document.createElement('button');dismiss.type='button';dismiss.textContent='Dismiss';dismiss.addEventListener('click',()=>{lastState=null;banner.hidden=true});banner.append(message,document.createTextNode(' '),reconnect,dismiss)
 const attach=()=>{document.body.append(banner)};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',attach);else attach()
 let lastState
 window.addEventListener('septenture:connection',event=>{const {state,message:text}=event.detail;if(state==='connected'&&lastState==='action-failed')return;lastState=state;banner.hidden=state==='connected';message.textContent=text;reconnect.hidden=state!=='expired'})
 async function setup(){
  let config;try{const response=await fetch('/session/config',{cache:'no-store'});if(!response.ok)return;config=await response.json()}catch{return}
  const nova=new URL(config.novaUrl),site=new URL(config.chronicleUrl);if(nova.protocol!=='https:'&&!['localhost','127.0.0.1'].includes(nova.hostname))return
  const destination=(compose)=>{const url=new URL('/index.php/auth/start',nova);if(compose)url.searchParams.set('compose',compose);return url.href}
  let ready
  const ensureTools=()=>{if(window.SeptentureTableTools)return Promise.resolve();if(!ready)ready=new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='/js/table-tools.js?v=60992850dc97';script.onload=resolve;script.onerror=reject;document.head.append(script)}).catch(error=>{ready=null;throw error});return ready}
  const openTools=(kind,control)=>ensureTools().then(()=>window.SeptentureTableTools.open(kind,control)).catch(()=>window.dispatchEvent(new CustomEvent('septenture:connection',{detail:{state:'action-failed',message:'Table tools could not load. Try Dice or Sheets again.'}})))
  ensureTools().catch(()=>{})
  for(const file of ['manual-sheet.css','table-tools.css']){const css=document.createElement('link');css.rel='stylesheet';css.href='/css/'+file+'?v=repairs20261105b';document.head.append(css)}
  for(const [selector,label,kind]of [['.show_collectables','Character sheets','sheets'],['.show_dice','Dice','dice']])for(const old of document.querySelectorAll(selector)){const control=document.createElement('button');control.type='button';control.className=old.className;control.classList.remove(selector.slice(1));control.textContent=label;control.addEventListener('click',()=>{openTools(kind,control)});old.replaceWith(control)}
  const shortcuts=document.querySelector('div.topbar .btn-group');if(shortcuts)for(const [label,kind]of [['Dice','dice'],['Sheets','sheets']]){const control=document.createElement('button');control.type='button';control.className='btn btn-primary btn-xs';control.textContent=label;control.addEventListener('click',()=>openTools(kind,control));shortcuts.append(control)}
  for(const old of document.querySelectorAll('.show_journal')){const link=document.createElement('a');link.className=old.className;link.classList.remove('show_journal');link.href=new URL(site.pathname.replace(/\/?$/,'/')+'fiction/?kind=Journal',site.origin).href;link.textContent='Journals';link.target='_blank';link.rel='noopener';old.replaceWith(link)}
  window.addEventListener('keydown',event=>{if(event.key==='~'&&!event.target.closest('input,textarea,[contenteditable]')){event.preventDefault();event.stopImmediatePropagation();openTools('dice')}},true)
  for(const selector of ['.start_combat','.show_spells','.play_audio','.pictures','.playvideo'])for(const control of document.querySelectorAll(selector))control.hidden=true
  for(const input of document.querySelectorAll('input:not([aria-label]):not([type=hidden])')){if(input.closest('label')||input.id&&document.querySelector(`label[for="${CSS.escape(input.id)}"]`))continue;input.setAttribute('aria-label',input.placeholder||input.name?.replaceAll('_',' ')||'Table input')}
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup);else setup()
})()
