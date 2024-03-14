<?php
/*
Plugin Name: Bot Tracker
Description: This plugin tracks and stores a list of site visitors identified as bots. Additionally, it clears out it's own database every 30 days.
Version: 0.1
Author: Andrew Wood
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
        date_visited datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'bot_tracker_create_table' );

// Add visitor to bot tracker if identified as bot
function bot_tracker_track_visitor() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Bot detection logic
    $is_bot = false;

    // Check if user agent or IP address matches known bot patterns
    if (strpos($user_agent, 'bot') !== false || strpos($user_agent, 'spider') !== false || strpos($user_agent, 'crawl') !== false) {
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
            )
        );
    }
}
add_action( 'init', 'bot_tracker_track_visitor' );

// Schedule cron to clear out database every 30 days
function bot_tracker_schedule_cron() {
    if ( ! wp_next_scheduled( 'bot_tracker_clear_database' ) ) {
        // Schedule the event to run daily, just to check if it's time to clear the database
        wp_schedule_event( time(), 'daily', 'bot_tracker_clear_database' );
    }
}
add_action( 'wp', 'bot_tracker_schedule_cron' );

// Clear database of bot records older than 30 days
function bot_tracker_clear_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';

    // Calculate date 30 days ago
    $older_than = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

    // Delete records older than 30 days
    $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE date_visited < %s", $older_than ) );
}

// Display list of bot visitors in WordPress admin
function bot_tracker_admin_menu() {
    add_menu_page( 'Bot Tracker', 'Bot Tracker', 'manage_options', 'bot-tracker', 'bot_tracker_list_page' );
}
add_action( 'admin_menu', 'bot_tracker_admin_menu' );

function bot_tracker_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bot_tracker';

    $bot_visitors = $wpdb->get_results( "SELECT * FROM $table_name" );

    echo '<div class="wrap">';
    echo '<h2>Bot Tracker</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>User Agent</th><th>IP Address</th><th>Date Visited</th></tr></thead>';
    echo '<tbody>';
    foreach ($bot_visitors as $visitor) {
        echo '<tr>';
        echo '<td>' . $visitor->id . '</td>';
        echo '<td>' . $visitor->user_agent . '</td>';
        echo '<td>' . $visitor->ip_address . '</td>';
        echo '<td>' . $visitor->date_visited . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
