<?php
namespace AdvancedNewsletter\Integrations;

class WooCommerce {

    private $wpdb;

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;

        // Hooks
        add_action('woocommerce_checkout_order_processed', [$this, 'subscribe_on_checkout'], 10, 1);
        add_action('woocommerce_cart_is_empty', [$this, 'check_abandoned_cart']);
        add_action('advnews_check_abandoned_carts', [$this, 'send_abandoned_cart_emails']);

        // Product purchase automation
        add_action('woocommerce_order_status_completed', [$this, 'send_product_followup']);

        // Admin AJAX
        add_action('wp_ajax_advnews_create_product_campaign', [$this, 'ajax_create_product_campaign']);
    }

    /**
     * Create WooCommerce tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Abandoned carts table
        $table_carts = $wpdb->prefix . 'advnews_abandoned_carts';
        $sql_carts = "CREATE TABLE IF NOT EXISTS $table_carts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            cart_data longtext DEFAULT NULL,
            cart_value decimal(10,2) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            reminder_sent datetime DEFAULT NULL,
            reminder_count int(11) DEFAULT 0,
            recovered tinyint(1) DEFAULT 0,
            recovered_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY recovered (recovered)
        ) $charset_collate;";

        // Product recommendations table
        $table_recommendations = $wpdb->prefix . 'advnews_product_recommendations';
        $sql_recommendations = "CREATE TABLE IF NOT EXISTS $table_recommendations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            score decimal(5,2) DEFAULT 0,
            reason varchar(100) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_carts);
        dbDelta($sql_recommendations);
    }

    /**
     * Subscribe customer on checkout
     */
    public function subscribe_on_checkout($order_id) {
        $order = wc_get_order($order_id);
        $email = $order->get_billing_email();
        $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Check if customer opted in
        $optin = get_post_meta($order_id, '_newsletter_optin', true);

        if ($optin === 'yes') {
            $subscriber = new \AdvancedNewsletter\Core\Subscriber();
            $subscriber->subscribe([
                'email' => $email,
                'name' => $name,
                'source' => 'woocommerce_checkout',
                'gdpr_consent' => 1
            ]);
        }
    }

    /**
     * Track abandoned cart
     */
    public function check_abandoned_cart() {
        if (!is_user_logged_in() && !isset($_COOKIE['cart_email'])) {
            return;
        }

        $cart = WC()->cart->get_cart();

        if (empty($cart)) {
            return;
        }

        $email = is_user_logged_in()
            ? wp_get_current_user()->user_email
            : (isset($_COOKIE['cart_email']) ? sanitize_email($_COOKIE['cart_email']) : '');

        if (empty($email)) {
            return;
        }

        $cart_value = WC()->cart->get_cart_contents_total();
        $cart_data = json_encode($cart);

        $table_carts = $this->wpdb->prefix . 'advnews_abandoned_carts';

        // Check if cart already exists
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_carts} WHERE email = %s AND recovered = 0 ORDER BY created_date DESC LIMIT 1",
            $email
        ));

        if ($existing) {
            // Update existing cart
            $this->wpdb->update(
                $table_carts,
                [
                    'cart_data' => $cart_data,
                    'cart_value' => $cart_value
                ],
                ['id' => $existing->id]
            );
        } else {
            // Create new abandoned cart record
            $this->wpdb->insert($table_carts, [
                'email' => $email,
                'cart_data' => $cart_data,
                'cart_value' => $cart_value,
                'created_date' => current_time('mysql')
            ]);
        }
    }

    /**
     * Send abandoned cart emails
     */
    public function send_abandoned_cart_emails() {
        $table_carts = $this->wpdb->prefix . 'advnews_abandoned_carts';

        // Get carts abandoned for 1 hour
        $carts = $this->wpdb->get_results("
            SELECT * FROM {$table_carts}
            WHERE recovered = 0
            AND reminder_count < 3
            AND (
                (reminder_count = 0 AND created_date <= DATE_SUB(NOW(), INTERVAL 1 HOUR))
                OR (reminder_count = 1 AND reminder_sent <= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                OR (reminder_count = 2 AND reminder_sent <= DATE_SUB(NOW(), INTERVAL 3 DAY))
            )
        ");

        foreach ($carts as $cart) {
            $this->send_abandoned_cart_email($cart);
        }
    }

    /**
     * Send single abandoned cart email
     */
    private function send_abandoned_cart_email($cart) {
        $cart_items = json_decode($cart->cart_data, true);
        $content = $this->build_abandoned_cart_content($cart_items, $cart->cart_value);

        $subject = $cart->reminder_count == 0
            ? 'You left items in your cart'
            : 'Your cart is still waiting';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($cart->email, $subject, $content, $headers);

        // Update reminder sent
        $table_carts = $this->wpdb->prefix . 'advnews_abandoned_carts';
        $this->wpdb->update(
            $table_carts,
            [
                'reminder_sent' => current_time('mysql'),
                'reminder_count' => $cart->reminder_count + 1
            ],
            ['id' => $cart->id]
        );
    }

    /**
     * Build abandoned cart email content
     */
    private function build_abandoned_cart_content($cart_items, $cart_value) {
        $html = '<h2>You left these items in your cart</h2>';
        $html .= '<div style="margin: 20px 0;">';

        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = wc_get_product($cart_item['product_id']);

            if (!$product) {
                continue;
            }

            $html .= '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e1e1e1;">';
            $html .= '<img src="' . wp_get_attachment_url($product->get_image_id()) . '" alt="" style="width: 100px; height: auto; float: left; margin-right: 15px;" />';
            $html .= '<h3 style="margin: 0;">' . $product->get_name() . '</h3>';
            $html .= '<p>Quantity: ' . $cart_item['quantity'] . '</p>';
            $html .= '<p><strong>' . wc_price($product->get_price()) . '</strong></p>';
            $html .= '<div style="clear: both;"></div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<p><strong>Cart Total: ' . wc_price($cart_value) . '</strong></p>';
        $html .= '<p><a href="' . wc_get_cart_url() . '" class="button" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;">Complete Your Purchase</a></p>';

        return $html;
    }

    /**
     * Send product follow-up email
     */
    public function send_product_followup($order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        // Get products from order
        $product_ids = [];
        foreach ($items as $item) {
            $product_ids[] = $item->get_product_id();
        }

        // Get related products
        $related_products = $this->get_related_products($product_ids);

        if (empty($related_products)) {
            return;
        }

        // Build email content
        $content = $this->build_product_recommendation_content($related_products);

        $subject = 'You might also like these products';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($order->get_billing_email(), $subject, $content, $headers);
    }

    /**
     * Get related products
     */
    private function get_related_products($product_ids, $limit = 4) {
        $related = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $related_ids = wc_get_related_products($product_id, $limit);
            $related = array_merge($related, $related_ids);
        }

        return array_unique(array_slice($related, 0, $limit));
    }

    /**
     * Build product recommendation content
     */
    private function build_product_recommendation_content($product_ids) {
        $html = '<h2>You might also like</h2>';
        $html .= '<div style="margin: 20px 0;">';

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $html .= '<div style="display: inline-block; width: 48%; margin: 1%; vertical-align: top; text-align: center;">';
            $html .= '<img src="' . wp_get_attachment_url($product->get_image_id()) . '" alt="" style="width: 100%; height: auto; border-radius: 4px;" />';
            $html .= '<h3 style="margin: 10px 0;">' . $product->get_name() . '</h3>';
            $html .= '<p><strong>' . wc_price($product->get_price()) . '</strong></p>';
            $html .= '<a href="' . $product->get_permalink() . '" class="button" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;">View Product</a>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Create product campaign
     */
    public function create_product_campaign($product_ids, $list_id, $subject) {
        $content = $this->build_product_recommendation_content($product_ids);

        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';

        $this->wpdb->insert($table_campaigns, [
            'name' => 'Product Campaign - ' . current_time('Y-m-d'),
            'subject' => $subject,
            'content' => $content,
            'status' => 'draft',
            'created_date' => current_time('mysql')
        ]);

        $campaign_id = $this->wpdb->insert_id;

        // Add to list
        $table_campaign_lists = $this->wpdb->prefix . 'advnews_campaign_lists';
        $this->wpdb->insert($table_campaign_lists, [
            'campaign_id' => $campaign_id,
            'list_id' => $list_id
        ]);

        return $campaign_id;
    }

    /**
     * AJAX: Create product campaign
     */
    public function ajax_create_product_campaign() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $product_ids = array_map('intval', $_POST['product_ids'] ?? []);
        $list_id = intval($_POST['list_id'] ?? 0);
        $subject = sanitize_text_field($_POST['subject'] ?? '');

        if (empty($product_ids) || !$list_id) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $campaign_id = $this->create_product_campaign($product_ids, $list_id, $subject);

        wp_send_json_success([
            'message' => 'Product campaign created',
            'campaign_id' => $campaign_id
        ]);
    }
}
