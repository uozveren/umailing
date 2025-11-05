<?php
namespace AdvancedNewsletter;

class Installer {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Subscribers table
        $table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $sql_subscribers = "CREATE TABLE IF NOT EXISTS $table_subscribers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            confirmation_token varchar(64) DEFAULT NULL,
            subscription_ip varchar(45) DEFAULT NULL,
            subscription_date datetime DEFAULT CURRENT_TIMESTAMP,
            confirmed_date datetime DEFAULT NULL,
            unsubscribe_token varchar(64) DEFAULT NULL,
            unsubscribed_date datetime DEFAULT NULL,
            bounce_count int(11) DEFAULT 0,
            last_bounce_date datetime DEFAULT NULL,
            custom_fields text DEFAULT NULL,
            source varchar(100) DEFAULT NULL,
            gdpr_consent tinyint(1) DEFAULT 0,
            gdpr_consent_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY subscription_date (subscription_date)
        ) $charset_collate;";

        // Lists table
        $table_lists = $wpdb->prefix . 'advnews_lists';
        $sql_lists = "CREATE TABLE IF NOT EXISTS $table_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            subscriber_count int(11) DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Subscriber list relations
        $table_subscriber_lists = $wpdb->prefix . 'advnews_subscriber_lists';
        $sql_subscriber_lists = "CREATE TABLE IF NOT EXISTS $table_subscriber_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) NOT NULL,
            list_id bigint(20) NOT NULL,
            subscribed_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_list (subscriber_id, list_id),
            KEY subscriber_id (subscriber_id),
            KEY list_id (list_id)
        ) $charset_collate;";

        // Campaigns table
        $table_campaigns = $wpdb->prefix . 'advnews_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            preheader varchar(255) DEFAULT NULL,
            from_name varchar(255) DEFAULT NULL,
            from_email varchar(255) DEFAULT NULL,
            reply_to varchar(255) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            content longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            send_type varchar(20) DEFAULT 'immediate',
            scheduled_date datetime DEFAULT NULL,
            sent_date datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            total_sent int(11) DEFAULT 0,
            total_opens int(11) DEFAULT 0,
            total_clicks int(11) DEFAULT 0,
            total_bounces int(11) DEFAULT 0,
            total_unsubscribes int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            ab_test_enabled tinyint(1) DEFAULT 0,
            ab_test_config text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_date (scheduled_date)
        ) $charset_collate;";

        // Campaign lists (which lists to send to)
        $table_campaign_lists = $wpdb->prefix . 'advnews_campaign_lists';
        $sql_campaign_lists = "CREATE TABLE IF NOT EXISTS $table_campaign_lists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            list_id bigint(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_list (campaign_id, list_id)
        ) $charset_collate;";

        // Email queue
        $table_queue = $wpdb->prefix . 'advnews_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            subscriber_id bigint(20) NOT NULL,
            email varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            priority int(11) DEFAULT 5,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            error_message text DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            sent_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";

        // Email tracking (opens)
        $table_opens = $wpdb->prefix . 'advnews_opens';
        $sql_opens = "CREATE TABLE IF NOT EXISTS $table_opens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            subscriber_id bigint(20) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            opened_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY opened_date (opened_date)
        ) $charset_collate;";

        // Click tracking
        $table_clicks = $wpdb->prefix . 'advnews_clicks';
        $sql_clicks = "CREATE TABLE IF NOT EXISTS $table_clicks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            subscriber_id bigint(20) NOT NULL,
            url text NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            clicked_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY clicked_date (clicked_date)
        ) $charset_collate;";

        // Bounces
        $table_bounces = $wpdb->prefix . 'advnews_bounces';
        $sql_bounces = "CREATE TABLE IF NOT EXISTS $table_bounces (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) DEFAULT NULL,
            subscriber_id bigint(20) NOT NULL,
            email varchar(255) NOT NULL,
            bounce_type varchar(20) DEFAULT 'hard',
            reason text DEFAULT NULL,
            bounced_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY bounce_type (bounce_type)
        ) $charset_collate;";

        // Templates
        $table_templates = $wpdb->prefix . 'advnews_templates';
        $sql_templates = "CREATE TABLE IF NOT EXISTS $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            thumbnail varchar(500) DEFAULT NULL,
            content longtext DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Automation rules
        $table_automations = $wpdb->prefix . 'advnews_automations';
        $sql_automations = "CREATE TABLE IF NOT EXISTS $table_automations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_config text DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            action_config text DEFAULT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY trigger_type (trigger_type)
        ) $charset_collate;";

        // Settings
        $table_settings = $wpdb->prefix . 'advnews_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            autoload tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql_subscribers);
        dbDelta($sql_lists);
        dbDelta($sql_subscriber_lists);
        dbDelta($sql_campaigns);
        dbDelta($sql_campaign_lists);
        dbDelta($sql_queue);
        dbDelta($sql_opens);
        dbDelta($sql_clicks);
        dbDelta($sql_bounces);
        dbDelta($sql_templates);
        dbDelta($sql_automations);
        dbDelta($sql_settings);

        // Insert default data
        self::insert_default_data();

        // Set version
        update_option('advnews_version', ADV_NEWSLETTER_VERSION);
        update_option('advnews_installed_date', current_time('mysql'));
    }

    private static function insert_default_data() {
        global $wpdb;

        // Insert default list
        $table_lists = $wpdb->prefix . 'advnews_lists';
        $existing_lists = $wpdb->get_var("SELECT COUNT(*) FROM $table_lists");

        if ($existing_lists == 0) {
            $wpdb->insert($table_lists, [
                'name' => 'Main List',
                'description' => 'Default subscriber list',
                'created_date' => current_time('mysql')
            ]);
        }

        // Insert default template
        $table_templates = $wpdb->prefix . 'advnews_templates';
        $existing_templates = $wpdb->get_var("SELECT COUNT(*) FROM $table_templates");

        if ($existing_templates == 0) {
            $default_template = self::get_default_template();
            $wpdb->insert($table_templates, [
                'name' => 'Default Template',
                'description' => 'Simple and clean newsletter template',
                'content' => $default_template,
                'is_default' => 1,
                'created_date' => current_time('mysql')
            ]);
        }

        // Insert default settings
        $table_settings = $wpdb->prefix . 'advnews_settings';
        $existing_settings = $wpdb->get_var("SELECT COUNT(*) FROM $table_settings");

        if ($existing_settings == 0) {
            $default_settings = [
                'sender_name' => get_bloginfo('name'),
                'sender_email' => get_option('admin_email'),
                'reply_to' => get_option('admin_email'),
                'double_optin' => '1',
                'gdpr_enabled' => '1',
                'emails_per_batch' => '50',
                'batch_interval' => '60',
                'tracking_enabled' => '1',
                'smtp_enabled' => '0',
            ];

            foreach ($default_settings as $key => $value) {
                $wpdb->insert($table_settings, [
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
            }
        }
    }

    private static function get_default_template() {
        return '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background-color: #333333; padding: 20px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; }
        .content { padding: 30px; color: #333333; line-height: 1.6; }
        .footer { background-color: #eeeeee; padding: 20px; text-align: center; font-size: 12px; color: #666666; }
        .button { display: inline-block; padding: 12px 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{site_name}}</h1>
        </div>
        <div class="content">
            {{content}}
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. Tüm hakları saklıdır.</p>
            <p><a href="{{unsubscribe_url}}">Abonelikten Çık</a></p>
        </div>
    </div>
</body>
</html>';
    }

    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('advnews_send_queue');
    }

    public static function uninstall() {
        global $wpdb;

        // Drop all tables
        $tables = [
            'advnews_subscribers',
            'advnews_lists',
            'advnews_subscriber_lists',
            'advnews_campaigns',
            'advnews_campaign_lists',
            'advnews_queue',
            'advnews_opens',
            'advnews_clicks',
            'advnews_bounces',
            'advnews_templates',
            'advnews_automations',
            'advnews_settings'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        // Delete options
        delete_option('advnews_version');
        delete_option('advnews_installed_date');
    }
}
