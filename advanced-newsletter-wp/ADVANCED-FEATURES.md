# Advanced Newsletter - Gelişmiş Özellikler

Bu dokümanda, Advanced Newsletter eklentisinin gelişmiş özellikleri detaylı olarak açıklanmaktadır.

## 🎯 Gelişmiş Segmentasyon

### Özellikler
- Davranışsal segmentler (email açma, tıklama bazlı)
- Demografik segmentasyon
- Engagement skoruna göre filtreleme
- Özel alan bazlı segmentasyon
- Konum bazlı segmentasyon
- İnaktif abone segmentleri

### Kullanım
```php
$segmentation = new \AdvancedNewsletter\Core\Segmentation();

// Segment oluştur
$segment_id = $segmentation->create_segment([
    'name' => 'Highly Engaged Users',
    'conditions' => [
        ['field' => 'engagement_score', 'value' => 'high'],
        ['field' => 'opened_campaign', 'campaign_id' => 123]
    ]
]);

// Segment abonelerini getir
$subscribers = $segmentation->get_segment_subscribers($segment_id);
```

### Hazır Segmentler
- **Highly Engaged**: Sık email açan ve tıklayan aboneler
- **Inactive Subscribers**: 90+ gün aktivite göstermeyen aboneler
- **New Subscribers**: Son 30 günde abone olan kullanıcılar
- **Never Opened**: Hiç email açmamış aboneler

---

## 📧 RSS-to-Email Automation

### Özellikler
- RSS feed'lerden otomatik newsletter oluşturma
- Zamanlanmış gönderim (günlük, haftalık, aylık)
- Özelleştirilebilir email templates
- Maksimum içerik sayısı kontrolü
- Görsel içerik desteği

### Kullanım
```php
$rss = new \AdvancedNewsletter\Core\RSSToEmail();

// RSS feed ekle
$feed_id = $rss->add_feed([
    'name' => 'Blog Updates',
    'feed_url' => 'https://example.com/feed',
    'frequency' => 'weekly',
    'send_time' => '09:00',
    'max_items' => 5,
    'list_id' => 1,
    'email_subject' => 'Latest Blog Posts'
]);
```

### Desteklenen Frekanslar
- `hourly` - Her saat
- `daily` - Günlük
- `weekly` - Haftalık

---

## 🛒 WooCommerce Entegrasyonu

### Özellikler
- Checkout sırasında newsletter aboneliği
- Sepet terk emailleri (Abandoned Cart)
- Ürün önerileri
- Satın alma sonrası follow-up emailler
- Ürün bazlı kampanyalar

### Sepet Terk Emailleri
Otomatik 3 aşamalı hatırlatma:
1. 1 saat sonra ilk hatırlatma
2. 24 saat sonra ikinci hatırlatma
3. 3 gün sonra final hatırlatma

### Kullanım
```php
// Abandoned cart kontrolü
do_action('advnews_check_abandoned_carts');

// Ürün kampanyası oluştur
$woo = new \AdvancedNewsletter\Integrations\WooCommerce();
$campaign_id = $woo->create_product_campaign(
    [123, 456, 789], // Product IDs
    1, // List ID
    'You might also like these products'
);
```

---

## 🎨 Özel Alanlar (Custom Fields)

### Özellikler
- Sınırsız özel alan desteği
- Farklı alan tipleri (text, email, number, date, select, checkbox, radio, textarea)
- Zorunlu alan tanımlama
- Form'da gösterim kontrolü
- Sıralama desteği

### Kullanım
```php
$custom_fields = new \AdvancedNewsletter\Core\CustomFields();

// Özel alan oluştur
$field_id = $custom_fields->create_field([
    'field_key' => 'company',
    'field_label' => 'Company Name',
    'field_type' => 'text',
    'show_in_form' => 1,
    'required' => 0
]);

// Abone için değer kaydet
$custom_fields->update_subscriber_field($subscriber_id, 'company', 'Acme Corp');

// Değeri getir
$value = $custom_fields->get_subscriber_field($subscriber_id, 'company');
```

### Hazır Alanlar
- **company**: Şirket adı
- **phone**: Telefon numarası
- **birthday**: Doğum tarihi
- **country**: Ülke
- **interests**: İlgi alanları

