# Advanced Newsletter - WordPress Eklentisi

Gelişmiş ve profesyonel bir WordPress newsletter eklentisi. Abone yönetimi, kampanya oluşturma, email gönderimi, analitik ve daha fazlasını içerir.

## 🚀 Özellikler

### 📧 Abone Yönetimi
- ✅ Double opt-in desteği
- ✅ Abone listeleri ve segmentasyon
- ✅ CSV import/export
- ✅ GDPR uyumluluğu
- ✅ Otomatik bounce yönetimi
- ✅ Abonelik durumu takibi (aktif, beklemede, abonelikten çıkmış)

### 🎨 Kampanya Yönetimi
- ✅ Sürükle-bırak email editörü
- ✅ Hazır email templates
- ✅ Kişiselleştirme (merge tags)
- ✅ Email önizleme ve test gönderimi
- ✅ Zamanlanmış gönderim
- ✅ A/B testing desteği

### 📊 Analytics ve Tracking
- ✅ Email açılma takibi
- ✅ Link tıklama takibi
- ✅ Detaylı kampanya raporları
- ✅ Abone büyüme grafikleri
- ✅ Engagement metrikleri
- ✅ Export edilebilir raporlar

### 🔄 Otomasyon
- ✅ Hoş geldin emaili
- ✅ Onay emaili
- ✅ Zamanlanmış kampanyalar
- ✅ Email kuyruğu sistemi
- ✅ Batch gönderim (performans için)

### 🎯 Frontend
- ✅ Özelleştirilebilir abonelik formları
- ✅ Widget desteği
- ✅ Shortcode desteği
- ✅ Responsive tasarım
- ✅ Dark mode desteği

### ⚙️ Teknik Özellikler
- ✅ Modern OOP mimarisi
- ✅ WordPress best practices
- ✅ AJAX tabanlı arayüz
- ✅ SMTP desteği
- ✅ Güvenli ve optimize edilmiş
- ✅ Çoklu dil desteği (i18n hazır)

## 📦 Kurulum

### Otomatik Kurulum
1. WordPress admin paneline gidin
2. Eklentiler > Yeni Ekle'ye tıklayın
3. "Advanced Newsletter" araması yapın
4. "Şimdi Yükle" ve ardından "Etkinleştir" butonuna tıklayın

### Manuel Kurulum
1. `advanced-newsletter-wp` klasörünü `/wp-content/plugins/` dizinine yükleyin
2. WordPress admin panelinde 'Eklentiler' menüsünden eklentiyi etkinleştirin
3. İlk kurulumda veritabanı tabloları otomatik oluşturulacaktır

## 🎯 Kullanım

### Dashboard
Eklenti etkinleştirildikten sonra WordPress admin panelinde "Newsletter" menüsü görünecektir.

**Dashboard Özellikleri:**
- Genel istatistikler
- Son kampanyalar
- Hızlı eylemler

### Abone Ekleme

**Manuel Olarak:**
1. Newsletter > Subscribers sayfasına gidin
2. "Add Subscriber" butonuna tıklayın
3. Email ve isim bilgilerini girin
4. "Add Subscriber" butonuna tıklayın

**CSV Import:**
1. Newsletter > Subscribers sayfasına gidin
2. "Import CSV" butonuna tıklayın
3. CSV dosyanızı seçin (email, name sütunları)
4. "Import" butonuna tıklayın

### Kampanya Oluşturma

1. Newsletter > Campaigns sayfasına gidin
2. "Create Campaign" butonuna tıklayın
3. Kampanya detaylarını doldurun:
   - İsim ve konu
   - Gönderen bilgileri
   - Email içeriği
   - Template seçimi
4. "Save" butonuna tıklayın
5. "Send Test Email" ile test edin
6. "Send Campaign" ile gönderin veya zamanlayın

### Abonelik Formu Ekleme

**Shortcode ile:**
```php
[newsletter_form title="Bültene Abone Ol" button_text="Abone Ol"]
```

**Widget ile:**
1. Görünüm > Widget'lar sayfasına gidin
2. "Newsletter Subscription" widget'ını istediğiniz alana sürükleyin
3. Widget ayarlarını yapılandırın

**PHP ile (tema dosyalarında):**
```php
<?php echo do_shortcode('[newsletter_form]'); ?>
```

### Ayarlar

Newsletter > Settings sayfasından şu ayarları yapılandırabilirsiniz:

**Genel Ayarlar:**
- Gönderen adı ve email
- Reply-to email
- Double opt-in etkin/devre dışı
- GDPR onayı etkin/devre dışı
- Email tracking etkin/devre dışı

**Gönderim Ayarları:**
- Batch başına email sayısı (önerilen: 50)
- Batch aralığı (saniye, önerilen: 60)

**SMTP Ayarları:**
- SMTP host, port
- Kullanıcı adı ve şifre
- Şifreleme (SSL/TLS)

## 📝 Merge Tags (Kişiselleştirme)

Email içeriklerinizde şu merge tag'leri kullanabilirsiniz:

