<?php
namespace AdvancedNewsletter\Core;

class SubscriptionPreferences {

    private $wpdb;
    private $table_preferences;
    private $table_subscriber_preferences;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_preferences = $wpdb->prefix . 'advnews_preference_options';
        $this->table_subscriber_preferences = $wpdb->prefix . 'advnews_subscriber_preferences';

        // Handle preference center access
        add_action('template_redirect', [$this, 'handle_preference_center']);
    }

    /**
     * Create preference tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Preference options table
        $table_options = $wpdb->prefix . 'advnews_preference_options';
        $sql_options = "CREATE TABLE IF NOT EXISTS $table_options (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            option_key varchar(100) NOT NULL,
            option_label varchar(255) NOT NULL,
            option_description text DEFAULT NULL,
            option_type varchar(50) DEFAULT 'checkbox',
            default_value varchar(255) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            is_required tinyint(1) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY option_key (option_key)
        ) $charset_collate;";

        // Subscriber preferences table
        $table_prefs = $wpdb->prefix . 'advnews_subscriber_preferences';
        $sql_prefs = "CREATE TABLE IF NOT EXISTS $table_prefs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) NOT NULL,
            option_key varchar(100) NOT NULL,
            option_value text DEFAULT NULL,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_option (subscriber_id, option_key),
            KEY subscriber_id (subscriber_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_options);
        dbDelta($sql_prefs);
    }

    /**
     * Install default preferences
     */
    public function install_default_preferences() {
        $defaults = [
            [
                'option_key' => 'email_frequency',
                'option_label' => 'Email Frequency',
                'option_description' => 'How often would you like to receive emails?',
                'option_type' => 'select',
                'default_value' => 'weekly',
                'sort_order' => 1
            ],
            [
                'option_key' => 'topics_news',
                'option_label' => 'News & Updates',
                'option_description' => 'Receive news and product updates',
                'option_type' => 'checkbox',
                'default_value' => '1',
                'sort_order' => 2
            ],
            [
                'option_key' => 'topics_promotions',
                'option_label' => 'Promotions & Offers',
                'option_description' => 'Receive special offers and promotions',
                'option_type' => 'checkbox',
                'default_value' => '1',
                'sort_order' => 3
            ],
            [
                'option_key' => 'topics_tips',
                'option_label' => 'Tips & Tutorials',
                'option_description' => 'Receive helpful tips and tutorials',
                'option_type' => 'checkbox',
                'default_value' => '1',
                'sort_order' => 4
            ],
            [
                'option_key' => 'topics_announcements',
                'option_label' => 'Important Announcements',
                'option_description' => 'Receive important announcements only',
                'option_type' => 'checkbox',
                'default_value' => '1',
                'sort_order' => 5,
                'is_required' => 1
            ]
        ];

        foreach ($defaults as $pref) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->table_preferences} WHERE option_key = %s",
                $pref['option_key']
            ));

            if (!$existing) {
                $this->wpdb->insert($this->table_preferences, $pref);
            }
        }
    }

    /**
     * Get all preference options
     */
    public function get_preference_options() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_preferences} ORDER BY sort_order ASC"
        );
    }

    /**
     * Get subscriber preferences
     */
    public function get_subscriber_preferences($subscriber_id) {
        $preferences = [];

        $saved_prefs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT option_key, option_value
            FROM {$this->table_subscriber_preferences}
            WHERE subscriber_id = %d",
            $subscriber_id
        ), OBJECT_K);

        $all_options = $this->get_preference_options();

        foreach ($all_options as $option) {
            if (isset($saved_prefs[$option->option_key])) {
                $preferences[$option->option_key] = $saved_prefs[$option->option_key]->option_value;
            } else {
                $preferences[$option->option_key] = $option->default_value;
            }
        }

        return $preferences;
    }

    /**
     * Update subscriber preferences
     */
    public function update_subscriber_preferences($subscriber_id, $preferences) {
        foreach ($preferences as $key => $value) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->table_subscriber_preferences}
                WHERE subscriber_id = %d AND option_key = %s",
                $subscriber_id,
                $key
            ));

            if ($existing) {
                $this->wpdb->update(
                    $this->table_subscriber_preferences,
                    ['option_value' => $value],
                    ['id' => $existing]
                );
            } else {
                $this->wpdb->insert($this->table_subscriber_preferences, [
                    'subscriber_id' => $subscriber_id,
                    'option_key' => $key,
                    'option_value' => $value
                ]);
            }
        }

        return true;
    }

    /**
     * Check if subscriber wants a specific type of email
     */
    public function subscriber_wants_email($subscriber_id, $email_type) {
        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT option_value FROM {$this->table_subscriber_preferences}
            WHERE subscriber_id = %d AND option_key = %s",
            $subscriber_id,
            $email_type
        ));

        // If not set, use default
        if ($value === null) {
            $option = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT default_value FROM {$this->table_preferences} WHERE option_key = %s",
                $email_type
            ));

            $value = $option ? $option->default_value : '1';
        }

        return $value == '1' || $value == 'yes';
    }

    /**
     * Get preference center URL
     */
    public function get_preference_center_url($subscriber_token) {
        return add_query_arg([
            'advnews_action' => 'preferences',
            'token' => $subscriber_token
        ], home_url());
    }

    /**
     * Handle preference center page
     */
    public function handle_preference_center() {
        if (!isset($_GET['advnews_action']) || $_GET['advnews_action'] !== 'preferences') {
            return;
        }

        $token = sanitize_text_field($_GET['token'] ?? '');

        if (empty($token)) {
            wp_die(__('Invalid access.', 'advanced-newsletter'));
        }

        // Get subscriber by unsubscribe token
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_subscribers} WHERE unsubscribe_token = %s",
            $token
        ));

        if (!$subscriber) {
            wp_die(__('Invalid token.', 'advanced-newsletter'));
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advnews_save_preferences'])) {
            check_admin_referer('advnews_preferences_' . $subscriber->id);

            $preferences = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'pref_') === 0) {
                    $pref_key = str_replace('pref_', '', $key);
                    $preferences[$pref_key] = sanitize_text_field($value);
                }
            }

            $this->update_subscriber_preferences($subscriber->id, $preferences);

            $success_message = __('Your preferences have been updated!', 'advanced-newsletter');
        }

        // Render preference center
        $this->render_preference_center($subscriber, $success_message ?? '');
        exit;
    }

    /**
     * Render preference center
     */
    private function render_preference_center($subscriber, $success_message = '') {
        $options = $this->get_preference_options();
        $preferences = $this->get_subscriber_preferences($subscriber->id);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php _e('Email Preferences', 'advanced-newsletter'); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                h1 { margin-top: 0; color: #333; }
                .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
                .form-group { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e1e1e1; }
                .form-group:last-child { border-bottom: none; }
                label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
                .description { font-size: 14px; color: #666; margin-bottom: 10px; }
                select, input[type="text"], input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
                input[type="checkbox"] { margin-right: 8px; }
                .checkbox-label { font-weight: normal; cursor: pointer; }
                .submit-btn { background: #007bff; color: #fff; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
                .submit-btn:hover { background: #0056b3; }
                .unsubscribe-link { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e1e1; }
                .unsubscribe-link a { color: #999; text-decoration: none; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php _e('Email Preferences', 'advanced-newsletter'); ?></h1>

                <p><?php printf(__('Hi %s,', 'advanced-newsletter'), esc_html($subscriber->name ?: $subscriber->email)); ?></p>
                <p><?php _e('Manage your email preferences below:', 'advanced-newsletter'); ?></p>

                <?php if ($success_message): ?>
                    <div class="success"><?php echo esc_html($success_message); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php wp_nonce_field('advnews_preferences_' . $subscriber->id); ?>

                    <?php foreach ($options as $option): ?>
                        <div class="form-group">
                            <label><?php echo esc_html($option->option_label); ?></label>

                            <?php if ($option->option_description): ?>
                                <div class="description"><?php echo esc_html($option->option_description); ?></div>
                            <?php endif; ?>

                            <?php
                            $field_name = 'pref_' . $option->option_key;
                            $value = $preferences[$option->option_key] ?? $option->default_value;

                            switch ($option->option_type):
                                case 'select':
                                    echo '<select name="' . esc_attr($field_name) . '">';
                                    $select_options = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
                                    foreach ($select_options as $key => $label) {
                                        $selected = ($value == $key) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                    echo '</select>';
                                    break;

                                case 'checkbox':
                                    $checked = ($value == '1' || $value == 'yes') ? 'checked' : '';
                                    $disabled = $option->is_required ? 'disabled' : '';
                                    echo '<label class="checkbox-label">';
                                    echo '<input type="checkbox" name="' . esc_attr($field_name) . '" value="1" ' . $checked . ' ' . $disabled . '/>';
                                    echo __('Yes, I want to receive these emails', 'advanced-newsletter');
                                    echo '</label>';
                                    if ($option->is_required) {
                                        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="1" />';
                                    }
                                    break;
                            endswitch;
                            ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="advnews_save_preferences" class="submit-btn">
                        <?php _e('Save Preferences', 'advanced-newsletter'); ?>
                    </button>
                </form>

                <div class="unsubscribe-link">
                    <a href="<?php echo esc_url(add_query_arg(['advnews_action' => 'unsubscribe', 'token' => $subscriber->unsubscribe_token], home_url())); ?>">
                        <?php _e('Unsubscribe from all emails', 'advanced-newsletter'); ?>
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
