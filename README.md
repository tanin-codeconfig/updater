# WordPress Custom Update System

A complete custom update system for WordPress plugins with version management, storage handling, and automated update checks via cron scheduling.

## Overview

This project consists of two main components:

1. **my-plugin** - The client plugin that receives updates
2. **api-plugin** - The server plugin that manages versions and serves update information

## Features

- Custom update API with REST endpoints
- ZIP file upload and automatic version detection
- Storage management for plugin ZIP files
- Cron-based update checks (every 6 hours / 4 times daily)
- Manual "Check for Updates" button
- Version activation/deactivation
- Changelog management
- Freemius integration for Pro versions
- Delete files from storage directly from admin

## Directory Structure

```
Updater/
├── my-plugin/              # Client plugin (receives updates)
│   ├── my-plugin.php       # Main plugin file
│   ├── config.php          # Configuration (API URL, slug, etc.)
│   ├── includes/
│   │   ├── class-updater.php    # Update checker with cron scheduling
│   │   ├── class-ajax.php       # AJAX handlers for manual checks
│   │   ├── class-admin.php      # Admin page with status display
│   │   └── freemius.php         # Freemius integration
│   └── assets/
│       ├── js/admin.js           # Admin JavaScript
│       └── css/admin.css         # Admin styles
│
└── api-plugin/             # Server plugin (manages versions)
    ├── myplugin-api.php     # Main API plugin file
    ├── includes/
    │   ├── class-admin.php       # Admin UI for version management
    │   ├── class-routes.php      # REST API routes
    │   ├── class-update-check.php # Update check logic
    │   ├── class-version-db.php   # Database operations
    │   ├── class-zip-parser.php   # ZIP file parser
    │   └── class-download.php      # Download handler
    └── storage/              # Directory for uploaded ZIP files
```

## Installation

### 1. Install the API Plugin (Server)

1. Copy `api-plugin/` to `wp-content/plugins/myplugin-api/`
2. Activate the plugin in WordPress admin
3. Go to **MyPlugin API** in the admin menu
4. Upload your plugin ZIP files through the interface

### 2. Install the Client Plugin (my-plugin)

1. Copy `my-plugin/` to `wp-content/plugins/myplugin/`
2. Activate the plugin in WordPress admin
3. Go to **My Plugin** in the admin menu to see update status

### 3. Configure Connection

1. In the API plugin admin, find your API endpoint URL
2. In the client plugin admin, ensure the API URL in `config.php` matches
3. Use "Test Connection" to verify the connection

## How It Works

### Update Flow

1. **Cron Check**: The client plugin checks for updates via cron every 6 hours (4 times daily)
2. **Manual Check**: User can click "Check for Updates" for immediate check
3. **Version Comparison**: API compares installed version with active version in database
4. **Update Notification**: WordPress displays update notification if newer version available
5. **Download & Install**: WordPress downloads and installs the update from your API

### Version Management

1. Upload ZIP file to API plugin
2. System auto-detects plugin slug and version from ZIP
3. Activate the version you want to serve
4. Clients will receive updates for the active version

## Configuration

### Client Plugin (`my-plugin/config.php`)

```php
define('MY_PLUGIN_API_URL', 'http://your-site.com/wp-json/myplugin/v1');
define('MY_PLUGIN_SLUG', 'my-plugin');
define('MY_PLUGIN_BASENAME', 'my-plugin/my-plugin.php');
```

### Storage

Uploaded ZIP files are stored in:
```
wp-content/plugins/myplugin-api/storage/
```

Files are named as: `{slug}-v{version}.zip` (e.g., `my-plugin-v1.0.10.zip`)

## Admin Interface

### API Plugin Admin (Server)
- **Add New Version**: Upload ZIP files with auto-detection
- **Version History**: View, activate, deactivate, or delete versions
- **Storage Files**: View and delete ZIP files in storage
- **Media Library**: Select ZIP files from WordPress Media Library

### Client Plugin Admin (Client)
- **Update Status**: Current version, status, and last check time
- **Actions**: Check for Updates, Force Refresh
- **API Connection**: Test connection to update server
- **Changelog**: View latest version changelog

## Cron Schedule

The update check runs automatically via WordPress cron:
- **Schedule**: Every 6 hours (4 times daily)
- **Filter**: `my_plugin_cron_update_check`

To modify the schedule, edit `my-plugin/includes/class-updater.php`:
```php
$schedules['six_hours'] = array(
    'interval' => 6 * HOUR_IN_SECONDS,
    'display'  => 'Every 6 Hours (4 times daily)',
);
```

## REST API Endpoints

### Update Check
```
GET /wp-json/myplugin/v1/update-check?version={version}&slug={slug}
```

**Response:**
```json
{
  "success": true,
  "update": true,
  "new_version": "1.0.11",
  "slug": "my-plugin",
  "package": "http://example.com/download-link",
  "changelog": "New features and bug fixes"
}
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- ZIP extension enabled
- WordPress cron enabled (or system cron setup)

## License

MIT License

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

For issues and feature requests, please use the GitHub issue tracker.
