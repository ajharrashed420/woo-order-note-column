<?php
/**
 * Plugin Name: Order Note Admin Column for WooCommerce
 * Plugin URI: https://wpmethods.com/wc-order-notes-column
 * Description: Adds a notes column to WooCommerce orders admin with note management functionality.
 * Version: 1.2.2
 * Author: WP Methods
 * Author URI: https://wpmethods.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: order-note-admin-column
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.1.0
 * WC tested up to: 8.6.1
 * WooCommerce-compliant: yes
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

if ( ! defined( 'ORDER_NOTE_COLUMN_VERSION_ONC' ) ) {
	// Replace the version number of the theme on each release.
	define( 'ORDER_NOTE_COLUMN_VERSION_ONC', '1.2.2' );
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class WONC_Order_Notes_Column_HPOS {

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'wonc_woocommerce_missing_notice'));
            return;
        }

        $this->wonc_add_order_column_hooks();

        add_action('wp_ajax_wonc_order_notes_get_notes', array($this, 'wonc_get_order_notes_ajax'));
        add_action('wp_ajax_wonc_order_notes_add_note', array($this, 'wonc_add_order_note_ajax'));

        add_action('admin_enqueue_scripts', array($this, 'wonc_enqueue_admin_scripts'));
    }

    private function wonc_add_order_column_hooks() {
        add_filter('manage_edit-shop_order_columns', array($this, 'wonc_add_order_notes_column'), 999);
        add_action('manage_shop_order_posts_custom_column', array($this, 'wonc_display_order_notes_column'), 10, 2);

        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'wonc_add_order_notes_column'), 999);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'wonc_display_order_notes_column'), 10, 2);
    }

    public function wonc_add_order_notes_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_total' === $key || 'wc_actions' === $key) {
                $new_columns['wonc_order_notes'] = __('Notes', 'order-note-admin-column');
            }
        }
        return $new_columns;
    }

    public function wonc_display_order_notes_column($column, $order) {
        if ('wonc_order_notes' !== $column) return;

        if (empty($order)) return;

        $order_obj = is_a($order, 'WC_Order') ? $order : wc_get_order($order);
        if (!$order_obj) return;

        $order_id = $order_obj->get_id();
        $note_count = $this->wonc_get_order_note_count($order_obj);

        // Fetch last private note
        $private_notes = wc_get_order_notes(array('order_id' => $order_id, 'type' => 'internal', 'limit' => 1, 'orderby' => 'date_created', 'order' => 'DESC'));
        if (!empty($private_notes)) {
            $last_private_note = $private_notes[0];
            echo '<div class="wonc-last-private-note" style="margin-bottom:6px;font-size:11px;color:#666;max-width:250px;">' . esc_html(wp_strip_all_tags($last_private_note->content)) . '</div>';
        }

        echo '<a href="#" class="wonc-order-notes-toggle" data-order-id="' . esc_attr($order_id) . '" title="' . esc_attr__('Order notes', 'order-note-admin-column') . '">';
        // Change icon to plus
        echo '<span class="dashicons dashicons-plus"></span> Add Note';
        
        echo '</a>';

        echo '<div class="wonc-order-notes-container" id="wonc-order-notes-container-' . esc_attr($order_id) . '" style="display:none;">';
        echo '<button type="button" class="wonc-order-notes-close" title="' . esc_attr__('Close', 'order-note-admin-column') . '">&times;</button>';
        echo '<div class="wonc-order-notes-list"></div>';
        echo '<div class="wonc-order-notes-add">';
        echo '<textarea class="wonc-order-notes-new-note" placeholder="' . esc_attr__('Add a new note...', 'order-note-admin-column') . '"></textarea>';
        echo '<select class="wonc-order-notes-type">';
        echo '<option value="private">' . esc_html__('Private note', 'order-note-admin-column') . '</option>';
        echo '<option value="customer">' . esc_html__('Note to customer', 'order-note-admin-column') . '</option>';
        echo '</select>';
        echo '<button class="button wonc-order-notes-add-note" data-order-id="' . esc_attr($order_id) . '">' . esc_html__('Add Note', 'order-note-admin-column') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function wonc_get_order_note_count($order) {
        if (!is_a($order, 'WC_Order')) return 0;
        $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
        return count($notes);
    }

    public function wonc_get_order_notes_ajax() {
        check_ajax_referer('wonc_order_notes_nonce', 'security');
        if (!current_user_can('edit_shop_orders')) wp_die(-1);

        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order) wp_die(-1);

        $notes = wc_get_order_notes(array('order_id' => $order_id));

        ob_start();
        if ($notes) {
            foreach ($notes as $note) {
                $note_classes = array('note');
                $note_classes[] = $note->customer_note ? 'customer-note' : '';
                ?>
                <div class="<?php echo esc_attr(implode(' ', array_filter($note_classes))); ?>">
                    <div class="note-content">
                        <?php echo wpautop(wptexturize(wp_kses_post($note->content))); ?>
                    </div>
                    <p class="meta">
                        <?php
                        printf(
                            __('Added by %s on %s at %s', 'order-note-admin-column'),
                            esc_html($note->added_by),
                            date_i18n(wc_date_format(), strtotime($note->date_created)),
                            date_i18n(wc_time_format(), strtotime($note->date_created))
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        } else {
            echo '<p class="no-notes">' . esc_html__('No notes yet.', 'order-note-admin-column') . '</p>';
        }
        wp_send_json_success(ob_get_clean());
    }

    public function wonc_add_order_note_ajax() {
        check_ajax_referer('wonc_order_notes_nonce', 'security');
        if (!current_user_can('edit_shop_orders')) wp_die(-1);

        $order_id = absint($_POST['order_id']);
        $note = wp_kses_post(trim(wp_unslash($_POST['note'])));
        $note_type = isset($_POST['note_type']) && $_POST['note_type'] === 'customer' ? true : false;
        if (!$order_id || empty($note)) wp_die(-1);

        $order = wc_get_order($order_id);
        if (!$order) wp_die(-1);

        $note_id = $order->add_order_note($note, $note_type, false);
        if ($note_id) {
            // Set the note author to the current user if available
            $user_id = get_current_user_id();
            if ($user_id) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->comments,
                    array('user_id' => $user_id),
                    array('comment_ID' => $note_id)
                );
            }
            $new_note = wc_get_order_note($note_id);
            ob_start();
            ?>
            <div class="note">
                <div class="note-content">
                    <?php echo wpautop(wptexturize(wp_kses_post($new_note->content))); ?>
                </div>
                <p class="meta">
                    <?php
                    printf(
                        __('Added by %s on %s at %s', 'order-note-admin-column'),
                        esc_html($new_note->added_by),
                        date_i18n(wc_date_format(), strtotime($new_note->date_created)),
                        date_i18n(wc_time_format(), strtotime($new_note->date_created))
                    );
                    ?>
                </p>
            </div>
            <?php
            wp_send_json_success(ob_get_clean());
        }
        wp_die(-1);
    }

    public function wonc_enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        if (!isset($screen->id) || (strpos($screen->id, 'shop_order') === false && $hook !== 'woocommerce_page_wc-orders')) {
            return;
        }

        $min_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style(
            'order-note-admin-column',
            plugins_url('assets/css/admin'.$min_suffix.'.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin'.$min_suffix.'.css')
        );

        wp_enqueue_script(
            'order-note-admin-column',
            plugins_url('assets/js/admin'.$min_suffix.'.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin'.$min_suffix.'.js'),
            true
        );

        wp_localize_script('order-note-admin-column', 'wonc_order_notes_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wonc_order_notes_nonce'),
            'i18n' => array(
                'add_note_error' => __('Failed to add note. Please try again.', 'order-note-admin-column'),
                'adding_note' => __('Adding...', 'order-note-admin-column'),
                'loading' => __('Loading...', 'order-note-admin-column')
            )
        ));
    }

    public function wonc_woocommerce_missing_notice() {
        echo '<div class="error"><p>' . sprintf(
            esc_html__('WooCommerce Order Notes Column requires %s to be installed and active.', 'order-note-admin-column'),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        ) . '</p></div>';
    }
}

// Initialize plugin after all plugins are loaded
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        new WONC_Order_Notes_Column_HPOS();
    }
});
