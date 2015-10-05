function toggleTextbox () {
    var box = document.getElementById('new_project');
    if (box.style.display === 'none') {
        box.style.display = 'inline';
        document.getElementById('project_id').style.display = 'none';
        document.getElementById('update_callback').style.visibility = 'hidden';
        document.getElementById('callback_label').style.display = 'none';
        document.getElementById('update_callback').checked = false;
        document.getElementById('create').innerHTML = '- Use Existing Project';
    }
    else if (box.style.display === 'inline') {
        box.style.display = 'none';
        document.getElementById('project_id').style.display= 'initial';
        document.getElementById('update_callback').style.visibility = 'visible';
        document.getElementById('callback_label').style.display = 'initial';
        document.getElementById('create').innerHTML = '+ Create New Project';
    }
}

jQuery(document).ready(function($) {
    function init_profiles() {
        var pl_choice = $('#post_lang_choice').val();
        var content_profiles = $('#lingotek-language-profiles').text();
        var content_json = $.parseJSON(content_profiles);
        var profile_name = content_json[pl_choice] ? content_json[pl_choice] : content_json.defaults.content_default;
        $('#lingotek_profile_meta option:first').text(content_json.defaults.title + ' (' + profile_name + ')');
    }
  if ($('#lingotek_profile_meta').val() == 'default') {
        $('#post_lang_choice').change(function(){
            init_profiles();
        });
        init_profiles();
    }
});