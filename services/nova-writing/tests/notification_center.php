<?php
require '/var/www/fiction-release/application/septenture/config.php';require '/var/www/fiction-release/application/septenture/writing_workspace.php';
$db=septenture_db();if(septenture_config('DB_NAME')!=='nova_play')exit(9);
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$alice=new Septenture_notification_center($db,1);$bob=new Septenture_notification_center($db,2);
// Service methods commit preference/retry claims; use disposable fixtures and restore preferences in finally.
$original=$alice->preferences();$bobOriginal=$bob->preferences();$setting=$db->query("SELECT setting_value FROM nova_settings WHERE setting_key='system_email'")->fetchColumn();
$id='d6025a46-428c-4b33-a843-29593b1029d9';$story=910001;$calls=0;
$deliver=function()use(&$calls){$calls++;return 'sent';};
try{
 $db->exec("UPDATE nova_settings SET setting_value='on' WHERE setting_key='system_email'");
 $alice->savePreferences(['new_stories'=>false,'tagged_updates'=>false]);check(!$alice->preferences()['new_stories']&&!$alice->preferences()['tagged_updates'],'own preferences roundtrip');check($bob->preferences()['new_stories']===$bobOriginal['new_stories'],'other account preferences unchanged');
 try{$alice->savePreferences(['new_stories'=>true,'tagged_updates'=>true,'user'=>2]);throw new Exception('accepted foreign user');}catch(InvalidArgumentException $e){check(true,'caller-selected owner rejected');}
 $bob->savePreferences(['new_stories'=>true,'tagged_updates'=>true]);
 $db->exec("INSERT INTO nova_posts(post_id,post_title,post_status,post_content,post_date,post_mission,post_authors,post_authors_users,post_revision) VALUES($story,'ISOLATED delivery verification','saved','PRIVATE PROSE',0,1,'1,2','1,2',2)");
 $q=$db->prepare("INSERT INTO nova_story_turns(id,story_id,sender,recipient,revision,created_at,email_status,email_attempted_at) VALUES(?,?,1,2,2,?,'retryable',?)");$q->execute([$id,$story,time(),time()-31]);
 $in=['kind'=>'tag','id'=>$id];
 try{$bob->retry($in,$deliver,$deliver);throw new Exception('cross-owner retry allowed');}catch(RuntimeException $e){check($e->getCode()===404,'recipient cannot retry sender delivery');}
 $rows=$alice->deliveries();$json=json_encode($rows);check(!str_contains($json,'example.test')&&!str_contains($json,'PRIVATE PROSE'),'history omits email addresses and prose');
 $nested=false;$during=function()use($alice,$in,$deliver,&$nested,&$calls){$calls++;try{$alice->retry($in,$deliver,$deliver);}catch(RuntimeException $e){$nested=$e->getCode()===409;}return 'sent';};
 $result=$alice->retry($in,$during,$deliver);check($nested,'in-flight retry is blocked by the committed pending claim');check($result['status']==='sent'&&$calls===1,'definite preconnection failure retries once');
 try{$alice->retry($in,$deliver,$deliver);throw new Exception('resent accepted message');}catch(RuntimeException $e){check($e->getCode()===409&&$calls===1,'accepted delivery cannot be retried');}
 foreach(['pending','uncertain','failed','disabled'] as $status){$q=$db->prepare('UPDATE nova_story_turns SET email_status=? WHERE id=?');$q->execute([$status,$id]);try{$alice->retry($in,$deliver,$deliver);throw new Exception('unsafe retry');}catch(RuntimeException $e){check($e->getCode()===409&&$calls===1,"$status retry blocked");}}
 $db->exec("UPDATE nova_story_turns SET email_status='retryable',email_attempts=1,email_attempted_at=UNIX_TIMESTAMP() WHERE id='$id'");try{$alice->retry($in,$deliver,$deliver);throw new Exception('rate ignored');}catch(RuntimeException $e){check($e->getCode()===429,'retry cooldown enforced');}
 $db->exec("UPDATE nova_story_turns SET email_attempted_at=0,email_attempts=3 WHERE id='$id'");try{$alice->retry($in,$deliver,$deliver);throw new Exception('limit ignored');}catch(RuntimeException $e){check($e->getCode()===409,'three-attempt ceiling enforced');}
 $db->exec("UPDATE nova_story_turns SET email_attempts=1,revision=1 WHERE id='$id'");try{$alice->retry($in,$deliver,$deliver);throw new Exception('stale ignored');}catch(RuntimeException $e){check($e->getCode()===409,'stale revision cannot notify');}
 $db->exec("UPDATE nova_story_turns SET revision=2 WHERE id='$id'");$bob->savePreferences(['new_stories'=>false,'tagged_updates'=>false]);check($alice->retry($in,$deliver,$deliver)['status']==='disabled'&&$calls===1,'retry rechecks recipient opt-out');
 $db->exec("UPDATE nova_posts SET post_status='activated' WHERE post_id=$story");$to=$db->query('SELECT LOWER(TRIM(email)) FROM nova_users WHERE userid=2')->fetchColumn();$q=$db->prepare("INSERT INTO nova_publication_mail(story_id,recipient,status,attempted_at) VALUES(?,?,'retryable',0)");$q->execute([$story,$to]);$bob->savePreferences(['new_stories'=>true,'tagged_updates'=>true]);
 $publication=['kind'=>'publication','id'=>$story,'recipient_id'=>2];try{(new Septenture_notification_center($db,3))->retry($publication,$deliver,$deliver);throw new Exception('foreign publication retry');}catch(RuntimeException $e){check($e->getCode()===404,'non-author cannot retry publication mail');}check($alice->retry($publication,$deliver,$deliver)['status']==='sent'&&$calls===2,'publication retry accepts one message');try{$alice->retry($publication,$deliver,$deliver);throw new Exception('publication resent');}catch(RuntimeException $e){check($e->getCode()===409&&$calls===2,'publication accepted claim deduplicated');}
 check(septenture_mail_result(false)==='uncertain'&&septenture_mail_result('retryable')==='retryable','ambiguous transport failure never enables retry');
}finally{
 $alice->savePreferences(array_intersect_key($original,array_flip(['new_stories','tagged_updates'])));$bob->savePreferences(array_intersect_key($bobOriginal,array_flip(['new_stories','tagged_updates'])));
 $q=$db->prepare("UPDATE nova_settings SET setting_value=? WHERE setting_key='system_email'");$q->execute([$setting]);
 $db->exec("DELETE FROM nova_story_turns WHERE id='$id'");$db->exec("DELETE FROM nova_publication_mail WHERE story_id=$story");$db->exec("DELETE FROM nova_posts WHERE post_id=$story");
}
