// Served on the Chronicle origin so it uses the existing player session.
async function setupTabletop(){
const root=document.querySelector('[data-tabletop]');
if(root?.dataset.ready)return;if(root)root.dataset.ready='true';
if(root){
 const status=root.querySelector('[role=status]'),form=root.querySelector('form'),button=form.querySelector('button');
 let client,config;
 async function enter(){
  const {data,error}=await client.auth.getSession();
  if(error||!data.session){form.hidden=false;status.textContent='Sign in with your Septenture account to take your seat.';return;}
  // Full-page POST keeps the access token out of URLs, history, storage and referrers.
  const post=document.createElement('form');post.method='POST';post.action=new URL('/session/handoff',config.tabletopUrl).href;
  for(const [name,value] of Object.entries({token:data.session.access_token,campaign:config.campaign||'septenture'})){
   const field=document.createElement('input');field.type='hidden';field.name=name;field.value=value;post.append(field);
  }
  document.body.append(post);status.textContent='Opening the table…';post.submit();
 }
 try{
  const selfHosted=location.pathname==='/session/connect';
  const response=await fetch(selfHosted?'/session/config':new URL('tabletop-config.json',import.meta.url));if(!response.ok)throw Error('The table is not configured yet.');config=await response.json();
  const table=new URL(config.tabletopUrl);if(table.protocol!=='https:'&&!['localhost','127.0.0.1'].includes(table.hostname))throw Error('The table needs a secure connection.');
  const account=selfHosted?config.account:await (await fetch(new URL('community-config.json',import.meta.url))).json();
  if(selfHosted){const {createClient}=await import('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm');client=createClient(account.url,account.publishableKey,{auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}})}else{const {getAccount}=await import(new URL('./campaign-account.js',import.meta.url));client=(await getAccount()).client}
  form.addEventListener('submit',async e=>{e.preventDefault();button.disabled=true;try{
   const fields=new FormData(form);const {error}=await client.auth.signInWithPassword({email:String(fields.get('email')),password:String(fields.get('password'))});form.elements.password.value='';
   if(error)throw Error('That sign-in did not work. Check your email and password.');await enter();
  }catch(e){status.textContent=e.message}finally{button.disabled=false}});
  await enter();
 }catch(e){status.textContent=e.message;button.disabled=true}
}

}
setupTabletop();document.addEventListener('nav',setupTabletop);
