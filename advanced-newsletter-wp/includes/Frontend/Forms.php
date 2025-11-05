<?php
namespace AdvancedNewsletter\Frontend;

class Forms {

    public function __construct() {
        // Handle confirmation and unsubscription from URL params
        add_action('template_redirect', [$this, 'handle_actions']);

        // Register widget
        add_action('widgets_init', [$this, 'register_widget']);
    }

    public function handle_actions() {
        if (!isset($_GET['advnews_action'])) {
            return;
        }

        $action = sanitize_text_field($_GET['advnews_action']);

        switch ($action) {
            case 'confirm':
                $this->handle_confirmation();
                break;

            case 'unsubscribe':
                $this->handle_unsubscribe();
                break;

            case 'track_open':
                $this->handle_track_open();
                break;

            case 'track_click':
                $this->handle_track_click();
                break;
        }
    }

    private function handle_confirmation() {
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (empty($token)) {
            wp_die(__('Invalid confirmation link.', 'advanced-newsletter'));
        }

        $subscriber = new \AdvancedNewsletter\Core\Subscriber();
        $result = $subscriber->confirm($token);

        if ($result) {
            wp_die(__('Thank you! Your subscription has been confirmed.', 'advanced-newsletter'), __('Subscription Confirmed', 'advanced-newsletter'));
        } else {
            wp_die(__('Invalid or expired confirmation link.', 'advanced-newsletter'));
        }
    }

    private function handle_unsubscribe() {
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (empty($token)) {
            wp_die(__('Invalid unsubscribe link.', 'advanced-newsletter'));
        }

        $subscriber = new \AdvancedNewsletter\Core\Subscriber();
        $result = $subscriber->unsubscribe(['token' => $token]);

        if ($result['success']) {
            wp_die(__('You have been successfully unsubscribed.', 'advanced-newsletter'), __('Unsubscribed', 'advanced-newsletter'));
        } else {
            wp_die(__('Invalid unsubscribe link.', 'advanced-newsletter'));
        }
    }

    private function handle_track_open() {
        $campaign_id = intval($_GET['c'] ?? 0);
        $subscriber_id = intval($_GET['s'] ?? 0);
        $token = sanitize_text_field($_GET['t'] ?? '');

        $sender = new \AdvancedNewsletter\Core\EmailSender();
        $sender->track_open($campaign_id, $subscriber_id, $token);
    }

    private function handle_track_click() {
        $campaign_id = intval($_GET['c'] ?? 0);
        $subscriber_id = intval($_GET['s'] ?? 0);
        $url = urldecode($_GET['url'] ?? '');
        $token = sanitize_text_field($_GET['t'] ?? '');

        $sender = new \AdvancedNewsletter\Core\EmailSender();
        $sender->track_click($campaign_id, $subscriber_id, $url, $token);
    }

    public function register_widget() {
        register_widget('AdvancedNewsletter\Frontend\SubscriptionWidget');
    }
}

// Widget class
class SubscriptionWidget extends \WP_Widget {

    public function __construct() {
        parent::__construct(
            'advnews_subscription',
            __('Newsletter Subscription', 'advanced-newsletter'),
            ['description' => __('Newsletter subscription form', 'advanced-newsletter')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $atts = [
            'title' => $instance['title'] ?? '',
            'description' => $instance['description'] ?? '',
            'button_text' => $instance['button_text'] ?? __('Subscribe', 'advanced-newsletter'),
            'show_name' => $instance['show_name'] ?? 'yes',
            'lists' => $instance['lists'] ?? ''
        ];

        ob_start();
        require ADV_NEWSLETTER_PLUGIN_DIR . 'templates/frontend/subscription-form.php';
        echo ob_get_clean();

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = $instance['title'] ?? __('Subscribe to Newsletter', 'advanced-newsletter');
        $description = $instance['description'] ?? '';
        $button_text = $instance['button_text'] ?? __('Subscribe', 'advanced-newsletter');
        $show_name = $instance['show_name'] ?? 'yes';
        $lists = $instance['lists'] ?? '';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'advanced-newsletter'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('description'); ?>"><?php _e('Description:', 'advanced-newsletter'); ?></label>
            <textarea class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>"><?php echo esc_textarea($description); ?></textarea>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('button_text'); ?>"><?php _e('Button Text:', 'advanced-newsletter'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('button_text'); ?>" name="<?php echo $this->get_field_name('button_text'); ?>" type="text" value="<?php echo esc_attr($button_text); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('show_name'); ?>"><?php _e('Show Name Field:', 'advanced-newsletter'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('show_name'); ?>" name="<?php echo $this->get_field_name('show_name'); ?>">
                <option value="yes" <?php selected($show_name, 'yes'); ?>><?php _e('Yes', 'advanced-newsletter'); ?></option>
                <option value="no" <?php selected($show_name, 'no'); ?>><?php _e('No', 'advanced-newsletter'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('lists'); ?>"><?php _e('Lists (comma-separated IDs):', 'advanced-newsletter'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('lists'); ?>" name="<?php echo $this->get_field_name('lists'); ?>" type="text" value="<?php echo esc_attr($lists); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['description'] = (!empty($new_instance['description'])) ? strip_tags($new_instance['description']) : '';
        $instance['button_text'] = (!empty($new_instance['button_text'])) ? strip_tags($new_instance['button_text']) : '';
        $instance['show_name'] = (!empty($new_instance['show_name'])) ? strip_tags($new_instance['show_name']) : 'yes';
        $instance['lists'] = (!empty($new_instance['lists'])) ? strip_tags($new_instance['lists']) : '';

        return $instance;
    }
}
