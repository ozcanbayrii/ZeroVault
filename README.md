# ZeroVault

> **Zero-database, zero-config, self-hosted secure file sharing portal & ephemeral drop vault.**

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Database](https://img.shields.io/badge/Database-Zero%20(Flat--File)-blue.svg)](#)
[![Theme](https://img.shields.io/badge/Theme-Dark%20%7C%20Light-orange.svg)](#)

---

Language / Dil: **[English](#-english-documentation)** | **[Türkçe](#-türkçe-dokümantasyon)**

---

## 🇬🇧 English Documentation

ZeroVault is a lightweight, zero-database, self-hosted file delivery portal. It allows you to safely upload, manage, and share files with external clients or friends using private single-file URLs, optional passcode protection, and integrated VirusTotal security verification.

### 🌟 Key Features

- **🌐 Multi-Language Architecture (i18n):**
  - **English** (default) and **Turkish** included out-of-the-box.
  - **Dynamic Language Manager:** Add any language (German, Spanish, French, etc.), customize translations live in the admin panel, or remove custom language packs without touching code.
  - Quick language selector in the header.
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

### 🚀 Quick Start & Installation

#### Requirements
- **PHP 8.0 or higher**
- Apache web server (`mod_rewrite` & `mod_headers` recommended)
- PHP extensions: `fileinfo`, `session`, `json`

#### Running Locally (Windows)
Double-click `start-server.bat` in the project root or run:
```bash
php -S localhost:8000 index.php
```
Then visit: **`http://localhost:8000`**

#### Deploying to Production (cPanel / Apache / VPS)
1. Upload all project files to your web root (or subdirectory).
2. Ensure the `vault/` and `languages/` directories have write permissions (`0755` or `0775`).
3. Open your domain in any browser to launch the **Initial Setup Wizard**.
4. Define your administrator credentials and launch your secure vault!

---

### 📁 Project Structure

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
└── README.md             # Bilingual documentation
```

---

<br>

## 🇹🇷 Türkçe Dokümantasyon

ZeroVault; veritabanı kurulumu gerektirmeyen, hafif ve kendi sunucunuzda barındırabileceğiniz güvenli bir dosya dağıtım ve depolama merkezidir. Müşterilerinize veya arkadaşlarınıza tekil güvenli linkler, isteğe bağlı şifre koruması ve VirusTotal analiz raporları ile dosya iletmenizi sağlar.

### 🌟 Öne Çıkan Özellikler

- **🌐 Çoklu Dil Desteği (i18n):**
  - **İngilizce** (varsayılan) ve **Türkçe** paketleri hazır olarak gelir.
  - **Dinamik Dil Yönetimi:** Yönetim panelinden istediğiniz yeni dili (Almanca, İspanyolca, Fransızca vb.) oluşturabilir, çevirileri doğrudan panelden düzenleyebilir veya paketleri kaldırabilirsiniz.
  - Üst çubukta tek tıkla dil değiştirici menü.
- **🌗 Aydınlık & Karanlık Tema:**
  - Modern koyu tema ve göz yormayan aydınlık tema.
  - `localStorage` ile hatırlanan, sayfa yenilendiğinde titreşme yapmayan hızlı tema değiştirici.
- **⚡ Sıfır Veritabanı (Zero-Database):**
  - MySQL, PostgreSQL veya Redis kurulumu gerektirmez.
  - Eşzamanlı yazma çakışmalarını önleyen `LOCK_EX` dosya kilitlemeli flat-file JSON mimarisiyle çalışır.
- **🛡️ Üst Düzey Güvenlik:**
  - `vault/` depolama dizini `.htaccess` kurallarıyla doğrudan HTTP erişimine ve kod çalıştırmaya tamamen kapatılmıştır.
  - Yüklenen dosyalar rastgele hash adlarıyla gizlenir; sunucu dizin yapısı asla dışarı sızdırılmaz.
  - IP tabanlı indirme hız sınırlaması (rate-limiting).
  - IP tabanlı brute-force koruması ve otomatik süreli kilitleme.
  - Gerçek MIME türü doğrulaması (`finfo`) ve çift uzantı denetimi.
  - Path traversal saldırılarını önleyen katı `realpath` doğrulaması.
  - Formlarda tam CSRF token koruması.
- **📤 Akıllı Dosya Dağıtımı:**
  - Modern sürükle & bırak (drag & drop) dosya yükleme alanı.
  - Dosyalara isteğe bağlı gizli erişim şifresi koyabilme.
  - VirusTotal güvenlik raporu bağlantısı ekleme.
  - Tek tıkla bağlantı kopyalama ve durum göstergeleri.
  - Canlı dosya arama ve filtreleme.
  - Dosyaları dilediğiniz an askıya alma veya fiziksel diskten kalıcı olarak imha etme.

---

### 🚀 Kurulum ve Çalıştırma

#### Gereksinimler
- **PHP 8.0 veya üzeri**
- Apache web sunucusu (`mod_rewrite` ve `mod_headers` önerilir)
- PHP eklentileri: `fileinfo`, `session`, `json`

#### Yerel Ortamda Çalıştırma (Windows)
Proje kökündeki `start-server.bat` dosyasına çift tıklayın veya terminalden çalıştırın:
```bash
php -S localhost:8000 index.php
```
Ardından tarayıcınızdan **`http://localhost:8000`** adresine gidin.

#### Canlı Sunucuya Kurulum (cPanel / Apache / VPS)
1. Proje dosyalarını sunucunuzun kök dizinine (veya alt klasöre) yükleyin.
2. `vault/` ve `languages/` klasörlerinin yazma izinlerine (`0755` veya `0775`) sahip olduğundan emin olun.
3. Alan adınızı tarayıcıda açtığınızda **İlk Kurulum Sihirbazı** otomatik olarak başlayacaktır.
4. Yönetici kullanıcı adı ve şifrenizi belirleyerek kasanızı hemen kullanmaya başlayabilirsiniz!

---

## 📜 Lisans
Bu proje **MIT Lisansı** ile lisanslanmıştır.
