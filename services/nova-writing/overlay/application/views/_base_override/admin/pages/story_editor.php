<?php if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}?>

<?php if(!$this->uri->segment(3)&&!empty($_SESSION['compose_character'])){$suggested=(int)$_SESSION['compose_character'];foreach(($all_characters??[]) as $group)if(is_array($group)&&array_key_exists($suggested,$group))$authors_selected=[$suggested];} ?>
<link rel="stylesheet" href="/assets/writing-workspace.css?v=20261104a">
<div class="writing-studio" data-writing-studio><p class="writing-eyebrow">THE SEPTENTURE WRITING ROOM</p>
<div class="studio-controls"><a href="<?php echo site_url('write/index'); ?>">← My drafts</a><button type="button" data-studio-focus aria-pressed="false">Focus mode</button><button type="button" data-studio-save>Save now</button><button type="button" data-studio-tag disabled>@ Tag writer</button><a href="/index.php/play/notebook" target="_blank" rel="noopener">My private notes ↗</a></div>
<?php echo text_output($story_id?'Continue the story':'A new scene', 'h1', 'page-head');?>

<?php if (isset($missions) and  ! $missions): ?>
	<?php echo text_output($label['no_mission'], 'p', 'bold');?>
<?php else: ?>

	<div id="editable">
		<?php echo form_open($form_action, array('id' => 'writepost'));?>
			<p>
				<kbd><?php echo $label['title'];?></kbd>
				<?php $title_input=$inputs['title'];$title_input['placeholder']='Untitled scene';$title_input['aria-label']='Story title';echo form_input($title_input);?>
			</p>

			<div>
				<kbd><?php echo $label['content'];?></kbd>
				<div data-story-editor data-autosave="<?php echo septenture_config('STORY_AUTOSAVE','1'); ?>" data-rich-text="<?php echo septenture_config('STORY_RICH_TEXT','1'); ?>" data-story-id="<?php echo $story_id; ?>" data-compose-character="<?php echo $story_id?0:(int)($_SESSION['compose_character']??0); ?>" data-revision="<?php echo $story_revision; ?>" data-account="<?php echo (int)SEPTENTURE_PLAYER_ID; ?>" data-view-only="<?php echo $this->uri->segment(4)==='view'?'true':'false'; ?>" data-preview-csrf="<?php echo htmlspecialchars($_SESSION['csrf']??'',ENT_QUOTES,'UTF-8'); ?>" data-format="<?php echo htmlspecialchars($story_format,ENT_QUOTES,'UTF-8'); ?>">
                 <div class="story-format-toolbar" role="group" aria-label="Story formatting">
                  <input type="hidden" name="post_content_format" data-story-format value="<?php echo $story_format; ?>">
                  <input type="hidden" name="story_revision" value="<?php echo $story_revision; ?>">
                  <input type="hidden" name="story_tab" value="">
                  <button type="button" data-story-rich>Rich text</button>
                  <button type="button" data-story-source>Markdown source</button>
                  <button type="button" data-story-preview>Formatted preview</button>
                 </div>
                 <div data-rich-toolbar role="toolbar" aria-label="Text formatting" hidden></div>
                 <p data-story-save-status role="status">Opening draft…</p>
                 <p data-story-storage-status role="alert"></p>
                 <p data-story-preview-status role="status"></p>
                 <button type="button" data-story-resume hidden>Resume editing</button>
                 <button type="button" data-story-convert <?php echo $story_format==='legacy'?'':'hidden'; ?>>Convert for rich-text editing</button>
                 <details class="format-history" data-format-history hidden><summary>Formatting history</summary><button type="button" data-story-backups>Review conversion backups</button></details>
                 <div data-story-recovery hidden></div>
                 <div data-story-conflict hidden></div>
                 <dialog data-story-conversion><h2>Review formatting conversion</h2><p data-conversion-warning></p><div class="story-compare"><section><h3>Current rendering</h3><div data-conversion-before class="story-body"></div></section><section><h3>Proposed rendering</h3><div data-conversion-after class="story-body"></div></section></div><button type="button" data-conversion-accept>Accept conversion</button><button type="button" data-conversion-cancel>Cancel</button></dialog>
                 <div data-story-rich-root class="story-body" hidden></div>
                 <?php $source_input=$inputs['content'];$source_input['value']="\n".$source_input['value']; /* HTML consumes the first textarea newline; preserve the actual source. */ echo form_textarea($source_input);?>
                 <div data-story-rendered class="story-body" role="region" aria-label="Formatted story preview" tabindex="0" hidden></div>
                </div>
				<div class="counter fontSmall">
					<?php echo $label['total_words'];?>: <span id="counter">0</span>
				</div>
			</div>

