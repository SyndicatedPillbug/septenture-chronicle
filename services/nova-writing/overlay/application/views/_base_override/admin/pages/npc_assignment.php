<?php defined('BASEPATH') or exit('No direct script access allowed');$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<h1>Assign an NPC writer</h1>
<p>Choose who writes as this NPC. Private sheets and Table pieces have separate access controls.</p>
<p><?=anchor('characters/npcs','← NPC roster')?> · <?=anchor('characters/create','Create a new NPC')?></p>
<?php if($message):?><p role="status"><?=$esc($message)?></p><?php endif;?>
<?php foreach($rows as $npc):if($selected&&$selected!==(int)$npc['charid'])continue;?>
<section class="npc-assignment"><h2><?=$esc(trim($npc['first_name'].' '.$npc['last_name']))?></h2>
<?php if($npc['slug']):?><p>This NPC uses shared website ownership. <?=anchor('personnel/character/'.(int)$npc['charid'],'Open character and sheet access')?></p>
<?php else:?>
<?=form_open('characters/assign')?>
<input type="hidden" name="assignment_csrf" value="<?=$esc(SEPTENTURE_PLAYER_CSRF)?>">
<input type="hidden" name="character" value="<?=(int)$npc['charid']?>"><input type="hidden" name="previous_user" value="<?=$esc($npc['user'])?>">
<p><label>Writing player <select name="owner" required><?php foreach($members as $member):?><option value="<?=$esc($member['subject'])?>" <?=$npc['owner_subject']===$member['subject']?'selected':''?>><?=$esc($member['label'])?></option><?php endforeach;?></select></label></p>
<button type="submit" <?=$members?'':'disabled'?>>Assign player</button>
<?=form_close()?>
<?php endif;?>
<p><?=anchor('personnel/character/'.(int)$npc['charid'],'Open character and private sheet access')?></p></section>
<?php endforeach;?>
<?php if(!$rows):?><p>No NPCs are available.</p><?php endif;?>