---

## 📊 Lead Scoring (Abone Puanlama)

### Özellikler
- Otomatik aktivite bazlı puanlama
- Özelleştirilebilir puan kuralları
- Abone derecelendirmesi (A+, A, B, C, D, F)
- Puan geçmişi takibi
- İnaktiflik cezası

### Puanlama Kuralları
- Abonelik: +10 puan
- Email açma: +2 puan
- Email tıklama: +5 puan
- Form gönderimi: +15 puan
- Link tıklama: +3 puan
- Sosyal paylaşım: +8 puan
- Referral: +20 puan
- Satın alma: +50 puan
- İnaktiflik: -1 puan/gün

### Kullanım
```php
$lead_scoring = new \AdvancedNewsletter\Core\LeadScoring();

// Puan ekle
$new_score = $lead_scoring->add_score($subscriber_id, 'email_open', $campaign_id);

// Abone puanını getir
$score = $lead_scoring->get_subscriber_score($subscriber_id);

// En yüksek puanlı aboneler
$top_subscribers = $lead_scoring->get_top_subscribers(100);

// Puan aralığına göre aboneler
$high_scorers = $lead_scoring->get_subscribers_by_score(80, 100);
```

---

## 🔗 Webhook Desteği

### Özellikler
- Event bazlı webhook tetikleme
- POST/GET/PUT/DELETE desteği
- Özel header desteği
- Webhook test fonksiyonu
- Hata takibi ve logging

### Desteklenen Events
- `subscriber.subscribed` - Yeni abonelik
- `subscriber.unsubscribed` - Abonelik iptali
- `subscriber.confirmed` - Email onayı
- `campaign.sent` - Kampanya gönderimi
- `email.opened` - Email açılması
- `email.clicked` - Link tıklaması
- `bounce.received` - Bounce alındığında

### Kullanım
```php
$webhooks = new \AdvancedNewsletter\Core\Webhooks();

// Webhook oluştur
$webhook_id = $webhooks->create_webhook([
    'name' => 'Zapier Integration',
    'url' => 'https://hooks.zapier.com/hooks/catch/xxx/yyy',
    'event' => 'subscriber.subscribed',
    'method' => 'POST',
    'headers' => [
        'Authorization' => 'Bearer token_here'
    ]
]);

// Webhook test et
$webhooks->send_webhook($webhook, ['test' => 'data']);
```

---

## 🌐 REST API

### Endpoints

#### Subscribers
```
GET    /wp-json/advanced-newsletter/v1/subscribers
POST   /wp-json/advanced-newsletter/v1/subscribers
GET    /wp-json/advanced-newsletter/v1/subscribers/{id}
PUT    /wp-json/advanced-newsletter/v1/subscribers/{id}
DELETE /wp-json/advanced-newsletter/v1/subscribers/{id}
```

#### Campaigns
```
GET    /wp-json/advanced-newsletter/v1/campaigns
POST   /wp-json/advanced-newsletter/v1/campaigns
GET    /wp-json/advanced-newsletter/v1/campaigns/{id}
POST   /wp-json/advanced-newsletter/v1/campaigns/{id}/send
```

#### Analytics
```
GET /wp-json/advanced-newsletter/v1/analytics/overview
GET /wp-json/advanced-newsletter/v1/campaigns/{id}/analytics
```

#### Lists & Segments
```
GET  /wp-json/advanced-newsletter/v1/lists
POST /wp-json/advanced-newsletter/v1/lists
GET  /wp-json/advanced-newsletter/v1/segments
GET  /wp-json/advanced-newsletter/v1/segments/{id}/subscribers
```

