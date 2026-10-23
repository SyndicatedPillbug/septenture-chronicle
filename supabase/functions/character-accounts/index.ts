import { createClient } from 'npm:@supabase/supabase-js@2'

const cors = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, apikey, content-type, x-client-info',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Content-Type': 'application/json',
  'Cache-Control': 'no-store',
}
const reply = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: cors })
const hash = async (token: string) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token)))).map(x => x.toString(16).padStart(2,'0')).join('')

Deno.serve(async req => {
  if (req.method === 'OPTIONS') return new Response(null, { headers: cors })
  if (req.method !== 'POST') return reply({error:'Use POST.'},405)
  if (Number(req.headers.get('content-length') || 0) > 4096) return reply({error:'Request too large.'},413)
  const admin = createClient(Deno.env.get('SUPABASE_URL')!,Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,{auth:{persistSession:false,autoRefreshToken:false}})
  try {
    const raw = await req.text()
    if (raw.length > 4096) return reply({error:'Request too large.'},413)
    const body = JSON.parse(raw)
    if (body.action === 'invite') {
      const bearer = req.headers.get('authorization')?.replace(/^Bearer /i,'')
      if (!bearer) return reply({error:'Sign in first.'},401)
      const {data:{user}} = await admin.auth.getUser(bearer)
      if (!user) return reply({error:'Sign in first.'},401)
      const {data:allowed} = await admin.from('community_admins').select('user_id').eq('user_id',user.id).maybeSingle()
      if (!allowed) return reply({error:'Only the site owner can create invitations.'},403)
      const {data:profile} = await admin.from('character_profiles').select('slug').eq('slug',body.slug).maybeSingle()
      if (!profile) return reply({error:'Choose an existing character.'},400)
      const token = crypto.randomUUID().replaceAll('-','') + crypto.randomUUID().replaceAll('-','')
      const {error} = await admin.from('community_invites').insert({token_hash:await hash(token),character_slug:profile.slug})
      if (error) throw error
      return reply({token,expiresInDays:7})
    }
    if (body.action !== 'redeem') return reply({error:'Unknown action.'},400)
    if (typeof body.token !== 'string' || !/^[a-f0-9]{64}$/.test(body.token) || typeof body.email !== 'string' || body.email.length>254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email) || typeof body.password !== 'string' || body.password.length<12 || body.password.length>128) return reply({error:'Use a valid invitation, email, and a password of 12–128 characters.'},400)
    const tokenHash = await hash(body.token)
    const claim = crypto.randomUUID()
    const {data:invitations,error:claimError} = await admin.rpc('claim_community_invite',{hash:tokenHash,claim})
    if (claimError) throw claimError
    const invitation = invitations?.[0]
    if (!invitation) return reply({error:'That invitation has expired or has already been used.'},400)
    let createdId: string | undefined
    try {
      // Possession of a private, single-use invitation establishes roster membership.
      // No email is sent. Email ownership is not verified by this invite flow.
      const {data,error} = await admin.auth.admin.createUser({email:body.email,password:body.password,email_confirm:true})
      if (error || !data.user) throw new Error('Account could not be created. If you already have one, ask the site owner to assign that account.')
      createdId=data.user.id
      const slugs = invitation.is_admin
        ? (await admin.from('character_profiles').select('slug').throwOnError()).data!.map(p=>p.slug)
        : [invitation.character_slug]
      const {error:ownerError} = await admin.from('character_owners').insert(slugs.map(character_slug=>({character_slug,user_id:createdId})))
      if (ownerError) throw ownerError
      if (invitation.is_admin) {
        const {error:adminError} = await admin.from('community_admins').insert({user_id:createdId})
        if (adminError) throw adminError
      }
      return reply({ok:true})
    } catch (error) {
      // Keep a consumed token consumed if rollback cannot be confirmed.
      let rolledBack=true
      if (createdId) { const {error:deleteError}=await admin.auth.admin.deleteUser(createdId); rolledBack=!deleteError }
      if (rolledBack) await admin.rpc('release_community_invite',{hash:tokenHash,claim})
      return reply({error: error instanceof Error && error.message.startsWith('Account could') ? error.message : 'Account setup failed. Please contact the site owner.'},400)
    }
  } catch {
    return reply({error:'The account service could not complete that request.'},500)
  }
})
