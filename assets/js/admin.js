jQuery(document).ready(function($) {
    // Toggle notes popup
    $(document).on('click', '.wonc-order-notes-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        var $container = $('#wonc-order-notes-container-' + orderId);
        
        // Close all other open containers
        $('.wonc-order-notes-container').not($container).hide();
        
        // Toggle current container
        if ($container.is(':visible')) {
            $container.hide();
        } else {
            $container.css('display', 'block');
            loadOrderNotes(orderId, $container);
        }
    });
    
    // Add note
    $(document).on('click', '.wonc-order-notes-add-note', function() {
        var $button = $(this);
        var orderId = $button.data('order-id');
        var $container = $('#wonc-order-notes-container-' + orderId);
        var $textarea = $container.find('.wonc-order-notes-new-note');
        var note = $textarea.val().trim();
        var noteType = $container.find('.wonc-order-notes-type').val();
        
        if (!note) {
            return;
        }
        
        $button.prop('disabled', true).text(wonc_order_notes_params.i18n.adding_note || 'Adding...');
        
        $.ajax({
            url: wonc_order_notes_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wonc_order_notes_add_note',
                security: wonc_order_notes_params.nonce,
                order_id: orderId,
                note: note,
                note_type: noteType
            },
            success: function(response) {
                if (response.success) {
                    $textarea.val('');
                    $container.find('.wonc-order-notes-list').append(response.data);
                    updateNoteCount(orderId);
                }
            },
            complete: function() {
                $button.prop('disabled', false).text(wonc_order_notes_params.i18n.add_note || 'Add Note');
            }
        });
    });
    
    // Close notes popup when clicking the close button
    $(document).on('click', '.wonc-order-notes-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.wonc-order-notes-container').hide();
    });
    
    // Prevent click inside notes container from bubbling up
    $(document).on('click', '.wonc-order-notes-container', function(e) {
        e.stopPropagation();
    });
    
    // Close notes when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wonc-order-notes-container, .wonc-order-notes-toggle').length) {
            $('.wonc-order-notes-container').hide();
        }
    });
    
    // Load order notes
    function loadOrderNotes(orderId, $container) {
        var $list = $container.find('.wonc-order-notes-list');
        
        $list.html('<p class="loading">' + (wonc_order_notes_params.i18n.loading || 'Loading...') + '</p>');
        
        $.ajax({
            url: wonc_order_notes_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wonc_order_notes_get_notes',
                security: wonc_order_notes_params.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $list.html(response.data);
                }
            }
        });
    }
    
    // Update note count display
    function updateNoteCount(orderId) {
        var $button = $('.wonc-order-notes-toggle[data-order-id="' + orderId + '"]');
        var $count = $button.find('.wonc-order-notes-count');
        var currentCount = $count.length ? parseInt($count.text()) : 0;
        
        if (currentCount === 0) {
            $button.append('<span class="wonc-order-notes-count">1</span>');
        } else {
            $count.text(currentCount + 1);
        }
    }
});