jQuery(document).ready(function($) {
    // Toggle notes popup
    $(document).on('click', '.wc-order-notes-toggle', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        var $container = $('#wc-order-notes-container-' + orderId);
        
        // Close all other open containers
        $('.wc-order-notes-container').not($container).hide();
        
        // Toggle current container
        if ($container.is(':visible')) {
            $container.hide();
        } else {
            $container.show();
            loadOrderNotes(orderId, $container);
        }
        
        // Position container
        positionNotesContainer($button, $container);
    });
    
    // Add note
    $(document).on('click', '.wc-order-notes-add-note', function() {
        var $button = $(this);
        var orderId = $button.data('order-id');
        var $container = $('#wc-order-notes-container-' + orderId);
        var $textarea = $container.find('.wc-order-notes-new-note');
        var note = $textarea.val().trim();
        
        if (!note) {
            return;
        }
        
        $button.prop('disabled', true).text(wc_order_notes_params.i18n.adding_note || 'Adding...');
        
        $.ajax({
            url: wc_order_notes_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_order_notes_add_note',
                security: wc_order_notes_params.nonce,
                order_id: orderId,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    $textarea.val('');
                    $container.find('.wc-order-notes-list').append(response.data);
                    updateNoteCount(orderId);
                }
            },
            complete: function() {
                $button.prop('disabled', false).text(wc_order_notes_params.i18n.add_note || 'Add Note');
            }
        });
    });
    
    // Close notes when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wc-order-notes-container, .wc-order-notes-toggle').length) {
            $('.wc-order-notes-container').hide();
        }
    });
    
    // Load order notes
    function loadOrderNotes(orderId, $container) {
        var $list = $container.find('.wc-order-notes-list');
        
        $list.html('<p class="loading">' + (wc_order_notes_params.i18n.loading || 'Loading...') + '</p>');
        
        $.ajax({
            url: wc_order_notes_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_order_notes_get_notes',
                security: wc_order_notes_params.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $list.html(response.data);
                }
            }
        });
    }
    
    // Position notes container
    function positionNotesContainer($button, $container) {
        var buttonPos = $button.offset();
        var buttonWidth = $button.outerWidth();
        var containerWidth = $container.outerWidth();
        
        // Position below the button
        $container.css({
            top: buttonPos.top + $button.outerHeight() + 5,
            left: buttonPos.left + buttonWidth / 2 - containerWidth / 2
        });
    }
    
    // Update note count display
    function updateNoteCount(orderId) {
        var $button = $('.wc-order-notes-toggle[data-order-id="' + orderId + '"]');
        var $count = $button.find('.wc-order-notes-count');
        var currentCount = $count.length ? parseInt($count.text()) : 0;
        
        if (currentCount === 0) {
            $button.append('<span class="wc-order-notes-count">1</span>');
        } else {
            $count.text(currentCount + 1);
        }
    }
});