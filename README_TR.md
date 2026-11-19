# ZeroVault

> Veritabanı gerektirmeyen (zero-database), sıfır yapılandırmalı, kendi sunucunuzda barındırabileceğiniz güvenli dosya dağıtım ve depolama merkezi.

[For English documentation, see README.md.](README.md)

---

ZeroVault; harici bir SQL veritabanı (MySQL, PostgreSQL vb.) kurulumu gerektirmeyen, hafif ve güvenli bir kişisel dosya dağıtım sistemidir. Müşterilerinize, iş ortaklarınıza veya arkadaşlarınıza tekil güvenli bağlantılar, isteğe bağlı şifre koruması ve VirusTotal analiz raporlarıyla dosya teslim etmenizi sağlar.

## Öne Çıkan Özellikler

- **Çoklu Dil Desteği (i18n):**
  - İngilizce (varsayılan) ve Türkçe paketleri hazır olarak gelir.
  - Dinamik Dil Yönetimi: Yönetim panelindeki Dil Yönetimi sekmesinden dilediğiniz yeni dili oluşturabilir, çevirileri doğrudan panelden düzenleyebilir veya silebilirsiniz.
  - Üst gezinme çubuğunda hızlı dil değiştirici menü.
- **Aydınlık ve Karanlık Tema:**
  - Modern koyu tema ve göz yormayan aydınlık tema.
  - localStorage ile tercih edilen temayı hatırlar, sayfa geçişlerinde ekran titreşmesi yaşanmaz.
- **Sıfır Veritabanı (Flat-File Mimarisi):**
  - MySQL, MariaDB, PostgreSQL veya Redis gerektirmez.
  - Eşzamanlı yazma çakışmalarını önleyen `LOCK_EX` dosya kilitlemeli JSON veritabanı mimarisiyle çalışır.
- **Üst Düzey Güvenlik:**
  - `vault/` depolama dizini web kurallarıyla doğrudan HTTP erişimine ve kod çalıştırmaya tamamen kapatılmıştır.
  - Yüklenen dosyalar rastgele SHA-256 hash isimleriyle saklanır; sunucunun fiziksel dizin yapısı asla dışarı sızdırılmaz.
  - IP tabanlı indirme hız sınırlaması (rate-limiting) ile bant genişliği koruması.
  - IP tabanlı brute-force koruması ve otomatik süreli erişim kilidi.
  - Gerçek MIME türü doğrulaması (`finfo`) ve çift uzantı denetimi.
  - Path traversal saldırılarını önleyen katı `realpath` doğrulaması.
  - Form işlemlerinde tam CSRF token güvenliği.
- **Akıllı Dosya Dağıtımı:**
  - Sürükle ve bırak (drag & drop) dosya yükleme alanı.
  - Dosyalara isteğe bağlı gizli erişim şifresi belirleyebilme.
  - VirusTotal güvenlik raporu bağlantısı ekleme.
  - Tek tıkla bağlantı kopyalama ve görsel geri bildirim.
  - Canlı dosya arama ve anlık envanter filtreleme.
  - Dosyaları dilediğiniz an tek tıkla askıya alma veya fiziksel diskten kalıcı olarak imha etme.

---

## Sunucu Kurulumu ve Yapılandırma (CLI / SSH / CMD)

### Sistem Gereksinimleri
- PHP 8.0 veya üzeri
- Web sunucusu: Apache (`mod_rewrite` & `mod_headers` etkin) veya Nginx
- Gerekli PHP eklentileri: `fileinfo`, `session`, `json`

### 1. Depoyu Sunucuya Klonlayın (Git / Terminal)
Sunucunuzun web yayın dizinine (örneğin `/var/www/html` veya alan adı klasörünüze) gidin ve repoyu çekin:

```bash
git clone https://github.com/ozcanbayrii/ZeroVault.git .
```

*(Alt klasörde çalıştırmak isterseniz sondaki noktayı kaldırarak `ZeroVault` klasörüne klonlayabilirsiniz)*

### 2. Dizin İzinlerini Ayarlayın
Web sunucusu servisinin `vault/` ve `languages/` klasörlerine yazabilmesi gerekir:

**Linux / Ubuntu / Debian:**
```bash
sudo chown -R www-data:www-data vault languages
chmod -R 755 vault languages
```

**cPanel / Paylaşımlı Hosting / Windows Server:**
Dosya yöneticisinden veya CMD üzerinden `vault` ve `languages` klasör izinlerini `0755` veya `0775` olarak ayarlayın.

### 3. Web Sunucu Yapılandırması

#### Apache
Apache sunucularda kökteki ve alt klasörlerdeki `.htaccess` dosyaları otomatik olarak devreye girer. Yalnızca modüllerin aktif olduğundan emin olun:

```bash
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

#### Nginx
Nginx kullanıyorsanız `server { ... }` bloğunuzun içine şu yönlendirme ve güvenlik bloklarını ekleyin:

```nginx
server {
    listen 80;
    server_name alanadiniz.com;
    root /var/www/html;
    index index.php;

    # Vault ve languages klasörlerine doğrudan erişimi engelle
    location ~ ^/(vault|languages)/ {
        deny all;
        return 404;
    }

    # Gizli dosyalara erişimi engelle (.git, .env vb.)
    location ~ /\. {
        deny all;
        return 404;
    }

    # URL yönlendirmeleri
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }
}
```

### 4. Kurulum Sihirbazını Çalıştırın
Herhangi bir web tarayıcısından alan adınızı açın:
```text
https://alanadiniz.com
```
Otomatik olarak açılan **Kurulum Sihirbazı** üzerinden yönetici kullanıcı adınızı ve şifrenizi belirleyin. Kaydettiğiniz anda ZeroVault kullanıma hazırdır.

---

## Proje Dosya Yapısı

```text
├── index.php             # Çekirdek uygulama, rota ve arayüz motoru
├── languages/            # Yerelleştirme ve dil paketleri
│   ├── .htaccess         # Dil dosyalarına doğrudan erişim koruması
│   ├── en.json           # İngilizce dil paketi (Varsayılan)
│   └── tr.json           # Türkçe dil paketi
├── vault/                # Güvenli dosya depolama ve yapılandırma (Git'te yok sayılır)
│   ├── .htaccess         # Doğrudan web erişimini ve kod çalıştırmayı engelleyen kural
│   └── .gitkeep
├── .htaccess             # Kök dizin güvenlik başlıkları ve URL yönlendirme
├── .gitignore            # Özel verileri koruyan Git kuralları
├── README.md             # İngilizce dokümantasyon
└── README_TR.md          # Türkçe dokümantasyon
```

---

## Lisans
Bu proje MIT Lisansı ile lisanslanmıştır.
