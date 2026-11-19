# ZeroVault

> **Zero-database, zero-config, self-hosted secure file sharing portal & ephemeral drop vault.**

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Database](https://img.shields.io/badge/Database-Zero%20(Flat--File)-blue.svg)](#)
[![Theme](https://img.shields.io/badge/Theme-Dark%20%7C%20Light-orange.svg)](#)

---

🇹🇷 **Türkçe dokümantasyon için [README_TR.md](README_TR.md) dosyasına göz atın.**

---

ZeroVault is a lightweight, zero-database, self-hosted file delivery portal. It allows you to safely upload, manage, and share files with external clients or friends using private single-file URLs, optional passcode protection, and integrated VirusTotal security verification.

## 🌟 Key Features

- **🌐 Multi-Language Architecture (i18n):**
  - **English** (default) and **Turkish** included out-of-the-box.
  - **Dynamic Language Manager:** Add any language (German, Spanish, French, etc.), customize translations live in the admin panel, or remove custom language packs without touching code.
  - Quick language selector in the header navigation.
- **🌗 Light & Dark Theme:**
  - Modern, high-contrast dark theme and clean light theme.
  - Instant toggle with `localStorage` persistence and zero-flicker page loading.
- **⚡ Zero-Database (Flat-File):**
  - No MySQL, PostgreSQL, or Redis needed.
  - Operates using hardened JSON storage protected by `LOCK_EX` concurrency file locking.
- **🛡️ Enterprise-Grade Security:**
  - `vault/` storage directory completely blocked from web access and script execution via Apache rules.
  - Dynamic file hashing (SHA-256 / random hex) — real file names and server paths are never exposed.
  - IP-based rate limiting (preventing download spam & bandwidth exhaustion).
  - IP-based brute-force defense with automatic timed lockouts.
  - Real MIME-type detection (`finfo`) and banned extension inspection.
  - Strict `realpath` validation preventing path traversal attacks.
  - Full CSRF token protection on all administrative and authentication forms.
- **📤 Smart File Distribution:**
  - Modern drag-and-drop file upload zone.
  - Optional secret passcode protection for any file.
  - Integrated VirusTotal security scan report linking.
  - Instant one-click link copying with visual feedback.
  - Live inventory search and instant file filtering.
  - Suspend or permanently destroy files at any time.

---

## 🚀 Quick Start & Installation

### Requirements
- **PHP 8.0 or higher**
- Apache web server (`mod_rewrite` & `mod_headers` recommended)
- PHP extensions: `fileinfo`, `session`, `json`

### Running Locally (Windows)
Double-click `start-server.bat` in the project root or run:
```bash
php -S localhost:8000 index.php
```
Then visit: **`http://localhost:8000`**

### Deploying to Production (cPanel / Apache / VPS)
1. Upload all project files to your web root (or subdirectory).
2. Ensure the `vault/` and `languages/` directories have write permissions (`0755` or `0775`).
3. Open your domain in any browser to launch the **Initial Setup Wizard**.
4. Define your administrator credentials and launch your secure vault!

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
├── README.md             # English documentation
└── README_TR.md          # Turkish documentation
```

---

## 📜 License
Distributed under the **MIT License**.
