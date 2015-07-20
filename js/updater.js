jQuery(document).ready(function($) {
  var checkboxes = $('#the-list').find('input');
  var current_ids = {};
  var post_data = {"check_ids" : current_ids};
  var url = window.location.href;
  var end = url.indexOf('wp-admin') + 'wp-admin'.length;
  var relative_url = url.substring(0,end);
  var ajax_url = relative_url + '/admin-ajax.php?action=get_current_status';
  var page_params = '/edit.php?';
  var tr_id = '#post-';
  if(url.indexOf('taxonomy') > -1){
    var begin = url.indexOf('taxonomy=') + 'taxonomy='.length;
    var taxonomy_type = url.substring(begin);
  }
  $(checkboxes).each(function(){
    current_ids[$(this).val()] = $(this).val();
  });
  if(taxonomy_type === 'category'){
    var uncategorized_id = 1;
    current_ids[uncategorized_id] = uncategorized_id;
  }
  if($('.edit-tags-php').length > 0){
    post_data['terms_translations'] = true;
    page_params = '/edit-tags.php?taxonomy=' + taxonomy_type + '&';
    tr_id = '#tag-';
  }
  setInterval(function(){
    $.ajax({
      type: 'POST',
      url: ajax_url,
      data: post_data,
      dataType: 'json',
      success: function (data) {
        if (data !== null) {
          update_indicators(data);
        }
      }
    });
  },10000);
  

  function update_indicators(data){
    for(var key in data){
      if(key.indexOf('_nonce') > -1) {
        continue;
      }
      var tr = $(tr_id + key);
      for(var locale in data[key]){
        if(locale === 'source' || locale === 'doc_id'){
          continue;
        }
        var td = $(tr).find('td.language_' + locale);
        switch(data[key][locale]['status']){
          case 'current':
            if(locale === data[key]['source']){
              $(td).find('.lingotek-color').remove();
              
              if(post_data['terms_translations'] === true){
                var request_link = $('<a></a>').attr('href', relative_url
                      + '/edit-tags.php?action=edit'
                      + '&taxonomy=' + taxonomy_type
                      + '&tag_ID=' + key
                      + '&post_type=post')
                .attr('title','Source uploaded')
                .addClass('lingotek-color dashicons dashicons-yes');
              }
              else {
                var request_link = $('<a></a>').attr('href', relative_url
                      + '/post.php?post= ' + key
                      + '&action=edit')
                .attr('title','Source uploaded')
                .addClass('lingotek-color dashicons dashicons-yes');
              }

              $(td).append(request_link);
            }
            else {
              $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', data[key][locale]['workbench_link'])
                .attr('title','Current')
                .attr('target','_blank')
                .addClass('lingotek-color dashicons dashicons-edit');
              $(td).append(request_link);
            }
            break;
          case 'pending':
            $(td).find('.lingotek-color').remove();
            var request_link = $('<a></a>').attr('href', data[key][locale]['workbench_link'])
              .attr('title','In Progress')
              .attr('target','_blank')
              .addClass('lingotek-color dashicons dashicons-clock');
            $(td).append(request_link);
            break;
          case 'importing':
            $(td).find('.lingotek-color').remove();
             var request_link = $('<a></a>').attr('href', relative_url
                     + page_params + 'document_id=' + data[key]['doc_id']
                     + '&locale=' + locale 
                     + '&action=lingotek-status'
                     + '&noheader=1'
                     + '&_wpnonce=' + data['status_nonce'])
                .attr('title','Importing source')
                .addClass('lingotek-color dashicons dashicons-clock');
              $(td).append(request_link);
            break;
          case 'not-current' :
              $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', data[key][locale]['workbench_link'])
                .attr('title','The target translation is no longer current as the source content has been updated')
                .attr('target','_blank')
                .addClass('lingotek-color dashicons dashicons-edit');
              $(td).append(request_link);
            break;
          case 'edited':
              $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', relative_url
                      + page_params + 'post= ' + key
                      + '&locale=' + locale
                      + '&action=lingotek-upload'
                      + '&noheader=1'
                      + '&_wpnonce=' + data['upload_nonce'])
                .attr('title','Upload Now')
                .addClass('lingotek-color dashicons dashicons-upload');
              $(td).append(request_link);
            break;
          case 'ready':
            $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', relative_url
                      + page_params + 'document_id=' + data[key]['doc_id']
                      + '&locale=' + locale 
                      + '&action=lingotek-download'
                      + '&noheader=1'
                      + '&_wpnonce='+data['download_nonce'])
                .attr('title','Ready to download')
                .addClass('lingotek-color dashicons dashicons-download');
              $(td).append(request_link);
            break;
          default:
            if(locale === data[key]['source']){
              $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', relative_url
                      + page_params + 'post= ' + key 
                      + '&locale=' + locale 
                      + '&action=lingotek-upload'
                      + '&noheader=1'
                      + '&_wpnonce=' + data['upload_nonce'])
                .attr('title','Upload Now')
                .addClass('lingotek-color dashicons dashicons-upload');
              $(td).append(request_link);
            }
            else if ($(td).find('.pll_icon_add').length > 0 && data[key][data[key]['source']]['status'] === 'none'){
              break;
            }
            else if(data[key][data[key]['source']]['status'] === 'current'){
              $(td).find('.pll_icon_add').remove();
              $(td).find('.lingotek-color').remove();
              var request_link = $('<a></a>').attr('href', relative_url
                      + page_params + 'document_id=' + data[key]['doc_id']
                      + '&locale='+locale+'&action=lingotek-request'
                      + '&noheader=1'
                      + '&_wpnonce='+data['request_nonce'])
                .attr('title','Request a translation')
                .addClass('lingotek-color dashicons dashicons-plus');
              $(td).append(request_link);
            }
            else {
              $(td).find('.pll_icon_add').remove();
              $(td).find('.lingotek-color').remove();
              var indicator = $('<div></div>').addClass('lingotek-color dashicons dashicons-no');
              $(td).append(indicator);
            }
            break;
        }
      }
    }
  }
});
