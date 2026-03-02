# LunarDesk

LunarDesk is a lightweight, self-hosted workspace for writing docs, publishing selected pages, and receiving webhook messages in a built-in stream.

Current app version: `v2.3.9`

## What It Does

- Organize content in a hierarchy: `spaces -> pages -> subpages`.
- Edit content with Editor.js blocks.
- Auto-save as draft while you work.
- Publish manually when ready.
- Set per-page public visibility and share via slug URL.
- Add and crop page banners before saving.
- Manage users and roles (`admin`, `user`).
- Receive external messages in channel streams via webhook URLs.
- Use an admin terminal with built-in commands.

## Core Features

### Documentation Workspace

- Create, rename, and delete spaces/pages/subpages.
- Reorder pages and subpages with up/down controls.
- Show item metadata (`Created` or `Updated`, actor, timestamp).

### Editor

Editor.js tools currently wired in:

- Header
- List
- Checklist
- Code
- Table
- Quote
- Warning
- Delimiter
- Inline code
- Image
- Embed
- Text color plugin

### Draft and Publish Model

- Draft save is automatic while editing.
- `Publish` copies draft content/title/banner to the live version.
- `Live` toggle controls public visibility (`is_public`).
- Public URL format: `p.php?s=<slug>`.

### Banner Management

- Add/change/remove banner from the page header.
- Crop flow is built in (Cropper.js).
- Cropped image is uploaded to `uploads/` and saved on the page.

### Channels and Webhooks

- Create channels ("rooms") in the left panel.
- Generate or revoke webhook keys per room.
- Receive JSON payloads at `webhook.php?key=<room_key>`.
- Clear a room stream from the UI.

### Admin Terminal

Available commands:

- `/help`
- `/ping`
- `/status`
- `/version`
- `/delete` (admin only, with YES/NO confirmation)

### Accounts and Access

- First run creates the first account as `admin`.
- Admin can invite users from the Users modal.
- Invite uses a token link through `reset.php`.
- Password reset request flow: `reset_request.php` -> email link -> `reset.php`.

## Tech Stack

- PHP (server-rendered pages + JSON API)
- SQLite (`data.db`)
- Vue 3 (frontend app in `assets/js/app.js`)
- Tailwind CSS via CDN
- Editor.js + plugins
- Cropper.js

## Project Structure

- `index.php`: Authenticated admin workspace UI.
- `api.php`: Main authenticated JSON API for workspace operations.
- `p.php`: Public read-only page viewer.
- `auth.php`: Session/auth bootstrap and initial DB/table setup.
- `webhook.php`: Public webhook ingest endpoint for channel messages.
- `reset_request.php`: Password reset request form.
- `reset.php`: Password reset and invite completion form.
- `assets/js/app.js`: Vue app logic.
- `assets/style.css`: Shared styling overrides.
- `.htaccess`: Blocks direct access to sensitive file types and directory listing.
- `version.php`: App version + default timezone setup.

## Installation

1. Copy the project to your web root (for example `C:\wamp64\www\lunardesk`).
2. Make sure PHP can write in the project directory.
3. Open `index.php` in the browser.
4. On first run, create the initial admin account.

LunarDesk creates these automatically when needed:

- `data.db`
- `uploads/`

## Quick Usage

1. Log in at `index.php`.
2. Create a space.
3. Create a page or subpage.
4. Edit content and banner.
5. Toggle `Live` if you want it publicly visible.
6. Click `Publish` to push draft to live.
7. Share `p.php?s=<slug>`.

## Webhook Example

```bash
curl -X POST "https://your-domain/lunardesk/webhook.php?key=ROOM_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"sender\":\"CI\",\"content\":\"Build finished successfully\"}"
```

Accepted payload shape:

- `sender`: optional string (defaults to `Signal`)
- `content`: optional string/object

## Security Notes

- API endpoints require authenticated session (`api.php` returns `401` when not logged in).
- `.htaccess` blocks direct access to `.db`, `.sqlite`, `.sqlite3`, `.md`, `.json`.
- Keep server/PHP updated and use HTTPS in production.
- Mail delivery depends on server mail configuration (`mail()`).

## Known Behavior

- Timestamps are stored/displayed with UTC defaults from `version.php`.
- Public viewer only loads pages where `is_public = 1`.
- Deleting a page also deletes direct children with matching `parent_id`.

## Support

Issues and feature requests:

`https://github.com/ByAldon/LunarDesk/issues`

