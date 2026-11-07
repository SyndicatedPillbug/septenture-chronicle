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
   addEventListener('dragover',e=>{if([...e.dataTransfer.types].includes('Files'))e.preventDefault()});
   addEventListener('drop',e=>{if(![...e.dataTransfer.types].includes('Files'))return;e.preventDefault();const image=e.dataTransfer.files[0];if(image)shell.contentWindow.postMessage({type:'septenture-scene-drop',image},location.origin)});
   const focus=active=>{document.body.classList.toggle('board-focus',active);shell.contentWindow.postMessage({type:'septenture-board-focus-state',active},location.origin)};
   addEventListener('message',e=>{if(e.origin!==location.origin||e.source!==shell.contentWindow)return;if(e.data?.type==='septenture-scene-backdrop'){const url=e.data.url;if(url===null||typeof url==='string'&&/^\/session\/scene\/image\?revision=[a-f0-9]{32}$/.test(url)){document.body.style.setProperty('--scene-backdrop',url?'url("'+url+'")':'none')}}if(e.data?.type==='septenture-board-focus')focus(e.data.active===true);if(e.data?.type==='septenture-tools-home'){control?.close();shell.contentWindow.postMessage({type:'septenture-tools-home'},location.origin)}});
   control?.querySelector('[data-control-home]')?.addEventListener('click',()=>{control.close();shell.contentWindow.postMessage({type:'septenture-tools-home'},location.origin)});
   addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('board-focus'))focus(false)});
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
  function open(title,source){setPainting(false);opener=source||document.activeElement;heading.textContent=title;if(nativeMenu?.parentElement===body)document.body.append(nativeMenu);body.replaceChildren();if(title!=='GM tools'&&title!=='Table tools')body.append(button('← Back to GM tools',()=>tools(source)));if(!modal.open)modal.showModal()}
  function nativeAction(selector){document.querySelector(selector)?.click()}
  const status=make('div');status.className='board-placement';status.hidden=true;status.setAttribute('role','status');const message=make('span');status.append(message);document.body.append(status);let pending=null,tokenRequest=0;
  let painting=false,virtualControl=false,paintButton;
  function setPainting(active){painting=active===true;if(paintButton){paintButton.textContent=painting?'Stop drawing':'Draw on map';paintButton.setAttribute('aria-pressed',String(painting))}play.classList.toggle('painting-map',painting);if(!painting&&virtualControl){window.ctrl_down=false;virtualControl=false}}
  play.addEventListener('mousedown',event=>{if(!dm||!painting||event.button!==0)return;virtualControl=!event.ctrlKey;if(virtualControl)window.ctrl_down=true},true);
  addEventListener('mouseup',event=>{if(virtualControl){window.ctrl_down=event.ctrlKey;virtualControl=false}});
  addEventListener('blur',()=>setPainting(false));
  addEventListener('keydown',event=>{if(event.key==='Escape'&&painting){setPainting(false);event.preventDefault();event.stopImmediatePropagation()}},true);
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
  function tools(source){if(nativeMenu?.parentElement===body)document.body.append(nativeMenu);open(dm?'GM tools':'Table tools',source);
   body.append(button('Dice',()=>{modal.close();window.SeptentureTableTools?.open('dice',source)}),button('Character sheets',()=>{modal.close();window.SeptentureTableTools?.open('sheets',source)}));
   if(dm){body.append(button('Place a token',()=>tokens(source)),button('Player control of NPC pieces',()=>{modal.close();if(parent!==self)parent.postMessage({type:'septenture-piece-control'},location.origin);else{const a=link('Open campaign Table for piece control','/session/table/septenture');body.append(a);modal.showModal()}}));}
   if(nativeMenu){for(const n of nativeMenu.querySelectorAll('button,a'))if(['Character sheets','Dice','Journals'].includes(n.textContent.trim()))n.hidden=true;nativeMenu.classList.add('board-native-menu');nativeMenu.style.display='block';body.append(nativeMenu);for(const selector of ['.show_collectables','.show_dice','.show_journal','.interface_color','.fullscreen','.map_image','a[href="/adventure"]'])for(const n of nativeMenu.querySelectorAll(selector))n.hidden=true;
    const recording=nativeMenu.querySelector('#session-recorder-panel');if(recording){let details=recording.closest('details');if(!details){details=make('details');details.append(make('summary','Recording view'));recording.before(details);details.append(recording)}}
    const map=nativeMenu.querySelector('select.map-selector');if(map){map.setAttribute('aria-label','Active map');}
   }
  }
  if(dm)toolbar.append(button('Tokens',e=>tokens(e.currentTarget)));
  for(const [label,kind]of [['Dice','dice'],['Sheets','sheets']])toolbar.append(button(label,e=>window.SeptentureTableTools?.open(kind,e.currentTarget)));
  if(dm){paintButton=button('Draw on map',()=>setPainting(!painting));paintButton.hidden=true;paintButton.setAttribute('aria-pressed','false');paintButton.title='Choose a color or terrain brush, then drag on the map. Stop drawing to pan again.';const sketch=button('Sketch tools',()=>{const shown=document.body.classList.toggle('show-sketch');sketch.setAttribute('aria-pressed',String(shown));paintButton.hidden=!shown;if(!shown)setPainting(false)});sketch.setAttribute('aria-pressed','false');toolbar.append(sketch,paintButton)}
  const activity=button('Chat & rolls',()=>{const shown=document.body.classList.toggle('show-activity');activity.setAttribute('aria-pressed',String(shown))});activity.setAttribute('aria-pressed','false');toolbar.append(activity,button(dm?'GM tools':'Table tools',e=>tools(e.currentTarget)));
  if(dm){const workshopLink=link('Map Workshop ↗','/vault/map');workshopLink.target='_blank';workshopLink.rel='noopener';toolbar.append(workshopLink)}
  addEventListener('septenture-tools-home',()=>tools());
  let focused=false;const setFocus=active=>{focused=active;document.body.classList.toggle('board-focus',active);expand.textContent=active?'Exit focus':'Focus board';expand.setAttribute('aria-pressed',String(active));window.dispatchEvent(new CustomEvent('septenture-focus',{detail:{active}}));if(parent!==self)parent.postMessage({type:'septenture-board-focus',active},location.origin)};
  const expand=button('Focus board',()=>setFocus(!focused));expand.setAttribute('aria-pressed','false');toolbar.append(expand);
  try{if(sessionStorage.getItem('septenture-return-focus')==='1'){sessionStorage.removeItem('septenture-return-focus');setFocus(true)}}catch{}
  addEventListener('message',e=>{if(e.origin!==location.origin||e.source!==parent)return;if(e.data?.type==='septenture-board-focus-state'){const changed=focused!==(e.data.active===true);focused=e.data.active===true;document.body.classList.toggle('board-focus',focused);expand.textContent=focused?'Exit focus':'Focus board';expand.setAttribute('aria-pressed',String(focused));if(changed)window.dispatchEvent(new CustomEvent('septenture-focus',{detail:{active:focused}}))}if(e.data?.type==='septenture-tools-home')tools()});
  addEventListener('keydown',e=>{if(e.key==='Escape'&&focused&&!document.querySelector('dialog[open]')){setFocus(false);e.preventDefault()}},true);
 });
})();
