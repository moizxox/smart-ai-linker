/**
 * Smart AI Linker Admin JavaScript
 * Handles all admin-side functionality including silo management, settings, and dashboard interactions
 */
(function($) {
    'use strict';

    // Global variables
    var ajaxInProgress = false;
    var currentSiloId = 0;
    var currentTab = 'dashboard';

    // Initialize the admin interface
    function init() {
        // Initialize tabs
        initTabs();
        
        // Initialize modals
        initModals();
        
        // Initialize tooltips
        initTooltips();
        
        // Initialize form handlers
        initForms();
        
        // Initialize AJAX actions
        initAjaxHandlers();
    }
    
    // Initialize tab navigation
    function initTabs() {
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show corresponding content
            var tab = $(this).data('tab');
            $('.tab-content').hide();
            $('#' + tab + '-tab').show();
            
            // Update current tab
            currentTab = tab;
            
            // Save tab state in URL
            if (history.pushState) {
                var newUrl = window.location.pathname + '?page=' + smartAIData.page + '&tab=' + tab;
                window.history.pushState({path: newUrl}, '', newUrl);
            }
        });
        
        // Check for tab in URL
        var urlParams = new URLSearchParams(window.location.search);
        var tabParam = urlParams.get('tab');
        if (tabParam) {
            $('.nav-tab-wrapper a[data-tab="' + tabParam + '"]').trigger('click');
        }
    }
    
    // Initialize modal dialogs
    function initModals() {
        // Close modal when clicking outside content
        $(document).on('click', '.modal', function(e) {
            if ($(e.target).hasClass('modal')) {
                closeModal($(this).attr('id'));
            }
        });
        
        // Close button
        $(document).on('click', '.close-modal', function() {
            closeModal($(this).closest('.modal').attr('id'));
        });
        
        // Escape key to close modal
        $(document).keyup(function(e) {
            if (e.key === 'Escape') {
                $('.modal:visible').each(function() {
                    closeModal($(this).attr('id'));
                });
            }
        });
    }
    
    // Initialize tooltips
    function initTooltips() {
        $('.tooltip-trigger').tooltip({
            content: function() {
                return $(this).attr('title');
            },
            tooltipClass: 'smart-ai-tooltip',
            position: {
                my: 'center bottom-10',
                at: 'center top',
                using: function(position, feedback) {
                    $(this).css(position);
                    $('<div>')
                        .addClass('arrow')
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);
                }
            }
        });
    }
    
    // Initialize form handlers
    function initForms() {
        // Create silo form
        $('#create-silo-form').on('submit', function(e) {
            e.preventDefault();
            createSilo();
        });
        
        // Edit silo form
        $('#edit-silo-form').on('submit', function(e) {
            e.preventDefault();
            updateSilo();
        });
        
        // Bulk assign form
        $('#bulk-assign-form').on('submit', function(e) {
            e.preventDefault();
            bulkAssignSilos();
        });
    }
    
    // Initialize AJAX handlers
    function initAjaxHandlers() {
        // Delete silo
        $(document).on('click', '.delete-silo', function(e) {
            e.preventDefault();
            
            if (!confirm(smartAIData.i18n.confirm_delete)) {
                return;
            }
            
            var $row = $(this).closest('tr');
            var siloId = $(this).data('silo-id');
            
            $.ajax({
                url: smartAIData.ajax_url,
                type: 'POST',
                data: {
                    action: 'smart_ai_delete_silo',
                    nonce: smartAIData.nonce,
                    silo_id: siloId
                },
                beforeSend: function() {
                    $row.addClass('updating');
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        showNotice('Silo deleted successfully', 'success');
                    } else {
                        showNotice(response.data.message || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    showNotice('Failed to delete silo. Please try again.', 'error');
                },
                complete: function() {
                    $row.removeClass('updating');
                }
            });
        });
        
        // Edit silo
        $(document).on('click', '.edit-silo', function(e) {
            e.preventDefault();
            
            currentSiloId = $(this).data('silo-id');
            var name = $(this).data('name');
            var description = $(this).data('description');
            
            $('#edit-silo-name').val(name);
            $('#edit-silo-description').val(description);
            
            openModal('edit-silo-modal');
        });
        
        // Cancel edit
        $(document).on('click', '.cancel-edit', function() {
            closeModal('edit-silo-modal');
        });
        
        // Bulk assign button
        $('#bulk-assign-button').on('click', function() {
            bulkAssignSilos();
        });
        
        // Analyze all content
        $('#analyze-all-content').on('click', function() {
            if (confirm('This will analyze all your content for silo assignment. This may take a while. Continue?')) {
                analyzeAllContent();
            }
        });
        
        // Clear cache
        $('#clear-cache').on('click', function() {
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $notice = $('#cache-cleared');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: smartAIData.ajax_url,
                type: 'POST',
                data: {
                    action: 'smart_ai_clear_cache',
                    nonce: smartAIData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notice.fadeIn().delay(3000).fadeOut();
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
    }
    
    // Create a new silo
    function createSilo() {
        if (ajaxInProgress) return;
        
        var $form = $('#create-silo-form');
        var $button = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        
        var name = $('#silo-name').val().trim();
        var description = $('#silo-description').val().trim();
        
        if (!name) {
            showNotice('Please enter a silo name', 'error');
            return;
        }
        
        ajaxInProgress = true;
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: smartAIData.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_ai_create_silo',
                nonce: smartAIData.nonce,
                name: name,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    // Add new silo to the table
                    var silo = response.data.silo;
                    var newRow = `
                        <tr data-silo-id="${silo.id}">
                            <td class="silo-name">
                                <strong>${escapeHtml(silo.name)}</strong>
                                ${silo.description ? `<p class="description">${escapeHtml(silo.description)}</p>` : ''}
                            </td>
                            <td><code>${escapeHtml(silo.slug)}</code></td>
                            <td>0</td>
                            <td class="actions">
                                <a href="#" class="button button-small edit-silo" 
                                   data-silo-id="${silo.id}"
                                   data-name="${escapeHtml(silo.name)}"
                                   data-description="${escapeHtml(silo.description || '')}">
                                    Edit
                                </a>
                                <a href="#" class="button button-small view-posts" 
                                   data-silo-id="${silo.id}">
                                    View Posts
                                </a>
                                <a href="#" class="button button-small delete-silo" 
                                   data-silo-id="${silo.id}">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    `;
                    
                    $('.silos-list-card tbody').prepend(newRow);
                    
                    // Update bulk assign dropdown
                    $('#bulk-silo').append(`
                        <option value="${silo.id}">${escapeHtml(silo.name)}</option>
                    `);
                    
                    // Reset form
                    $form.trigger('reset');
                    showNotice('Silo created successfully', 'success');
                } else {
                    showNotice(response.data.message || 'Failed to create silo', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                ajaxInProgress = false;
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    // Update an existing silo
    function updateSilo() {
        if (ajaxInProgress || !currentSiloId) return;
        
        var $form = $('#edit-silo-form');
        var $button = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        
        var name = $('#edit-silo-name').val().trim();
        var description = $('#edit-silo-description').val().trim();
        
        if (!name) {
            showNotice('Please enter a silo name', 'error');
            return;
        }
        
        ajaxInProgress = true;
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: smartAIData.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_ai_update_silo',
                nonce: smartAIData.nonce,
                silo_id: currentSiloId,
                name: name,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    // Update the silo row
                    var silo = response.data.silo;
                    var $row = $(`tr[data-silo-id="${silo.id}"]`);
                    
                    $row.find('.silo-name strong').text(silo.name);
                    $row.find('.silo-name .description').text(silo.description || '');
                    $row.find('code').text(silo.slug);
                    
                    // Update edit button data
                    $row.find('.edit-silo')
                        .data('name', silo.name)
                        .data('description', silo.description || '');
                    
                    // Update bulk assign dropdown
                    $(`#bulk-silo option[value="${silo.id}"]`).text(silo.name);
                    
                    closeModal('edit-silo-modal');
                    showNotice('Silo updated successfully', 'success');
                } else {
                    showNotice(response.data.message || 'Failed to update silo', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                ajaxInProgress = false;
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    // Bulk assign silos to posts
    function bulkAssignSilos() {
        if (ajaxInProgress) return;
        
        var postType = $('#bulk-post-type').val();
        var siloId = $('#bulk-silo').val();
        
        if (!siloId) {
            showNotice('Please select a silo', 'error');
            return;
        }
        
        if (!confirm('This will assign the selected silo to all ' + postType + ' posts. Continue?')) {
            return;
        }
        
        ajaxInProgress = true;
        var $button = $('#bulk-assign-button');
        var $spinner = $button.siblings('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Show progress modal
        var $modal = $('#ai-progress-modal');
        var $progressBar = $modal.find('.progress-bar');
        var $progressMessage = $modal.find('#progress-message');
        var $progressDetails = $modal.find('#progress-details');
        
        $progressBar.css('width', '0%');
        $progressMessage.text('Starting bulk assignment...');
        $progressDetails.empty();
        
        openModal('ai-progress-modal');
        
        // Start the process
        processBulkAssignment(postType, siloId, 0, 20, function(processed, total) {
            var percent = Math.round((processed / total) * 100);
            $progressBar.css('width', percent + '%');
            $progressMessage.text('Processed ' + processed + ' of ' + total + ' posts');
            
            if (processed >= total) {
                $progressMessage.text('Bulk assignment completed!');
                showNotice('Bulk assignment completed successfully', 'success');
                closeModal('ai-progress-modal');
            }
        });
    }
    
    // Process bulk assignment in chunks
    function processBulkAssignment(postType, siloId, offset, limit, progressCallback) {
        if (ajaxInProgress === false) return;
        
        $.ajax({
            url: smartAIData.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_ai_bulk_assign_silos',
                nonce: smartAIData.nonce,
                post_type: postType,
                silo_id: siloId,
                offset: offset,
                limit: limit
            },
            success: function(response) {
                if (response.success) {
                    var processed = offset + response.data.processed;
                    var total = response.data.total;
                    
                    progressCallback(processed, total);
                    
                    // Continue processing if there are more posts
                    if (processed < total) {
                        setTimeout(function() {
                            processBulkAssignment(postType, siloId, processed, limit, progressCallback);
                        }, 500);
                    } else {
                        ajaxInProgress = false;
                        $('#bulk-assign-button').prop('disabled', false)
                            .siblings('.spinner').removeClass('is-active');
                    }
                } else {
                    ajaxInProgress = false;
                    closeModal('ai-progress-modal');
                    showNotice(response.data.message || 'Bulk assignment failed', 'error');
                }
            },
            error: function() {
                ajaxInProgress = false;
                closeModal('ai-progress-modal');
                showNotice('An error occurred during bulk assignment', 'error');
            }
        });
    }
    
    // Analyze all content for silo assignment
    function analyzeAllContent() {
        if (ajaxInProgress) return;
        
        ajaxInProgress = true;
        var $button = $('#analyze-all-content');
        var $spinner = $button.siblings('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Show progress modal
        var $modal = $('#ai-progress-modal');
        var $progressBar = $modal.find('.progress-bar');
        var $progressMessage = $modal.find('#progress-message');
        var $progressDetails = $modal.find('#progress-details');
        
        $progressBar.css('width', '0%');
        $progressMessage.text('Analyzing content...');
        $progressDetails.empty();
        
        openModal('ai-progress-modal');
        
        // Start analysis
        analyzeContentBatch(0, 10, 0, 0, function(processed, total, assigned) {
            var percent = total > 0 ? Math.round((processed / total) * 100) : 0;
            $progressBar.css('width', percent + '%');
            $progressMessage.text(
                'Analyzed ' + processed + ' of ' + total + ' posts. ' +
                'Assigned ' + assigned + ' silos so far.'
            );
            
            if (processed >= total) {
                $progressMessage.text('Analysis completed!');
                showNotice('Content analysis completed successfully', 'success');
                closeModal('ai-progress-modal');
            }
        });
    }
    
    // Analyze content in batches
    function analyzeContentBatch(offset, limit, totalProcessed, totalAssigned, progressCallback) {
        if (ajaxInProgress === false) return;
        
        $.ajax({
            url: smartAIData.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_ai_analyze_content_batch',
                nonce: smartAIData.nonce,
                offset: offset,
                limit: limit
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var processed = totalProcessed + data.processed;
                    var assigned = totalAssigned + (data.assigned || 0);
                    
                    progressCallback(processed, data.total, assigned);
                    
                    // Continue processing if there are more posts
                    if (processed < data.total) {
                        setTimeout(function() {
                            analyzeContentBatch(processed, limit, processed, assigned, progressCallback);
                        }, 500);
                    } else {
                        ajaxInProgress = false;
                        $('#analyze-all-content').prop('disabled', false)
                            .siblings('.spinner').removeClass('is-active');
                    }
                } else {
                    ajaxInProgress = false;
                    closeModal('ai-progress-modal');
                    showNotice(response.data.message || 'Content analysis failed', 'error');
                }
            },
            error: function() {
                ajaxInProgress = false;
                closeModal('ai-progress-modal');
                showNotice('An error occurred during content analysis', 'error');
            }
        });
    }
    
    // Open a modal dialog
    function openModal(modalId) {
        $('body').addClass('modal-open');
        $('#' + modalId).fadeIn(200);
    }
    
    // Close a modal dialog
    function closeModal(modalId) {
        $('body').removeClass('modal-open');
        $('#' + modalId).fadeOut(200);
    }
    
    // Show a notice message
    function showNotice(message, type) {
        var $notice = $('<div>').addClass('notice notice-' + type + ' is-dismissible')
            .append($('<p>').text(message))
            .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>')
            .hide()
            .insertAfter('.wp-header-end')
            .fadeIn();
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Dismiss on click
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize the main functionality
        init();
        
        // Handle Generate Links button click
        $(document).on('click', '#smart-ai-linker-generate', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $message = $('#smart-ai-linker-message');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.removeClass('error success').text(smartAILinker.i18n.generating);
            
            // Make AJAX request to generate links
            $.ajax({
                url: smartAILinker.ajax_url,
                type: 'POST',
                data: {
                    action: 'smart_ai_linker_generate_links',
                    nonce: smartAILinker.nonce,
                    post_id: smartAILinker.postId || 0
                },
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success').text(response.data.message || smartAILinker.i18n.success);
                        
                        // Update stats if available
                        if (response.data.stats) {
                            var stats = response.data.stats;
                            $('.smart-ai-stats .links-created').text(stats.links_created || '0');
                            $('.smart-ai-stats .posts-processed').text(stats.posts_processed || '0');
                        }
                        
                        // Reload the page if this is a single post edit
                        if (smartAILinker.postId) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        $message.removeClass('success').addClass('error')
                            .text(response.data.message || smartAILinker.i18n.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $message.removeClass('success').addClass('error')
                        .text(smartAILinker.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Handle Clear Links button click
        $(document).on('click', '#smart-ai-linker-clear', function(e) {
            e.preventDefault();
            
            if (!confirm(smartAILinker.i18n.confirm_clear || 'Are you sure you want to clear all generated links?')) {
                return;
            }
            
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $message = $('#smart-ai-linker-message');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.removeClass('error success').text(smartAILinker.i18n.clearing || 'Clearing links...');
            
            // Make AJAX request to clear links
            $.ajax({
                url: smartAILinker.ajax_url,
                type: 'POST',
                data: {
                    action: 'smart_ai_linker_clear_links',
                    nonce: smartAILinker.nonce,
                    post_id: smartAILinker.postId || 0
                },
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success')
                            .text(response.data.message || 'Links cleared successfully');
                        
                        // Reset stats
                        $('.smart-ai-stats .links-created').text('0');
                        $('.smart-ai-stats .posts-processed').text('0');
                        
                        // Reload the page if this is a single post edit
                        if (smartAILinker.postId) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        $message.removeClass('success').addClass('error')
                            .text(response.data.message || smartAILinker.i18n.error || 'An error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $message.removeClass('success').addClass('error')
                        .text(smartAILinker.i18n.error || 'An error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
    });
    
    // Close the IIFE
})(jQuery);
