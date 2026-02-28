# Private Workspace Instance

A lightweight, self-hosted workspace and documentation tool built with PHP, SQLite, and Vue.js. Designed for shared hosting environments with a focus on privacy and a hidden access portal.

## Features

- **Flat-file nature:** Operates entirely within a single directory using an SQLite database (`data.db`).
- **Hidden Login Portal:** The login screen is concealed from regular web traffic to prevent unauthorized access attempts.
- **First Install Wizard:** Automatically detects a fresh install and prompts for admin account creation.
- **Public/Private Toggle:** Selectively publish documentation pages to a public-facing URL (`p.php`).
- **Secure API:** Backend operations are protected by PHP sessions.

## File Structure

- `index.php`: The core router. Handles the setup process, the hidden login gateway, and serves the Vue.js Single Page Application (SPA).
- `api.php`: The secure REST API that communicates with the SQLite database. Requires an active session.
- `p.php`: The public viewer. Only displays content where `is_public` is explicitly set to `1`.
- `data.db`: The SQLite database file (automatically generated upon first run).

## Installation & Setup

1. Create a new directory on your web server or local environment (e.g., `/workspace/`).
2. Upload `index.php`, `api.php`, and `p.php` to this directory. Ensure the directory has correct write permissions (CHMOD 755 or 777) so PHP can generate `data.db`.
3. Navigate to the directory in your web browser. 
4. The system will detect that no users exist and will present the **First Install Setup** screen.
5. Enter your desired Username and Password to create the administrator account.

## How to Log In (The Hidden Portal)

For security, navigating to the standard directory URL will result in a fake `403 Forbidden` message. 

To access the login screen, append `?portal=open` to your `index.php` URL.
- **Example:** `https://yourdomain.com/workspace/index.php?portal=open`

## Usage

- **Spaces & Pages:** Create a "Space" to act as a folder/category. Create "Pages" within that Space for your actual content.
- **Publishing:** Toggle the "Public" checkbox on a page. You will receive a unique link (e.g., `p.php?s=your-page-1234`) that you can share with clients or external users.

## Security Notes

- The setup screen is permanently disabled once the first user is registered.
- Passwords are encrypted using PHP's native `password_hash()` mechanism.
- API endpoints strictly verify `$_SESSION['logged_in']` before executing any database queries.