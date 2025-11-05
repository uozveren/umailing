<?php
/**
 * Admin Campaigns Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap advnews-campaigns">
    <h1><?php _e('Campaigns', 'advanced-newsletter'); ?></h1>

    <div class="advnews-toolbar">
        <button class="button button-primary" id="advnews-create-campaign">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Create Campaign', 'advanced-newsletter'); ?>
        </button>
    </div>

    <div class="advnews-filters">
        <select id="advnews-filter-status">
            <option value=""><?php _e('All Status', 'advanced-newsletter'); ?></option>
            <option value="draft"><?php _e('Draft', 'advanced-newsletter'); ?></option>
            <option value="scheduled"><?php _e('Scheduled', 'advanced-newsletter'); ?></option>
            <option value="sending"><?php _e('Sending', 'advanced-newsletter'); ?></option>
            <option value="sent"><?php _e('Sent', 'advanced-newsletter'); ?></option>
        </select>

        <input type="text" id="advnews-search" placeholder="<?php esc_attr_e('Search campaigns...', 'advanced-newsletter'); ?>" />

        <button class="button" id="advnews-apply-filters"><?php _e('Apply Filters', 'advanced-newsletter'); ?></button>
    </div>

    <div id="advnews-campaigns-table">
        <p><?php _e('Loading campaigns...', 'advanced-newsletter'); ?></p>
    </div>
</div>
