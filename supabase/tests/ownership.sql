-- Run against a fresh configured project. Every test record rolls back.
begin;
create temporary table community_test_context as select gen_random_uuid() as a,gen_random_uuid() as b;
grant select on community_test_context to anon,authenticated;
insert into auth.users(id) select a from community_test_context union all select b from community_test_context;
insert into public.character_owners(user_id,character_slug) select a,'page' from community_test_context union all select b,'ceridwen' from community_test_context;
set local role authenticated;
select set_config('request.jwt.claim.sub',(select a::text from community_test_context),true);
do $$ declare n int; begin
  update public.character_profiles set mood='ownership test' where slug='page';
  get diagnostics n=row_count;
  if n<>1 then raise exception 'Owner cannot edit own page'; end if;
  update public.character_profiles set mood='must not save' where slug='ceridwen';
  get diagnostics n=row_count;
  if n<>0 then raise exception 'Owner can edit another page'; end if;
  begin
    insert into public.character_owners select a,'legion' from community_test_context;
    raise exception 'Player can grant themselves ownership';
  exception when insufficient_privilege then null; end;
  begin
    update public.character_profiles set slug='hijacked' where slug='page';
    raise exception 'Player can rename protected slug';
  exception when insufficient_privilege then null; end;
  insert into public.character_guestbook(character_slug,name,message) values('page','Test','A'),('page','Test','B'),('page','Test','C');
  begin
    insert into public.character_guestbook(character_slug,name,message) values('page','Test','D');
    raise exception 'Rate limit failed';
  exception when raise_exception then
    if sqlerrm<>'Please wait a minute before leaving another note.' then raise; end if;
  end;
  begin
    insert into public.character_guestbook(character_slug,name,message,author_id) select 'page','Spoof','No',b from community_test_context;
    raise exception 'Player can spoof authorship';
  exception when insufficient_privilege then null; end;
  begin
    perform * from public.community_invites;
    raise exception 'Player can read private invites';
  exception when insufficient_privilege then null; end;
  insert into storage.objects(bucket_id,name) values('character-media','page/00000000-0000-4000-8000-000000000001.png');
  begin
    insert into storage.objects(bucket_id,name) values('character-media','ceridwen/00000000-0000-4000-8000-000000000001.png');
    raise exception 'Player can upload to another character';
  exception when insufficient_privilege then null; end;
end $$;
select set_config('request.jwt.claim.sub',(select b::text from community_test_context),true);
do $$ declare n int; begin
  delete from public.character_guestbook where character_slug='page';get diagnostics n=row_count;
  if n<>0 then raise exception 'Another player can delete notes on Page';end if;
end $$;
set local role anon;
select set_config('request.jwt.claim.sub','',true);
do $$ begin
  if(select count(*) from public.character_profiles)<>7 then raise exception 'Public profiles unreadable';end if;
  perform id,name,message,created_at from public.character_guestbook;
  begin
    update public.character_profiles set mood='Anonymous' where slug='page';
    raise exception 'Anonymous editing allowed';
  exception when insufficient_privilege then null;end;
  begin
    insert into public.character_guestbook(character_slug,name,message) values('page','Anonymous','No');
    raise exception 'Anonymous writing allowed';
  exception when insufficient_privilege then null;end;
  begin
    perform author_id from public.character_guestbook;
    raise exception 'Public can read private author ids';
  exception when insufficient_privilege then null;end;
end $$;
reset role;
rollback;
select 'PASS: ownership, protected columns, guestbook, moderation, rate limit, media, anonymous access' as result;