- `{{email}}` - Abone email adresi
- `{{name}}` - Abone adı
- `{{first_name}}` - Abone ilk adı
- `{{site_name}}` - Site adı
- `{{site_url}}` - Site URL'i
- `{{subject}}` - Email konusu
- `{{year}}` - Mevcut yıl
- `{{unsubscribe_url}}` - Abonelikten çıkma linki

**Örnek Kullanım:**
```html
Merhaba {{first_name}},

{{site_name}} bültenine hoş geldiniz!

<a href="{{unsubscribe_url}}">Abonelikten çık</a>
```

## 🎨 Email Template Yapısı

Default template aşağıdaki placeholder'ları destekler:

```html
<!DOCTYPE html>
<html>
<head>
    <title>{{subject}}</title>
</head>
<body>
    <div class="header">
        <h1>{{site_name}}</h1>
    </div>
    <div class="content">
        {{content}}
    </div>
    <div class="footer">
        <p>&copy; {{year}} {{site_name}}</p>
        <p><a href="{{unsubscribe_url}}">Abonelikten Çık</a></p>
    </div>
</body>
</html>
```

## 🔧 Teknik Detaylar

### Veritabanı Tabloları

Eklenti şu tabloları oluşturur:

- `wp_advnews_subscribers` - Aboneler
- `wp_advnews_lists` - Abone listeleri
- `wp_advnews_subscriber_lists` - Abone-liste ilişkileri
- `wp_advnews_campaigns` - Kampanyalar
- `wp_advnews_campaign_lists` - Kampanya-liste ilişkileri
- `wp_advnews_queue` - Email gönderim kuyruğu
- `wp_advnews_opens` - Email açılma takibi
- `wp_advnews_clicks` - Link tıklama takibi
- `wp_advnews_bounces` - Bounce takibi
- `wp_advnews_templates` - Email templates
- `wp_advnews_automations` - Otomasyon kuralları
- `wp_advnews_settings` - Ayarlar

### Cron Jobs

Eklenti şu cron job'ları kullanır:

- `advnews_send_queue` - Email kuyruğunu işler (her dakika)

### AJAX Endpoints

**Admin:**
- `advnews_get_subscribers` - Aboneleri listele
- `advnews_add_subscriber` - Abone ekle
- `advnews_delete_subscriber` - Abone sil
- `advnews_bulk_delete_subscribers` - Toplu abone silme
- `advnews_export_subscribers` - Aboneleri export et
- `advnews_import_subscribers` - Aboneleri import et
- `advnews_get_campaigns` - Kampanyaları listele
- `advnews_save_campaign` - Kampanya kaydet
- `advnews_send_campaign` - Kampanya gönder
- `advnews_get_analytics` - Analitik verileri al
- `advnews_get_settings` - Ayarları al
- `advnews_save_settings` - Ayarları kaydet

**Frontend:**
- `advnews_subscribe` - Abone ol
- `advnews_unsubscribe` - Abonelikten çık

### Tracking URLs

- `?advnews_action=confirm&token={token}` - Email onaylama
- `?advnews_action=unsubscribe&token={token}` - Abonelikten çıkma
- `?advnews_action=track_open&c={campaign_id}&s={subscriber_id}&t={token}` - Açılma takibi
- `?advnews_action=track_click&c={campaign_id}&s={subscriber_id}&url={url}&t={token}` - Tıklama takibi

## 🔒 Güvenlik

- Tüm formlar nonce ile korunmaktadır
- Kullanıcı girişleri sanitize edilmektedir
- SQL injection koruması
- XSS koruması
- CSRF koruması
- Capability kontrolleri

## 🌐 Çoklu Dil Desteği

Eklenti translation-ready'dir. Kendi diliniz için tercüme dosyaları ekleyebilirsiniz:

1. `languages/advanced-newsletter-tr_TR.po` dosyasını oluşturun
2. Poedit gibi bir araç kullanarak tercümeleri yapın
3. `.mo` dosyasını generate edin

## 📋 Gereksinimler

- WordPress 5.8 veya üzeri
- PHP 7.4 veya üzeri
- MySQL 5.6 veya üzeri
- mod_rewrite etkin

## 🤝 Katkıda Bulunma

Katkılarınızı bekliyoruz! Pull request göndermekten çekinmeyin.

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Değişikliklerinizi commit edin (`git commit -m 'Add amazing feature'`)
4. Branch'inizi push edin (`git push origin feature/amazing-feature`)
5. Pull Request açın

## 📄 Lisans

Bu proje GPL v3 veya daha yeni bir lisans altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

## 🐛 Hata Bildirimi

Hata bulduysanız veya öneriniz varsa, lütfen [GitHub Issues](https://github.com/yourusername/advanced-newsletter/issues) sayfasından bildirin.

## 📞 İletişim

Sorularınız için:
- Email: support@example.com
- Website: https://example.com

## 📚 Changelog

### Version 1.0.0 (2024)
- İlk sürüm
- Abone yönetimi
- Kampanya oluşturma
- Email gönderimi
- Analytics
- Template sistemi
- SMTP desteği
- GDPR uyumluluğu

## 🙏 Teşekkürler

Bu eklentiyi kullandığınız için teşekkür ederiz!

---

**Developed with ❤️ for WordPress Community**
