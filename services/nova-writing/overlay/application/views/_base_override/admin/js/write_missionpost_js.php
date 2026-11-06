<?php if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}?>

<?php $string = random_string('alnum', 8);?>

<link rel="stylesheet" href="<?php echo base_url().MODFOLDER;?>/assets/js/css/bootstrap.css" />

<style>.publication-confirmation::backdrop{background:rgba(0,0,0,.65)}</style>
<script type="text/javascript">

	$(document).ready(function(){

		// using the CI user agent library instead of jquery's $.browser since the latter is deprecated
		var browser = "<?php echo $this->agent->browser();?>";
		var version = parseFloat("<?php echo $this->agent->version();?>");

		// check to see if we should be using the Chosen plugin
		if (browser == 'Internet Explorer' && version < 8)
			$('#chosen-incompat').show();
		else
			$('.chosen').chosen();

		$('#toggle_notes').click(function(){
			$('.notes_content').slideToggle('fast');
			return false;
		});

		$('[rel=tooltip]').twipsy({
			animate: false,
			offset: 5,
			placement: 'right'
		});

		$('#submitDelete').click(function(){
			return confirm('<?php echo lang('confirm_delete_missionpost');?>');
		});

		const publishButton=document.getElementById('submitPost');
		if(publishButton){
			const dialog=document.createElement('dialog');
			dialog.className='publication-confirmation';
			dialog.setAttribute('aria-labelledby','publish-scene-title');
			dialog.setAttribute('aria-describedby','publish-scene-description');
			dialog.style.cssText='max-width:32rem;width:calc(100% - 3rem);box-sizing:border-box;border:1px solid var(--st-edge);border-radius:.6rem;padding:1.5rem;background:var(--st-surface);color:var(--st-ink)';
			dialog.innerHTML='<h2 id="publish-scene-title">Publish this story?</h2><p id="publish-scene-description">The story will become readable by all Septenture players in the Exchange. Players with new-story emails enabled will receive a notification.</p><p>Your draft stays private until you publish.</p><div style="display:flex;gap:.7rem;justify-content:flex-end;flex-wrap:wrap"><button type="button" data-publish-cancel>Keep writing</button><button type="button" data-publish-confirm>Publish story</button></div>';
			document.body.append(dialog);
			const confirmButton=dialog.querySelector('[data-publish-confirm]');
			publishButton.addEventListener('click',event=>{event.preventDefault();confirmButton.disabled=false;dialog.showModal();dialog.querySelector('[data-publish-cancel]').focus();});
			dialog.querySelector('[data-publish-cancel]').addEventListener('click',()=>dialog.close());
			confirmButton.addEventListener('click',()=>{if(confirmButton.disabled)return;confirmButton.disabled=true;dialog.close();publishButton.form.requestSubmit(publishButton);});
		}


		<?php if ($missionCount == 0 and $authorized): ?>
			$.facebox(function(){
				$.get('<?php echo site_url('ajax/add_mission');?>/<?php echo $string;?>', function(data) {
					$.facebox(data);
				});
			});

			$(document).on('click', '#addMission', function(){
				var title = $('#addMissionTitle').val();
				var desc = $('#addMissionDesc').val();
				var option = $('#addMissionOption').val();

				$.ajax({
					type: "POST",
					url: "<?php echo site_url('ajax/add_mission_action');?>",
					data: { title: title, desc: desc, option: option, 'nova_csrf_token': $('input[name=nova_csrf_token]').val() }
				});
			});
		<?php endif;?>

		<?php if ($this->uri->segment(4) == 'view'): ?>
			$('#editable').hide();
			$('#readonly').show();
		<?php endif;?>

		<?php if ($missionNotesUpdate === true): ?>
			$('.notes_content').show();
		<?php endif;?>


	});
</script>