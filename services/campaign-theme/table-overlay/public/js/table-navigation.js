// One compact play bar; existing authenticated tools keep their original handlers.
(()=>{const ready=fn=>document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn,{once:true}):fn();ready(()=>{
 const toolbar=document.querySelector('.live-board .board-toolbar');if(!toolbar)return;
 const play=document.querySelector('.playarea'),gm=play.getAttribute('is_dm')==='yes';
 const el=(tag,text)=>{const n=document.createElement(tag);if(text)n.textContent=text;return n};
 const originals=[...toolbar.children],byName=name=>originals.find(n=>n.textContent===name),original={scene:byName('Scene image'),sketch:byName('Sketch tools'),draw:byName('Draw on map'),advanced:byName(gm?'GM tools':'Table tools')};
 const stash=el('div');stash.className='nav-original-controls';stash.hidden=true;stash.append(...originals);document.body.append(stash);
 const main=el('div');main.className='nav-main-tools';const fixed=el('div');fixed.className='nav-fixed-tools';
 const button=(name,run)=>{const b=el('button',name);b.type='button';b.addEventListener('click',async()=>{if(b.disabled)return;b.disabled=true;try{await run()}catch(e){window.dispatchEvent(new CustomEvent('septenture:connection',{detail:{state:'action-failed',message:e.message}}))}finally{b.disabled=false}});return b};
 const open=run=>{window.dispatchEvent(new CustomEvent('septenture:tool-start',{detail:{owner:'dialog'}}));return run()};
 const ruler=button('Ruler',()=>window.SeptentureStage.beginMeasurement());ruler.title='Measure between two map points';ruler.className='nav-ruler';
 const templates=button('Templates',()=>open(()=>window.SeptentureStage.open('quick-measure')));templates.title='Circle, cone and rectangle';
 const dice=byName('Dice'),sheet=byName('Sheets'),chat=byName('Chat & rolls');if(sheet)sheet.textContent=gm?'Sheets':'My sheet';
 for(const node of [dice,sheet])node?.addEventListener('click',()=>window.dispatchEvent(new CustomEvent('septenture:tool-start',{detail:{owner:'dialog'}})),true);
 for(const node of [dice,ruler,templates,sheet,chat])if(node)main.append(node);
 const tools=button('Tools',()=>open(()=>window.SeptenturePlayKit.open('home')));tools.className='nav-tools-button';tools.title='Find play tools and GM preparation';
 const focus=originals.find(n=>n.classList.contains('board-focus-toggle'));if(focus)focus.title='Focus changes only through this button';fixed.append(tools);if(focus)fixed.append(focus);toolbar.replaceChildren(main,fixed);toolbar.classList.add('modern-board-toolbar');toolbar.id='table-play-toolbar';document.body.classList.add('has-table-navigation');
 // Route every native context-menu ruler to the same private, saved measurement tool.
 // Markers are temporary pointer signals, anchored at the pin tip in map coordinates.
 window.marker_create=function(x,y,name=null){const host=play.querySelector('.markers');if(!host)return;const marker=el('div');marker.className='marker';const size=Number(play.getAttribute('grid_cell_size'))||50;Object.assign(marker.style,{position:'absolute',left:x+'px',top:y+'px',width:size+'px',height:size+'px',transform:'translate(-50%, -100%)',pointerEvents:'none',zIndex:'200000'});const image=el('img');image.src='/images/marker.png';image.draggable=false;image.style.width=size+'px';image.style.height=size+'px';marker.append(image);if(name){const label=el('span',name);Object.assign(label.style,{position:'absolute',bottom:'100%',marginBottom:'3px'});marker.append(label)}host.append(marker);setTimeout(()=>marker.remove(),5000)};
 play.addEventListener('dragstart',e=>{if(e.target.closest?.('.marker'))e.preventDefault()},true);
 const nativeContext=window.context_menu_show;
 if(nativeContext)window.context_menu_show=function(obj,event,entries,callback,options){const origin={clientX:event.clientX,clientY:event.clientY};const items={...entries};if(items.sea){delete items.sea;items.stage_templates={name:'Area templates',icon:'fa-magic'}}if(items.distance)items.measurements={name:'Manage measurements',icon:'fa-map-signs'};return nativeContext.call(this,obj,event,items,function(key){if(key==='marker'){const rect=play.getBoundingClientRect(),x=Math.max(0,origin.clientX-rect.left+play.scrollLeft-1),y=Math.max(0,origin.clientY-rect.top+play.scrollTop-1);window.marker_create(x,y);window.websocket_send?.({action:'marker',pos_x:x,pos_y:y});return}if(key==='distance'){window.measuring_stop?.();window.SeptentureStage.beginMeasurement('ruler',false,origin).catch(e=>window.dispatchEvent(new CustomEvent('septenture:connection',{detail:{state:'action-failed',message:e.message}})));return}if(key==='stage_templates'){window.SeptentureStage.open('quick-measure');return}if(key==='measurements'){window.SeptentureStage.open('quick-measure');return}return callback.apply(this,arguments)},options)};
 // Keep drawing's stop button visible while drawing; selecting another tool stops it.
 const updateDraw=()=>{const draw=original.draw;if(!draw)return;if(draw.getAttribute('aria-pressed')==='true'){if(draw.parentElement!==main)main.append(draw)}else if(draw.parentElement===main)stash.append(draw)};
 if(original.draw)new MutationObserver(updateDraw).observe(original.draw,{attributes:true,attributeFilter:['aria-pressed']});
 window.SeptentureNavigation={invoke:key=>{const target=original[key];if(!target)throw Error('That control is unavailable in this view.');if(key==='draw'){if(!document.body.classList.contains('show-sketch'))original.sketch?.click();target.hidden=false}target.click()}};
 // The duplicate shortcuts remain wired for existing dialogs, outside the visible bar.
 const quick=document.querySelector('.kit-quickbar');if(quick){for(const node of quick.children)if(node.tagName==='BUTTON'&&['Sheets','My sheet','Play tools'].includes(node.textContent))node.classList.add('nav-duplicate');}
 let activeOwner='';addEventListener('septenture:tool-start',e=>{activeOwner=e.detail?.owner||'';ruler.setAttribute('aria-pressed',String(activeOwner==='stage'&&e.detail?.shape==='ruler'));});
 // A finished or cancelled measurement clears the pressed state without affecting focus.
 new MutationObserver(()=>{if(activeOwner==='stage'&&!document.querySelector('.stage-picking'))ruler.setAttribute('aria-pressed','false')}).observe(play,{attributes:true,attributeFilter:['class']});
});})();
