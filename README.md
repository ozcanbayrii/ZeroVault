# ZeroVault

Zero-database, zero-config, self-hosted secure file sharing portal and ephemeral drop vault with end-to-end access control.

---

## 🌟 Key Features

- **🌐 Multi-Language Architecture (i18n):**
  - **English** (default) and **Turkish** included out-of-the-box.
  - **Dynamic Language Manager:** Add any language (German, Spanish, French, etc.), edit translation strings directly in the admin panel, or remove custom language packs without touching code.
  - Quick language switcher in the header navigation.
- **🌗 Light & Dark Theme:**
  - Modern, high-contrast dark theme and clean light theme.
  - Fast toggle in the navigation bar with `localStorage` persistence and zero-flicker load.
- **⚡ Zero-Database (Flat-File):**
  - No MySQL, PostgreSQL, or Redis required.
  - Operates using hardened JSON storage protected by `LOCK_EX` concurrency file locking.
- **🛡️ Enterprise-Grade Security:**
  - `vault/` storage directory completely blocked from web access and script execution via Apache rules.
  - Dynamic file hashing (SHA-256 / random hex) — real file names and server paths are never exposed.
  - IP-based rate limiting (preventing download spam & bandwidth exhaustion).
  - IP-based brute-force defense with automatic timed lockouts.
  - Real MIME-type detection (`finfo`) and banned extension inspection.
  - Path traversal protection using strict `realpath` validation.
  - Full CSRF token protection on all administrative and authentication forms.
- **📤 Smart File Distribution:**
  - Drag-and-drop file upload zone.
  - Optional secret passcode protection for any file.
  - Integrated VirusTotal security scan report linking.
  - Instant one-click link copying with visual feedback.
  - Live inventory search and instant file filtering.
  - Suspend or permanently destroy files at any time.

---

## 🚀 Quick Start & Installation

### Requirements
- **PHP 8.0+**
- Apache web server (`mod_rewrite` & `mod_headers` recommended)
- PHP extensions: `fileinfo`, `session`, `json`

### Running Locally (Windows)
Double-click `start-server.bat` in the project root or run:
```bash
php -S localhost:8000 index.php
```
Then visit: **`http://localhost:8000`**

### Deploying to Production (cPanel / Apache / VPS)
1. Upload all files to your web root (or subdirectory).
2. Ensure the `vault/` and `languages/` directories have write permissions (`0755` or `0775`).
3. Open your domain in any browser to launch the **Initial Setup Wizard**.
4. Set your administrator credentials and launch your secure vault!

---

## 📁 Project Structure

```text
├── index.php             # Unified monolithic core application & router
├── languages/            # Localization dictionary files
│   ├── .htaccess         # Access protection for language files
│   ├── en.json           # English language pack (Default)
│   └── tr.json           # Turkish language pack
├── vault/                # Secure file storage & configuration (Git-ignored)
│   ├── .htaccess         # Absolute web access denial & execution prevention
│   └── .gitkeep
├── .htaccess             # Root security headers & URL rewriting
├── .gitignore            # Git exclusion rules protecting private data
├── start-server.bat      # One-click Windows local server launcher
└── README.md             # Documentation
```

---

## 📜 License
Distributed under the **MIT License**.