<details class="scene-details"><summary>Scene &amp; collaborators</summary><p>Scene collaborators can read and edit this draft. Add someone here before calling them into the scene.</p><div data-collaborators><label>Find a player<input type="search" data-collaborator-search placeholder="Player or character name"></label><div data-collaborator-list></div></div><details><summary>Additional character authors</summary>
			<?php if (isset($all_characters) and is_array($all_characters)): ?>
				<p>
					<kbd><?php echo $label['authors'];?></kbd>
					<span id="chosen-incompat" class="gray fontSmall bold hidden"><?php echo $label['chosen_incompat'];?><br /><br /></span>
					<?php echo form_multiselect('authors[]', $all_characters, $authors_selected, 'id="all" class="chosen" title="'.$label['select'].'"');?>
				</p>
			<?php endif;?></details>

			<p>
				<kbd><?php echo $label['mission'];?></kbd>
				<?php if (isset($missions)): ?>
					<?php echo form_dropdown('mission', $missions, $inputs['mission'], 'class="chosen"');?>
				<?php else: ?>
					<?php echo anchor('sim/missions/id/'. $mission['id'], $mission['title']); ?>
					<?php echo form_hidden('mission', $mission['id']);?>
				<?php endif; ?>
			</p>

			</details>
<details class="scene-details"><summary>Where &amp; when</summary><p>
				<kbd><?php echo $label['location'];?></kbd>
				<?php echo form_input($inputs['location']);?>
			</p>

			<p>
				<kbd><?php echo $label['timeline'];?></kbd>
				<?php echo form_input($inputs['timeline']);?>
			</p>

			</details>
<section class="scene-tags" data-scene-tags><h2>Pass the thread</h2><button type="button" data-tag-writer disabled>@ Tag a writer</button><div data-tag-picker hidden><label>Find a scene writer<input type="search" aria-label="Find a scene writer" autocomplete="off"></label><div role="listbox" aria-label="Scene writers"></div><button type="button" data-close-picker>Close picker</button></div><label>Whose turn is it?<select aria-label="Writer to notify"><option value="">Choose a scene writer</option></select></label><label><input type="checkbox" data-pass-turn checked> Pause my editing so they can write next</label><button type="button" data-notify-writer disabled>Notify writer</button><p role="status">Loading scene writers…</p></section>
            <details class="scene-details"><summary>Story topics</summary><p>
                <kbd><?php echo $label['tags'];?></kbd>
				<?php echo text_output($label['tags_sep'], 'span', 'fontSmall gray bold');?><br />
				<?php echo form_input($inputs['tags']);?>
				<?php echo img($images['help']);?>
			</p></details><br />

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
		<?php echo form_close();?>
	</div>

	<div id="readonly" class="hidden">
		<p class="fontMedium">
			<?php echo anchor('write/index', $label['back_wcp']);?>
			&middot;
			<?php echo anchor('write/missionpost/'.$this->uri->segment(3), $label['more_edits']);?>
		</p><br />

		<?php if ($inputs['locked']): ?>
			<div id="notes">
				<p class="float_left"><?php echo img($images['excl']);?>&nbsp;&nbsp;</p>
				<?php echo text_output($label['locked'], 'h4');?>
			</div>
		<?php endif;?>

		<p>
			<kbd><?php echo $label['authors'];?></kbd>
			<?php echo $this->char->get_authors(implode(',', $authors_selected));?>
		</p>

		<p>
			<kbd><?php echo $label['mission'];?></kbd>
			<?php if (isset($missions)): ?>
				<?php echo anchor('sim/missions/id/'.$inputs['mission'], $this->mis->get_mission($inputs['mission'], 'mission_title'));?>
			<?php else: ?>
				<?php echo anchor('sim/missions/id/'. $mission['id'], $mission['title']); ?>
			<?php endif; ?>
		</p>

		<p>
			<kbd><?php echo $label['title'];?></kbd>
			<?php echo $inputs['title']['value'];?>
		</p>

		<p>
			<kbd><?php echo $label['location'];?></kbd>
			<?php echo $inputs['location']['value'];?>
		</p>

		<p>
			<kbd><?php echo $label['timeline'];?></kbd>
			<?php echo $inputs['timeline']['value'];?>
		</p>

		<p>
			<kbd><?php echo $label['content'];?></kbd>
			</p><div class="story-body" data-story-readonly><?php if(!isset($GLOBALS['septenture_story_rejected']))echo Septenture_story_format::render((string)$inputs['content']['value'],$story_format); ?></div><p>
		</p>

		<p>
			<kbd><?php echo $label['tags'];?></kbd>
			<?php echo $inputs['tags']['value'];?>
		</p>
	</div>
<?php endif;?>
<details class="scene-details"><summary>Adventure reference</summary>	<?php if ($this->options['use_mission_notes'] == 'y'): ?>
		<div id="notes">
			<p class="float_right fontSmall">
				<a href="#" id="toggle_notes"><strong><?php echo $label['showhide'];?></strong></a>
			</p>
			<h3><?php echo $label['mission_notes'];?> <small class="gray"><?php echo $label['note_last_updated'];?></small></h3>
			<div class="notes_content hidden">
				<?php if ($missionNotesUpdate === true): ?>
					<span class="label label-warning"><?php echo $label['updated'];?></span>
				<?php endif;?>
				<?php if (isset($mission)): ?>
					<?php echo text_output($mission['notes']);?>
				<?php elseif (isset($mission_notes)): ?>
					<?php foreach ($mission_notes as $m): ?>
						<?php echo text_output($m['title'], 'p', 'bold');?>
						<?php echo text_output($m['notes']);?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</details></div><script src="/assets/writing-workspace.js?v=20261104c"></script>
