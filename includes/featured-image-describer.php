<?php
if (!defined('ABSPATH')) exit;

// Hook into post save
add_action('save_post', function($post_ID, $post, $update) {
    // Only run for posts/pages
    if (!in_array($post->post_type, array('post', 'page'))) return;
    // Only run for published or updated posts
    if (!in_array($post->post_status, array('publish', 'future', 'draft', 'pending', 'private'))) return;
    // Get featured image ID
    $thumb_id = get_post_thumbnail_id($post_ID);
    if (!$thumb_id) return;
    // Get file path
    $image_path = get_attached_file($thumb_id);
    if (!$image_path || !file_exists($image_path)) return;
    // Get current title and alt
    $current_title = get_the_title($thumb_id);
    $current_alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
    // Only describe if title or alt is empty
    if (!empty($current_title) && !empty($current_alt)) return;
    // Prepare API call
    $api_key = get_option('smart_ai_linker_ideogram_api_key', '');
    if (empty($api_key)) return; // Don't proceed if no API key is set
    $url = 'https://api.ideogram.ai/describe';
    // Use cURL for multipart/form-data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Api-Key: ' . $api_key
    ));
    $cfile = curl_file_create($image_path);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('image_file' => $cfile));
    $result = curl_exec($ch);
    curl_close($ch);
    if (!$result) return;
    $data = json_decode($result, true);
    if (empty($data['descriptions'][0]['text'])) return;
    $desc = trim($data['descriptions'][0]['text']);
    // Update title and alt if empty
    if (empty($current_title)) {
        wp_update_post(array('ID' => $thumb_id, 'post_title' => $desc));
    }
    if (empty($current_alt)) {
        update_post_meta($thumb_id, '_wp_attachment_image_alt', $desc);
    }
}, 21, 3);