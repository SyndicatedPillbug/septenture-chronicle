-- This table is intentionally server-only, including for signed-in players.
create policy invites_server_only on public.community_invites for all to anon,authenticated using(false) with check(false);
grant all on public.character_profiles,public.character_owners,public.community_admins,public.character_guestbook,public.community_invites to service_role;
