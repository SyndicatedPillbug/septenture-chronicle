<?php
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE nova_user_prefs(pref_key TEXT,pref_default TEXT);CREATE TABLE nova_user_prefs_values(prefvalue_id INTEGER PRIMARY KEY,prefvalue_user INTEGER,prefvalue_key TEXT,prefvalue_value TEXT)');
$db->exec("INSERT INTO nova_user_prefs VALUES('email_mission_posts_save','y'),('email_mission_posts','y');INSERT INTO nova_user_prefs_values(prefvalue_user,prefvalue_key,prefvalue_value) VALUES(2,'email_mission_posts_save','n')");
$s=file_get_contents(__DIR__.'/../identity.php');preg_match("~\\\$q=\\\$db->prepare\\('([^']*INSERT INTO nova_user_prefs_values[^']*)'\\);~",$s,$match);if(!$match)throw new RuntimeException('Cannot find production preference backfill.');
$q=$db->prepare($match[1]);$q->execute([1,1]);$q->execute([2,2]);$q->execute([1,1]);$q->execute([2,2]);
if((int)$db->query('SELECT COUNT(*) FROM nova_user_prefs_values')->fetchColumn()!==4)throw new RuntimeException('Backfill is not idempotent.');
if($db->query("SELECT prefvalue_value FROM nova_user_prefs_values WHERE prefvalue_user=2 AND prefvalue_key='email_mission_posts_save'")->fetchColumn()!=='n')throw new RuntimeException('Opt-out changed.');
if($db->query("SELECT prefvalue_value FROM nova_user_prefs_values WHERE prefvalue_user=1 AND prefvalue_key='email_mission_posts_save'")->fetchColumn()!=='y')throw new RuntimeException('New account not subscribed.');
echo 'PASS new-account defaults, repair of partial existing accounts, preserved opt-outs, and repeat-login idempotency.'.PHP_EOL;
