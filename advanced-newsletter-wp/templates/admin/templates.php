<?php
/**
 * Admin Templates Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap advnews-templates">
    <h1><?php _e('Email Templates', 'advanced-newsletter'); ?></h1>

    <div class="advnews-toolbar">
        <button class="button button-primary" id="advnews-create-template">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Create Template', 'advanced-newsletter'); ?>
        </button>
    </div>

    <div id="advnews-templates-grid">
        <p><?php _e('Loading templates...', 'advanced-newsletter'); ?></p>
    </div>
</div>
