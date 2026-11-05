<?php
/** Private NPC writing assignments. Public profile ownership stays authoritative. */
final class Septenture_npc_assignment {
    public function __construct(private PDO $db,private array $actor) {
        if(!($actor['site_admin']??false))throw new RuntimeException('Only the campaign administrator may assign NPCs.',403);
    }
    public function rows(): array {
        return $this->db->query("SELECT c.charid,c.first_name,c.last_name,c.user,u.name AS writer,i.subject AS owner_subject,s.revision AS sheet_revision,s.owner_subject AS sheet_owner,m.slug FROM nova_characters c LEFT JOIN nova_users u ON u.userid=c.user LEFT JOIN nova_septenture_identities i ON i.userid=c.user LEFT JOIN nova_play_sheets s ON s.character_id=c.charid LEFT JOIN nova_septenture_cast m ON m.charid=c.charid WHERE c.crew_type='npc' ORDER BY c.first_name,c.charid")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function assign(array $input,array $members): void {
        $id=filter_var($input['character']??null,FILTER_VALIDATE_INT);$owner=$input['owner']??'';
        if(!$id||!is_string($owner)||!in_array($owner,array_column($members,'subject'),true))throw new InvalidArgumentException('Choose an NPC and an invited player.',400);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT c.user,m.slug FROM nova_characters c LEFT JOIN nova_septenture_cast m ON m.charid=c.charid WHERE c.charid=? AND c.crew_type=\'npc\' FOR UPDATE');$q->execute([$id]);$npc=$q->fetch(PDO::FETCH_ASSOC);
            if(!$npc)throw new RuntimeException('NPC unavailable.',404);
            if($npc['slug'])throw new RuntimeException('This NPC has a shared public profile. Change its account ownership through the campaign account service first.',409);
            if((string)$npc['user']!==(string)($input['previous_user']??''))throw new RuntimeException('This NPC was reassigned in another window. Reload before changing it.',409);
            $q=$this->db->prepare('SELECT userid FROM nova_septenture_identities WHERE subject=?');$q->execute([$owner]);$user=$q->fetchColumn();
            if(!$user)throw new RuntimeException('This player needs to open the writing desk once before receiving an existing NPC.',409);
            $q=$this->db->prepare('UPDATE nova_characters SET user=? WHERE charid=?');$q->execute([$user,$id]);
            $q=$this->db->prepare('UPDATE nova_characters_data SET data_user=? WHERE data_char=?');$q->execute([$user,$id]);
            // Attaching a writing character does not silently disclose its private sheet.
            $this->db->commit();
        } catch(Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
