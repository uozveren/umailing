/**
 * Advanced Newsletter - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Form validation and submission is handled inline in the template
    // This file can be used for additional frontend functionality

    // Example: Track external link clicks
    $(document).on('click', 'a[href^="http"]', function() {
        // Could send analytics data here
    });

    // Example: Auto-hide success/error messages
    $('.advnews-message').each(function() {
        var $message = $(this);
        if ($message.is(':visible')) {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
    });

})(jQuery);
