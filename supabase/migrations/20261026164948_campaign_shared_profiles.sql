begin;
create table public.campaigns (
 id text primary key check (id ~ '^[a-z][a-z0-9-]{0,40}$'),
 name text not null
);
create table public.campaign_members (
 campaign_id text not null references public.campaigns(id),
 user_id uuid not null references auth.users(id) on delete cascade,
 role text not null default 'player' check (role in ('owner','gm','player')),
 primary key(campaign_id,user_id)
);
alter table public.campaigns enable row level security;
alter table public.campaign_members enable row level security;
revoke all on public.campaigns,public.campaign_members from anon,authenticated;
grant select on public.campaigns to anon,authenticated;
grant select on public.campaign_members to authenticated;
grant all on public.campaigns,public.campaign_members to service_role;
create policy campaign_read on public.campaigns for select to anon,authenticated using(true);
create policy membership_self on public.campaign_members for select to authenticated using(user_id=(select auth.uid()));
-- No authenticated INSERT/UPDATE/DELETE grants: roles are assigned administratively,
-- never through metadata, a profile field, an invitation parameter or a role selector.
insert into public.campaigns values ('septenture','Septenture');
insert into public.campaign_members(campaign_id,user_id,role)
 select 'septenture',user_id,'player' from public.character_owners group by user_id;
-- Resolve the already verified owner through the protected administrator record.
-- Fail closed if the existing owner cannot be identified uniquely.
do $$ begin
 if (select count(*) from public.community_admins a join auth.users u on u.id=a.user_id where lower(u.email)='odin@odinhalvorson.com') <> 1 then
  raise exception 'Expected campaign owner could not be uniquely identified';
 end if;
end $$;
insert into public.campaign_members(campaign_id,user_id,role)
 select 'septenture',a.user_id,'owner' from public.community_admins a
 join auth.users u on u.id=a.user_id where lower(u.email)='odin@odinhalvorson.com'
 on conflict(campaign_id,user_id) do update set role='owner';
alter table public.character_profiles add column campaign_id text not null default 'septenture' references public.campaigns(id);
alter table public.character_profiles add column character_details jsonb not null default '{}'::jsonb;
create function public.valid_shared_character_details(value jsonb) returns boolean
 language sql immutable security invoker set search_path='' as $$
 select jsonb_typeof(value)='object' and octet_length(value::text)<=100000 and not exists (
  select 1 from jsonb_each(case when jsonb_typeof(value)='object' then value else '{}'::jsonb end) d
  where d.key <> all(array['gender','species','age','height','weight','hair_color','eye_color','physical_desc','personality','strengths','ambitions','hobbies','spouse','children','father','mother','brothers','sisters','other_family','service_record'])
   or jsonb_typeof(d.value)<>'string' or char_length(d.value #>> '{}')>5000
 );
$$;
revoke all on function public.valid_shared_character_details(jsonb) from public;
grant execute on function public.valid_shared_character_details(jsonb) to anon,authenticated,service_role;
alter table public.character_profiles add constraint profile_details_object check(public.valid_shared_character_details(character_details));
grant update(character_details) on public.character_profiles to authenticated;
commit;
