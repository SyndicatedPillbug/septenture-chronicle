<?php
/** Account-scoped preferences and sender-scoped delivery receipts. No email addresses in API responses. */
final class Septenture_notification_center {
 public function __construct(private PDO $db,private int $user){}
 private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
 public function preferences(): array {
  $rows=$this->query("SELECT prefvalue_key,prefvalue_value FROM nova_user_prefs_values WHERE prefvalue_user=? AND prefvalue_key IN ('email_mission_posts','email_mission_posts_save')",[$this->user])->fetchAll(PDO::FETCH_KEY_PAIR);
  return ['new_stories'=>($rows['email_mission_posts']??'n')==='y','tagged_updates'=>($rows['email_mission_posts_save']??'y')!=='n','email_available'=>$this->query("SELECT setting_value FROM nova_settings WHERE setting_key='system_email'")->fetchColumn()==='on'];
 }
 public function savePreferences(array $in): array {
  if(count($in)!==2||array_diff(array_keys($in),['new_stories','tagged_updates'])||!is_bool($in['new_stories']??null)||!is_bool($in['tagged_updates']??null))throw new InvalidArgumentException('Choose both email preferences.',400);
  $this->db->beginTransaction();try{
   $this->query('SELECT userid FROM nova_users WHERE userid=? FOR UPDATE',[$this->user]);
   foreach(['new_stories'=>'email_mission_posts','tagged_updates'=>'email_mission_posts_save'] as $key=>$pref){
    $exists=$this->query('SELECT prefvalue_id FROM nova_user_prefs_values WHERE prefvalue_user=? AND prefvalue_key=?',[$this->user,$pref])->fetchColumn();
    if($exists!==false)$this->query('UPDATE nova_user_prefs_values SET prefvalue_value=? WHERE prefvalue_user=? AND prefvalue_key=?',[$in[$key]?'y':'n',$this->user,$pref]);
    else $this->query('INSERT INTO nova_user_prefs_values(prefvalue_user,prefvalue_key,prefvalue_value) VALUES(?,?,?)',[$this->user,$pref,$in[$key]?'y':'n']);
   }$this->db->commit();return $this->preferences();
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function deliveries(): array {
  $turns=$this->query("SELECT t.id,t.story_id,t.email_status AS status,t.email_attempts AS attempts,t.email_attempted_at AS attempted_at,t.created_at,p.post_title AS title,u.name AS recipient,t.revision,p.post_revision,p.post_status FROM nova_story_turns t JOIN nova_posts p ON p.post_id=t.story_id JOIN nova_users u ON u.userid=t.recipient WHERE t.sender=? AND FIND_IN_SET(?,p.post_authors_users)>0 AND FIND_IN_SET(t.recipient,p.post_authors_users)>0 ORDER BY t.created_at DESC LIMIT 30",[$this->user,(string)$this->user])->fetchAll(PDO::FETCH_ASSOC);
  foreach($turns as &$r){$r['kind']='tag';$r['can_retry']=$r['status']==='retryable'&&(int)$r['attempts']<3&&(int)$r['revision']===(int)$r['post_revision']&&$r['post_status']==='saved';unset($r['revision'],$r['post_revision'],$r['post_status']);}unset($r);
  $pub=$this->query("SELECT m.story_id,m.status,m.attempts,m.attempted_at,m.attempted_at AS created_at,p.post_title AS title,u.name AS recipient,u.userid AS recipient_id FROM nova_publication_mail m JOIN nova_posts p ON p.post_id=m.story_id JOIN nova_users u ON BINARY LOWER(TRIM(u.email))=BINARY m.recipient JOIN nova_septenture_identities i ON i.userid=u.userid WHERE p.post_status='activated' AND FIND_IN_SET(?,p.post_authors_users)>0 ORDER BY m.attempted_at DESC LIMIT 30",[(string)$this->user])->fetchAll(PDO::FETCH_ASSOC);
  foreach($pub as &$r){$r['kind']='publication';$r['id']=(int)$r['story_id'];$r['recipient_id']=(int)$r['recipient_id'];$r['can_retry']=$r['status']==='retryable'&&(int)$r['attempts']<3;}unset($r);
  $rows=array_merge($turns,$pub);usort($rows,fn($a,$b)=>(int)$b['created_at']<=>(int)$a['created_at']);return array_slice($rows,0,50);
 }
 public function retry(array $in,callable $turn,callable $publication): array {
  if(array_diff(array_keys($in),['kind','id','recipient_id'])||!in_array($in['kind']??null,['tag','publication'],true))throw new InvalidArgumentException('Choose a delivery to retry.',400);
  $kind=$in['kind'];$this->db->beginTransaction();try{
   if($kind==='tag'){
    $id=Septenture_story_drafts::uuid($in['id']??null);
    $r=$this->query('SELECT * FROM nova_story_turns WHERE id=? AND sender=? FOR UPDATE',[$id,$this->user])->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('Delivery unavailable.',404);
    $p=$this->query('SELECT * FROM nova_posts WHERE post_id=? FOR UPDATE',[$r['story_id']])->fetch(PDO::FETCH_ASSOC);
    if(!$p||!in_array((string)$this->user,explode(',',$p['post_authors_users']),true)||!in_array((string)$r['recipient'],explode(',',$p['post_authors_users']),true)||(int)$p['post_revision']!==(int)$r['revision']||$p['post_status']!=='saved')throw new RuntimeException('This scene has changed. Send a new update from the current draft.',409);
    $target=$this->query("SELECT LOWER(TRIM(email)) FROM nova_users u JOIN nova_septenture_identities i ON i.userid=u.userid WHERE u.userid=? AND u.status='active'",[$r['recipient']])->fetchColumn();
    $status=$r['email_status'];$attempts=(int)$r['email_attempts'];$at=(int)$r['email_attempted_at'];$to=(int)$r['recipient'];$story=(int)$r['story_id'];$pref='email_mission_posts_save';
   }else{
    if(!is_int($in['id']??null)||!is_int($in['recipient_id']??null))throw new InvalidArgumentException('Choose a delivery.',400);
    $story=$in['id'];$to=$in['recipient_id'];
    $p=$this->query("SELECT post_title,post_authors_users FROM nova_posts WHERE post_id=? AND post_status='activated' FOR UPDATE",[$story])->fetch(PDO::FETCH_ASSOC);
    if(!$p||!in_array((string)$this->user,explode(',',$p['post_authors_users']),true))throw new RuntimeException('Delivery unavailable.',404);
    $target=$this->query("SELECT LOWER(TRIM(email)) FROM nova_users u JOIN nova_septenture_identities i ON i.userid=u.userid WHERE u.userid=? AND u.status='active'",[$to])->fetchColumn();
    $r=$this->query('SELECT * FROM nova_publication_mail WHERE story_id=? AND recipient=? FOR UPDATE',[$story,$target?:''])->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('Delivery unavailable.',404);
    $status=$r['status'];$attempts=(int)$r['attempts'];$at=(int)$r['attempted_at'];$pref='email_mission_posts';
   }
   if($status!=='retryable')throw new RuntimeException('This delivery cannot be retried safely. It may already have been accepted.',409);
   if($attempts>=3)throw new RuntimeException('Retry limit reached. Ask the site administrator to check email delivery.',409);
   if($at>time()-30)throw new RuntimeException('Wait 30 seconds between delivery attempts.',429);
   $value=$this->query('SELECT prefvalue_value FROM nova_user_prefs_values WHERE prefvalue_user=? AND prefvalue_key=?',[$to,$pref])->fetchColumn();
   $enabled=$target&&($pref==='email_mission_posts'?$value==='y':$value!=='n')&&$this->query("SELECT setting_value FROM nova_settings WHERE setting_key='system_email'")->fetchColumn()==='on';
   $next=$enabled?'pending':'disabled';
   if($kind==='tag')$this->query('UPDATE nova_story_turns SET email_status=?,email_attempts=email_attempts+1,email_attempted_at=? WHERE id=?',[$next,time(),$id]);
   else $this->query('UPDATE nova_publication_mail SET status=?,attempts=attempts+1,attempted_at=? WHERE story_id=? AND recipient=?',[$next,time(),$story,$target]);
   $this->db->commit();
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
  if(!$enabled)return ['status'=>'disabled'];
  try{$result=$kind==='tag'?$turn($target,$story):$publication($target,$p['post_title'],$story);$status=septenture_mail_result($result);}catch(Throwable $e){$status='uncertain';}
  if($kind==='tag')$this->query('UPDATE nova_story_turns SET email_status=? WHERE id=?',[$status,$id]);
  else $this->query('UPDATE nova_publication_mail SET status=? WHERE story_id=? AND recipient=?',[$status,$story,$target]);
  return ['status'=>$status];
 }
}
function septenture_notification_page(string $csrf): never {
 $esc=fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type: text/html; charset=utf-8');
 echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="septenture-site" content="'.$esc(rtrim(septenture_config('SITE_URL'),'/').'/').'"><meta name="septenture-renew-csrf" content="'.$esc($csrf).'"><title>Email preferences &amp; delivery · Septenture</title><link rel="stylesheet" href="/assets/writing-workspace.css?v=20261104a"><link rel="stylesheet" href="/assets/notification-center.css?v=20261106a"><main class="notebook-page" data-notification-center><nav><a href="/index.php/write/index">← Writing desk</a></nav><h1>Email preferences &amp; delivery</h1><form data-mail-preferences><fieldset disabled><legend>Your emails</legend><p><label><input type="checkbox" name="new_stories"> New stories</label><br>A short notice when a story is published to the Exchange.</p><p><label><input type="checkbox" name="tagged_updates"> Tagged updates</label><br>A scene writer deliberately calls you in with an update. Ordinary saves stay silent.</p><button type="submit">Save email preferences</button></fieldset><p role="status" data-preference-status>Opening your preferences…</p></form><section><h2>Emails you requested</h2><p>Recent tagged updates you sent and publication notices for stories you coauthor. Sent means the mail server accepted the message; it does not confirm that it reached the inbox.</p><button type="button" data-refresh-deliveries>Refresh delivery status</button><div data-deliveries role="status">Checking delivery…</div></section></main><script src="/assets/notification-center.js?v='.substr(hash_file('sha256',__DIR__.'/../../assets/notification-center.js'),0,12).'"></script><script src="/assets/nova-session.js?v=20261104a"></script></html>';exit;
}
