# Bulk Processing Error Handling Guide

## Overview

This guide helps identify and fix errors that occur during bulk processing of internal links. The system now includes comprehensive error handling and debugging to help identify issues.

## Common Error Scenarios

### 1. **API Key Issues**
**Symptoms**: Posts show as "error" with "Failed to get AI suggestions" message
**Solutions**:
- Check if API key is set in plugin settings
- Verify API key is valid and has sufficient credits
- Test API connection manually

### 2. **Content Processing Issues**
**Symptoms**: Posts show as "error" with "Failed to insert links" message
**Solutions**:
- Check if post content is valid HTML
- Verify post has sufficient content (minimum word count)
- Check for malformed HTML that might break DOM processing

### 3. **Database Issues**
**Symptoms**: Processing stops unexpectedly or progress is lost
**Solutions**:
- Check database permissions
- Verify WordPress database is accessible
- Check for database connection issues

### 4. **Memory/Timeout Issues**
**Symptoms**: Processing stops after a few posts or times out
**Solutions**:
- Increase PHP memory limit
- Increase max execution time
- Process fewer posts at once

## Debugging Steps

### Step 1: Enable Debug Logging

Add this to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Check Error Logs

Look for errors in:
- `wp-content/debug.log` (WordPress debug log)
- Server error logs
- Plugin-specific logs with `[Smart AI]` prefix

### Step 3: Run Diagnostic Test

Visit any admin page with `?test_bulk_processing=1` to run comprehensive tests.

### Step 4: Check System Requirements

Verify these extensions are loaded:
- `curl` - for API requests
- `json` - for JSON processing
- `mbstring` - for text processing
- `libxml` - for HTML processing

## Error Categories and Solutions

### API Errors

**Error**: "Failed to get AI suggestions after X attempts"
**Causes**:
- Invalid API key
- Network connectivity issues
- API rate limiting
- Invalid response format

**Solutions**:
1. Verify API key in plugin settings
2. Check network connectivity
3. Reduce processing speed (add delays)
4. Check API response format

### Content Processing Errors

**Error**: "Failed to insert links into post"
**Causes**:
- Invalid HTML content
- DOM processing errors
- Memory issues
- Database update failures

**Solutions**:
1. Check post content for malformed HTML
2. Increase PHP memory limit
3. Verify database permissions
4. Check for conflicting plugins

### Database Errors

**Error**: "Failed to update post in database"
**Causes**:
- Database connection issues
- Insufficient permissions
- Table corruption
- Locked tables

**Solutions**:
1. Check database connectivity
2. Verify user permissions
3. Repair database tables
4. Check for database locks

## Testing Individual Components

### Test AI Client
```php
// Test AI suggestions
$content = "Sample content for testing";
$post_id = 123;
$suggestions = smart_ai_linker_get_ai_link_suggestions($content, $post_id, 'post');
var_dump($suggestions);
```

### Test Internal Linking
```php
// Test link insertion
$post_id = 123;
$links = [
    ['anchor' => 'test', 'url' => 'https://example.com/test']
];
$result = smart_ai_linker_insert_links_into_post($post_id, $links);
var_dump($result);
```

### Test Single Post Processing
```php
// Test complete processing
$post_id = 123;
$result = smart_ai_linker_process_single_post($post_id);
var_dump($result);
```

## Performance Optimization

### For Large Sites
1. **Reduce batch size**: Process fewer posts at once
2. **Add delays**: Add sleep() between posts
3. **Increase timeouts**: Extend PHP execution time
4. **Use background processing**: Consider WP-Cron or custom cron

### For Small Sites
1. **Process all at once**: Use larger batch sizes
2. **Reduce delays**: Minimize sleep() times
3. **Optimize queries**: Use efficient database queries

## Troubleshooting Checklist

### Before Processing
- [ ] API key is set and valid
- [ ] Required extensions are loaded
- [ ] Database is accessible
- [ ] Sufficient memory available
- [ ] Debug logging is enabled

### During Processing
- [ ] Monitor error logs
- [ ] Check progress regularly
- [ ] Verify database updates
- [ ] Monitor memory usage

### After Processing
- [ ] Verify links were inserted
- [ ] Check post content integrity
- [ ] Review error logs
- [ ] Test sample posts

## Common Error Messages and Solutions

### "API key not set"
**Solution**: Set API key in plugin settings

### "cURL is not available"
**Solution**: Install/enable cURL extension

### "Failed to parse JSON response"
**Solution**: Check API response format, verify API key

### "No suitable text nodes found"
**Solution**: Check post content, ensure it has text content

### "Failed to update post in database"
**Solution**: Check database permissions, verify table integrity

### "Memory limit exceeded"
**Solution**: Increase PHP memory limit in php.ini

### "Maximum execution time exceeded"
**Solution**: Increase max_execution_time in php.ini

## Advanced Debugging

### Enable Verbose Logging
Add this to your theme's functions.php or a custom plugin:
```php
add_action('init', function() {
    if (isset($_GET['debug_ai'])) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
});
```

### Test Individual Functions
Create a test script to verify each component:
```php
// Test script
require_once 'wp-config.php';
require_once 'wp-load.php';

// Test AI client
$result = smart_ai_linker_get_ai_link_suggestions("test content", 1, 'post');
echo "AI Test: " . (empty($result) ? "FAILED" : "PASSED") . "\n";

// Test link insertion
$result = smart_ai_linker_insert_links_into_post(1, [['anchor' => 'test', 'url' => '/test']]);
echo "Link Test: " . ($result ? "PASSED" : "FAILED") . "\n";
```

## Getting Help

If you're still experiencing issues:

1. **Enable debug logging** and check logs
2. **Run the diagnostic test** with `?test_bulk_processing=1`
3. **Check system requirements** (extensions, memory, etc.)
4. **Test with a single post** first
5. **Review error messages** in the logs

The enhanced error handling will provide detailed information about what's failing and where the issue occurs. 