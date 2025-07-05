<?php
/**
 * Plugin Name: WooCommerce Order Notes Column (HPOS Compatible)
 * Plugin URI: https://wpmethods.com/plugins/wc-order-notes-column
 * Description: Adds a notes column to WooCommerce orders admin with note management functionality.
 * Version: 1.2.2
 * Author: WP Methods
 * Author URI: https://wpmethods.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-order-notes-column
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.1.0
 * WC tested up to: 8.6.1
 * WooCommerce-compliant: yes
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class WC_Order_Notes_Column_HPOS {

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->add_order_column_hooks();

        add_action('wp_ajax_wc_order_notes_get_notes', array($this, 'get_order_notes_ajax'));
        add_action('wp_ajax_wc_order_notes_add_note', array($this, 'add_order_note_ajax'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    private function add_order_column_hooks() {
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_notes_column'), 999);
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_notes_column'), 10, 2);

        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_notes_column'), 999);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_order_notes_column'), 10, 2);
    }

    public function add_order_notes_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_total' === $key || 'wc_actions' === $key) {
                $new_columns['order_notes'] = __('Notes', 'wc-order-notes-column');
            }
        }
        return $new_columns;
    }

    public function display_order_notes_column($column, $order) {
        if ('order_notes' !== $column) return;

        if (empty($order)) return;

        $order_obj = is_a($order, 'WC_Order') ? $order : wc_get_order($order);
        if (!$order_obj) return;

        $order_id = $order_obj->get_id();
        $note_count = $this->get_order_note_count($order_obj);

        echo '<a href="#" class="wc-order-notes-toggle" data-order-id="' . esc_attr($order_id) . '" title="' . esc_attr__('Order notes', 'wc-order-notes-column') . '">';
        echo '<span class="dashicons dashicons-admin-comments"></span>';
        if ($note_count > 0) {
            echo '<span class="wc-order-notes-count">' . esc_html($note_count) . '</span>';
        }
        echo '</a>';

        echo '<div class="wc-order-notes-container" id="wc-order-notes-container-' . esc_attr($order_id) . '" style="display:none;">';
        echo '<div class="wc-order-notes-list"></div>';
        echo '<div class="wc-order-notes-add">';
        echo '<textarea class="wc-order-notes-new-note" placeholder="' . esc_attr__('Add a new note...', 'wc-order-notes-column') . '"></textarea>';
        echo '<button class="button wc-order-notes-add-note" data-order-id="' . esc_attr($order_id) . '">' . esc_html__('Add Note', 'wc-order-notes-column') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function get_order_note_count($order) {
        if (!is_a($order, 'WC_Order')) return 0;
        $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
        return count($notes);
    }

    public function get_order_notes_ajax() {
        check_ajax_referer('wc_order_notes_nonce', 'security');
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
                            __('Added by %s on %s at %s', 'wc-order-notes-column'),
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
            echo '<p class="no-notes">' . esc_html__('No notes yet.', 'wc-order-notes-column') . '</p>';
        }
        wp_send_json_success(ob_get_clean());
    }

    public function add_order_note_ajax() {
        check_ajax_referer('wc_order_notes_nonce', 'security');
        if (!current_user_can('edit_shop_orders')) wp_die(-1);

        $order_id = absint($_POST['order_id']);
        $note = wp_kses_post(trim(wp_unslash($_POST['note'])));
        if (!$order_id || empty($note)) wp_die(-1);

        $order = wc_get_order($order_id);
        if (!$order) wp_die(-1);

        $note_id = $order->add_order_note($note, false, false);
        if ($note_id) {
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
                        __('Added by %s on %s at %s', 'wc-order-notes-column'),
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

    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        if (!isset($screen->id) || (strpos($screen->id, 'shop_order') === false && $hook !== 'woocommerce_page_wc-orders')) {
            return;
        }

        wp_enqueue_style(
            'wc-order-notes-column',
            plugins_url('assets/css/admin.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin.css')
        );

        wp_enqueue_script(
            'wc-order-notes-column',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin.js'),
            true
        );

        wp_localize_script('wc-order-notes-column', 'wc_order_notes_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_order_notes_nonce'),
            'i18n' => array(
                'add_note_error' => __('Failed to add note. Please try again.', 'wc-order-notes-column'),
                'adding_note' => __('Adding...', 'wc-order-notes-column'),
                'loading' => __('Loading...', 'wc-order-notes-column')
            )
        ));
    }

    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>' . sprintf(
            esc_html__('WooCommerce Order Notes Column requires %s to be installed and active.', 'wc-order-notes-column'),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        ) . '</p></div>';
    }
}

// Initialize plugin after all plugins are loaded
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        new WC_Order_Notes_Column_HPOS();
    }
});
