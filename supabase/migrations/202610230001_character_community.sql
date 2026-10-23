-- Public pages, private ownership, and a shared guestbook.
create table public.character_profiles (
  slug text primary key check (slug ~ '^[a-z][a-z-]{0,40}$'),
  display_name text not null check (char_length(display_name) between 1 and 80),
  tagline text not null check (char_length(tagline) <= 160),
  heading text not null check (char_length(heading) <= 160),
  bio text not null check (char_length(bio) <= 5000),
  mood text not null check (char_length(mood) <= 160),
  location text not null check (char_length(location) <= 160),
  interests text not null check (char_length(interests) <= 1200),
  theme text not null check (theme in ('moon','candle','water','hive','rose','watch','powder')),
  accent text check (accent ~ '^#[0-9a-fA-F]{6}$'),
  music_title text not null default '' check (char_length(music_title) <= 160),
  portrait_path text check (portrait_path is null or (portrait_path like slug || '/%' and portrait_path ~ '^[a-z-]+/[a-f0-9-]+\.(jpg|png|webp)$')),
  background_path text check (background_path is null or (background_path like slug || '/%' and background_path ~ '^[a-z-]+/[a-f0-9-]+\.(jpg|png|webp)$')),
  music_path text check (music_path is null or (music_path like slug || '/%' and music_path ~ '^[a-z-]+/[a-f0-9-]+\.(mp3|ogg|wav|m4a|aac)$')),
  updated_at timestamptz not null default now()
);
create table public.character_owners (
  user_id uuid not null references auth.users(id) on delete cascade,
  character_slug text not null references public.character_profiles(slug) on delete cascade,
  primary key(user_id, character_slug)
);
create index character_owners_slug_idx on public.character_owners(character_slug);
create table public.community_admins (
  user_id uuid primary key references auth.users(id) on delete cascade
);
create table public.character_guestbook (
  id uuid primary key default gen_random_uuid(),
  character_slug text not null references public.character_profiles(slug) on delete cascade,
  author_id uuid not null default auth.uid() references auth.users(id) on delete cascade,
  name text not null check (char_length(trim(name)) between 1 and 60),
  message text not null check (char_length(trim(message)) between 1 and 600),
  created_at timestamptz not null default now()
);
create index character_guestbook_page_idx on public.character_guestbook(character_slug, created_at desc);
create index character_guestbook_author_idx on public.character_guestbook(author_id, created_at desc);
create table public.community_invites (
  token_hash text primary key,
  character_slug text references public.character_profiles(slug) on delete cascade,
  is_admin boolean not null default false,
  expires_at timestamptz not null default now() + interval '7 days',
  claimed_at timestamptz,
  claim_id uuid,
  check (character_slug is not null or is_admin)
);
alter table public.character_profiles enable row level security;
alter table public.character_owners enable row level security;
alter table public.community_admins enable row level security;
alter table public.character_guestbook enable row level security;
alter table public.community_invites enable row level security;
revoke all on public.character_profiles, public.character_owners, public.community_admins, public.character_guestbook, public.community_invites from anon, authenticated;
grant select on public.character_profiles to anon, authenticated;
grant update(display_name,tagline,heading,bio,mood,location,interests,theme,accent,music_title,portrait_path,background_path,music_path) on public.character_profiles to authenticated;
grant select on public.character_owners, public.community_admins to authenticated;
grant select(id,character_slug,name,message,created_at) on public.character_guestbook to anon;
grant select on public.character_guestbook to authenticated;
grant insert(character_slug,name,message) on public.character_guestbook to authenticated;
grant delete on public.character_guestbook to authenticated;
-- Owner identifiers never appear in the public API's guestbook projection.
create policy profile_read on public.character_profiles for select to anon, authenticated using (true);
create policy profile_update on public.character_profiles for update to authenticated
  using (exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=slug))
  with check (exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=slug));
create policy owners_self on public.character_owners for select to authenticated using (user_id=(select auth.uid()));
create policy admins_self on public.community_admins for select to authenticated using (user_id=(select auth.uid()));
create policy guestbook_read on public.character_guestbook for select to anon, authenticated using (true);
create policy guestbook_insert on public.character_guestbook for insert to authenticated with check (
  author_id=(select auth.uid()) and exists(select 1 from public.character_owners where user_id=(select auth.uid()))
);
create policy guestbook_delete on public.character_guestbook for delete to authenticated using (
  author_id=(select auth.uid()) or exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=character_guestbook.character_slug)
);
create function public.character_profile_timestamp() returns trigger language plpgsql set search_path='' as $$
begin new.updated_at=clock_timestamp(); return new; end $$;
create trigger character_profile_timestamp before update on public.character_profiles for each row execute function public.character_profile_timestamp();
create function public.guestbook_post_limit() returns trigger language plpgsql security definer set search_path='' as $$
begin
  perform pg_advisory_xact_lock(hashtextextended(new.author_id::text, 0));
  if (select count(*) from public.character_guestbook where author_id=new.author_id and created_at > now()-interval '1 minute') >= 3 then
    raise exception 'Please wait a minute before leaving another note.';
  end if;
  return new;
end $$;
create trigger guestbook_post_limit before insert on public.character_guestbook for each row execute function public.guestbook_post_limit();
revoke all on function public.character_profile_timestamp(), public.guestbook_post_limit() from public, anon, authenticated;

insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
values('character-media','character-media',true,20971520,array['image/jpeg','image/png','image/webp','audio/mpeg','audio/ogg','audio/wav','audio/x-wav','audio/mp4','audio/aac']);
create policy character_media_upload on storage.objects for insert to authenticated with check (
 bucket_id='character-media' and exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=(storage.foldername(name))[1])
);
create policy character_media_remove on storage.objects for delete to authenticated using (
 bucket_id='character-media' and exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=(storage.foldername(name))[1])
);
create policy character_media_owner_read on storage.objects for select to authenticated using (
 bucket_id='character-media' and exists(select 1 from public.character_owners o where o.user_id=(select auth.uid()) and o.character_slug=(storage.foldername(name))[1])
);
-- Invite secrets are hashed; only the authenticated server function can claim them.
create function public.claim_community_invite(hash text, claim uuid)
returns table(character_slug text,is_admin boolean) language sql security definer set search_path='' as $$
 update public.community_invites set claimed_at=now(),claim_id=claim
 where token_hash=hash and claimed_at is null and expires_at>now()
 returning character_slug,is_admin;
$$;
create function public.release_community_invite(hash text, claim uuid)
returns void language sql security definer set search_path='' as $$
 update public.community_invites set claimed_at=null,claim_id=null where token_hash=hash and claim_id=claim;
$$;
revoke all on function public.claim_community_invite(text,uuid), public.release_community_invite(text,uuid) from public, anon, authenticated;
grant execute on function public.claim_community_invite(text,uuid), public.release_community_invite(text,uuid) to service_role;