### Örnek Kullanım
```bash
# Abone ekle
curl -X POST https://example.com/wp-json/advanced-newsletter/v1/subscribers \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","name":"John Doe"}'

# Kampanya gönder
curl -X POST https://example.com/wp-json/advanced-newsletter/v1/campaigns/123/send \
  -H "Authorization: Bearer YOUR_TOKEN"

# Analytics al
curl https://example.com/wp-json/advanced-newsletter/v1/analytics/overview \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🧹 Liste Temizleme (List Cleaning)

### Özellikler
- İnaktif abone tespiti
- High bounce rate tespiti
- Geçersiz email tespiti
- Duplicate email temizleme
- Re-engagement kampanyaları
- Liste sağlık skoru

### Temizleme Kriterleri
- **90 Gün İnaktif**: Düşük seviye
- **180 Gün İnaktif**: Orta seviye
- **365 Gün İnaktif**: Yüksek seviye
- **Hiç Açmamış**: Orta seviye
- **High Bounce**: Yüksek seviye
- **Geçersiz Email**: Kritik seviye
- **Duplicate Email**: Orta seviye

### Kullanım
```php
$list_cleaning = new \AdvancedNewsletter\Core\ListCleaning();

// Temizleme önizlemesi
$preview = $list_cleaning->preview_cleaning(['inactive_365_days', 'high_bounce_rate']);

// Temizleme uygula
$results = $list_cleaning->execute_cleaning(
    ['inactive_365_days', 'high_bounce_rate'],
    'unsubscribe' // veya 'delete', 'archive'
);

// Liste sağlık skoru
$health_score = $list_cleaning->get_list_health_score();

// Otomatik öneriler
$recommendations = $list_cleaning->get_recommendations();
```

---

## ⚙️ Abonelik Tercihleri (Subscription Preferences)

### Özellikler
- Abone tercih merkezi
- Email frekans kontrolü
- Konu bazlı abonelik
- Zorunlu/opsiyonel tercihler
- Tercih geçmişi

### Varsayılan Tercihler
- **Email Frequency**: Günlük/Haftalık/Aylık
- **News & Updates**: Haber ve güncellemeler
- **Promotions & Offers**: Promosyonlar ve teklifler
- **Tips & Tutorials**: İpuçları ve eğitimler
- **Important Announcements**: Önemli duyurular (zorunlu)

### Kullanım
```php
$preferences = new \AdvancedNewsletter\Core\SubscriptionPreferences();

// Tercihler oluştur
$preferences->install_default_preferences();

// Abone tercihlerini al
$subscriber_prefs = $preferences->get_subscriber_preferences($subscriber_id);

// Tercih güncelle
$preferences->update_subscriber_preferences($subscriber_id, [
    'email_frequency' => 'weekly',
    'topics_news' => '1',
    'topics_promotions' => '0'
]);

// Abone email istiyor mu kontrol et
$wants_email = $preferences->subscriber_wants_email($subscriber_id, 'topics_news');

// Tercih merkezi URL
$url = $preferences->get_preference_center_url($subscriber->unsubscribe_token);
```

---

## ✉️ Email Doğrulama (Email Verification)

### Özellikler
- Syntax kontrolü
- DNS/MX kayıt kontrolü
- Disposable email tespiti
- Role-based email tespiti
- Email kalite skoru
- Toplu email doğrulama

### Email Kalite Dereceleri
- **Excellent** (90-100): Mükemmel
- **Good** (70-89): İyi
- **Fair** (50-69): Orta
- **Poor** (0-49): Zayıf

### Kullanım
```php
$email_verification = new \AdvancedNewsletter\Core\EmailVerification();

// Tekil email doğrula
$result = $email_verification->verify_email('user@example.com');
/*
[
    'email' => 'user@example.com',
    'valid' => true,
    'score' => 90,
    'quality' => 'Excellent',
    'checks' => [
        'syntax' => true,
        'dns' => true,
        'disposable' => true,
        'role_based' => true
    ]
]
*/

// Toplu doğrulama
$emails = ['email1@example.com', 'email2@example.com'];
$results = $email_verification->bulk_verify($emails);

// Tüm aboneleri doğrula
$verification_results = $email_verification->verify_all_subscribers();

