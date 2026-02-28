# LunarDesk

A lightweight, self-hosted workspace and documentation tool built with PHP, SQLite, and Vue.js. Designed for shared hosting environments with a focus on privacy, rapid documentation, and a hidden access portal.

## 🚀 Key Features

### 📂 Hierarchical Documentation
Organize your thoughts and documentation cleanly.
- **Spaces:** Act as root folders or categories for your projects.
- **Pages & Subpages:** Create deep, structured documentation within your Spaces.

### 📝 Rich Block Editor
Powered by Editor.js, providing a seamless block-based writing experience.
- **Tools Included:** Headers, Lists, Checklists, Code blocks, Quotes, Warnings, Delimiters, and Embeds.
- **Advanced Tables:** Custom tables with individual cell-color highlighting.
- **Inline Formatting:** Bold, Italic, Links, Inline Code, Text Color, and Text Markers.

### 🖼️ Banner Management
Personalize your pages with custom cover images.
- Upload any image as a page banner.
- **Built-in Cropper:** Includes a Cropper.js integration to zoom, move, and crop your image to a perfect 21:9 ultra-wide aspect ratio before uploading.

### 🔒 Privacy & Security
Built with shared hosting in mind.
- **Hidden Login Portal:** The login screen is completely hidden from regular web traffic. You must know the exact URL parameter to access it.
- **Flat-File Database:** Uses a single SQLite `data.db` file. No complex MySQL setup required.
- **Protected Assets:** An included `.htaccess` file prevents visitors from directly downloading your database or viewing directory contents.
- **User Management:** Supports multiple users with 'Admin' and 'User' roles, plus secure password resets.

### 🌍 Public Viewer
Share your work selectively.
- **Draft & Publish:** Pages are saved as drafts automatically. You control when a page goes live.
- **Public Links:** Toggle "Public" to generate a unique, read-only link.
- **Sidebar Navigation:** The public viewer automatically builds a responsive sidebar menu (with mobile support) so guests can browse through all public Spaces, Pages, and Subpages.

### ⚡ Real-Time Channels & Webhooks
- **Channels:** Create dedicated streams within your workspace.
- **Webhooks:** Generate unique Webhook URLs for channels to receive and display external automated messages or logs in real-time.
- **Admin Terminal:** A built-in command-line interface for quick actions (e.g., `/create`, `/delete`).

## 📁 File Structure

- `index.php`: The core application. Handles the setup process, the hidden login gateway, and the Vue.js Single Page Application (SPA).
- `api.php`: The secure REST API communicating with the database and handling file uploads.
- `p.php`: The public viewer for shared pages.
- `auth.php`: Core authentication, session management, and database initialization.
- `reset.php`: Handles secure password recovery via email.
- `webhook.php`: Endpoint for receiving external webhook payloads.
- `assets/js/app.js`: The frontend Vue.js logic and Editor.js configuration.
- `style.css`: Custom dark-mode styling (Tailwind CSS is loaded via CDN).
- `.htaccess`: Apache configuration to block direct file access.
- `data.db`: The SQLite database (automatically generated).
- `uploads/`: Directory for banner images (automatically generated).

## 🛠️ Installation & Setup

1. Create a new directory on your web server (e.g., `/lunardesk/`).
2. Upload all the files into this directory. 
3. Ensure the directory has correct write permissions (CHMOD 755 or 777) so PHP can generate the `data.db` file and the `uploads/` folder.
4. Navigate to the directory in your web browser. 
5. The system will detect a fresh install and present the **First Install Setup** screen.
6. Enter your desired Username, Email, Display Name, and Password to create the administrator account.

## 🔑 How to Log In (The Hidden Portal)

For security, navigating to the standard directory URL once the setup is complete will result in a fake `403 Forbidden` message. 

To access the login screen, you must append `?portal=open` to the URL.
- **Example:** `https://yourdomain.com/lunardesk/index.php?portal=open`

## ⚠️ Beta Notice

**LunarDesk is currently in Beta.** This means the software is provided as-is and bugs or unexpected errors may occur. It is highly recommended not to use this system for critical or production work yet. 

Always keep a local backup of your textual data, as the database structure might undergo breaking changes in future updates.

Found a bug or have a feature request? Please report it here:
[https://github.com/ByAldon/LunarDesk/issues](https://github.com/ByAldon/LunarDesk/issues)

---
*LunarDesk — Made by [Aldon](https://github.com/ByAldon)*