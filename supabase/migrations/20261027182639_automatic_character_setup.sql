-- The privileged implementation is deliberately outside the exposed API schema.
create schema if not exists septenture_private;
revoke all on schema septenture_private from public;
grant usage on schema septenture_private to authenticated;
create table septenture_private.character_setup (
  operation_id uuid primary key,
  actor_id uuid not null references auth.users(id),
  campaign_id text not null references public.campaigns(id),
  owner_id uuid not null references auth.users(id),
  initial_name text not null,
  profile_slug text not null unique references public.character_profiles(slug),
  created_at timestamptz not null default now()
);
alter table septenture_private.character_setup enable row level security;
revoke all on septenture_private.character_setup from public,anon,authenticated;
create function septenture_private.create_shared_character_impl(operation uuid,campaign text,owner_id uuid,character_name text)
returns text language plpgsql security definer set search_path='' as $$
declare actor uuid := auth.uid(); existing septenture_private.character_setup%rowtype; new_slug text;
begin
  if actor is null or not exists(select 1 from public.campaign_members m where m.campaign_id=campaign and m.user_id=actor and m.role in ('owner','gm')) then
    raise exception 'Only this campaign owner or GM can create shared characters.' using errcode='42501';
  end if;
  if operation is null or owner_id is null or character_name is null or char_length(btrim(character_name)) not between 1 and 80 then
    raise exception 'Choose a character name and owning player.' using errcode='22023';
  end if;
  if not exists(select 1 from public.campaign_members m where m.campaign_id=campaign and m.user_id=owner_id) then
    raise exception 'Choose an owning player in this campaign.' using errcode='42501';
  end if;
  -- Serialize the operation across repeated HTTP requests, including lost responses.
  perform pg_advisory_xact_lock(hashtextextended(operation::text,0));
  select * into existing from septenture_private.character_setup s where s.operation_id=operation;
  if found then
    if existing.actor_id<>actor or existing.campaign_id<>campaign or existing.owner_id<>owner_id or existing.initial_name<>btrim(character_name) then
      raise exception 'This setup request belongs to a different character.' using errcode='42501';
    end if;
    return existing.profile_slug;
  end if;
  new_slug:='pc-'||translate(replace(operation::text,'-',''),'0123456789','ghijklmnop');
  insert into public.character_profiles(slug,campaign_id,display_name,tagline,heading,bio,mood,location,interests,theme,accent,music_title,character_details)
    values(new_slug,campaign,btrim(character_name),'',btrim(character_name),'','','','','moon',null,'','{}');
  insert into public.character_owners(user_id,character_slug) values(owner_id,new_slug);
  -- Existing campaign owner/GM editing rights are retained for the new public page.
  insert into public.character_owners(user_id,character_slug)
    select m.user_id,new_slug from public.campaign_members m where m.campaign_id=campaign and m.role in ('owner','gm') on conflict do nothing;
  insert into septenture_private.character_setup(operation_id,actor_id,campaign_id,owner_id,initial_name,profile_slug)
    values(operation,actor,campaign,owner_id,btrim(character_name),new_slug);
  return new_slug;
end $$;
revoke all on function septenture_private.create_shared_character_impl(uuid,text,uuid,text) from public,anon;
grant execute on function septenture_private.create_shared_character_impl(uuid,text,uuid,text) to authenticated;
create function public.create_shared_character(operation uuid,campaign text,owner_id uuid,character_name text)
returns text language sql security invoker set search_path='' as $$
  select septenture_private.create_shared_character_impl(operation,campaign,owner_id,character_name)
$$;
revoke all on function public.create_shared_character(uuid,text,uuid,text) from public,anon;
grant execute on function public.create_shared_character(uuid,text,uuid,text) to authenticated;
create function septenture_private.character_setup_members_impl(campaign text)
returns table(subject uuid,label text) language plpgsql stable security definer set search_path='' as $$
begin
  if auth.uid() is null or not exists(select 1 from public.campaign_members m where m.campaign_id=campaign and m.user_id=auth.uid() and m.role in ('owner','gm')) then
    raise exception 'Only this campaign owner or GM can create characters.' using errcode='42501';
  end if;
  return query select m.user_id,
    case when m.role in ('owner','gm') then 'Game Master' else coalesce((select min(p.display_name) from public.character_profiles p join public.character_owners o on o.character_slug=p.slug where p.campaign_id=campaign and o.user_id=m.user_id),'Player') end
    from public.campaign_members m where m.campaign_id=campaign order by m.user_id;
end $$;
revoke all on function septenture_private.character_setup_members_impl(text) from public,anon;
grant execute on function septenture_private.character_setup_members_impl(text) to authenticated;
create function public.character_setup_members(campaign text)
returns table(subject uuid,label text) language sql stable security invoker set search_path='' as $$
  select * from septenture_private.character_setup_members_impl(campaign)
$$;
revoke all on function public.character_setup_members(text) from public,anon;
grant execute on function public.character_setup_members(text) to authenticated;
