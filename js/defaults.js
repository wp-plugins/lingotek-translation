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