<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once MODPATH.'core/controllers/nova_manage.php';
require_once APPPATH.'septenture/story_drafts.php';
require_once APPPATH.'septenture/publication_mail.php';
class Manage extends Nova_manage {
    public function posts() {
        if(isset($_POST['submit'])&&$this->uri->segment(5)==='update'){
            try{$drafts=new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN);$drafts->guard($drafts->row((int)$this->uri->segment(4)),['revision'=>$_POST['story_revision']??null,'tab'=>$_POST['story_tab']??null]);Septenture_story_format::validate($_POST['post_content']??null,septenture_story_request_format($this->uri->segment(4)));}
            catch(InvalidArgumentException|LengthException|RuntimeException $e){
                $GLOBALS['septenture_manage_story_error']=$e->getMessage();
                $GLOBALS['septenture_manage_story_rejected']=$_POST;
                unset($_POST['submit']);$this->output->set_status_header($e->getCode());
            }
        }
        try{parent::posts();}catch(RuntimeException $e){if($e->getCode()!==409)throw $e;$GLOBALS['septenture_manage_story_error']=$e->getMessage();$GLOBALS['septenture_manage_story_rejected']=$_POST;unset($_POST['submit']);$this->output->set_status_header(409);parent::posts();}
    }
    protected function _email($type,$data) {
        if($type==='post')return septenture_notify_publication((int)$this->input->post('id'));
        return parent::_email($type,$data);
    }
}
