// A Chronicle session may renew only its already-open, configured table parent.
(async()=>{
 if(window.parent===window)return;
 const config=await (await fetch(new URL('tabletop-config.json',import.meta.url))).json();
 const tableOrigin=new URL(config.tabletopUrl).origin;
 const account=await (await fetch(new URL('community-config.json',import.meta.url))).json();
 const {createClient}=await import('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm');
 const client=createClient(account.url,account.publishableKey,{auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}});
 window.addEventListener('message',async event=>{
  if(event.origin!==tableOrigin||event.source!==window.parent||event.data?.type!=='septenture-renew-request'||typeof event.data.nonce!=='string'||!/^[a-f0-9-]{36}$/.test(event.data.nonce))return;
  const {data,error}=await client.auth.getSession();
  if(error||!data.session)return;
  window.parent.postMessage({type:'septenture-renew-proof',nonce:event.data.nonce,token:data.session.access_token},tableOrigin);
 });
 window.parent.postMessage({type:'septenture-renew-ready'},tableOrigin);
})();
