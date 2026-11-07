/* A WebSocket-shaped adapter for Cauldron's authenticated HTTP event channel.
   The unrestricted upstream relay is never contacted by integrated sessions. */
(() => {
 window.TABLETOP_HTTP_TRANSPORT=true;
 const NativeSocket=window.WebSocket;
 function connection(state,message){window.dispatchEvent(new CustomEvent('septenture:connection',{detail:{state,message}}))}
 if(window.jQuery)jQuery.ajaxPrefilter((options,original,xhr)=>{
  if(options.url?.startsWith('/object/'))xhr.setRequestHeader('X-Tabletop-CSRF',window.TABLETOP_CSRF||'');
 });
 class TableSocket {
  constructor(){this.readyState=0;this.cursor=0;this.queue=Promise.resolve();this.closed=false;this.lastPieces=null;this.controller=new AbortController();setTimeout(()=>this.poll(true),0)}
  async poll(init=false){
   if(this.closed)return;
   try{
    const response=await fetch('/session/events?'+(init?'init=1':'after='+this.cursor),{credentials:'include',cache:'no-store',signal:this.controller.signal});
    if(response.status===401||response.status===403){connection('expired','Your table session needs refreshing. Reopen the Table to reconnect.');this.close();return}
    if(!response.ok)throw Error('Connection unavailable');const data=await response.json();this.cursor=data.cursor;if(data.csrf)window.TABLETOP_CSRF=data.csrf;
    if(data.mapId&&typeof map_id!=='undefined'&&map_id!==null&&Number(map_id)!==Number(data.mapId)){try{if(document.body.classList.contains('board-focus'))sessionStorage.setItem('septenture-return-focus','1')}catch{}location.assign('/adventure/'+adventure_id);return;}
    if(Array.isArray(data.pieces)&&typeof grid_cell_size!=='undefined'){
     const keys=data.pieces.map(p=>p.id).sort().join(',');
     if(this.pieceKeys!==undefined&&this.pieceKeys!==keys){try{if(document.body.classList.contains('board-focus'))sessionStorage.setItem('septenture-return-focus','1')}catch{}location.reload();return;}this.pieceKeys=keys;
     const serialized=JSON.stringify(data.pieces);
     if(serialized!==this.lastPieces){this.lastPieces=serialized;for(const piece of data.pieces){
      const node=document.getElementById(piece.id);if(!node||node.classList.contains('ui-draggable-dragging'))continue;
      node.style.left=(Number(piece.pos_x)*grid_cell_size)+'px';node.style.top=(Number(piece.pos_y)*grid_cell_size)+'px';
      for(const key of ['armor_class','hitpoints','damage','rotation'])node.setAttribute(key,piece[key]);
     }}
    }
    connection('connected','Connected');
    if(this.readyState!==1){this.readyState=1;this.initializing=true;try{this.onopen?.({})}finally{this.initializing=false}}
    for(const event of data.events){if(event.action==='reload'){try{if(document.body.classList.contains('board-focus'))sessionStorage.setItem('septenture-return-focus','1')}catch{}location.reload();return;}if(event.action?.startsWith('draw_')&&event.event_id<=(window.KIT_DRAW_CURSOR||0))continue;this.onmessage?.({data:JSON.stringify(event)});}
    this.timer=setTimeout(()=>this.poll(),750);
   }catch(e){if(!this.closed){connection('reconnecting','Connection interrupted. Reconnecting…');this.timer=setTimeout(()=>this.poll(init),2000)}}
  }
  send(raw){
   if(this.closed)return;
   const message=JSON.parse(raw),action=message.action;if(this.initializing&&(action==='draw_clear'||action==='say'&&message.name===null))return;
   this.queue=this.queue.then(async()=>{
    const response=await fetch('/session/events',{method:'POST',credentials:'include',cache:'no-store',headers:{'Content-Type':'application/json','X-Tabletop-CSRF':window.TABLETOP_CSRF||''},body:raw,signal:this.controller.signal});
    if(response.status===401){connection('expired','Your table session needs refreshing. Reopen the Table to reconnect.');this.close();return}
    // Unsupported legacy effects are rejected by the server; preserve the live table.
    if(response.status===403){let detail;try{detail=await response.json()}catch{};const action=JSON.parse(raw).action;const labels={draw_brush:'Terrain brush',draw_move:'Drawing',draw_line:'Drawing',move:'Move piece',rotate:'Rotate piece'};connection('action-failed',(labels[action]||'Table action')+': '+(detail?.error||'This control is unavailable for this account.'));return}
    if(!response.ok)throw Error('Event was not delivered');
    if(['draw_line','draw_clear','fill_texture'].includes(action))window.dispatchEvent(new Event('septenture:drawing-committed'));
   }).catch((e)=>{if(!this.closed)connection('action-failed','Your last action was not confirmed. Check the board or chat before trying it again.')});
  }
  close(){if(this.closed)return;this.closed=true;this.readyState=3;clearTimeout(this.timer);this.controller.abort();this.onclose?.({code:1000})}
 }
 window.WebSocket=class extends TableSocket {constructor(url,...args){if(!String(url).includes('/websocket'))return new NativeSocket(url,...args);super()}};
 for(const [key,value] of Object.entries({CONNECTING:0,OPEN:1,CLOSING:2,CLOSED:3}))window.WebSocket[key]=value;
 window.addEventListener('pagehide',()=>{});
})();
