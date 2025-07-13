# Smart AI Linker

A WordPress plugin that uses AI to automatically add relevant internal links to your posts.

## Features

- **AI-Powered Internal Linking**: Uses DeepSeek AI to analyze your content and suggest relevant internal links
- **Automatic Link Insertion**: Automatically adds up to 7 relevant internal links when publishing a post
- **Manual Control**: Generate or clear links manually from the post editor
- **Customizable Settings**: Configure which post types to process and how many links to add
- **No URL Changes**: Works with your existing content structure without modifying URLs

## Installation

1. Upload the `smart-ai-linker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Smart AI Linker and enter your DeepSeek API key
4. Configure the plugin settings to your preference

## Configuration

1. **DeepSeek API Key**: Required for the plugin to work. Get your API key from [DeepSeek Platform](https://platform.deepseek.com/)
2. **Enable Auto-Linking**: Toggle automatic link generation when publishing posts
3. **Maximum Links per Post**: Set the maximum number of internal links to add to each post (1-10)
4. **Post Types**: Select which post types should be processed for internal linking

## Usage

### Automatic Linking

When you publish a new post, the plugin will automatically analyze the content and add relevant internal links based on the AI's suggestions.

### Manual Linking

1. Edit any post or page
2. Find the "Smart AI Linker" meta box in the sidebar
3. Click "Generate Links" to manually generate and insert links
4. Use "Clear Links" to remove all generated links from the post

## Frequently Asked Questions

### Where do I get a DeepSeek API key?

You can get an API key by signing up at [DeepSeek Platform](https://platform.deepseek.com/).

### Can I customize which posts the plugin links to?

The plugin automatically selects the most relevant internal links based on your content. You can manually edit or remove any links after they're inserted.

### Will this slow down my site?

The plugin is optimized for performance. The AI processing happens asynchronously, so it won't slow down your site's frontend.

## Support

For support, please [open an issue](https://github.com/moizxox/smart-ai-linker/issues) on GitHub.

## Changelog

### 1.0.0
* Initial release
