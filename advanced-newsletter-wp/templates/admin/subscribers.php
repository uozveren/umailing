<?php
/**
 * Admin Subscribers Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap advnews-subscribers">
    <h1><?php _e('Subscribers', 'advanced-newsletter'); ?></h1>

    <div class="advnews-toolbar">
        <button class="button button-primary" id="advnews-add-subscriber">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Add Subscriber', 'advanced-newsletter'); ?>
        </button>

        <button class="button" id="advnews-import-subscribers">
            <span class="dashicons dashicons-upload"></span>
            <?php _e('Import CSV', 'advanced-newsletter'); ?>
        </button>

        <button class="button" id="advnews-export-subscribers">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export CSV', 'advanced-newsletter'); ?>
        </button>

        <button class="button" id="advnews-bulk-delete" style="display:none;">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Delete Selected', 'advanced-newsletter'); ?>
        </button>
    </div>

    <div class="advnews-filters">
        <select id="advnews-filter-status">
            <option value=""><?php _e('All Status', 'advanced-newsletter'); ?></option>
            <option value="active"><?php _e('Active', 'advanced-newsletter'); ?></option>
            <option value="pending"><?php _e('Pending', 'advanced-newsletter'); ?></option>
            <option value="unsubscribed"><?php _e('Unsubscribed', 'advanced-newsletter'); ?></option>
        </select>

        <input type="text" id="advnews-search" placeholder="<?php esc_attr_e('Search by email or name...', 'advanced-newsletter'); ?>" />

        <button class="button" id="advnews-apply-filters"><?php _e('Apply Filters', 'advanced-newsletter'); ?></button>
    </div>

    <div id="advnews-subscribers-table">
        <p><?php _e('Loading subscribers...', 'advanced-newsletter'); ?></p>
    </div>

    <div id="advnews-pagination"></div>
</div>

<!-- Add Subscriber Modal -->
<div id="advnews-add-subscriber-modal" class="advnews-modal" style="display:none;">
    <div class="advnews-modal-content">
        <span class="advnews-modal-close">&times;</span>
        <h2><?php _e('Add Subscriber', 'advanced-newsletter'); ?></h2>
        <form id="advnews-add-subscriber-form">
            <p>
                <label><?php _e('Email', 'advanced-newsletter'); ?> <span class="required">*</span></label>
                <input type="email" name="email" required class="widefat" />
            </p>
            <p>
                <label><?php _e('Name', 'advanced-newsletter'); ?></label>
                <input type="text" name="name" class="widefat" />
            </p>
            <p>
                <label>
                    <input type="checkbox" name="gdpr_consent" value="1" checked />
                    <?php _e('GDPR Consent', 'advanced-newsletter'); ?>
                </label>
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php _e('Add Subscriber', 'advanced-newsletter'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="advnews-import-modal" class="advnews-modal" style="display:none;">
    <div class="advnews-modal-content">
        <span class="advnews-modal-close">&times;</span>
        <h2><?php _e('Import Subscribers', 'advanced-newsletter'); ?></h2>
        <form id="advnews-import-form">
            <p><?php _e('Upload a CSV file with columns: email, name', 'advanced-newsletter'); ?></p>
            <p>
                <input type="file" name="file" accept=".csv" required />
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php _e('Import', 'advanced-newsletter'); ?></button>
            </p>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Load subscribers function
    function loadSubscribers(page = 1) {
        var status = $('#advnews-filter-status').val();
        var search = $('#advnews-search').val();

        $.ajax({
            url: advNewsletterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_get_subscribers',
                nonce: advNewsletterAdmin.nonce,
                status: status,
                search: search,
                limit: 20,
                offset: (page - 1) * 20
            },
            success: function(response) {
                if (response.success) {
                    renderSubscribersTable(response.data.subscribers);
                    renderPagination(response.data.total, page);
                }
            }
        });
    }

    function renderSubscribersTable(subscribers) {
        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" id="select-all" /></th>';
        html += '<th><?php _e('Email', 'advanced-newsletter'); ?></th>';
        html += '<th><?php _e('Name', 'advanced-newsletter'); ?></th>';
        html += '<th><?php _e('Status', 'advanced-newsletter'); ?></th>';
        html += '<th><?php _e('Subscription Date', 'advanced-newsletter'); ?></th>';
        html += '<th><?php _e('Actions', 'advanced-newsletter'); ?></th>';
        html += '</tr></thead><tbody>';

        if (subscribers.length === 0) {
            html += '<tr><td colspan="6"><?php _e('No subscribers found.', 'advanced-newsletter'); ?></td></tr>';
        } else {
            subscribers.forEach(function(sub) {
                html += '<tr>';
                html += '<td><input type="checkbox" class="subscriber-checkbox" value="' + sub.id + '" /></td>';
                html += '<td>' + sub.email + '</td>';
                html += '<td>' + (sub.name || '-') + '</td>';
                html += '<td><span class="advnews-status advnews-status-' + sub.status + '">' + sub.status + '</span></td>';
                html += '<td>' + sub.subscription_date + '</td>';
                html += '<td><button class="button button-small advnews-delete-subscriber" data-id="' + sub.id + '"><?php _e('Delete', 'advanced-newsletter'); ?></button></td>';
                html += '</tr>';
            });
        }

        html += '</tbody></table>';
        $('#advnews-subscribers-table').html(html);
    }

    function renderPagination(total, currentPage) {
        var totalPages = Math.ceil(total / 20);
        var html = '<div class="tablenav"><div class="tablenav-pages">';
        html += '<span class="displaying-num">' + total + ' <?php _e('items', 'advanced-newsletter'); ?></span>';

        for (var i = 1; i <= totalPages; i++) {
            var activeClass = i === currentPage ? 'current' : '';
            html += '<a class="button ' + activeClass + '" data-page="' + i + '">' + i + '</a>';
        }

        html += '</div></div>';
        $('#advnews-pagination').html(html);
    }

    // Initial load
    loadSubscribers();

    // Apply filters
    $('#advnews-apply-filters').on('click', function() {
        loadSubscribers();
    });

    // Pagination
    $(document).on('click', '#advnews-pagination a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadSubscribers(page);
    });

    // Add subscriber modal
    $('#advnews-add-subscriber').on('click', function() {
        $('#advnews-add-subscriber-modal').fadeIn();
    });

    $('.advnews-modal-close').on('click', function() {
        $(this).closest('.advnews-modal').fadeOut();
    });

    // Add subscriber form
    $('#advnews-add-subscriber-form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: advNewsletterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_add_subscriber',
                nonce: advNewsletterAdmin.nonce,
                email: $(this).find('[name="email"]').val(),
                name: $(this).find('[name="name"]').val(),
                gdpr_consent: $(this).find('[name="gdpr_consent"]').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#advnews-add-subscriber-modal').fadeOut();
                    loadSubscribers();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Delete subscriber
    $(document).on('click', '.advnews-delete-subscriber', function() {
        if (!confirm(advNewsletterAdmin.strings.confirmDelete)) {
            return;
        }

        var id = $(this).data('id');

        $.ajax({
            url: advNewsletterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_delete_subscriber',
                nonce: advNewsletterAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadSubscribers();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});
</script>
