# ZeroVault

> **Veritabanı gerektirmeyen (zero-database), sıfır yapılandırmalı, kendi sunucunuzda barındırabileceğiniz güvenli dosya dağıtım ve depolama merkezi.**

[![PHP Sürümü](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Lisans: MIT](https://img.shields.io/badge/Lisans-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Veritabanı](https://img.shields.io/badge/Veritaban%C4%B1-S%C4%B1f%C4%B1r%20(Flat--File)-blue.svg)](#)
[![Tema](https://img.shields.io/badge/Tema-Koyu%20%7C%20A%C3%A7%C4%B1k-orange.svg)](#)

---

🇬🇧 **For English documentation, see [README.md](README.md).**

---

ZeroVault; harici bir SQL veritabanı (MySQL, PostgreSQL vb.) kurulumu gerektirmeyen, hafif ve güvenli bir kişisel dosya dağıtım sistemidir. Müşterilerinize, iş ortaklarınıza veya arkadaşlarınıza tekil güvenli bağlantılar, isteğe bağlı şifre koruması ve VirusTotal analiz raporlarıyla dosya teslim etmenizi sağlar.

## 🌟 Öne Çıkan Özellikler

- **🌐 Çoklu Dil Desteği (i18n):**
  - **İngilizce** (varsayılan) ve **Türkçe** paketleri sistemde hazır olarak yer alır.
  - **Dinamik Dil Yönetimi:** Yönetim panelindeki Dil Yönetimi sekmesinden dilediğiniz yeni dili (Almanca, İspanyolca, Fransızca vb.) oluşturabilir, çevirileri doğrudan panelden düzenleyebilir veya paketleri silebilirsiniz.
  - Üst gezinme çubuğunda hızlı dil değiştirici menü.
- **🌗 Aydınlık & Karanlık Tema:**
  - Modern, yüksek kontrastlı koyu tema ve göz yormayan aydınlık tema.
  - `localStorage` ile tercih edilen temayı hatırlar, sayfa geçişlerinde ekran titreşmesi yaşanmaz.
- **⚡ Sıfır Veritabanı (Flat-File Mimarisi):**
  - MySQL, MariaDB, PostgreSQL veya Redis gerektirmez.
  - Eşzamanlı yazma çakışmalarını önleyen `LOCK_EX` dosya kilitlemeli JSON veritabanı mimarisiyle çalışır.
- **🛡️ Üst Düzey Güvenlik:**
  - `vault/` depolama dizini `.htaccess` kurallarıyla doğrudan HTTP erişimine ve betik çalıştırılmasına tamamen kapatılmıştır.
  - Yüklenen dosyalar rastgele SHA-256 hash isimleriyle saklanır; sunucunun fiziksel dizin yapısı asla dışarı sızdırılmaz.
  - IP tabanlı indirme hız sınırlaması (rate-limiting) ile bant genişliği koruması.
  - IP tabanlı brute-force koruması ve otomatik süreli erişim kilidi.
  - Gerçek MIME türü doğrulaması (`finfo`) ve çift uzantı denetimi.
  - Path traversal saldırılarını önleyen katı `realpath` doğrulaması.
  - Form işlemlerinde tam CSRF token güvenliği.
- **📤 Akıllı Dosya Dağıtımı:**
  - Modern sürükle & bırak (drag & drop) dosya yükleme alanı.
  - Dosyalara isteğe bağlı gizli erişim şifresi belirleyebilme.
  - VirusTotal güvenlik raporu bağlantısı ekleme.
  - Tek tıkla bağlantı kopyalama ve görsel geri bildirim.
  - Canlı dosya arama ve anlık envanter filtreleme.
  - Dosyaları dilediğiniz an tek tıkla askıya alma veya fiziksel diskten kalıcı olarak imha etme.

---

## 🚀 Kurulum ve Çalıştırma

### Gereksinimler
- **PHP 8.0 veya üzeri**
- Apache web sunucusu (`mod_rewrite` ve `mod_headers` önerilir)
- PHP eklentileri: `fileinfo`, `session`, `json`

### Yerel Ortamda Çalıştırma (Windows)
Proje kökündeki `start-server.bat` dosyasına çift tıklayın veya komut satırından çalıştırın:
```bash
php -S localhost:8000 index.php
```
Ardından tarayıcınızdan **`http://localhost:8000`** adresine gidin.

### Canlı Sunucuya Kurulum (cPanel / Apache / VPS)
1. Proje dosyalarını sunucunuzun kök dizinine (veya dilediğiniz bir alt klasöre) yükleyin.
2. `vault/` ve `languages/` klasörlerinin yazma izinlerine (`0755` veya `0775`) sahip olduğundan emin olun.
3. Tarayıcınızda sitenizi açtığınızda **İlk Kurulum Sihirbazı** otomatik olarak başlayacaktır.
4. Yönetici kullanıcı adı ve şifrenizi belirleyerek güvenli dosya kasanızı kullanmaya başlayabilirsiniz!

---

## 📁 Proje Dosya Yapısı

```text
├── index.php             # Çekirdek uygulama, rota ve arayüz motoru
├── languages/            # Yerelleştirme ve dil paketleri
│   ├── .htaccess         # Dil dosyalarına doğrudan web erişimini engelleyen kural
│   ├── en.json           # İngilizce dil paketi (Varsayılan)
│   └── tr.json           # Türkçe dil paketi
├── vault/                # Güvenli dosya depolama ve yapılandırma (Git'te yok sayılır)
│   ├── .htaccess         # Doğrudan web erişimini ve kod çalıştırmayı engelleyen kural
│   └── .gitkeep
├── .htaccess             # Kök dizin güvenlik başlıkları ve URL yönlendirme
├── .gitignore            # Özel verileri ve depoları koruyan Git kuralları
├── start-server.bat      # Windows için tek tıkla yerel sunucu başlatıcı
├── README.md             # İngilizce dokümantasyon
└── README_TR.md          # Türkçe dokümantasyon
```

---

## 📜 Lisans
Bu proje **MIT Lisansı** ile lisanslanmıştır.
