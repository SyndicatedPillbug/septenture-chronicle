create schema auth;
create table auth.users(id uuid primary key);
create function auth.uid() returns uuid language sql stable as $$select nullif(current_setting('request.jwt.claim.sub',true),'')::uuid$$;
grant usage on schema auth to authenticated,anon;
grant execute on function auth.uid() to authenticated,anon;
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

create table public.campaigns(id text primary key,name text);
create table public.campaign_members(campaign_id text references public.campaigns(id),user_id uuid references auth.users(id),role text,primary key(campaign_id,user_id));
alter table public.character_profiles add campaign_id text references public.campaigns(id);
alter table public.character_profiles add character_details jsonb not null default '{}';
alter table public.character_profiles enable row level security;
alter table public.character_owners enable row level security;
alter table public.campaign_members enable row level security;
grant select on public.character_profiles,public.character_owners,public.campaign_members to authenticated;
grant update(display_name,portrait_path,background_path,music_path) on public.character_profiles to authenticated;
create policy read_profiles on public.character_profiles for select to authenticated using(true);
create policy read_own_owners on public.character_owners for select to authenticated using(user_id=auth.uid());
create policy read_own_membership on public.campaign_members for select to authenticated using(user_id=auth.uid());
create policy edit_owned_profiles on public.character_profiles for update to authenticated using(exists(select 1 from public.character_owners o where o.character_slug=slug and o.user_id=auth.uid()));
insert into auth.users values('11111111-1111-4111-8111-111111111111'),('22222222-2222-4222-8222-222222222222'),('33333333-3333-4333-8333-333333333333'),('44444444-4444-4444-8444-444444444444');
insert into public.campaigns values('septenture','Septenture'),('other','Other');
insert into public.campaign_members values('septenture','11111111-1111-4111-8111-111111111111','owner'),('septenture','22222222-2222-4222-8222-222222222222','player'),('other','33333333-3333-4333-8333-333333333333','gm'),('septenture','44444444-4444-4444-8444-444444444444','player');
