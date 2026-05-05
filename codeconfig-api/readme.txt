=== CodeConfig API ===
Contributors: codeconfig
Tags: plugin, updates, api, custom-updates
Requires at least: 5.6
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom update API server for CodeConfig plugins. Manage and distribute plugin updates from your own WordPress site.

== Description ==

CodeConfig API is a custom update server that allows you to host and distribute plugin updates from your WordPress site. It provides a complete solution for managing plugin versions, tracking downloads, and serving updates to clients.

= Features =

* **Version Management** - Upload and manage multiple plugin versions
* **Update API** - RESTful endpoint for client plugins to check updates
* **Direct Downloads** - Secure download with token-based authorization
* **User Management** - Track and manage API users with keys
* **Analytics** - Track update checks and downloads
* **Headless Mode** - Option to show a simple page for non-WordPress requests
* **Auto-deactivation** - Automatically handles missing plugin files

= Usage =

1. Install and activate the plugin
2. Go to **CodeConfig API → Versions** to upload your first plugin version
3. In your client plugin, configure the update server URL:
   ```php
   ccupd(array(
       'api_url' => 'https://your-site.com/wp-json/codeconfig/v1',
       'slug' => 'your-plugin-slug',
       'basename' => 'your-plugin/your-plugin.php',
       'version' => '1.0.0',
       'name' => 'Your Plugin',
   ));
   ```

= REST API Endpoints =

* `GET /wp-json/codeconfig/v1/update-check` - Check for updates
  * Parameters: `version`, `slug`, `api_key`, `domain`
* `GET /wp-json/codeconfig/v1/latest-download` - Get latest download URL
  * Parameters: `slug`

= Support =

For support, visit https://codeconfig.io

== Installation ==

1. Upload the `codeconfig-api` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure versions at **CodeConfig API → Versions**

== Changelog ==

= 1.0.0 =
* Initial release
* Version management with upload/activate/deactivate/delete
* Download button in versions table
* File not found detection and auto-deactivation
* Admin notice for auto-deactivated versions
* REST API endpoints for update checking
* Direct download via ID
* User and API key management
* Analytics tracking
* Headless mode support