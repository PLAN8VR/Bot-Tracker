<?php
/*
Plugin Name: Good Bot / Bad Bot
Description: This plugin tracks and stores a list of site visitors identified as bots. Additionally, it clears out its own database every 30 days.
Version: 0.0.2
Author: PLAN8
Author URI: https://plan8.earth
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

*/

// Create database table on plugin activation
function bot_tracker_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_agent text NOT NULL,
        ip_address varchar(100) NOT NULL,
        url varchar(100) NOT NULL,
        date_visited datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'bot_tracker_create_table');

// Add visitor to bot tracker if identified as bot
function bot_tracker_track_visitor() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $url = $_SERVER['REQUEST_URI'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Exclude certain IP addresses from being tracked
    if ($ip_address == '192.168.1.254') {
        return; // Skip tracking for this IP address
    }

    // Bot detection logic
    $is_bot = false;

    // Check if user agent or IP address matches known bot patterns
    if (strpos($user_agent, 'bot') !== false || strpos($user_agent, 'spider') !== false || strpos($user_agent, 'crawl') !== false || bot_tracker_is_good_bot($user_agent)) {
        $is_bot = true;
    }

    // Add more bot detection rules here if needed

    if ($is_bot) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bot_tracker';

        $wpdb->insert(
            $table_name,
            array(
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'url' => $url,
            )
        );
    }
}
add_action('init', 'bot_tracker_track_visitor');

// Schedule cron to clear out database every 30 days
function bot_tracker_schedule_cron() {
    if (!wp_next_scheduled('bot_tracker_clear_database')) {
        // Schedule the event to run daily, just to check if it's time to clear the database
        wp_schedule_event(time(), 'daily', 'bot_tracker_clear_database');
    }
}
add_action('wp', 'bot_tracker_schedule_cron');

// Clear database of bot records older than 30 days
function bot_tracker_clear_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';

    // Delete records older than 30 days
    $older_than = date('Y-m-d H:i:s', strtotime('-30 days'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE date_visited < %s", $older_than));
}

// Enqueue CSS directly in the plugin file
function bot_tracker_enqueue_styles() {
    wp_enqueue_style('bot-tracker-styles', plugin_dir_url(__FILE__) . 'bot-tracker-styles.css');
}
add_action('admin_menu', 'bot_tracker_enqueue_styles');

// Display list of bot visitors in WordPress admin
function bot_tracker_admin_menu() {
    add_menu_page('Bot Tracker', 'Bot Tracker', 'manage_options', 'bot-tracker', 'bot_tracker_render_page');
}
add_action('admin_menu', 'bot_tracker_admin_menu');

function bot_tracker_render_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';

    $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'id';
    $order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

    // Pagination setup
    $items_per_page = 100;
    $current_page = isset($_GET['paged']) && $_GET['paged'] > 0 ? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $query = "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT $offset, $items_per_page";
    $bot_visitors = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h2>Bot Tracker</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th><a href="?page=bot-tracker&orderby=id&order=' . ($orderby == 'id' && $order == 'ASC' ? 'DESC' : 'ASC') . '">ID</a></th>';
    echo '<th><a href="?page=bot-tracker&orderby=user_agent&order=' . ($orderby == 'user_agent' && $order == 'ASC' ? 'DESC' : 'ASC') . '">User Agent</a></th>';
    echo '<th><a href="?page=bot-tracker&orderby=ip_address&order=' . ($orderby == 'ip_address' && $order == 'ASC' ? 'DESC' : 'ASC') . '">IP Address</a></th>';
    echo '<th><a href="?page=bot-tracker&orderby=date_visited&order=' . ($orderby == 'date_visited' && $order == 'ASC' ? 'DESC' : 'ASC') . '">Date Visited</a></th>';
    echo '<th>URL</th>';
    echo '<th>Bot Type</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ($bot_visitors as $visitor) {
        $is_good = bot_tracker_is_good_bot($visitor->user_agent);
        $bot_type = $is_good ? 'Good Bot' : '** Bad Bot ??'; // Determine bot type
        $row_class = $is_good ? '' : 'not-google';
        echo '<tr class="' . $row_class . '">';
        echo '<td>' . $visitor->id . '</td>';
        echo '<td>' . $visitor->user_agent . '</td>';
        echo '<td>' . $visitor->ip_address . '</td>';
        echo '<td>' . $visitor->date_visited . '</td>';
        echo '<td>' . $visitor->url . '</td>';
        echo '<td>' . $bot_type . '</td>'; // Output bot type in the new column
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Pagination links
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $total_pages = ceil($total_items / $items_per_page);
    echo '<div class="tablenav">';
    echo '<div class="tablenav-pages">';
    echo paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => __('&laquo; Previous'),
        'next_text' => __('Next &raquo;'),
        'total' => $total_pages,
        'current' => $current_page
    ));
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