// Email önerisi (typo düzeltme)
$suggestion = $email_verification->suggest_email('user@gmai.com');
// Returns: 'user@gmail.com'
```

### Tespit Edilen Disposable Domainler
- tempmail.com
- guerrillamail.com
- 10minutemail.com
- mailinator.com
- throwaway.email
- ve daha fazlası...

---

## 📈 Kullanım Senaryoları

### 1. E-ticaret Sitesi
```php
// WooCommerce entegrasyonu aktif
// Sepet terk emailleri otomatik
// Ürün önerileri ile cross-sell
// Satın alma sonrası follow-up
```

### 2. Blog/Haber Sitesi
```php
// RSS-to-Email ile otomatik newsletter
// Segmentasyon ile ilgiye göre içerik
// Lead scoring ile en aktif okuyucular
// Custom fields ile okuyucu profilleme
```

### 3. SaaS Ürünü
```php
// Webhook ile CRM entegrasyonu
// REST API ile kendi arayüzünüz
// Lead scoring ile nitelikli leads
// Subscription preferences ile onboarding
```

### 4. Ajans/Consultant
```php
// Custom fields ile müşteri segmentasyonu
// Email verification ile clean liste
// List cleaning ile yüksek deliverability
// Analytics ile detaylı raporlama
```

---

## 🔧 Gelişmiş Konfigürasyon

### Cron Jobs
```php
// Email kuyruğu (her dakika)
wp_schedule_event(time(), 'every_minute', 'advnews_send_queue');

// RSS kontrol (her 5 dakika)
wp_schedule_event(time(), 'every_five_minutes', 'advnews_check_rss_feeds');

// Liste temizleme (günlük)
wp_schedule_event(time(), 'daily', 'advnews_clean_lists');

// Abandoned cart kontrolü (saatlik)
wp_schedule_event(time(), 'hourly', 'advnews_check_abandoned_carts');
```

### Performans Optimizasyonu
- Batch email gönderimi (varsayılan: 50 email/batch)
- Database indexleme
- Query cache kullanımı
- Background processing
- Lazy loading

### Güvenlik
- Nonce verification tüm AJAX isteklerinde
- Input sanitization
- Output escaping
- Capability checks
- Rate limiting (webhook ve API)

---

## 📚 Kod Örnekleri

### Segment Bazlı Kampanya
```php
// Highly engaged abonelere özel kampanya
$segmentation = new \AdvancedNewsletter\Core\Segmentation();
$segment_id = $segmentation->create_segment([
    'name' => 'VIP Customers',
    'conditions' => [
        ['field' => 'engagement_score', 'value' => 'high'],
        ['field' => 'custom_field', 'field_name' => 'total_purchases', 'operator' => '>=', 'value' => '5']
    ]
]);

$subscribers = $segmentation->get_segment_subscribers($segment_id);
// Bu abonelere özel kampanya gönder
```

### Re-engagement Workflow
```php
// İnaktif aboneleri yeniden aktive et
$list_cleaning = new \AdvancedNewsletter\Core\ListCleaning();
$inactive = $list_cleaning->get_subscribers_by_criterion('inactive_90_days');

// Re-engagement kampanyası oluştur ve gönder
$campaign_id = create_reengagement_campaign();
$list_cleaning->send_reengagement_campaign($inactive, $campaign_id);
```

### Webhook ile Slack Entegrasyonu
```php
$webhooks = new \AdvancedNewsletter\Core\Webhooks();
$webhooks->create_webhook([
    'name' => 'Slack Notifications',
    'url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
    'event' => 'campaign.sent',
    'method' => 'POST'
]);
```

---

## 🎓 Best Practices

### 1. Liste Hijyeni
- Düzenli liste temizleme (aylık)
- Email verification kullanımı
- Double opt-in tercih edilmesi
- Bounce'ları düzenli takip

### 2. Engagement Artırma
- Segmentasyon ile hedefli içerik
- Subscription preferences ile tercih yönetimi
- Lead scoring ile VIP abone tanımlama
- Re-engagement kampanyaları

### 3. Deliverability
- SMTP kullanımı
- Email authentication (SPF, DKIM, DMARC)
- Spam score kontrolü
- Unsubscribe link'i her emailde

### 4. Analytics
- Open rate ve click rate takibi
- A/B testing
- Conversion tracking
- ROI ölçümü

---

## 📝 Changelog

### Version 2.0.0
- ✅ Advanced Segmentation
- ✅ RSS-to-Email Automation
- ✅ WooCommerce Integration
- ✅ Custom Fields
- ✅ Lead Scoring
- ✅ Webhooks
- ✅ REST API
- ✅ List Cleaning
- ✅ Subscription Preferences
- ✅ Email Verification

---

**Developed with ❤️ for WordPress Community**
