// A Chronicle session may renew only its already-open, configured table parent.
(async()=>{
 if(window.parent===window)return;
 const config=await (await fetch(new URL('tabletop-config.json',import.meta.url))).json();
 const fiction=await (await fetch(new URL('fiction-config.json',import.meta.url))).json();
 const allowed=new Set([new URL(config.tabletopUrl).origin,new URL(fiction.novaUrl).origin]);
 let parentOrigin;try{parentOrigin=new URL(document.referrer).origin}catch{return}
 if(!allowed.has(parentOrigin))return;
 const account=await (await fetch(new URL('community-config.json',import.meta.url))).json();
 const {getAccount}=await import(new URL('./campaign-account.js',import.meta.url));const {client}=await getAccount();
 window.addEventListener('message',async event=>{
  if(event.origin!==parentOrigin||event.source!==window.parent||event.data?.type!=='septenture-renew-request'||typeof event.data.nonce!=='string'||!/^[a-f0-9-]{36}$/.test(event.data.nonce))return;
  const {data,error}=await client.auth.getSession();
  if(error||!data.session)return;
  window.parent.postMessage({type:'septenture-renew-proof',nonce:event.data.nonce,token:data.session.access_token},parentOrigin);
 });
 window.parent.postMessage({type:'septenture-renew-ready'},parentOrigin);
})();
