\set ON_ERROR_STOP on
set role authenticated;
select set_config('request.jwt.claim.sub','11111111-1111-4111-8111-111111111111',false);
select public.create_shared_character('aaaaaaaa-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Future PC');
select public.create_shared_character('aaaaaaaa-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Future PC');
do $$ begin
  if (select count(*) from public.character_profiles)<>1 then raise exception 'Retry duplicated profile';end if;
  begin perform public.create_shared_character('aaaaaaaa-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Changed payload');raise exception 'Expected mismatch denial';exception when insufficient_privilege then null;end;
  begin perform public.create_shared_character('bbbbbbbb-1111-4111-8111-111111111111','septenture','33333333-3333-4333-8333-333333333333','Cross campaign owner');raise exception 'Expected cross-campaign denial';exception when insufficient_privilege then null;end;
end $$;
reset role;
do $$ begin
 if (select count(*) from public.character_owners)<>2 then raise exception 'Owner and GM grants not created atomically';end if;
 if exists(select 1 from public.character_profiles where bio<>'' or character_details<>'{}' or portrait_path is not null) then raise exception 'Unexpected publication of private fields';end if;
end $$;
set role authenticated;
select set_config('request.jwt.claim.sub','22222222-2222-4222-8222-222222222222',false);
update public.character_profiles set display_name='Later name',portrait_path=slug||'/01234567-89ab-cdef-0123-456789abcdef.png',background_path=slug||'/01234567-89ab-cdef-0123-456789abcdef.webp',music_path=slug||'/01234567-89ab-cdef-0123-456789abcdef.mp3';
do $$ begin
 begin perform public.create_shared_character('cccccccc-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Unapproved');raise exception 'Expected player denial';exception when insufficient_privilege then null;end;
 begin perform public.character_setup_members('septenture');raise exception 'Expected player roster denial';exception when insufficient_privilege then null;end;
end $$;
select set_config('request.jwt.claim.sub','33333333-3333-4333-8333-333333333333',false);
do $$ begin
 begin perform public.create_shared_character('dddddddd-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Other GM');raise exception 'Expected other GM denial';exception when insufficient_privilege then null;end;
end $$;
select set_config('request.jwt.claim.sub','11111111-1111-4111-8111-111111111111',false);
select public.create_shared_character('aaaaaaaa-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Future PC');
do $$ begin
 if (select display_name from public.character_profiles limit 1)<>'Later name' then raise exception 'Retry overwrote later profile edit';end if;
 if (select count(*) from public.character_setup_members('septenture'))<>3 then raise exception 'Invalid campaign members';end if;
end $$;
reset role;
set role anon;
do $$ begin
 begin perform public.create_shared_character('eeeeeeee-1111-4111-8111-111111111111','septenture','22222222-2222-4222-8222-222222222222','Anonymous');raise exception 'Expected anon denial';exception when insufficient_privilege then null;end;
end $$;
reset role;
do $$ begin
 if (select prosecdef from pg_proc where oid='public.create_shared_character(uuid,text,uuid,text)'::regprocedure) then raise exception 'Public entry must be invoker';end if;
end $$;
select 'PASS: idempotency, mismatch, actor roles, campaign isolation, atomic owners, private-field boundary, real media paths, preserved edits, anonymous denial and private implementation';
