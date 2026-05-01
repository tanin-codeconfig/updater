# WordPress Custom Update System (Free + Freemius Pro) — Build Plan

## Goal

Build a complete update system where:
- **Free plugin** → updates from your own WordPress REST API
- **Pro plugin** → managed by Freemius (licensing + updates)
- Clean separation, no conflicts

---

## System Architecture

### 1. Plugin (Client)
- Checks for updates
- Connects to your API
- Skips updater if Pro (Freemius handles it)

### 2. API Server (WordPress)
- REST endpoints: `/update-check`, `/download`
- Serves version info + plugin ZIP

### 3. Storage
- Plugin ZIP files
- Version metadata (Database table: `wp_myplugin_versions`)

---

## Project Structure

### API Plugin (Server)
```
wp-content/plugins/myplugin-api/
├── myplugin-api.php
├── includes/
│   ├── class-routes.php
│   ├── class-update-check.php
│   ├── class-download.php
│   ├── class-version-db.php
│   └── class-admin.php
├── assets/
│   └── css/admin.css
└── storage/
```

### Client Plugin
```
my-plugin/
├── my-plugin.php
├── includes/
│   ├── class-updater.php
│   └── freemius.php
└── config.php
```

---

## Development Phases

### Phase 1: Basic API Setup
- Create API plugin structure
- Register REST routes: `/update-check`, `/download`
- Create database table for version management on activation

### Phase 2: Update Check Logic
- Accept parameters: plugin version
- Compare versions
- Return update JSON

### Phase 3: Download System
- Serve plugin ZIP file
- Token validation (transient-based, 5-min expiry)

### Phase 4: Client Integration
- Hook into `pre_set_site_transient_update_plugins`
- Call API
- Inject update response into WordPress
- Skip if Pro (Freemius)

### Phase 5: Freemius Integration
- Detect Pro version
- Ensure no custom updater runs for Pro
- Keep Freemius fully active

### Phase 6: Security Layer
- Transient-based token generation
- Token expiration (5 min)
- Validate token exists and not expired

### Phase 7: Version Management
- Database table CRUD operations
- Store version, changelog, download path

### Phase 8: Admin Panel
- Upload new plugin ZIP
- Set latest version
- Write changelog

---

## Security Plan
- Token-based download
- Token expiration (5 min via transients)

## Update Flow

### Free User
1. Plugin loads
2. Calls API `/update-check`
3. API returns update info
4. WordPress shows update
5. Plugin downloads from `/download`

### Pro User
1. Freemius handles everything
2. Custom updater is skipped

## Rules
- Never run 2 updaters at same time
- Always check: `if (is_pro) return;`
- Keep version numbers consistent
- Always test update process locally

## Testing Plan
- No update available
- Update available
- Invalid token
- Missing ZIP
- Pro user update (Freemius only)

## MVP Checklist
- [ ] API plugin created
- [ ] Update-check endpoint working
- [ ] Download endpoint working
- [ ] Client updater working
- [ ] Freemius compatibility ensured
- [ ] Admin UI for version management

---

## Next Step
Build Phase 1 + Phase 2 (API basics), then connect plugin (Phase 4)
