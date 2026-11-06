<?php
/** Publication notices are separate from private draft turn notifications. */
final class Septenture_publication_mail {
 public function __construct(private PDO $db){}
 public function send(int $story,callable $deliver): array {
  $q=$this->db->prepare("SELECT post_title FROM nova_posts WHERE post_id=? AND post_status='activated'");$q->execute([$story]);$title=$q->fetchColumn();
  if($title===false)return ['sent'=>0,'failed'=>0,'skipped'=>true];
  if($this->db->query("SELECT setting_value FROM nova_settings WHERE setting_key='system_email'")->fetchColumn()!=='on')return ['sent'=>0,'failed'=>0,'disabled'=>true];
  $rows=$this->db->query("SELECT DISTINCT LOWER(TRIM(u.email)) AS email FROM nova_users u JOIN nova_septenture_identities i ON i.userid=u.userid JOIN nova_user_prefs_values p ON p.prefvalue_user=u.userid AND p.prefvalue_key='email_mission_posts' AND p.prefvalue_value='y' WHERE u.status='active'")->fetchAll(PDO::FETCH_COLUMN);
  $out=['sent'=>0,'failed'=>0,'duplicate'=>0];
  foreach($rows as $to){
   if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to))continue;
   // Unique recipient/story claim makes reposts and repeated approvals silent.
   $claim=$this->db->prepare("INSERT IGNORE INTO nova_publication_mail(story_id,recipient,status,attempted_at) VALUES(?,?,'pending',?)");$claim->execute([$story,$to,time()]);
   if(!$claim->rowCount()){$out['duplicate']++;continue;}
   try{$status=$deliver($to,(string)$title,$story)?'sent':'failed';}catch(Throwable $e){$status='failed';error_log('Septenture publication mail failed for story '.$story);}
   $q=$this->db->prepare('UPDATE nova_publication_mail SET status=? WHERE story_id=? AND recipient=?');$q->execute([$status,$story,$to]);$out[$status]++;
  }
  return $out;
 }
}
function septenture_publication_deliver(string $to,string $title,int $story): bool {
 require_once dirname(__DIR__,2).'/nova/vendor/autoload.php';
 $db=septenture_db();$from=$db->query("SELECT setting_value FROM nova_settings WHERE setting_key='default_email_address'")->fetchColumn();
 if(!is_string($from)||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
 $site=rtrim(septenture_config('SITE_URL'),'/');
 $message=(new Swift_Message('Septenture — a new story in the Exchange'))
  ->setFrom([$from=>'Septenture'])->setTo([$to])
  ->setBody("A new story has been published: ".$title."\n\nRead it in the Exchange:\n".$site."/fiction/#story=nova-".$story."\n\nSign in with your Septenture player account to read.\nYou can turn publication emails off in Nova’s email preferences.\n",'text/plain');
 return (new Swift_Mailer(new Swift_SmtpTransport('127.0.0.1',25)))->send($message)===1;
}
function septenture_notify_publication(int $story): bool {
 try{$result=(new Septenture_publication_mail(septenture_db()))->send($story,'septenture_publication_deliver');return ($result['failed']??0)===0;}
 catch(Throwable $e){error_log('Septenture publication notification error for story '.$story);return false;}
}
