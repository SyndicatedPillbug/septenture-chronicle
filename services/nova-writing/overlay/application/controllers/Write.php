<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once MODPATH.'core/controllers/nova_write.php';
require_once APPPATH.'septenture/story_drafts.php';
require_once APPPATH.'septenture/publication_mail.php';
class Write extends Nova_write {
    private int $publicationStory=0;
    public function missionpost($id=false) {
        $this->publicationStory=0;
        if($id && strtolower((string)($_POST['submit']??''))==='post'){
            $r=(new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN))->row((int)$id);
            if($r['post_status']!=='activated')$this->publicationStory=(int)$id;
        }
        if($id){try{(new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN))->row((int)$id);}catch(Throwable $e){septenture_fail('Story unavailable.',404);}}

        if(isset($_POST['submit'])&&in_array(strtolower((string)$_POST['submit']),['save','post','delete'],true)){
            try{if($id){$drafts=new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN);$drafts->guard($drafts->row((int)$id),['revision'=>$_POST['story_revision']??null,'tab'=>$_POST['story_tab']??null]);}else throw new RuntimeException('Wait for your private draft to be created before saving.',409);Septenture_story_format::validate($_POST['content']??null,septenture_story_request_format($id));}
            catch(InvalidArgumentException|LengthException|RuntimeException $e){$GLOBALS['septenture_story_error']=$e->getMessage();$GLOBALS['septenture_story_rejected']=$_POST;$_POST['submit']='invalid_format';$this->output->set_status_header($e->getCode());}
        }
        try{parent::missionpost($id);}catch(RuntimeException $e){if($e->getCode()!==409)throw $e;$GLOBALS['septenture_story_error']=$e->getMessage();$GLOBALS['septenture_story_rejected']=$_POST;$_POST['submit']='invalid_format';$this->output->set_status_header(409);parent::missionpost($id);}
    }
    public function personallog($id=false) {
        try {parent::personallog($id);}
        catch(InvalidArgumentException|LengthException|RuntimeException $e){
            $GLOBALS['septenture_journal_error']=$e->getMessage();$GLOBALS['septenture_journal_rejected']=$_POST;
            $_POST['submit']='invalid_format';$this->output->set_status_header(in_array($e->getCode(),[400,403,404,409,413,503])?$e->getCode():400);parent::personallog($id);
        }
    }
    protected function _email($type,$data) {
        if($type==='post')return $this->publicationStory>0 ? septenture_notify_publication($this->publicationStory) : true;
        // Ordinary saves, pending drafts and deletions never broadcast private prose.
        if(in_array($type,['post_pending','post_save','post_delete'],true))return true;
        if(in_array($type,['log','log_pending','log_save'],true)&&isset($data['content']))$data['content']=Septenture_story_format::mail($data['content'],$_POST['log_content_format']??'legacy',$this->mail->mailtype);
        return parent::_email($type,$data);
    }
}
