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
   try{$result=$deliver($to,(string)$title,$story);$status=septenture_mail_result($result);}catch(Throwable $e){$status='uncertain';error_log('Septenture publication mail failed for story '.$story);}
   $q=$this->db->prepare('UPDATE nova_publication_mail SET status=? WHERE story_id=? AND recipient=?');$q->execute([$status,$story,$to]);$out[$status==='sent'?'sent':'failed']++;
  }
  return $out;
 }
}
/** Only a confirmed failure before a connection is safe to retry. */
function septenture_mail_result(mixed $result): string {
 return $result===true||$result==='sent'?'sent':($result==='retryable'?'retryable':'uncertain');
}
function septenture_smtp_mail(string $to,string $subject,string $body): string {
 if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to))return 'uncertain';
 $db=septenture_db();$from=$db->query("SELECT setting_value FROM nova_settings WHERE setting_key='default_email_address'")->fetchColumn();
 if(!is_string($from)||!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))return 'retryable';
 $payload="From: Septenture <".$from.">\r\nTo: ".$to."\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nDate: ".gmdate('D, d M Y H:i:s O')."\r\nMessage-ID: <".bin2hex(random_bytes(16))."@".parse_url(septenture_config('NOVA_URL'),PHP_URL_HOST).">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body));
 $stream=fopen('php://temp','r+');fwrite($stream,$payload);rewind($stream);$ch=curl_init('smtp://127.0.0.1:25');
 curl_setopt_array($ch,[CURLOPT_MAIL_FROM=>$from,CURLOPT_MAIL_RCPT=>[$to],CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>strlen($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8]);
 $ok=curl_exec($ch)!==false;$error=curl_errno($ch);curl_close($ch);fclose($stream);
 return $ok?'sent':(in_array($error,[CURLE_COULDNT_CONNECT,CURLE_COULDNT_RESOLVE_HOST],true)?'retryable':'uncertain');
}
function septenture_publication_deliver(string $to,string $title,int $story): string {
 $site=rtrim(septenture_config('SITE_URL'),'/');
 return septenture_smtp_mail($to,'Septenture — a new story in the Exchange',"A new story has been published: ".$title."\n\nRead it in the Exchange:\n".$site."/fiction/#story=nova-".$story."\n\nSign in with your Septenture player account to read.\nManage email preferences: ".rtrim(septenture_config('NOVA_URL'),'/')."/index.php/play/writing/notifications\n");
}
function septenture_notify_publication(int $story): bool {
 try{$result=(new Septenture_publication_mail(septenture_db()))->send($story,'septenture_publication_deliver');return ($result['failed']??0)===0;}
 catch(Throwable $e){error_log('Septenture publication notification error for story '.$story);return false;}
}
