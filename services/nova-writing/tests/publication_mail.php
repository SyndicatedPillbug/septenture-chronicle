<?php
require '/var/www/fiction-release/application/septenture/config.php';
require '/var/www/fiction-release/application/septenture/publication_mail.php';
$db=septenture_db();if(septenture_config('DB_NAME')!=='nova_play')exit(9);
$db->exec(file_get_contents(__DIR__.'/../migrations/007_publication_mail.sql'));
$db->beginTransaction();
try{
 $db->exec("UPDATE nova_settings SET setting_value='on' WHERE setting_key='system_email'");
 $db->exec("INSERT INTO nova_posts(post_id,post_title,post_status,post_content,post_date,post_mission) VALUES(900006,'Publication fixture','saved','PRIVATE PROSE MUST NOT BE EMAILED',0,1)");
 $service=new Septenture_publication_mail($db);$sent=[];$deliver=function($to,$title,$id)use(&$sent){$sent[]=[$to,$title,$id];return true;};
 if(!($service->send(900006,$deliver)['skipped']??false)||$sent)throw new Exception('Draft caused email');
 $db->exec("UPDATE nova_posts SET post_status='pending' WHERE post_id=900006");
 if(!($service->send(900006,$deliver)['skipped']??false))throw new Exception('Pending caused email');
 $db->exec("UPDATE nova_posts SET post_status='activated' WHERE post_id=900006");
 $expected=$db->query("SELECT DISTINCT LOWER(TRIM(u.email)) FROM nova_users u JOIN nova_septenture_identities i ON i.userid=u.userid JOIN nova_user_prefs_values p ON p.prefvalue_user=u.userid AND p.prefvalue_key='email_mission_posts' AND p.prefvalue_value='y' WHERE u.status='active'")->fetchAll(PDO::FETCH_COLUMN);
 if(!$expected)throw new Exception('Fixture needs subscribed users');
 $first=$service->send(900006,$deliver);if($first['sent']!==count($expected)||count($sent)!==count($expected))throw new Exception('Recipient filtering failed');
 $second=$service->send(900006,$deliver);if($second['sent']!==0||$second['duplicate']!==count($expected))throw new Exception('Duplicate sent');
 $one=$db->query("SELECT i.userid FROM nova_septenture_identities i JOIN nova_users u ON u.userid=i.userid WHERE u.status='active' LIMIT 1")->fetchColumn();
 $q=$db->prepare("UPDATE nova_user_prefs_values SET prefvalue_value='n' WHERE prefvalue_user=? AND prefvalue_key='email_mission_posts'");$q->execute([$one]);
 $db->exec("INSERT INTO nova_posts(post_id,post_title,post_status,post_content,post_date,post_mission) VALUES(900008,'Opt-out fixture','activated','PRIVATE',0,1)");
 $opted=$db->prepare("SELECT LOWER(TRIM(email)) FROM nova_users WHERE userid=?");$opted->execute([$one]);$optedEmail=$opted->fetchColumn();$called=[];
 $service->send(900008,function($to)use(&$called){$called[]=$to;return false;});
 if(in_array($optedEmail,$called,true))throw new Exception('Opt-out ignored');
 if(!$called || (int)$db->query("SELECT COUNT(*) FROM nova_publication_mail WHERE story_id=900008 AND status='uncertain'")->fetchColumn()!==count($called))throw new Exception('Failures not recorded');
 $db->exec("UPDATE nova_settings SET setting_value='off' WHERE setting_key='system_email'");if(!($service->send(900006,$deliver)['disabled']??false))throw new Exception('Email-off ignored');
 echo "PASS draft/pending privacy; active subscribed recipients; per-recipient sends; duplicate suppression; opt-outs; recorded failures; email-off. All changes rolled back.\n";
}finally{$db->rollBack();}
