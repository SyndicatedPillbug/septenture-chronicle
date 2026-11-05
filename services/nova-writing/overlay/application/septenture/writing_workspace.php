<?php
require_once __DIR__.'/story_drafts.php';
/** Personal account notes have no administrator bypass and no caller-supplied owner. */
final class Septenture_notebook {
 public function __construct(private PDO $db,private string $subject) {
  if(!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$subject))throw new RuntimeException('Sign in again.',401);
 }
 public function read(): array {
  $q=$this->db->prepare('SELECT source,format,revision,updated_at FROM nova_player_notebooks WHERE subject=?');$q->execute([$this->subject]);$r=$q->fetch(PDO::FETCH_ASSOC);
  return $r?['source'=>$r['source'],'format'=>$r['format'],'revision'=>(int)$r['revision'],'updated_at'=>(int)$r['updated_at']]:['source'=>'','format'=>'markdown','revision'=>0,'updated_at'=>null];
 }
 public function save(array $in): array {
  if(array_diff(array_keys($in),['source','format','revision']))throw new InvalidArgumentException('Provide only notebook text, format and revision.',400);
  if(!is_int($in['revision']??null)||$in['revision']<0||!is_string($in['source']??null)||strlen($in['source'])>262144||!mb_check_encoding($in['source'],'UTF-8')||($in['format']??null)!=='markdown')throw new InvalidArgumentException('Use Markdown notes up to 256 KB with the saved revision.',400);
  $this->db->beginTransaction();try{
   $q=$this->db->prepare("INSERT IGNORE INTO nova_player_notebooks(subject,source,format,revision,updated_at) VALUES(?,'','markdown',0,?)");$q->execute([$this->subject,time()]);
   $q=$this->db->prepare('SELECT source,revision FROM nova_player_notebooks WHERE subject=? FOR UPDATE');$q->execute([$this->subject]);$row=$q->fetch(PDO::FETCH_ASSOC);
   if((int)$row['revision']!==$in['revision'])throw new RuntimeException('Your notebook changed in another tab. Your unsaved notes are kept here.',409);
   if($row['source']!==$in['source']||(int)$row['revision']===0){$q=$this->db->prepare('UPDATE nova_player_notebooks SET source=?,revision=revision+1,updated_at=? WHERE subject=?');$q->execute([$in['source'],time(),$this->subject]);}
   $this->db->commit();return $this->read();
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
 }
}
final class Septenture_writing_workspace {
 public function __construct(private PDO $db,private int $user,private bool $admin){}
 private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
 public function people(): array {
  $rows=$this->query("SELECT u.userid,u.name,c.charid,TRIM(CONCAT_WS(' ',c.first_name,c.last_name)) AS character_name FROM nova_septenture_identities i JOIN nova_users u ON u.userid=i.userid JOIN nova_characters c ON c.user=u.userid ORDER BY u.name,c.charid")->fetchAll(PDO::FETCH_ASSOC);
  return array_map(fn($r)=>['user_id'=>(int)$r['userid'],'name'=>$r['name'],'character_id'=>(int)$r['charid'],'character_name'=>$r['character_name']],$rows);
 }
 public function inbox(): array {
  // Recheck draft access on every read, including notifications made before a collaborator was removed.
  $q=$this->query("SELECT t.id,t.story_id,t.created_at,t.read_at,u.name AS sender,p.post_title AS title FROM nova_story_turns t JOIN nova_posts p ON p.post_id=t.story_id JOIN nova_users u ON u.userid=t.sender WHERE t.recipient=? AND FIND_IN_SET(?,p.post_authors_users)>0 ORDER BY t.created_at DESC LIMIT 30",[$this->user,(string)$this->user]);
  return $q->fetchAll(PDO::FETCH_ASSOC);
 }
 public function markRead(array $in): array {
  if(array_diff(array_keys($in),['id']))throw new InvalidArgumentException('Choose a notification.',400);$id=Septenture_story_drafts::uuid($in['id']??null);
  $this->query('UPDATE nova_story_turns SET read_at=COALESCE(read_at,?) WHERE id=? AND recipient=?',[time(),$id,$this->user]);return ['ok'=>true];
 }
 public function notify(int $story,array $in,callable $send): array {
  if(array_diff(array_keys($in),['recipient','revision','operation_id']))throw new InvalidArgumentException('Choose a scene collaborator and saved revision.',400);
  $id=Septenture_story_drafts::uuid($in['operation_id']??null);$to=$in['recipient']??null;
  if(!is_int($to)||$to<1||!is_int($in['revision']??null))throw new InvalidArgumentException('Choose a scene collaborator.',400);
  $this->db->beginTransaction();try{
   $drafts=new Septenture_story_drafts($this->db,$this->user,$this->admin);$row=$drafts->row($story,true);
   if((int)$row['post_revision']!==$in['revision'])throw new RuntimeException('Save your latest changes before calling in a writer.',409);
   $target=$this->query('SELECT u.email FROM nova_users u JOIN nova_septenture_identities i ON i.userid=u.userid WHERE u.userid=?',[$to])->fetchColumn();
   if(!$target||!in_array((string)$to,explode(',',(string)$row['post_authors_users']),true))throw new RuntimeException('Add this player as a scene collaborator and save before tagging them.',403);
   $old=$this->query('SELECT * FROM nova_story_turns WHERE id=? OR (story_id=? AND sender=? AND recipient=? AND revision=?)',[$id,$story,$this->user,$to,$in['revision']])->fetch(PDO::FETCH_ASSOC);
   if($old){if((int)$old['sender']!==$this->user||(int)$old['recipient']!==$to||(int)$old['story_id']!==$story||(int)$old['revision']!==$in['revision'])throw new RuntimeException('Notification ID already used.',409);$this->db->commit();return ['ok'=>true,'duplicate'=>true,'email_status'=>$old['email_status']];}
   $this->query('SELECT userid FROM nova_users WHERE userid=? FOR UPDATE',[$this->user]);
   $rate=count($this->query('SELECT id FROM nova_story_turns WHERE sender=? AND created_at>? FOR UPDATE',[$this->user,time()-600])->fetchAll(PDO::FETCH_COLUMN));
   if((int)$rate>=10)throw new RuntimeException('You have sent several turn notifications. Try again in a few minutes.',429);
   $this->query("INSERT INTO nova_story_turns(id,story_id,sender,recipient,revision,created_at,email_status) VALUES(?,?,?,?,?,?,'pending')",[$id,$story,$this->user,$to,$in['revision'],time()]);
   $this->db->commit();
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
  // A notebook or unpublished prose is never included in mail. No mail on ordinary autosaves.
  $pref=$this->query("SELECT prefvalue_value FROM nova_user_prefs_values WHERE prefvalue_user=? AND prefvalue_key='email_mission_posts_save'",[$to])->fetchColumn();
  $setting=$this->query("SELECT setting_value FROM nova_settings WHERE setting_key='system_email'")->fetchColumn();
  $status=$pref==='n'||$setting==='off'?'disabled':($send((string)$target,$story)?'sent':'failed');
  $this->query('UPDATE nova_story_turns SET email_status=? WHERE id=?',[$status,$id]);return ['ok'=>true,'duplicate'=>false,'email_status'=>$status];
 }
}
function septenture_turn_mail(string $to,int $story): bool {
 if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to))return false;
 $db=septenture_db();$q=$db->prepare("SELECT setting_value FROM nova_settings WHERE setting_key='default_email_address'");$q->execute();$from=$q->fetchColumn();
 if(!is_string($from)||!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))return false;
 $link=rtrim(septenture_config('NOVA_URL'),'/').'/index.php/auth/start?draft='.$story;
 $payload="From: Septenture <".$from.">\r\nTo: ".$to."\r\nSubject: Septenture - your turn to write\r\nDate: ".gmdate('D, d M Y H:i:s O')."\r\nMessage-ID: <".bin2hex(random_bytes(16))."@".parse_url(septenture_config('NOVA_URL'),PHP_URL_HOST).">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSomeone in your scene has called you in.\r\n\r\nOpen the scene: ".$link."\r\n\r\nSign in with your Septenture account to continue. Your scene is also listed in the writing desk.\r\n";
 $stream=fopen('php://temp','r+');fwrite($stream,$payload);rewind($stream);$ch=curl_init('smtp://127.0.0.1:25');curl_setopt_array($ch,[CURLOPT_MAIL_FROM=>$from,CURLOPT_MAIL_RCPT=>[$to],CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>strlen($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8]);$ok=curl_exec($ch)!==false;curl_close($ch);fclose($stream);return $ok;
}
function septenture_notebook_page(array $identity,string $csrf): never {
 $esc=fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type: text/html; charset=utf-8');
 echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="septenture-site" content="'.$esc(rtrim(septenture_config('SITE_URL'),'/').'/').'"><meta name="septenture-renew-csrf" content="'.$esc($csrf).'"><title>My game notes · Septenture</title><link rel="stylesheet" href="/assets/writing-workspace.css?v=20261104a"><main class="notebook-page" data-notebook data-account="'.$esc($identity['uuid']).'" data-csrf="'.$esc($csrf).'"><nav><a href="/index.php/write/index">← Writing desk</a><a href="/index.php/write/missionpost">New story</a></nav><header><p class="writing-eyebrow">YOUR PERSONAL NOTEBOOK</p><h1>My game notes</h1><p>Only you can open these notes through the site. They are never published or sent in notifications.</p></header><div class="notebook-actions"><button type="button" data-notes-save>Save now</button><button type="button" data-notes-copy>Download a copy</button><span data-notes-status role="status">Opening your notebook…</span></div><section data-notes-recovery hidden></section><section data-notes-conflict hidden></section><div data-notes-editor></div></main><script src="/assets/story-rich.js"></script><script src="/assets/prose-editor.js"></script><script src="/assets/player-notebook.js?v=20261104a"></script><script src="/assets/nova-session.js?v=20261104a"></script></html>';exit;
}
function septenture_writing_api(string $route,string $method,array $identity,array $input,string $csrf): never {
 $db=septenture_db();$workspace=new Septenture_writing_workspace($db,SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN);
 if($route==='play/notebook'&&$method==='GET')septenture_notebook_page($identity,$csrf);
 if($route==='play/player-notes'){$notes=new Septenture_notebook($db,$identity['uuid']);if($method==='GET')septenture_json(['notes'=>$notes->read(),'account'=>$identity['uuid']]);if($method==='POST')septenture_json(['notes'=>$notes->save($input),'account'=>$identity['uuid']]);}
 if($route==='play/writing/session'&&$method==='GET')septenture_json(['csrf'=>$csrf,'account_subject'=>$identity['uuid'],'user_id'=>SEPTENTURE_PLAYER_ID]);
 if($route==='play/writing/people'&&$method==='GET')septenture_json(['people'=>$workspace->people(),'user_id'=>SEPTENTURE_PLAYER_ID]);
 if($route==='play/writing/inbox'&&$method==='GET')septenture_json(['notifications'=>$workspace->inbox()]);
 if($route==='play/writing/read'&&$method==='POST')septenture_json($workspace->markRead($input));
 if(preg_match('~^play/writing/scene/([1-9][0-9]*)/notify$~D',$route,$m)&&$method==='POST')septenture_json($workspace->notify((int)$m[1],$input,'septenture_turn_mail'));
 throw new RuntimeException('Writing tool unavailable.',404);
}
