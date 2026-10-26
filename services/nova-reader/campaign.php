<?php
require_once __DIR__.'/config.php';
function septenture_campaign(): string {
    $id=septenture_config('CAMPAIGN_ID','septenture');
    if(!preg_match('/^[a-z][a-z0-9-]{0,40}$/D',$id))throw new RuntimeException('Invalid campaign configuration.',503);
    return $id;
}
function septenture_campaign_role(array $members,string $uuid,string $campaign): ?string {
    foreach($members as $member)if(($member['user_id']??null)===$uuid&&($member['campaign_id']??null)===$campaign&&in_array($member['role']??null,['owner','gm','player'],true))return $member['role'];
    return null;
}
