<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap smart-ai-bulk-center">
    <h1><?php _e('Bulk Processing Center', 'smart-ai-linker'); ?></h1>
    <div style="margin-bottom:20px;">
        <label for="bulk-center-post-type"><strong><?php _e('Select Post Type:', 'smart-ai-linker'); ?></strong></label>
        <select id="bulk-center-post-type">
            <?php
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $type) {
                if ($type->name === 'attachment') continue;
                echo '<option value="' . esc_attr($type->name) . '">' . esc_html($type->labels->singular_name) . '</option>';
            }
            ?>
        </select>
        <button id="bulk-center-load-posts" class="button">Load Unprocessed</button>
    </div>
    <div id="bulk-center-list-container">
        <!-- Unprocessed posts will be listed here -->
    </div>
    <div style="margin-top:20px;">
        <button id="bulk-center-start" class="button button-primary" disabled><?php _e('Bulk Process', 'smart-ai-linker'); ?></button>
    </div>
    <script>
(function($){
    let postList = [];
    let postType = '';
    let polling = null;

    function renderList(statuses) {
        let html = '<ul style="margin:0 0 20px 0;">';
        postList.forEach(function(post) {
            let badge = '';
            if (statuses && statuses[post.id]) {
                if (statuses[post.id] === 'processed') badge = '<span style="margin-left:8px;color:#fff;background:#46b450;padding:2px 8px;border-radius:8px;font-size:11px;">Processed</span>';
                else if (statuses[post.id] === 'error') badge = '<span style="margin-left:8px;color:#fff;background:#dc3232;padding:2px 8px;border-radius:8px;font-size:11px;">Error</span>';
                else if (statuses[post.id] === 'processing') badge = '<span style="margin-left:8px;color:#fff;background:#0073aa;padding:2px 8px;border-radius:8px;font-size:11px;">Processing</span>';
                else badge = '<span style="margin-left:8px;color:#fff;background:#aaa;padding:2px 8px;border-radius:8px;font-size:11px;">Queued</span>';
            } else {
                badge = '<span style="margin-left:8px;color:#fff;background:#aaa;padding:2px 8px;border-radius:8px;font-size:11px;">Queued</span>';
            }
            html += '<li data-id="'+post.id+'">['+post.id+'] '+$('<div>').text(post.title).html()+badge+'</li>';
        });
        html += '</ul>';
        $('#bulk-center-list-container').html(html);
    }

    function pollStatus() {
        $.post(ajaxurl, {action:'smart_ai_bulk_status'}, function(response){
            if(response.success) {
                renderList(response.data.progress.status);
                if(response.data.running) {
                    $('#bulk-center-start').prop('disabled', true);
                    $('#bulk-center-stop').prop('disabled', false);
                } else {
                    clearInterval(polling);
                    $('#bulk-center-start').prop('disabled', false);
                    $('#bulk-center-stop').prop('disabled', true);
                }
            }
        });
    }

    $('#bulk-center-load-posts').on('click', function() {
        postType = $('#bulk-center-post-type').val();
        $('#bulk-center-list-container').html('<em>Loading...</em>');
        $('#bulk-center-start').prop('disabled', true);
        $.post(ajaxurl, {
            action: 'smart_ai_bulk_get_unprocessed',
            post_type: postType
        }, function(response) {
            if (response.success && response.data.length > 0) {
                postList = response.data;
                renderList();
                $('#bulk-center-start').prop('disabled', false);
            } else {
                $('#bulk-center-list-container').html('<em>No unprocessed posts found.</em>');
                $('#bulk-center-start').prop('disabled', true);
            }
        });
    });

    $('#bulk-center-start').on('click', function() {
        if (!postList.length) return;
        $('#bulk-center-start').prop('disabled', true);
        $('#bulk-center-stop').prop('disabled', false);
        // Start processing
        $.post(ajaxurl, {action:'smart_ai_bulk_start', post_type:postType}, function(response){
            if(response.success) {
                // Start polling and processing
                polling = setInterval(function(){
                    $.post(ajaxurl, {action:'smart_ai_bulk_next'}, function(nextResp){
                        pollStatus();
                        if(nextResp.success && nextResp.data.done) {
                            clearInterval(polling);
                            pollStatus();
                        }
                    });
                }, 2000);
            }
        });
    });

    $('#bulk-center-stop').on('click', function() {
        $.post(ajaxurl, {action:'smart_ai_bulk_stop'}, function(){
            clearInterval(polling);
            pollStatus();
        });
    });

    // On page load, check if a process is running and restore state
    $(function(){
        $('#bulk-center-stop').prop('disabled', true);
        $.post(ajaxurl, {action:'smart_ai_bulk_status'}, function(response){
            if(response.success && response.data.running) {
                // If running, reload the list and start polling
                postType = $('#bulk-center-post-type').val();
                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_get_unprocessed',
                    post_type: postType
                }, function(resp) {
                    if (resp.success && resp.data.length > 0) {
                        postList = resp.data;
                        renderList(response.data.progress.status);
                        polling = setInterval(function(){
                            $.post(ajaxurl, {action:'smart_ai_bulk_next'}, function(nextResp){
                                pollStatus();
                                if(nextResp.success && nextResp.data.done) {
                                    clearInterval(polling);
                                    pollStatus();
                                }
                            });
                        }, 2000);
                        $('#bulk-center-start').prop('disabled', true);
                        $('#bulk-center-stop').prop('disabled', false);
                    }
                });
            }
        });
    });
})(jQuery);
</script>
</div>