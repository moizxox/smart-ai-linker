# Smart AI Linker

A powerful WordPress plugin that uses advanced AI to automatically create meaningful internal links, improving your site's SEO and user engagement.

## 🚀 Key Features

- **Advanced AI-Powered Linking**: Leverages DeepSeek AI for intelligent, context-aware internal link suggestions
- **Smart Content Analysis**: Automatically analyzes and processes both posts and pages for optimal linking
- **Automatic Link Insertion**: Seamlessly adds up to 7 relevant internal links (configurable) when publishing content
- **Intelligent Anchor Text**: Generates natural-sounding anchor text that flows with your content
- **Comprehensive Post & Page Support**: Works with all standard post types and pages
- **Content Safety**: Preserves all existing HTML formatting and prevents broken links
- **SEO Optimization**: Enhances your site's internal linking structure for better search engine visibility
- **Performance Optimized**: Efficient processing with minimal impact on site speed

## 🛠 Installation

1. Upload the `smart-ai-linker` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings > Smart AI Linker
4. Enter your DeepSeek API key (get it from [DeepSeek Platform](https://platform.deepseek.com/))
5. Configure your preferred settings (auto-linking, maximum links, post types, etc.)

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- cURL extension enabled
- DeepSeek API key

## ⚙️ Configuration

### Plugin Settings

1. **API Configuration**
   - **DeepSeek API Key**: Required for AI processing. [Get your API key](https://platform.deepseek.com/)
   
2. **Linking Settings**
   - **Enable Auto-Linking**: Toggle automatic link generation when publishing/updating content
   - **Maximum Links per Post**: Set between 1-10 links (default: 7)
   - **Link Target**: Choose whether links open in the same or new tab
   
3. **Content Types**
   - **Post Types**: Select which content types to process (posts, pages, custom post types)
   - **Include Pages**: Toggle whether to include pages in link suggestions
   
4. **Advanced Options**
   - **Minimum Word Count**: Set minimum content length for processing (default: 100 words)
   - **Enable Debug Logging**: For troubleshooting (recommended for development only)

## 🎯 Usage

### Automatic Linking (Recommended)
1. Write or edit your content as usual
2. Publish or update the post/page
3. The plugin will automatically analyze and insert relevant internal links
4. Links are inserted with the class `smart-ai-link` for easy styling

### Manual Linking
1. Edit any post or page
2. Locate the "Smart AI Linker" meta box in the sidebar
3. Click "Generate Links" to create and insert links manually
4. Use "Clear Links" to remove all AI-generated links from the content

### Bulk Processing

#### Process Multiple Posts/Pages
1. Navigate to Posts or Pages in WordPress admin
2. Select the posts/pages you want to process
3. Choose "Generate AI Links" from the Bulk Actions dropdown
4. Click "Apply" to process all selected items

#### Process All Unprocessed Content
1. Go to Posts or Pages in WordPress admin
2. Look for the "Process All Unprocessed" button above the posts list
3. Click the button to automatically process all unprocessed posts/pages
4. A progress indicator will show the status
5. You'll receive a success message when complete

#### How It Works
- The plugin tracks which posts have been processed
- Only unprocessed posts will be included in bulk actions
- You can reprocess posts by manually clearing the "Processed" flag in post meta
- The system automatically skips posts that are too short or already processed

## ❓ Frequently Asked Questions

### Where do I get a DeepSeek API key?
Sign up at [DeepSeek Platform](https://platform.deepseek.com/) to get your API key. The free tier includes sufficient requests for most small to medium sites.

### How does the plugin choose which links to insert?
The AI analyzes your content's context and suggests the most relevant internal links based on semantic meaning, content relevance, and your site's structure.

### Can I edit the suggested links?
Yes! All AI-generated links can be manually edited or removed directly in the post editor. The plugin won't modify your changes during future updates.

### Will this affect my site's performance?
The plugin is optimized for minimal impact:
- AI processing happens asynchronously
- No frontend scripts are loaded for visitors
- Efficient database queries
- Built-in caching of AI responses

### How many links will be added to each post?
By default, up to 7 links are added, but you can adjust this in the settings. The actual number may be less if the AI can't find enough relevant content to link to.

## 📈 Benefits

### For Content Creators
- Saves hours of manual link building
- Improves content quality and depth
- Enhances reader experience with relevant internal links

### For Site Owners
- Boosts SEO performance
- Increases page views and reduces bounce rates
- Strengthens content hierarchy and site structure

## 🛠 Support

For support or feature requests, please [open an issue](https://github.com/nerdxsolution/smart-ai-linker/issues) on GitHub.

## 📜 Changelog

### 1.1.0
* Added support for both posts and pages
* Improved AI prompt for better link suggestions
* Enhanced JSON parsing for reliability
* Added more detailed logging
* Improved error handling and user feedback

### 1.0.0
* Initial release with core functionality
