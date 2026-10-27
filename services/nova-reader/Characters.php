<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once MODPATH.'core/controllers/nova_characters.php';
require_once APPPATH.'septenture/shared_profile.php';
class Characters extends Nova_characters {
    public function create() {
        Auth::check_access();
        require_once APPPATH.'septenture/character_setup.php';
        $message='';$members=[];$pending=[];$submitted=$_SERVER['REQUEST_METHOD']==='POST';
        $values=['operation'=>septenture_setup_operation(),'name'=>'','kind'=>'pc','owner'=>SEPTENTURE_PLAYER_SUBJECT];
        try{
            $identity=septenture_identity(SEPTENTURE_PLAYER_TOKEN);
            $store=new Septenture_character_setup(septenture_db(),$identity,fn($action,$input)=>septenture_setup_rpc(SEPTENTURE_PLAYER_TOKEN,$action,$input));
            if($submitted){
                foreach(array_keys($values) as $key)$values[$key]=$this->input->post($key);
                $result=$store->create($values);
                redirect('personnel/character/'.$result['character_id']);return;
            }
            $operation=$this->input->get('setup');
            if(is_string($operation)&&$operation!==''){
                $saved=$store->pending($operation)[0]??null;
                if(!$saved)throw new RuntimeException('This setup is unavailable.',404);
                if($saved['character_id']){redirect('personnel/character/'.(int)$saved['character_id']);return;}
                $values=['operation'=>$saved['operation_id'],'name'=>$saved['display_name'],'kind'=>$saved['character_kind'],'owner'=>$saved['owner_subject']];
                $message='This setup is unfinished. Submit it again to resume; the same character and profile will be used.';
            }
            $members=$store->members();$pending=$store->pending();
        }catch(Throwable $e){
            $code=in_array($e->getCode(),[400,403,404,409,503])?$e->getCode():503;
            $this->output->set_status_header($code);
            $message=$e instanceof RuntimeException||$e instanceof InvalidArgumentException?$e->getMessage():'Character setup is temporarily unavailable. Your form has been kept.';
            if(isset($store)){try{$members=$store->members();$pending=$store->pending();}catch(Throwable $ignored){}}
        }
        $this->_regions['content']=Location::view('character_setup',$this->skin,'admin',['values'=>$values,'members'=>$members,'pending'=>$pending,'message'=>$message]);
        $this->_regions['title'].='Create a character';Template::assign($this->_regions);Template::render();
    }

    public function bio($id=false) {
        Auth::check_access();
        $id=$id?:$this->session->userdata('main_char');
        $q=septenture_db()->prepare('SELECT slug FROM nova_septenture_cast WHERE charid=?');$q->execute([(int)$id]);$slug=$q->fetchColumn();
        if(!$slug)return parent::bio($id);
        $profile=null;$message='';$submitted=$_SERVER['REQUEST_METHOD']==='POST';
        try {
            $profile=septenture_shared_profile($slug,SEPTENTURE_PLAYER_TOKEN);
            $owners=septenture_api('/rest/v1/character_owners?select=character_slug&user_id=eq.'.rawurlencode(SEPTENTURE_PLAYER_SUBJECT),SEPTENTURE_PLAYER_TOKEN);
            if(!in_array($slug,array_column($owners,'character_slug'),true)){show_error('You do not own this shared profile.',403);return;}
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $submitted=true;
                $profile=septenture_save_shared_profile($slug,SEPTENTURE_PLAYER_TOKEN,$_POST,(string)($_POST['profile_revision']??''));
                $submitted=false;$message='Saved. Nova and the Chronicle now use these same character details.';
                try{septenture_refresh_profiles(SEPTENTURE_PLAYER_TOKEN);}catch(Throwable $e){$message='Shared details saved. Nova’s display will refresh on your next visit.';}
            }
        }catch(Throwable $e){
            $this->output->set_status_header(in_array($e->getCode(),[400,401,403,404,409,503])?$e->getCode():503);
            $message=$e instanceof RuntimeException||$e instanceof InvalidArgumentException?$e->getMessage():'The shared profile is unavailable. Your text has been kept.';
        }
        $data=['profile'=>$profile??($submitted?['updated_at'=>$_POST['profile_revision']??'']:null),'profile_slug'=>$slug,'character_id'=>(int)$id,'message'=>$message,'submitted'=>$submitted?$_POST:null];
        $this->_regions['content']=Location::view('shared_profile',$this->skin,'admin',$data);
        $this->_regions['title'].='Shared character profile';
        Template::assign($this->_regions);Template::render();
    }
}
