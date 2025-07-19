# Smart AI Linker

A powerful WordPress plugin that uses advanced AI to automatically create meaningful internal links and manage content silos, significantly improving your site's SEO, content organization, and user engagement.

## 🚀 Key Features

### AI-Powered Internal Linking
- **Smart Content Analysis**: Automatically analyzes content to identify optimal linking opportunities
- **Context-Aware Suggestions**: Uses DeepSeek AI to generate relevant, natural-sounding anchor text
- **Automated Link Insertion**: Adds up to 7 relevant internal links (configurable)
- **Bulk Processing**: Generate or update links across multiple posts at once

### Content Silo Management
- **Silo Creation & Management**: Easily create and organize content silos
- **Bulk Silo Assignment**: Assign multiple posts to silos in one go
- **Auto-Silo Assignment**: AI analyzes content to suggest appropriate silos
- **Silo-Based Navigation**: Built-in tools to create silo-based navigation structures

### SEO & Performance
- **SEO Optimization**: Enhances internal linking structure for better search visibility
- **Content Organization**: Improves content architecture with silo structures
- **Performance Optimized**: Efficient processing with minimal impact on site speed
- **Comprehensive Reporting**: Track link performance and silo effectiveness

## 🛠 Installation

1. Upload the `smart-ai-linker` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create necessary database tables
4. Navigate to Smart AI Linker > Settings in your WordPress admin
5. Enter your DeepSeek API key (get it from [DeepSeek Platform](https://platform.deepseek.com/))
6. Configure your preferred settings for linking and silo management

### Requirements
- WordPress 5.6 or higher
- PHP 7.4 or higher (PHP 8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- cURL extension enabled
- DeepSeek API key (for AI features)

## ⚙️ Configuration

### 1. General Settings
- **API Configuration**
  - **DeepSeek API Key**: Required for AI processing [Get your API key](https://platform.deepseek.com/)
  - **Enable AI Features**: Toggle AI-powered suggestions on/off

### 2. Linking Settings
- **Auto-Linking**: Enable/disable automatic link generation
  - **On Publish**: Generate links when content is published/updated
  - **On Save**: Generate links when content is saved as draft
- **Link Generation**
  - **Maximum Links**: Set between 1-10 links per post (default: 7)
  - **Link Target**: Choose _blank (new tab) or _self (same tab)
  - **Nofollow Links**: Add rel="nofollow" to generated links

### 3. Silo Management
- **Auto-Silo Assignment**: Enable AI to suggest silos based on content
- **Default Silo**: Set a default silo for new content
- **Silo Hierarchy**: Enable parent-child relationships between silos

### 4. Content Types
- **Enabled Post Types**: Select which content types to process
- **Minimum Word Count**: Set minimum content length for processing (default: 100 words)
- **Exclude IDs**: List post IDs to exclude from processing

### 5. Advanced Options
- **Debug Mode**: Enable detailed error logging
- **API Timeout**: Set timeout for API requests (default: 30s)
- **Batch Size**: Number of posts to process in bulk operations

## 🎯 Getting Started

### 1. Setting Up Your First Silo
1. Go to Smart AI Linker > Silo Management
2. Click "Add New Silo"
3. Enter a name and description for your silo
4. (Optional) Set a parent silo to create hierarchies
5. Click "Save Silo"

### 2. Assigning Content to Silos
#### Manual Assignment
1. Edit any post/page
2. Find the "Silo Assignment" meta box
3. Select one or more silos for the content
4. Update the post

#### Bulk Assignment
1. Go to Posts/Pages list
2. Select multiple items using checkboxes
3. Choose "Edit" from Bulk Actions
4. Set the silo in the bulk editor
5. Click "Update"

### 3. Generating Links
#### Automatic Linking
1. Ensure "Auto-Linking" is enabled in settings
2. Create or update a post
3. The plugin will automatically generate relevant links

#### Manual Linking
1. Edit a post
2. Click "Generate Links" in the Smart AI Linker meta box
3. Review and adjust suggestions
4. Click "Insert Links"

### 4. Bulk Processing
1. Go to Smart AI Linker > Dashboard
2. Click "Bulk Generate Links"
3. Select content to process
4. Click "Process" and monitor progress

## 📊 Monitoring & Reporting

### Link Analysis
- View all generated links
- See which posts link to each other
- Check for broken or outdated links

### Silo Reports
- Content distribution across silos
- Internal link structure visualization
- SEO impact analysis

## 🔍 Advanced Features

### Custom Link Rules
- Define specific linking rules
- Set preferred anchor text
- Create manual link overrides

### Performance Optimization
- Schedule bulk operations during off-peak hours
- Set processing priorities
- Monitor resource usage

## 🤝 Support

For support, feature requests, or bug reports, please:
1. Check the [FAQ](#) section
2. Visit our [support forum](#)
3. Contact support@example.com

## 📜 Changelog

### 1.0.0 - 2025-07-19
- Initial release with complete silo management
- AI-powered internal linking
- Bulk processing capabilities
- Comprehensive reporting

## 🔒 Security

All data is processed securely:
- API connections use HTTPS
- No sensitive data is stored
- Regular security audits
- GDPR compliant

## 👥 Contributing

We welcome contributions! Please read our [contributing guidelines](CONTRIBUTING.md) before submitting pull requests.

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
