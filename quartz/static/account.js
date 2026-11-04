import {getAccount,accountPage} from './campaign-account.js'
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;return n}
const link=(label,url)=>{const a=el('a',label);a.href=url;return a}
let cleanup=()=>{}
async function boot(){
 cleanup();let active=true,subscription;cleanup=()=>{active=false;subscription?.unsubscribe()}
 const nav=document.querySelector('.site-nav .site-links'),root=document.querySelector('[data-campaign-account]')
 const accountLink=link('ACCOUNT',accountPage().href);accountLink.dataset.accountNav='true'
 if(nav&&!nav.querySelector('[data-account-nav]'))nav.append(accountLink)
 try{
  const {client,config}=await getAccount();if(!active)return
  const status=root?.querySelector('[role=status]'),body=root?.querySelector('[data-account-body]')
  async function show(){
   const {data,error}=await client.auth.getSession();if(error)throw error;if(!active)return
   const session=data.session;accountLink.textContent=session?'MY ACCOUNT':'PLAYER LOGIN'
   if(!root)return;body.replaceChildren();status.textContent=session?'Signed in as '+session.user.email:'Sign in to Septenture.'
   const addForm=(title,fields,submitText,action)=>{
    const form=el('form'),heading=el('h2',title);form.append(heading);const controls={}
    for(const [name,label,type]of fields){const wrap=el('label',label+' '),input=el('input');input.name=name;input.type=type;input.required=true;input.autocomplete=type==='password'?(name==='newPassword'?'new-password':'current-password'):'email';if(name==='newPassword'){input.minLength=12;input.maxLength=128}wrap.append(input);form.append(wrap);controls[name]=input}
    const button=el('button',submitText);button.type='submit';form.append(button)
    form.addEventListener('submit',async event=>{event.preventDefault();button.disabled=true;try{await action(controls)}catch(error){status.textContent=error.message||'That action did not finish. Please try again.'}finally{for(const input of Object.values(controls))if(input.type==='password')input.value='';button.disabled=false}});body.append(form)
   }
   if(!session){
    addForm('Take your place',[['email','Email','email'],['password','Password','password']],'Sign in',async fields=>{const {error}=await client.auth.signInWithPassword({email:fields.email.value,password:fields.password.value});if(error)throw Error('That sign-in did not work. Check your email and password.');await show()})
    addForm('Recover your account',[['email','Email','email']],'Send recovery email',async fields=>{const {error}=await client.auth.resetPasswordForEmail(fields.email.value,{redirectTo:accountPage().href});if(error)throw Error('The recovery email could not be requested. Please try again later.');status.textContent='If that address has an account, a recovery link is on its way. Open it in this browser.'})
    return
   }
   const characters=el('section'),characterHeading=el('h2','Character pages you can edit'),characterDescription=el('p','Public profile editing access. Private sheet ownership and Table control are managed separately.');characters.append(characterHeading,characterDescription);body.append(characters)
   const [{data:assignments,error:ownershipError},{data:memberships,error:membershipError}]=await Promise.all([
    client.from('character_owners').select('character_slug').eq('user_id',session.user.id),
    client.from('campaign_members').select('role').eq('campaign_id',config.campaignId||'septenture').eq('user_id',session.user.id),
   ])
   if(!active)return
   if(!membershipError&&memberships?.some(member=>member.role==='owner'||member.role==='gm')){characterHeading.textContent='Campaign character pages';characterDescription.textContent='Your campaign role includes editing these public profiles. Each player’s private sheet ownership and Table controls are managed separately.'}
   if(ownershipError)characters.append(el('p','Your character list could not load. Please try again.'))
   else{
    const {data:profiles,error:profileError}=await client.from('character_profiles').select('slug,display_name').eq('campaign_id',config.campaignId||'septenture')
    if(!active)return
    if(profileError)characters.append(el('p','Character names are temporarily unavailable.'))
    const list=el('ul');for(const assignment of assignments||[]){const profile=profiles?.find(p=>p.slug===assignment.character_slug);if(!profile)continue;const li=el('li');const url=new URL('../the-cast/character/',accountPage());url.searchParams.set('character',profile.slug);li.append(link(profile.display_name,url.href));list.append(li)}
    characters.append(list);if(!list.children.length)characters.append(el('p','No public character profiles are available to edit with this account yet.'))
   }
   body.append(link('New story',new URL('../fiction/login/?compose=story',accountPage()).href),document.createTextNode(' · '),link('My private game notes',new URL('../fiction/login/?compose=notes',accountPage()).href),document.createTextNode(' · '));
   body.append(link('Open the writing desk',new URL('../fiction/login/',accountPage()).href),document.createTextNode(' · '),link('Take your seat at the Table',new URL('../tabletop/',accountPage()).href))
   addForm('Change your password',[['newPassword','New password','password']],'Save new password',async fields=>{const {error}=await client.auth.updateUser({password:fields.newPassword.value});if(error)throw Error('The password could not be changed. Reopen your recovery link or sign in again, then retry.');status.textContent='Your password has been changed.'})
   const signout=el('button','Sign out of this browser');signout.type='button';signout.addEventListener('click',async()=>{const {error}=await client.auth.signOut({scope:'local'});if(error)status.textContent='Sign-out did not finish. Please retry.';else await show()});body.append(signout)
   body.append(el('p','Leaving the Table keeps your player account signed in. Close private writing and Table tabs when you finish on a shared computer.'))
  }
  let rendering=false,again=false
  const update=async()=>{if(rendering){again=true;return}rendering=true;try{do{again=false;await show()}while(again&&active)}catch(error){if(status)status.textContent=error.message}finally{rendering=false}}
  subscription=client.auth.onAuthStateChange(event=>{if(['INITIAL_SESSION','SIGNED_IN','SIGNED_OUT','PASSWORD_RECOVERY'].includes(event))setTimeout(()=>{if(active)update()},0)}).data.subscription
  await update()
 }catch{if(root)root.querySelector('[role=status]').textContent='Player accounts are temporarily unavailable. Please try again later.'}
}
document.addEventListener('nav',boot)
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot()
