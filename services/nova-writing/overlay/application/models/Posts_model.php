<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once MODPATH.'core/models/nova_posts_model.php';
require_once APPPATH.'septenture/story_drafts.php';
class Posts_model extends Nova_posts_model {
    private function collaborator($row): bool {
        if (!$row || !defined('SEPTENTURE_PLAYER_ID')) return false;
        if (SEPTENTURE_PLAYER_ADMIN) return true;
        // Exact IDs avoid Nova's older substring checks confusing characters 1 and 11.
        $authors=array_filter(explode(',',(string)$row->post_authors_users));
        return in_array((string)SEPTENTURE_PLAYER_ID,$authors,true);
    }
    public function get_post($id='', $return='') {
        $row=parent::get_post($id);
        if ($row && $row->post_status!=='activated' && !$this->collaborator($row)) return false;
        return parent::get_post($id,$return);
    }
    public function update_post($id='', $data=[]) {
        $this->db->trans_begin();
        $row=$this->db->query('SELECT * FROM '.$this->db->dbprefix('posts').' WHERE post_id=? FOR UPDATE',[$id])->row();
        if (!$this->collaborator($row)) {$this->db->trans_rollback();return false;}
        $prose=array_intersect(array_keys($data),['post_content','post_title','post_authors','post_authors_users','post_mission','post_tags','post_location','post_timeline','post_status']);
        if($prose){try{(new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN))->guard((array)$row,['revision'=>$_POST['story_revision']??null,'tab'=>$_POST['story_tab']??null]);}catch(Throwable $e){$this->db->trans_rollback();throw $e;}$data['post_revision']=(int)$row->post_revision+1;}
        elseif(array_key_exists('post_lock_user',$data)){$this->db->trans_rollback();return false;}
        // Publishing must retain the saved scene roster. Nova's legacy participant
        // filter otherwise drops invited writers who have not taken a turn yet.
        if($row->post_status==='saved'&&in_array($data['post_status']??null,['activated','pending'],true)){
            $data['post_authors']=$row->post_authors;
            $data['post_authors_users']=$row->post_authors_users;
        }
        if (array_key_exists('post_content',$data)) {
            $format=$data['post_content_format']??($_POST['post_content_format']??($row->post_content_format??'legacy'));
            try{Septenture_story_format::validate($data['post_content'],$format);}catch(Throwable $e){$this->db->trans_rollback();throw $e;}
            if($format!==$row->post_content_format){$this->db->trans_rollback();throw new RuntimeException('Use the reviewed conversion to change formatting.',409);}$data['post_content_format']=$format;$data['post_words']=Septenture_story_format::words($data['post_content'],$format);
        }elseif(array_key_exists('post_content_format',$data)){ $this->db->trans_rollback();return false; }
        $ok=$this->db->where('post_id',$id)->update('posts',$data);
        if($ok&&$prose)$this->db->where('story_id',$id)->delete('story_leases');
        if ($ok) $this->db->trans_commit();else $this->db->trans_rollback();
        return $ok;
    }
    public function create_mission_entry($data='') {
        $data['post_content_format']=$data['post_content_format']??($_POST['post_content_format']??'markdown');
        Septenture_story_format::validate($data['post_content']??null,$data['post_content_format']);
        $data['post_words']=Septenture_story_format::words($data['post_content']??'',$data['post_content_format']);$this->db->insert('posts',$data);return $this->db->affected_rows();
    }
    public function update_post_lock($post,$user,$retain_lock=true) {
        return $this->update_post($post,['post_lock_user'=>$user,'post_lock_date'=>$retain_lock?time():0]);
    }
    public function delete_post($id='') {
        $this->db->trans_begin();
        try{$row=$this->db->query('SELECT * FROM '.$this->db->dbprefix('posts').' WHERE post_id=? FOR UPDATE',[$id])->row();
        if(!$this->collaborator($row))throw new RuntimeException('Story unavailable.',404);
        (new Septenture_story_drafts(septenture_db(),SEPTENTURE_PLAYER_ID,SEPTENTURE_PLAYER_ADMIN))->guard((array)$row,['revision'=>$_POST['story_revision']??null,'tab'=>$_POST['story_tab']??null]);
        $ok=parent::delete_post($id);$this->db->trans_commit();return $ok;
        }catch(Throwable $e){$this->db->trans_rollback();throw $e;}
    }
}
