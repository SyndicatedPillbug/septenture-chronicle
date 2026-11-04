// All Chronicle features share one client and one session-refresh owner.
export const SDK='https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.118.0/+esm'
let pending
export function getAccount(){
 if(!pending)pending=(async()=>{
  const response=await fetch(new URL('community-config.json',import.meta.url),{cache:'no-store'})
  if(!response.ok)throw Error('Player accounts are temporarily unavailable.')
  const config=await response.json()
  const {createClient}=await import(SDK)
  const client=createClient(config.url,config.publishableKey,{auth:{persistSession:true,autoRefreshToken:true,detectSessionInUrl:true,flowType:'pkce'}})
  return {client,config}
 })().catch(error=>{pending=undefined;throw error})
 return pending
}
export const accountPage=()=>new URL('../account/',import.meta.url)
