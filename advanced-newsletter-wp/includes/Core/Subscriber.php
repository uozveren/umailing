<?php
namespace AdvancedNewsletter\Core;

class Subscriber {

    private $wpdb;
    private $table_subscribers;
    private $table_subscriber_lists;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $this->table_subscriber_lists = $wpdb->prefix . 'advnews_subscriber_lists';
    }

    /**
     * Subscribe a new user
     */
    public function subscribe($data) {
        // Validate email
        $email = sanitize_email($data['email'] ?? '');
        if (!is_email($email)) {
            return [
                'success' => false,
                'message' => __('Geçersiz email adresi.', 'advanced-newsletter')
            ];
        }

        // Check if already subscribed
        $existing = $this->get_by_email($email);
        if ($existing) {
            if ($existing->status === 'active') {
                return [
                    'success' => false,
                    'message' => __('Bu email adresi zaten kayıtlı.', 'advanced-newsletter')
                ];
            } else if ($existing->status === 'pending') {
                // Resend confirmation email
                $this->send_confirmation_email($existing);
                return [
                    'success' => true,
                    'message' => __('Onay emaili tekrar gönderildi.', 'advanced-newsletter')
                ];
            }
        }

        // Get settings
        $double_optin = $this->get_setting('double_optin', '1');
        $gdpr_enabled = $this->get_setting('gdpr_enabled', '1');

        // Check GDPR consent
        if ($gdpr_enabled === '1' && empty($data['gdpr_consent'])) {
            return [
                'success' => false,
                'message' => __('Lütfen GDPR onayını kabul edin.', 'advanced-newsletter')
            ];
        }

        // Generate tokens
        $confirmation_token = wp_generate_password(32, false);
        $unsubscribe_token = wp_generate_password(32, false);

        // Prepare subscriber data
        $subscriber_data = [
            'email' => $email,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'status' => $double_optin === '1' ? 'pending' : 'active',
            'confirmation_token' => $confirmation_token,
            'subscription_ip' => $this->get_client_ip(),
            'subscription_date' => current_time('mysql'),
            'unsubscribe_token' => $unsubscribe_token,
            'source' => sanitize_text_field($data['source'] ?? 'website'),
            'gdpr_consent' => $gdpr_enabled === '1' ? 1 : 0,
            'gdpr_consent_date' => $gdpr_enabled === '1' ? current_time('mysql') : null,
        ];

        if ($double_optin !== '1') {
            $subscriber_data['confirmed_date'] = current_time('mysql');
        }

        // Insert subscriber
        $result = $this->wpdb->insert($this->table_subscribers, $subscriber_data);

        if (!$result) {
            return [
                'success' => false,
                'message' => __('Kayıt sırasında bir hata oluştu.', 'advanced-newsletter')
            ];
        }

        $subscriber_id = $this->wpdb->insert_id;

        // Add to lists
        $lists = !empty($data['lists']) ? explode(',', $data['lists']) : [1]; // Default to list 1
        foreach ($lists as $list_id) {
            $this->add_to_list($subscriber_id, intval($list_id));
        }

        // Send confirmation email if double opt-in enabled
        if ($double_optin === '1') {
            $subscriber_data['id'] = $subscriber_id;
            $this->send_confirmation_email((object)$subscriber_data);

            return [
                'success' => true,
                'message' => __('Lütfen email adresinize gönderilen onay linkini tıklayın.', 'advanced-newsletter')
            ];
        }

        // Send welcome email
        $this->send_welcome_email((object)$subscriber_data);

        // Trigger subscriber subscribed action for webhooks and lead scoring
        do_action('advnews_subscriber_subscribed', $subscriber_id);

        return [
            'success' => true,
            'message' => __('Başarıyla abone oldunuz!', 'advanced-newsletter')
        ];
    }

    /**
     * Confirm subscription
     */
    public function confirm($token) {
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_subscribers} WHERE confirmation_token = %s AND status = 'pending'",
            $token
        ));

        if (!$subscriber) {
            return false;
        }

        $this->wpdb->update(
            $this->table_subscribers,
            [
                'status' => 'active',
                'confirmed_date' => current_time('mysql')
            ],
            ['id' => $subscriber->id]
        );

        // Send welcome email
        $this->send_welcome_email($subscriber);

        // Trigger subscriber confirmed action for webhooks and lead scoring
        do_action('advnews_subscriber_subscribed', $subscriber->id);
        do_action('advnews_subscriber_confirmed', $subscriber->id);

        return true;
    }

    /**
     * Unsubscribe
     */
    public function unsubscribe($data) {
        $token = sanitize_text_field($data['token'] ?? '');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => __('Geçersiz istek.', 'advanced-newsletter')
            ];
        }

        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_subscribers} WHERE unsubscribe_token = %s",
            $token
        ));

        if (!$subscriber) {
            return [
                'success' => false,
                'message' => __('Abone bulunamadı.', 'advanced-newsletter')
            ];
        }

        $this->wpdb->update(
            $this->table_subscribers,
            [
                'status' => 'unsubscribed',
                'unsubscribed_date' => current_time('mysql')
            ],
            ['id' => $subscriber->id]
        );

        // Trigger unsubscribe action for webhooks
        do_action('advnews_subscriber_unsubscribed', $subscriber->id);

        return [
            'success' => true,
            'message' => __('Aboneliğiniz iptal edildi.', 'advanced-newsletter')
        ];
    }

    /**
     * Get subscriber by email
     */
    public function get_by_email($email) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_subscribers} WHERE email = %s",
            $email
        ));
    }

    /**
     * Get subscriber by ID
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_subscribers} WHERE id = %d",
            $id
        ));
    }

    /**
     * Add subscriber to list
     */
    public function add_to_list($subscriber_id, $list_id) {
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_subscriber_lists} WHERE subscriber_id = %d AND list_id = %d",
            $subscriber_id,
            $list_id
        ));

        if (!$existing) {
            $this->wpdb->insert($this->table_subscriber_lists, [
                'subscriber_id' => $subscriber_id,
                'list_id' => $list_id,
                'subscribed_date' => current_time('mysql')
            ]);

            // Update list subscriber count
            $this->update_list_count($list_id);
        }
    }

    /**
     * Remove subscriber from list
     */
    public function remove_from_list($subscriber_id, $list_id) {
        $this->wpdb->delete($this->table_subscriber_lists, [
            'subscriber_id' => $subscriber_id,
            'list_id' => $list_id
        ]);

        $this->update_list_count($list_id);
    }

    /**
     * Update list subscriber count
     */
    private function update_list_count($list_id) {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_subscriber_lists} WHERE list_id = %d",
            $list_id
        ));

        $table_lists = $this->wpdb->prefix . 'advnews_lists';
        $this->wpdb->update($table_lists, ['subscriber_count' => $count], ['id' => $list_id]);
    }

    /**
     * Send confirmation email
     */
    private function send_confirmation_email($subscriber) {
        $confirmation_url = add_query_arg([
            'advnews_action' => 'confirm',
            'token' => $subscriber->confirmation_token
        ], home_url());

        $subject = __('Email Adresinizi Onaylayın', 'advanced-newsletter');
        $message = sprintf(
            __('Merhaba %s,<br><br>Newsletter aboneliğinizi onaylamak için lütfen aşağıdaki linke tıklayın:<br><br><a href="%s">Aboneliği Onayla</a><br><br>Teşekkürler!', 'advanced-newsletter'),
            $subscriber->name ?: $subscriber->email,
            $confirmation_url
        );

        $this->send_email($subscriber->email, $subject, $message);
    }

    /**
     * Send welcome email
     */
    private function send_welcome_email($subscriber) {
        $subject = __('Hoş Geldiniz!', 'advanced-newsletter');
        $message = sprintf(
            __('Merhaba %s,<br><br>Newsletter listemize hoş geldiniz! Sizinle harika içerikler paylaşmak için sabırsızlanıyoruz.<br><br>Teşekkürler!', 'advanced-newsletter'),
            $subscriber->name ?: $subscriber->email
        );

        $this->send_email($subscriber->email, $subject, $message);
    }

    /**
     * Send email helper
     */
    private function send_email($to, $subject, $message) {
        $sender_name = $this->get_setting('sender_name', get_bloginfo('name'));
        $sender_email = $this->get_setting('sender_email', get_option('admin_email'));

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $sender_name, $sender_email)
        ];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get setting
     */
    private function get_setting($key, $default = '') {
        $table_settings = $this->wpdb->prefix . 'advnews_settings';
        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
            $key
        ));

        return $value !== null ? $value : $default;
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return sanitize_text_field($ip);
    }

    /**
     * Get all subscribers with filters
     */
    public function get_all($args = []) {
        $defaults = [
            'status' => '',
            'list_id' => 0,
            'search' => '',
            'orderby' => 'subscription_date',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];

        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("status = %s", $args['status']);
        }

        if (!empty($args['list_id'])) {
            $where[] = $this->wpdb->prepare(
                "id IN (SELECT subscriber_id FROM {$this->table_subscriber_lists} WHERE list_id = %d)",
                $args['list_id']
            );
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(email LIKE %s OR name LIKE %s)", $search, $search);
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('%s %s', sanitize_sql_orderby($args['orderby']), $args['order']);

        $query = "SELECT * FROM {$this->table_subscribers} WHERE {$where_clause} ORDER BY {$order_clause} LIMIT %d OFFSET %d";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $args['limit'], $args['offset']));
    }

    /**
     * Get total count
     */
    public function get_count($args = []) {
        $defaults = [
            'status' => '',
            'list_id' => 0,
            'search' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];

        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("status = %s", $args['status']);
        }

        if (!empty($args['list_id'])) {
            $where[] = $this->wpdb->prepare(
                "id IN (SELECT subscriber_id FROM {$this->table_subscriber_lists} WHERE list_id = %d)",
                $args['list_id']
            );
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(email LIKE %s OR name LIKE %s)", $search, $search);
        }

        $where_clause = implode(' AND ', $where);

        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_subscribers} WHERE {$where_clause}");
    }

    /**
     * Delete subscriber
     */
    public function delete($id) {
        // Delete from subscriber_lists
        $this->wpdb->delete($this->table_subscriber_lists, ['subscriber_id' => $id]);

        // Delete subscriber
        return $this->wpdb->delete($this->table_subscribers, ['id' => $id]);
    }

    /**
     * Bulk delete
     */
    public function bulk_delete($ids) {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        $ids = array_map('intval', $ids);
        $ids_string = implode(',', $ids);

        // Delete from subscriber_lists
        $this->wpdb->query("DELETE FROM {$this->table_subscriber_lists} WHERE subscriber_id IN ($ids_string)");

        // Delete subscribers
        return $this->wpdb->query("DELETE FROM {$this->table_subscribers} WHERE id IN ($ids_string)");
    }
}
