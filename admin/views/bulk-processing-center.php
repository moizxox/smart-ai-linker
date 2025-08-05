<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap smart-ai-bulk-center">
    <h1><?php _e('Bulk Processing Center', 'smart-ai-linker'); ?></h1>
    
    <div class="smart-ai-bulk-stats">
        <div class="stat-card">
            <h3><?php _e('Total Posts', 'smart-ai-linker'); ?></h3>
            <span id="total-posts">0</span>
        </div>
        <div class="stat-card">
            <h3><?php _e('Processed', 'smart-ai-linker'); ?></h3>
            <span id="processed-posts">0</span>
        </div>
        <div class="stat-card">
            <h3><?php _e('Skipped', 'smart-ai-linker'); ?></h3>
            <span id="skipped-posts">0</span>
        </div>
        <div class="stat-card">
            <h3><?php _e('Errors', 'smart-ai-linker'); ?></h3>
            <span id="error-posts">0</span>
        </div>
    </div>

    <div class="smart-ai-bulk-controls">
        <div class="control-group">
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
            <button id="bulk-center-load-posts" class="button"><?php _e('Load Unprocessed', 'smart-ai-linker'); ?></button>
        </div>
        
        <div class="control-group">
            <button id="bulk-center-start" class="button button-primary" disabled><?php _e('Start Processing', 'smart-ai-linker'); ?></button>
            <button id="bulk-center-stop" class="button button-secondary" style="display:none;"><?php _e('Stop Processing', 'smart-ai-linker'); ?></button>
            <button id="bulk-center-reset" class="button"><?php _e('Reset Progress', 'smart-ai-linker'); ?></button>
        </div>
    </div>

    <div class="smart-ai-progress-container" style="display:none;">
        <div class="progress-bar-container">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-text">0%</div>
        </div>
        <div class="progress-details">
            <div id="current-status"><?php _e('Ready to start...', 'smart-ai-linker'); ?></div>
            <div id="current-post">-</div>
        </div>
    </div>

    <div id="bulk-center-list-container">
        <div class="empty-state">
            <p><?php _e('Select a post type and click "Load Unprocessed" to see posts that need AI link processing.', 'smart-ai-linker'); ?></p>
        </div>
    </div>

    <div id="bulk-center-errors" class="error-container" style="display:none;">
        <h3><?php _e('Processing Errors', 'smart-ai-linker'); ?></h3>
        <div id="error-list"></div>
    </div>

    <style>
        .smart-ai-bulk-center {
            max-width: 1200px;
        }
        
        .smart-ai-bulk-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-card span {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .smart-ai-bulk-controls {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .control-group:last-child {
            margin-bottom: 0;
        }
        
        .smart-ai-progress-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .progress-bar-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            flex: 1;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-weight: bold;
            min-width: 50px;
        }
        
        .progress-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .progress-details > div {
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        #bulk-center-list-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        .post-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .post-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }
        
        .post-item:last-child {
            border-bottom: none;
        }
        
        .post-info {
            flex: 1;
        }
        
        .post-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .post-meta {
            font-size: 12px;
            color: #666;
        }
        
        .post-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-queued { background: #f0f0f0; color: #666; }
        .status-processing { background: #0073aa; color: #fff; }
        .status-processed { background: #46b450; color: #fff; }
        .status-skipped { background: #ffb900; color: #fff; }
        .status-error { background: #dc3232; color: #fff; }
        
        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px;
        }
        
        .error-container {
            background: #fff;
            border: 1px solid #dc3232;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .error-container h3 {
            color: #dc3232;
            margin-top: 0;
        }
        
        #error-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .error-item {
            padding: 8px;
            background: #fef7f7;
            border-left: 3px solid #dc3232;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>

    <script>
(function($){
    let postList = [];
    let postType = '';
    let polling = null;
    let isProcessing = false;
    let currentProgress = { total: 0, processed: 0, skipped: 0, errors: [] };

    function updateStats() {
        $('#total-posts').text(currentProgress.total);
        $('#processed-posts').text(currentProgress.processed);
        $('#skipped-posts').text(currentProgress.skipped);
        $('#error-posts').text(currentProgress.errors.length);
    }

    function renderList(statuses = {}) {
        if (postList.length === 0) {
            $('#bulk-center-list-container').html('<div class="empty-state"><p>No unprocessed posts found.</p></div>');
            return;
        }
        
        let html = '<div class="post-list">';
        postList.forEach(function(post) {
            let status = statuses[post.id] || 'queued';
            let statusClass = 'status-' + status;
            let statusText = status.charAt(0).toUpperCase() + status.slice(1);
            
            html += `
                <div class="post-item" data-id="${post.id}">
                    <div class="post-info">
                        <div class="post-title">${$('<div>').text(post.title).html()}</div>
                        <div class="post-meta">ID: ${post.id} | ${post.word_count} words</div>
                    </div>
                    <span class="post-status ${statusClass}">${statusText}</span>
                </div>
            `;
        });
        html += '</div>';
        $('#bulk-center-list-container').html(html);
    }

    function updateProgressBar(processed, total) {
        if (total === 0) return;
        
        let percent = Math.round((processed / total) * 100);
        $('.progress-fill').css('width', percent + '%');
        $('.progress-text').text(percent + '%');
    }

    function updateProgressDetails(progress) {
        currentProgress = progress;
        updateStats();
        updateProgressBar(progress.processed, progress.total);
        
        $('#current-status').text(`Processed: ${progress.processed} | Skipped: ${progress.skipped} | Errors: ${progress.errors.length}`);
        
        if (progress.status && Object.keys(progress.status).length > 0) {
            renderList(progress.status);
        }
    }

    function updateButtonStates() {
        if (isProcessing) {
            $('#bulk-center-start').prop('disabled', true).hide();
            $('#bulk-center-stop').show();
            $('.smart-ai-progress-container').show();
        } else {
            $('#bulk-center-start').prop('disabled', postList.length === 0).show();
            $('#bulk-center-stop').hide();
            $('.smart-ai-progress-container').hide();
        }
    }

    function showError(message) {
        $('#bulk-center-errors').show();
        $('#error-list').append(`<div class="error-item">${message}</div>`);
    }

    function clearErrors() {
        $('#bulk-center-errors').hide();
        $('#error-list').empty();
    }

    function pollStatus() {
        $.post(ajaxurl, {action:'smart_ai_bulk_status'}, function(response){
            if(response.success) {
                updateProgressDetails(response.data.progress);
                if(response.data.running) {
                    isProcessing = true;
                    updateButtonStates();
                } else {
                    clearInterval(polling);
                    isProcessing = false;
                    updateButtonStates();
                    if (response.data.progress.processed > 0) {
                        alert('Bulk processing completed!');
                    }
                }
            }
        });
    }

    function loadUnprocessedPosts() {
        postType = $('#bulk-center-post-type').val();
        $('#bulk-center-list-container').html('<div class="empty-state"><p>Loading...</p></div>');
        $('#bulk-center-start').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'smart_ai_bulk_get_unprocessed',
            post_type: postType
        }, function(response) {
            if (response.success && response.data.length > 0) {
                postList = response.data;
                renderList();
                updateButtonStates();
                clearErrors();
            } else {
                postList = [];
                $('#bulk-center-list-container').html('<div class="empty-state"><p>No unprocessed posts found.</p></div>');
                updateButtonStates();
            }
        }).fail(function() {
            $('#bulk-center-list-container').html('<div class="empty-state"><p>Error loading posts. Please try again.</p></div>');
            updateButtonStates();
        });
    }

    $('#bulk-center-load-posts').on('click', function() {
        loadUnprocessedPosts();
    });

    $('#bulk-center-post-type').on('change', function() {
        // Reset state when post type changes
        postList = [];
        isProcessing = false;
        currentProgress = { total: 0, processed: 0, skipped: 0, errors: [] };
        if (polling) {
            clearInterval(polling);
            polling = null;
        }
        renderList();
        updateButtonStates();
        clearErrors();
        updateStats();
    });

    $('#bulk-center-start').on('click', function() {
        if (!postList.length || isProcessing) return;
        
        isProcessing = true;
        updateButtonStates();
        clearErrors();
        
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
            } else {
                isProcessing = false;
                updateButtonStates();
                showError('Failed to start processing: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            isProcessing = false;
            updateButtonStates();
            showError('Failed to start processing. Please try again.');
        });
    });

    $('#bulk-center-stop').on('click', function() {
        $.post(ajaxurl, {action:'smart_ai_bulk_stop'}, function(){
            clearInterval(polling);
            isProcessing = false;
            updateButtonStates();
            pollStatus();
        });
    });

    $('#bulk-center-reset').on('click', function() {
        if (confirm('This will reset all progress and start fresh. Continue?')) {
            $.post(ajaxurl, {action:'smart_ai_bulk_stop'}, function(){
                clearInterval(polling);
                isProcessing = false;
                currentProgress = { total: 0, processed: 0, skipped: 0, errors: [] };
                updateButtonStates();
                updateStats();
                clearErrors();
                renderList();
            });
        }
    });

    // On page load, initialize the interface
    $(function(){
        updateButtonStates();
        updateStats();
        
        // Check for running process
        $.post(ajaxurl, {action:'smart_ai_bulk_status'}, function(resp) {
            if (resp.success && resp.data.running) {
                isProcessing = true;
                updateProgressDetails(resp.data.progress);
                updateButtonStates();
                setTimeout(pollStatus, 1000);
            }
        });
    });
})(jQuery);
</script>
</div>