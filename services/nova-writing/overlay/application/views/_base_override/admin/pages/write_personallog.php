<?php if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}?>

<?php
$journalId=(int)$this->uri->segment(3);if(!$journalId&&!empty($_SESSION['compose_character']))foreach(($characters??[]) as $group)if(is_array($group)&&array_key_exists((int)$_SESSION['compose_character'],$group))$key=(int)$_SESSION['compose_character'];$journalRow=$journalId?$this->logs->get_log($journalId):false;
$label['author']='Character';$writerRow=$this->db->select('name')->get_where('users',['userid'=>SEPTENTURE_PLAYER_ID])->row();echo '<p>Writer: '.htmlspecialchars($writerRow->name??'Player',ENT_QUOTES,'UTF-8').'</p>';
$journalFormat=$journalRow->log_content_format??'markdown';$journalRevision=$journalRow->log_revision??0;
$rejected=$GLOBALS['septenture_journal_rejected']??null;
if($rejected){foreach(['content','title','tags'] as $field)if(isset($rejected[$field]))$inputs[$field]['value']=$rejected[$field];}
if(isset($GLOBALS['septenture_journal_error']))echo '<p role="alert">'.htmlspecialchars($GLOBALS['septenture_journal_error'],ENT_QUOTES,'UTF-8').'</p>';
?>
<?php echo text_output($header, 'h1', 'page-head');?>

<?php echo form_open($form_action);?>
<input type="hidden" name="log_content_format" value="<?php echo htmlspecialchars($journalFormat,ENT_QUOTES,'UTF-8');?>">
<input type="hidden" name="journal_revision" value="<?php echo (int)$journalRevision;?>">
<div data-journal-editor data-account="<?php echo (int)SEPTENTURE_PLAYER_ID;?>" data-journal-id="<?php echo $journalId;?>" data-revision="<?php echo $journalRevision;?>" data-format="<?php echo $journalFormat;?>"></div>
	<p>
		<kbd><?php echo $label['author'];?></kbd>
		<?php if (isset($characters)): ?>
			<?php echo form_dropdown('author', $characters, $key, 'class="chosen"');?>
		<?php else: ?>
			<?php echo text_output($character['name'], ''); ?>
			<?php echo form_hidden('author', $character['id']);?>
		<?php endif; ?>
	</p>

	<p>
		<kbd><?php echo $label['title'];?></kbd>
		<?php echo form_input($inputs['title']);?>
	</p>

	<p>
		<kbd><?php echo $label['content'];?></kbd>
		<?php echo form_textarea($inputs['content']);?>
		<div class="counter fontSmall">
			<?php echo $label['total_words'];?>: <span id="counter">0</span>
		</div>
	</p>

	<p>
		<kbd><?php echo $label['tags'];?></kbd>
		<?php echo text_output($label['tags_sep'], 'span', 'fontSmall gray bold');?><br />
		<?php echo form_input($inputs['tags']);?>
		<?php echo img($images['help']);?>
	</p><br />

	<p>
		<?php echo form_button($inputs['post']);?>
		&nbsp;
		<?php echo form_button($inputs['save']);?>

		<?php if ($this->uri->segment(3) !== false): ?>
			&nbsp;
			<?php echo form_button($inputs['delete']);?>
		<?php endif; ?>

		&nbsp;
		<?php echo anchor('write/index', $label['back_wcp']);?>
	</p>
<?php echo form_close();?><script defer src="/assets/journal-editor.js?v=<?=substr(hash_file('sha256',APPPATH.'../assets/journal-editor.js'),0,12)?>"></script>