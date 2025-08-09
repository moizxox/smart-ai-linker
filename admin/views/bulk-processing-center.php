<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap smart-ai-bulk-center">
    <h1><?php _e('Bulk Processing Center', 'smart-ai-linker'); ?></h1>

    <div class="smart-ai-bulk-stats">
        <div class="stat-card">
            <h3><?php _e('Total Posts', 'smart-ai-linker'); ?></h3>
            <span id="total-posts">0</span>
        </div>
        <div class="stat-card">
            <h3><?php _e('Processed', 'smart-ai-linker'); ?></h3>Processed: 0 | Skipped: 0 | Errors: 0
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
            <button id="bulk-center-resume" class="button button-primary" style="display:none;"><?php _e('Resume Processing', 'smart-ai-linker'); ?></button>
            <button id="bulk-center-reset" class="button"><?php _e('Reset Progress', 'smart-ai-linker'); ?></button>
        </div>

        <div id="processing-status" class="processing-status" style="display:none;">
            <div class="status-indicator">
                <span class="spinner is-active"></span>
                <span class="status-text">Processing...</span>
            </div>
            <div class="status-details">
                <span id="current-processing-post-type"></span>
                <span id="processing-time"></span>
            </div>
        </div>
    </div>

    <div class="smart-ai-progress-container" style="display:none;" id="progress-container">
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

        .processing-status {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 10px 15px;
            margin-top: 10px;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .status-text {
            font-weight: bold;
            color: #0073aa;
        }

        .status-details {
            font-size: 12px;
            color: #666;
        }

        .status-details span {
            margin-right: 15px;
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

        .progress-details>div {
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

        .status-queued {
            background: #f0f0f0;
            color: #666;
        }

        .status-processing {
            background: #0073aa;
            color: #fff;
        }

        .status-processed {
            background: #46b450;
            color: #fff;
        }

        .status-skipped {
            background: #ffb900;
            color: #fff;
        }

        .status-error {
            background: #dc3232;
            color: #fff;
        }

        .post-status {
            position: relative;
            padding-right: 20px;
        }

        .post-status .verification-icon {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }

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

        .disabled-control {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>

    <script>
        (function($) {
            let postList = [];
            let postType = '';
            let polling = null;
            let isProcessing = false;
            let currentProgress = {
                total: 0,
                processed: 0,
                skipped: 0,
                errors: []
            };
            let processingStartTime = null;

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

                    // Add verification indicator for processed posts
                    let verificationIcon = '';
                    let linkCount = '';
                    if (status === 'processed') {
                        verificationIcon = ' <span style="color: #46b450; font-size: 12px;">✓</span>';
                        // Get actual link count for processed posts
                        $.post(ajaxurl, {
                            action: 'smart_ai_bulk_get_link_count',
                            post_id: post.id
                        }, function(response) {
                            if (response.success && response.data.link_count > 0) {
                                $(`.post-item[data-id="${post.id}"] .post-status`).append(` <span style="color: #46b450; font-size: 10px;">(${response.data.link_count} links)</span>`);
                            }
                        });
                    } else if (status === 'error') {
                        verificationIcon = ' <span style="color: #dc3232; font-size: 12px;">⚠</span>';
                    } else if (status === 'skipped') {
                        verificationIcon = ' <span style="color: #ffb900; font-size: 12px;">⏭</span>';
                    }

                    html += `
                <div class="post-item" data-id="${post.id}">
                    <div class="post-info">
                        <div class="post-title">${$('<div>').text(post.title).html()}</div>
                        <div class="post-meta">ID: ${post.id} | ${post.word_count} words</div>
                    </div>
                    <span class="post-status ${statusClass}">${statusText}${verificationIcon}</span>
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
                    $('#bulk-center-resume').hide(); // Hide resume by default, will be shown if stuck
                    $('#progress-container').show();
                    $('#processing-status').show();
                } else {
                    $('#bulk-center-start').prop('disabled', postList.length === 0).show();
                    $('#bulk-center-stop').hide();
                    $('#bulk-center-resume').hide();
                    $('#progress-container').hide();
                    $('#processing-status').hide();
                }
            }

            function updateProcessingStatus(processingInfo) {
                if (processingInfo && processingInfo.post_type) {
                    $('#current-processing-post-type').text(`Processing: ${processingInfo.post_type}`);

                    if (processingInfo.started_at) {
                        let startTime = new Date(processingInfo.started_at);
                        let now = new Date();
                        let diff = Math.floor((now - startTime) / 1000);
                        let minutes = Math.floor(diff / 60);
                        let seconds = diff % 60;
                        $('#processing-time').text(`Duration: ${minutes}m ${seconds}s`);
                    }
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

            // On page load, initialize the interface
            $(function() {
                updateButtonStates();
                updateStats();

                // Check for running process and post type locking
                checkProcessingStatus();

                // Set up periodic status checks
                setInterval(checkProcessingStatus, 10000); // Check every 10 seconds
            });

            // Enhanced function to check and resume processing
            function checkProcessingStatus() {
                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_get_processing_status'
                }, function(response) {
                    if (response.success) {
                        if (response.data.is_processing) {
                            // Another process is running
                            let currentProcessing = response.data.current_processing;
                            let progress = response.data.progress;
                            let isStuck = response.data.is_stuck;
                            let queueCount = response.data.queue_count;

                            console.log('Found running process:', currentProcessing);
                            console.log('Progress:', progress);
                            console.log('Is stuck:', isStuck);
                            console.log('Queue count:', queueCount);

                            // Set the post type to match the running process
                            if (currentProcessing.post_type) {
                                $('#bulk-center-post-type').val(currentProcessing.post_type);
                                postType = currentProcessing.post_type;
                            }

                            // Update the interface to show processing state
                            isProcessing = true;
                            updateButtonStates();
                            updateProcessingStatus(currentProcessing);

                            if (progress) {
                                updateProgressDetails(progress);

                                // Check if processing is stuck
                                if (isStuck) {
                                    console.log('Processing appears to be stuck, showing resume option');
                                    $('#bulk-center-resume').show();
                                    $('#bulk-center-stop').hide();
                                    showError('Processing appears to be stuck. Click "Resume Processing" to continue.');
                                } else if (queueCount > 0) {
                                    // If we have progress but no queue, try to resume processing
                                    if (progress.total > 0 && (progress.processed + progress.skipped) < progress.total) {
                                        console.log('Attempting to resume processing...');
                                        resumeProcessing();
                                    }
                                }
                            }

                            // Start polling for updates
                            if (!polling) {
                                polling = setInterval(pollStatus, 2000);
                            }
                        } else {
                            // No process running
                            isProcessing = false;
                            updateButtonStates();
                            if (polling) {
                                clearInterval(polling);
                                polling = null;
                            }
                        }
                    }
                }).fail(function() {
                    console.log('Status check failed');
                });
            }

            // New function to resume processing
            function resumeProcessing() {
                console.log('Resuming processing...');

                // Start the processing loop
                if (!polling) {
                    polling = setInterval(function() {
                        $.post(ajaxurl, {
                            action: 'smart_ai_bulk_next'
                        }, function(nextResp) {
                            if (nextResp.success) {
                                // Update progress immediately
                                if (nextResp.data.progress) {
                                    updateProgressDetails(nextResp.data.progress);
                                }

                                if (nextResp.data.done) {
                                    clearInterval(polling);
                                    isProcessing = false;
                                    updateButtonStates();
                                    alert('Bulk processing completed!');
                                } else {
                                    // Update current post info if available
                                    if (nextResp.data.current_post) {
                                        $('#current-post').text(nextResp.data.current_post);
                                    }
                                }
                            } else {
                                console.log('Processing error:', nextResp.data);
                                clearInterval(polling);
                                isProcessing = false;
                                updateButtonStates();
                                showError('Processing error: ' + (nextResp.data || 'Unknown error'));
                            }
                        }).fail(function() {
                            console.log('Processing request failed');
                            clearInterval(polling);
                            isProcessing = false;
                            updateButtonStates();
                            showError('Processing failed. Please try again.');
                        });
                    }, 2000);
                }
            }

            // Enhanced poll status function
            function pollStatus() {
                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_status'
                }, function(response) {
                    if (response.success) {
                        // Update progress with verified data
                        updateProgressDetails(response.data.progress);
                        updateProcessingStatus(response.data.current_processing);

                        if (response.data.running) {
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
                }).fail(function() {
                    console.log('Status polling failed, will retry...');
                });
            }

            // Enhanced load unprocessed posts function
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
                    } else if (response.success && response.data.length === 0) {
                        postList = [];
                        $('#bulk-center-list-container').html('<div class="empty-state"><p>No unprocessed posts found.</p></div>');
                        updateButtonStates();
                    } else {
                        // Handle error response
                        showError(response.data || 'Failed to load posts');
                        postList = [];
                        updateButtonStates();
                    }
                }).fail(function() {
                    $('#bulk-center-list-container').html('<div class="empty-state"><p>Error loading posts. Please try again.</p></div>');
                    updateButtonStates();
                });
            }

            // Enhanced post type change handler
            $('#bulk-center-post-type').on('change', function() {
                // Check if this post type is currently being processed
                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_get_processing_status'
                }, function(response) {
                    if (response.success && response.data.is_processing) {
                        let currentProcessing = response.data.current_processing;
                        if (currentProcessing.post_type === $('#bulk-center-post-type').val()) {
                            // This post type is being processed, show current status
                            isProcessing = true;
                            updateProcessingStatus(currentProcessing);
                            if (response.data.progress) {
                                updateProgressDetails(response.data.progress);
                            }
                            updateButtonStates();

                            // Start polling
                            if (!polling) {
                                polling = setInterval(pollStatus, 2000);
                            }
                        } else {
                            // Another post type is being processed
                            showError(`Another post type (${currentProcessing.post_type}) is currently being processed. Please wait for it to complete.`);
                            $('#bulk-center-post-type').addClass('disabled-control');
                            $('#bulk-center-load-posts').addClass('disabled-control');
                        }
                    } else {
                        // No processing happening, reset state
                        $('#bulk-center-post-type').removeClass('disabled-control');
                        $('#bulk-center-load-posts').removeClass('disabled-control');
                        postList = [];
                        isProcessing = false;
                        currentProgress = {
                            total: 0,
                            processed: 0,
                            skipped: 0,
                            errors: []
                        };
                        if (polling) {
                            clearInterval(polling);
                            polling = null;
                        }
                        renderList();
                        updateButtonStates();
                        clearErrors();
                        updateStats();
                        $('#progress-container').hide();
                        $('#processing-status').hide();
                    }
                });
            });

            // Enhanced start processing function
            $('#bulk-center-start').on('click', function() {
                if (!postList.length || isProcessing) return;

                isProcessing = true;
                updateButtonStates();
                clearErrors();
                processingStartTime = new Date();

                // Start processing
                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_start',
                    post_type: postType
                }, function(response) {
                    if (response.success) {
                        // Start polling and processing
                        polling = setInterval(function() {
                            $.post(ajaxurl, {
                                action: 'smart_ai_bulk_next'
                            }, function(nextResp) {
                                if (nextResp.success) {
                                    // Update progress immediately
                                    if (nextResp.data.progress) {
                                        updateProgressDetails(nextResp.data.progress);
                                    }

                                    if (nextResp.data.done) {
                                        clearInterval(polling);
                                        isProcessing = false;
                                        updateButtonStates();
                                        alert('Bulk processing completed!');
                                    } else {
                                        // Update current post info if available
                                        if (nextResp.data.current_post) {
                                            $('#current-post').text(nextResp.data.current_post);
                                        }

                                        // Show verification status if available
                                        if (nextResp.data.verified_status) {
                                            console.log(`Post ${nextResp.data.current_post_id} verified as: ${nextResp.data.verified_status}`);
                                        }
                                    }
                                } else {
                                    clearInterval(polling);
                                    isProcessing = false;
                                    updateButtonStates();
                                    showError('Processing error: ' + (nextResp.data || 'Unknown error'));
                                }
                            }).fail(function() {
                                clearInterval(polling);
                                isProcessing = false;
                                updateButtonStates();
                                showError('Processing failed. Please try again.');
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

            // Enhanced stop processing function
            $('#bulk-center-stop').on('click', function() {
                if (polling) {
                    clearInterval(polling);
                    polling = null;
                }

                $.post(ajaxurl, {
                    action: 'smart_ai_bulk_stop'
                }, function(response) {
                    if (response.success) {
                        isProcessing = false;
                        updateButtonStates();
                        showError('Processing stopped by user.');
                    } else {
                        showError('Failed to stop processing.');
                    }
                }).fail(function() {
                    isProcessing = false;
                    updateButtonStates();
                    showError('Failed to stop processing. Please try again.');
                });
            });

            // Enhanced reset function
            $('#bulk-center-reset').on('click', function() {
                if (confirm('This will reset all progress and start fresh. Continue?')) {
                    if (polling) {
                        clearInterval(polling);
                        polling = null;
                    }

                    $.post(ajaxurl, {
                        action: 'smart_ai_bulk_stop'
                    }, function(response) {
                        isProcessing = false;
                        currentProgress = {
                            total: 0,
                            processed: 0,
                            skipped: 0,
                            errors: []
                        };
                        updateButtonStates();
                        updateStats();
                        clearErrors();
                        renderList();
                        $('#progress-container').hide();
                        $('#processing-status').hide();
                        $('.progress-fill').css('width', '0%');
                        $('.progress-text').text('0%');
                        $('#current-status').text('Ready to start...');
                        $('#current-post').text('-');
                        $('#bulk-center-post-type').removeClass('disabled-control');
                        $('#bulk-center-load-posts').removeClass('disabled-control');
                    }).fail(function() {
                        // Even if the stop request fails, reset the UI
                        isProcessing = false;
                        currentProgress = {
                            total: 0,
                            processed: 0,
                            skipped: 0,
                            errors: []
                        };
                        updateButtonStates();
                        updateStats();
                        clearErrors();
                        renderList();
                        $('#progress-container').hide();
                        $('#processing-status').hide();
                        $('.progress-fill').css('width', '0%');
                        $('.progress-text').text('0%');
                        $('#current-status').text('Ready to start...');
                        $('#current-post').text('-');
                        $('#bulk-center-post-type').removeClass('disabled-control');
                        $('#bulk-center-load-posts').removeClass('disabled-control');
                    });
                }
            });

            // Add the load posts button handler
            $('#bulk-center-load-posts').on('click', function() {
                loadUnprocessedPosts();
            });

            // Add resume processing handler
            $('#bulk-center-resume').on('click', function() {
                if (confirm('Resume processing from where it left off?')) {
                    $.post(ajaxurl, {
                        action: 'smart_ai_bulk_force_resume'
                    }, function(response) {
                        if (response.success) {
                            if (response.data.resumed) {
                                console.log('Processing resumed successfully');
                                showError('Processing resumed successfully. Processing will continue...');

                                // Start the processing loop
                                if (!polling) {
                                    polling = setInterval(function() {
                                        $.post(ajaxurl, {
                                            action: 'smart_ai_bulk_next'
                                        }, function(nextResp) {
                                            if (nextResp.success) {
                                                // Update progress immediately
                                                if (nextResp.data.progress) {
                                                    updateProgressDetails(nextResp.data.progress);
                                                }

                                                if (nextResp.data.done) {
                                                    clearInterval(polling);
                                                    isProcessing = false;
                                                    updateButtonStates();
                                                    alert('Bulk processing completed!');
                                                } else {
                                                    // Update current post info if available
                                                    if (nextResp.data.current_post) {
                                                        $('#current-post').text(nextResp.data.current_post);
                                                    }
                                                }
                                            } else {
                                                console.log('Processing error:', nextResp.data);
                                                clearInterval(polling);
                                                isProcessing = false;
                                                updateButtonStates();
                                                showError('Processing error: ' + (nextResp.data || 'Unknown error'));
                                            }
                                        }).fail(function() {
                                            console.log('Processing request failed');
                                            clearInterval(polling);
                                            isProcessing = false;
                                            updateButtonStates();
                                            showError('Processing failed. Please try again.');
                                        });
                                    }, 2000);
                                }
                            } else if (response.data.done) {
                                alert('All posts have already been processed!');
                                // Clean up the interface
                                isProcessing = false;
                                updateButtonStates();
                                clearErrors();
                            }
                        } else {
                            showError('Failed to resume processing: ' + (response.data || 'Unknown error'));
                        }
                    }).fail(function() {
                        showError('Failed to resume processing. Please try again.');
                    });
                }
            });
        })(jQuery);
    </script>
</div>
</div>