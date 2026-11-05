// Play controls stay with the board; map preparation has its own workspace.
(()=>{
 const make=(tag,text)=>{const n=document.createElement(tag);if(text)n.textContent=text;return n};
 const button=(text,fn)=>{const n=make('button',text);n.type='button';n.addEventListener('click',fn);return n};
 const link=(text,url)=>{const n=make('a',text);n.href=url;return n};
 const onReady=fn=>document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn,{once:true}):fn();
 onReady(()=>{
  const shell=document.querySelector('.campaign-board');
  if(shell){
   const control=document.querySelector('#piece-control');let opener;
   addEventListener('message',e=>{if(e.origin!==location.origin||e.source!==shell.contentWindow||e.data?.type!=='septenture-piece-control'||!control)return;opener=document.activeElement;control.showModal();refreshPieces()});
   control?.querySelector('[data-close-control]').addEventListener('click',()=>control.close());
   control?.addEventListener('close',()=>opener?.focus());
   async function refreshPieces(){const form=control.querySelector('form');if(!form)return;const status=control.querySelector('[role=status]');status.textContent='Loading current pieces…';for(const b of form.querySelectorAll('button'))b.disabled=true;try{const response=await fetch('/session/control',{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});const data=await response.json();if(!response.ok)throw Error(data.error||'Pieces could not load.');const select=form.querySelector('[name=token]');select.replaceChildren();for(const piece of data.pieces){const option=make('option',piece.name);option.value=piece.id;select.append(option)}for(const b of form.querySelectorAll('button'))b.disabled=!data.pieces.length;status.textContent=data.pieces.length?'':'Place an NPC token on a map first.'}catch(e){status.textContent=e.message}}
   const form=control?.querySelector('form');form?.addEventListener('submit',async event=>{event.preventDefault();const status=control.querySelector('[role=status]');const data=new FormData(form);data.set('action',event.submitter.value);status.textContent='Saving control…';const controls=[...form.querySelectorAll('button')];controls.forEach(b=>b.disabled=true);try{const response=await fetch(form.getAttribute('action'),{method:'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin'});const result=await response.json();if(!response.ok)throw Error(result.error||'Control could not be saved.');status.textContent='Piece control saved.'}catch(e){status.textContent=e.message}finally{controls.forEach(b=>b.disabled=false)}});
   return;
  }
  const play=document.querySelector('div.playarea');
  const workshop=location.pathname.startsWith('/vault/');
  if(!play){if(workshop){document.body.classList.add('map-management');const bar=make('nav');bar.className='workshop-navigation';bar.setAttribute('aria-label','Map Workshop');bar.append(make('strong','Map Workshop'),link('Maps','/vault/map'),link('Token Library','/vault/token'),link('Return to Table','/session/table/septenture'));document.body.prepend(bar);const label=document.querySelector('[data-theme-control]');if(label){bar.append(label);document.querySelector('.septenture-theme-strip')?.remove()}for(const a of document.querySelectorAll('.wrapper a[href="/vault"]')){a.href='/session/table/septenture';a.textContent='Return to Table'}}return;}
  document.body.classList.add(workshop?'map-workspace':'live-board');
  const toolbar=make('nav');toolbar.className='board-toolbar';toolbar.setAttribute('aria-label',workshop?'Map Workshop':'Table controls');document.body.prepend(toolbar);
  if(workshop){
   toolbar.append(make('strong','Map Workshop'),link('Maps','/vault/map'),link('Token Library','/vault/token'));
   const back=link('Return to Table','/session/table/septenture');back.target='_self';toolbar.append(back);
   const label=document.querySelector('[data-theme-control]');if(label){toolbar.append(label);document.querySelector('.septenture-theme-strip')?.remove()}
   const native=document.querySelector('.topbar .open_menu');if(native){native.textContent='Map settings';native.setAttribute('aria-label','Map settings and import/export');}
   return;
  }
  const dm=play.getAttribute('is_dm')==='yes',nativeMenu=document.querySelector('div.topbar div.menu');
  const modal=make('dialog');modal.className='campaign-tool-dialog board-popup';modal.setAttribute('aria-labelledby','board-popup-title');const heading=make('h2');heading.id='board-popup-title';const close=button('Close',()=>modal.close()),body=make('div');modal.append(close,heading,body);document.body.append(modal);let opener;
  modal.addEventListener('keydown',e=>e.stopPropagation());modal.addEventListener('close',()=>opener?.focus());
  function open(title,source){opener=source||document.activeElement;heading.textContent=title;body.replaceChildren();if(!modal.open)modal.showModal()}
  function nativeAction(selector){document.querySelector(selector)?.click()}
  const status=make('div');status.className='board-placement';status.hidden=true;status.setAttribute('role','status');const message=make('span');status.append(message);document.body.append(status);let pending=null,tokenRequest=0;
  function cancelPlacement(){pending=null;status.hidden=true;play.classList.remove('placing-token')}
  status.append(button('Cancel placement',cancelPlacement));
  play.addEventListener('click',event=>{if(!pending)return;event.preventDefault();event.stopImmediatePropagation();const icon=pending;cancelPlacement();if(typeof window.object_create==='function')window.object_create(window.jQuery(icon),event.clientX,event.clientY)},true);
  addEventListener('keydown',e=>{if(e.key==='Escape'&&pending){cancelPlacement();e.preventDefault();e.stopImmediatePropagation()}},true);
  async function tokens(source){if(!dm)return;open('Place a token',source);const request=++tokenRequest;const filter=make('input');filter.type='search';filter.placeholder='Find a token';filter.setAttribute('aria-label','Find a token');const list=make('div');list.className='board-token-list';body.append(make('p','Choose a token, then click the map to place it. The popup closes while you choose its position.'),filter,list);
   let icons=[...document.querySelectorAll('div.library img.icon')];const loading=make('p','Loading token library…');loading.setAttribute('role','status');list.append(loading);
   try{const response=await fetch('/adventure/'+play.getAttribute('adventure_id')+'?library='+Date.now(),{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw Error();const html=new DOMParser().parseFromString(await response.text(),'text/html');const library=html.querySelector('div.library');if(library)icons=[...library.querySelectorAll('img.icon')]}catch{/* The loaded library remains usable during a transient outage. */}
   if(request!==tokenRequest||!modal.open||heading.textContent!=='Place a token')return;list.replaceChildren();
   for(const icon of icons){const name=icon.parentElement.querySelector('.name')?.textContent||'Token';const item=button(name,()=>{pending=icon;modal.close();message.textContent='Place '+name+': click the map. ';status.hidden=false;play.classList.add('placing-token')});const preview=icon.cloneNode();preview.removeAttribute('style');preview.removeAttribute('class');preview.alt='';item.prepend(preview);item.dataset.tokenName=name.toLowerCase();list.append(item)}
   if(!icons.length)list.append(make('p','Your token library is empty. Add reusable tokens in Token Library, then reopen this popup to place them.'));
   filter.addEventListener('input',()=>{for(const item of list.querySelectorAll('button'))item.hidden=!item.dataset.tokenName.includes(filter.value.toLowerCase())});const library=link('Manage Token Library','/vault/token');library.target='_blank';library.rel='noopener';body.append(library);
  }
  function tools(source){open(dm?'GM tools':'Table tools',source);
   if(dm){body.append(button('Place a token',()=>tokens(source)),button('Player control of NPC pieces',()=>{modal.close();if(parent!==self)parent.postMessage({type:'septenture-piece-control'},location.origin);else{const a=link('Open campaign Table for piece control','/session/table/septenture');body.append(a);modal.showModal()}}));}
   if(nativeMenu){for(const n of nativeMenu.querySelectorAll('button,a'))if(['Character sheets','Dice','Journals'].includes(n.textContent.trim()))n.hidden=true;nativeMenu.classList.add('board-native-menu');nativeMenu.style.display='block';body.append(nativeMenu);for(const selector of ['.show_collectables','.show_dice','.show_journal','.interface_color','.fullscreen','.map_image','a[href="/adventure"]'])for(const n of nativeMenu.querySelectorAll(selector))n.hidden=true;
    const recording=nativeMenu.querySelector('#session-recorder-panel');if(recording){let details=recording.closest('details');if(!details){details=make('details');details.append(make('summary','Recording view'));recording.before(details);details.append(recording)}}
    const map=nativeMenu.querySelector('select.map-selector');if(map){map.setAttribute('aria-label','Active map');}
   }
  }
  if(dm)toolbar.append(button('Tokens',e=>tokens(e.currentTarget)));
  for(const [label,kind]of [['Dice','dice'],['Sheets','sheets']])toolbar.append(button(label,e=>window.SeptentureTableTools?.open(kind,e.currentTarget)));
  if(dm){const sketch=button('Sketch tools',()=>{const shown=document.body.classList.toggle('show-sketch');sketch.setAttribute('aria-pressed',String(shown))});sketch.setAttribute('aria-pressed','false');toolbar.append(sketch)}
  const activity=button('Activity',()=>{const shown=document.body.classList.toggle('show-activity');activity.setAttribute('aria-pressed',String(shown))});activity.setAttribute('aria-pressed','false');toolbar.append(activity,button(dm?'GM tools':'Table tools',e=>tools(e.currentTarget)));
  if(dm){const workshopLink=link('Map Workshop ↗','/vault/map');workshopLink.target='_blank';workshopLink.rel='noopener';toolbar.append(workshopLink)}
  const expand=button('Expand board',async()=>{try{if(!document.fullscreenElement){await document.documentElement.requestFullscreen();expand.textContent='Exit fullscreen'}else{await document.exitFullscreen();expand.textContent='Expand board'}}catch{expand.textContent='Use browser fullscreen'}});toolbar.append(expand);document.addEventListener('fullscreenchange',()=>{expand.textContent=document.fullscreenElement?'Exit fullscreen':'Expand board'});
 });
})();