// Add settings page for "good bots" to the Bot Tracker tab
function bot_tracker_add_settings_page() {
    add_submenu_page('bot-tracker', 'Bot Tracker Settings', 'Settings', 'manage_options', 'bot-tracker-settings', 'bot_tracker_render_settings_page');
}
add_action('admin_menu', 'bot_tracker_add_settings_page');

// Render the settings page for the "good bots" list
function bot_tracker_render_settings_page() {
    ?>
    <div class="wrap">
        <h2>Bot Tracker Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('bot_tracker_good_bots_group'); ?>
            <?php do_settings_sections('bot_tracker_good_bots_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register and initialize the settings for the good bots list
function bot_tracker_register_settings() {
    register_setting('bot_tracker_good_bots_group', 'bot_tracker_good_bots', 'bot_tracker_sanitize_bot_list');
    add_settings_section('bot_tracker_good_bots_section', 'Good Bots', 'bot_tracker_good_bots_section_callback', 'bot_tracker_good_bots_settings');
    add_settings_field('bot_tracker_good_bots_field', 'List of Good Bots', 'bot_tracker_good_bots_field_callback', 'bot_tracker_good_bots_settings', 'bot_tracker_good_bots_section');
}
add_action('admin_init', 'bot_tracker_register_settings');

// Sanitize the input for the good bots list
function bot_tracker_sanitize_bot_list($input) {
    // Sanitize the input here if needed
    return $input;
}

// Callback function to render the field for the good bots list
function bot_tracker_good_bots_field_callback() {
    $good_bots = get_option('bot_tracker_good_bots');
    echo '<textarea id="bot_tracker_good_bots" name="bot_tracker_good_bots" rows="5" cols="50">' . esc_textarea($good_bots) . '</textarea>';
}

// Callback function to render the section description
function bot_tracker_good_bots_section_callback() {
    echo '<p>Enter a list of good bots, each separated by a new line.</p>';
}

// Modify the function to check if a user agent is a good bot
function bot_tracker_is_good_bot($user_agent) {
    $good_bots = get_option('bot_tracker_good_bots');
    $good_bots_list = explode("\n", $good_bots);
    $user_agent = strtolower($user_agent);

    foreach ($good_bots_list as $bot) {
        if (stripos($user_agent, trim(strtolower($bot))) !== false) {
            return true;
        }
    }

    return false;
}

// Compare the list of good bots before and after settings are saved and remove corresponding database records
function bot_tracker_compare_good_bots($old_value, $new_value) {
    if ($old_value !== $new_value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bot_tracker';

        $old_bots = explode("\n", $old_value);
        $new_bots = explode("\n", $new_value);

        // Find bots removed from the list
        $removed_bots = array_diff($old_bots, $new_bots);

        if (!empty($removed_bots)) {
            // Delete corresponding records from the database
            foreach ($removed_bots as $bot) {
                $wpdb->delete($table_name, array('user_agent' => $bot));
            }
        }
    }
}
add_action('update_option_bot_tracker_good_bots', 'bot_tracker_compare_good_bots', 10, 2);

// Register uninstall hook
register_uninstall_hook(__FILE__, 'bot_tracker_uninstall');

// Uninstall function to cleanup the database
function bot_tracker_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';

    // Drop the database table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // Clear scheduled cron events
    wp_clear_scheduled_hook('bot_tracker_clear_database');
}
?>
