jQuery(document).ready(function($) {
    'use strict';

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
        
        // Send AJAX request
        $.ajax({
            url: smartAILinker.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smart_ai_linker_generate_links',
                nonce: smartAILinker.nonce,
                post_id: smartAILinker.postId,
                action_type: 'generate'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(smartAILinker.i18n.success);
                    // Reload the page to show the updated content
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data || smartAILinker.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $message.removeClass('success').addClass('error').text(smartAILinker.i18n.error);
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
        
        if (!confirm(smartAILinker.i18n.clearConfirm)) {
            return;
        }
        
        var $button = $(this);
        var $spinner = $button.siblings('.spinner');
        var $message = $('#smart-ai-linker-message');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.removeClass('error success').text(smartAILinker.i18n.clearing);
        
        // Send AJAX request
        $.ajax({
            url: smartAILinker.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smart_ai_linker_generate_links',
                nonce: smartAILinker.nonce,
                post_id: smartAILinker.postId,
                action_type: 'clear'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data);
                    // Reload the page to show the updated content
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data || smartAILinker.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $message.removeClass('success').addClass('error').text(smartAILinker.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
