<?php defined('BASEPATH') or exit('No direct script access allowed');
$escape=static fn($v)=>htmlspecialchars(is_scalar($v)?(string)$v:'',ENT_QUOTES,'UTF-8'); ?>
<h1>Create a character</h1>
<p>Choose the owning player. Shared characters get their Nova character and public homepage connected automatically. You can enter their details afterward.</p>
<?php if($message):?><p role="status"><?=$escape($message)?></p><?php endif;?>
<?=form_open('characters/create')?>
<input type="hidden" name="operation" value="<?=$escape($values['operation'])?>">
<p><label>Character name<br><input name="name" required maxlength="80" value="<?=$escape($values['name'])?>"></label></p>
<p><label>Character type<br><select name="kind">
<?php foreach(['pc'=>'Player character · shared public profile','npc'=>'NPC · private','public_npc'=>'NPC · shared public profile'] as $key=>$label):?>
<option value="<?=$key?>" <?=$values['kind']===$key?'selected':''?>><?=$escape($label)?></option>
<?php endforeach;?></select></label></p>
<p><label>Owning player<br><select name="owner" required>
<?php foreach($members as $member):?><option value="<?=$escape($member['subject'])?>" <?=$values['owner']===$member['subject']?'selected':''?>><?=$escape($member['label'])?> · <?=$escape(substr($member['subject'],0,8))?></option><?php endforeach;?>
</select></label></p>
<p>A new public profile starts with the character’s name only. Private sheet notes and biographies are never copied into it. Choose a private NPC to keep the character off the public site.</p>
<button type="submit" <?=$members?'':'disabled'?>>Create character</button>
<?=form_close()?>
<p><?=anchor('characters/create','Start a new setup')?> · <?=anchor('personnel/index','Character directory')?></p>
<?php if($pending):?><section aria-label="Unfinished character setups"><h2>Unfinished setups</h2><p>Resume a setup here if a connection problem interrupted creation.</p><ul><?php foreach($pending as $entry):?><li><?=anchor('characters/create?setup='.rawurlencode($entry['operation_id']),$escape($entry['display_name']))?></li><?php endforeach;?></ul></section><?php endif;?>
