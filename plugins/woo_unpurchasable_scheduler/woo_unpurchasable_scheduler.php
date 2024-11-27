<?php
/*
Plugin Name: woo unpurchasable scheduler
Description: Toggles purchasable status of products in selected categories based on scheduled dates and allows instant reset. Purges LiteSpeed Cache accordingly.
Version: 1.0
Author: FrankenBubble
Text Domain: scheduled-purchasable
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu
add_action( 'admin_menu', 'spp_add_admin_menu' );

function spp_add_admin_menu() {
    add_options_page(
        __( 'Scheduled Purchasable Settings', 'scheduled-purchasable' ),
        __( 'Scheduled Purchasable', 'scheduled-purchasable' ),
        'manage_options',
        'scheduled-purchasable',
        'spp_options_page'
    );
}

// Register settings with validation
add_action( 'admin_init', 'spp_settings_init' );

function spp_settings_init() {
    register_setting( 'spp_settings_group', 'spp_settings', 'spp_settings_validate' );

    add_settings_section(
        'spp_settings_section',
        __( 'Scheduling Settings', 'scheduled-purchasable' ),
        'spp_settings_section_callback',
        'spp_settings_group'
    );

    add_settings_field(
        'spp_start_datetime',
        __( 'Start Date/Time', 'scheduled-purchasable' ),
        'spp_start_datetime_render',
        'spp_settings_group',
        'spp_settings_section'
    );

    add_settings_field(
        'spp_end_datetime',
        __( 'End Date/Time', 'scheduled-purchasable' ),
        'spp_end_datetime_render',
        'spp_settings_group',
        'spp_settings_section'
    );

    add_settings_field(
        'spp_categories',
        __( 'Select Categories', 'scheduled-purchasable' ),
        'spp_categories_render',
        'spp_settings_group',
        'spp_settings_section'
    );

    add_settings_field(
        'spp_enable_logging',
        __( 'Enable Logging', 'scheduled-purchasable' ),
        'spp_enable_logging_render',
        'spp_settings_group',
        'spp_settings_section'
    );
}

function spp_settings_validate( $input ) {
    $validated = array();

    // Validate start datetime
    if ( isset( $input['spp_start_datetime'] ) ) {
        $validated['spp_start_datetime'] = sanitize_text_field( $input['spp_start_datetime'] );
    }

    // Validate end datetime
    if ( isset( $input['spp_end_datetime'] ) ) {
        $validated['spp_end_datetime'] = sanitize_text_field( $input['spp_end_datetime'] );
    }

    // Validate categories
    if ( isset( $input['spp_categories'] ) && is_array( $input['spp_categories'] ) ) {
        $validated['spp_categories'] = array_map( 'absint', $input['spp_categories'] );
    } else {
        $validated['spp_categories'] = array();
    }

    // Validate logging option
    $validated['spp_enable_logging'] = isset( $input['spp_enable_logging'] ) ? 1 : 0;

    return $validated;
}

function spp_start_datetime_render() {
    $options = get_option( 'spp_settings' );
    ?>
    <input type="datetime-local" name="spp_settings[spp_start_datetime]" value="<?php echo isset( $options['spp_start_datetime'] ) ? esc_attr( $options['spp_start_datetime'] ) : ''; ?>">
    <?php
}

function spp_end_datetime_render() {
    $options = get_option( 'spp_settings' );
    ?>
    <input type="datetime-local" name="spp_settings[spp_end_datetime]" value="<?php echo isset( $options['spp_end_datetime'] ) ? esc_attr( $options['spp_end_datetime'] ) : ''; ?>">
    <?php
}

function spp_categories_render() {
    $options             = get_option( 'spp_settings' );
    $selected_categories = isset( $options['spp_categories'] ) ? $options['spp_categories'] : array();

    $args       = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    );
    $categories = get_terms( $args );

    foreach ( $categories as $category ) {
        ?>
        <label>
            <input type="checkbox" name="spp_settings[spp_categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( $category->term_id, $selected_categories ) ); ?>>
            <?php echo esc_html( $category->name ); ?>
        </label><br>
        <?php
    }
}

function spp_enable_logging_render() {
    $options = get_option( 'spp_settings' );
    ?>
    <input type="checkbox" name="spp_settings[spp_enable_logging]" value="1" <?php checked( isset( $options['spp_enable_logging'] ) && $options['spp_enable_logging'] == 1 ); ?>>
    <label for="spp_enable_logging"><?php _e( 'Enable plugin logging', 'scheduled-purchasable' ); ?></label>
    <?php
}

function spp_settings_section_callback() {
    $timezone_string = wp_timezone_string();
    echo sprintf(
        __( 'Configure the start and end date/time in the format YYYY-MM-DD HH:MM, select categories, and enable logging if needed. Current WordPress time zone: %s.', 'scheduled-purchasable' ),
        esc_html( $timezone_string )
    );
}

function spp_options_page() {
    ?>
    <h2><?php echo esc_html__( 'Scheduled Purchasable Settings', 'scheduled-purchasable' ); ?></h2>
    <?php settings_errors( 'spp_messages' ); ?>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'spp_settings_group' );
        do_settings_sections( 'spp_settings_group' );
        submit_button();
        ?>
    </form>
    <form method="post" style="margin-top: 20px;">
        <?php wp_nonce_field( 'spp_manual_action', 'spp_manual_action_nonce' ); ?>
        <input type="submit" name="spp_make_purchasable_now" class="button button-primary" value="<?php esc_attr_e( 'Make Purchasable Now', 'scheduled-purchasable' ); ?>">
        <input type="submit" name="spp_make_unpurchasable_now" class="button button-secondary" value="<?php esc_attr_e( 'Make Unpurchasable Now', 'scheduled-purchasable' ); ?>">
    </form>
    <?php
}

// Handle manual actions
add_action( 'admin_init', 'spp_handle_manual_actions' );

function spp_handle_manual_actions() {
    if ( isset( $_POST['spp_make_purchasable_now'] ) && check_admin_referer( 'spp_manual_action', 'spp_manual_action_nonce' ) ) {
        spp_make_products_purchasable_or_unpurchasable( true );
        add_settings_error( 'spp_messages', 'spp_message', __( 'Products have been set to purchasable immediately.', 'scheduled-purchasable' ), 'updated' );
    }

    if ( isset( $_POST['spp_make_unpurchasable_now'] ) && check_admin_referer( 'spp_manual_action', 'spp_manual_action_nonce' ) ) {
        spp_make_products_purchasable_or_unpurchasable( false );
        add_settings_error( 'spp_messages', 'spp_message', __( 'Products have been set to unpurchasable immediately.', 'scheduled-purchasable' ), 'updated' );
    }
}

// Provide admin notices
add_action( 'admin_notices', 'spp_admin_notices' );

function spp_admin_notices() {
    settings_errors( 'spp_messages' );
}

// Define the function to update purchasable status
function spp_make_products_purchasable_or_unpurchasable( $purchasable ) {
    $options      = get_option( 'spp_settings' );
    $category_ids = isset( $options['spp_categories'] ) ? $options['spp_categories'] : array();

    if ( ! empty( $category_ids ) ) {
        $products = spp_get_products_by_categories( $category_ids );

        foreach ( $products as $product_id ) {
            $status = $purchasable ? 'true' : 'false';
            update_post_meta( $product_id, '_spp_purchasable_status', $status );
            $action = $purchasable ? 'purchasable' : 'unpurchasable';
            spp_custom_log( 'Product ID ' . $product_id . ' set to ' . $action . '.' );

            // Purge cache for the product URL
            $product_url = get_permalink( $product_id );
            if ( $product_url ) {
                do_action( 'litespeed_purge_url', $product_url );
                spp_custom_log( 'Purged cache for product URL: ' . $product_url );
            } else {
                spp_custom_log( 'Failed to get URL for product ID: ' . $product_id );
            }
        }

        // Purge cache for categories and shop page
        spp_purge_cache_for_categories();
    }
}

// Hook into settings update to schedule events
add_action( 'update_option_spp_settings', 'spp_schedule_events', 10, 2 );

function spp_schedule_events( $old_value, $new_value ) {
    // Clear existing scheduled events
    wp_clear_scheduled_hook( 'spp_make_products_purchasable' );
    wp_clear_scheduled_hook( 'spp_make_products_unpurchasable' );

    // Get the WordPress time zone
    $timezone = new DateTimeZone( wp_timezone_string() );

    // Parse start and end times in WordPress time zone
    $start_datetime = DateTime::createFromFormat( 'Y-m-d\TH:i', $new_value['spp_start_datetime'], $timezone );
    $end_datetime   = DateTime::createFromFormat( 'Y-m-d\TH:i', $new_value['spp_end_datetime'], $timezone );

    // Validate start and end dates
    if ( $start_datetime && $end_datetime ) {
        if ( $start_datetime >= $end_datetime ) {
            spp_custom_log( 'Start date must be before end date.' );
            add_settings_error( 'spp_messages', 'spp_message', __( 'Start date must be before end date.', 'scheduled-purchasable' ), 'error' );
            return;
        }
    } else {
        spp_custom_log( 'Invalid start or end date.' );
        add_settings_error( 'spp_messages', 'spp_message', __( 'Invalid start or end date.', 'scheduled-purchasable' ), 'error' );
        return;
    }

    if ( $start_datetime ) {
        $start_timestamp = $start_datetime->getTimestamp();
        spp_custom_log( 'Parsed start datetime: ' . $start_datetime->format( 'Y-m-d H:i:s' ) . ' (Timestamp: ' . $start_timestamp . ')' );
    }

    if ( $end_datetime ) {
        $end_timestamp = $end_datetime->getTimestamp();
        spp_custom_log( 'Parsed end datetime: ' . $end_datetime->format( 'Y-m-d H:i:s' ) . ' (Timestamp: ' . $end_timestamp . ')' );
    }

    // Schedule start event
    if ( isset( $start_timestamp ) && $start_timestamp > time() ) {
        wp_schedule_single_event( $start_timestamp, 'spp_make_products_purchasable' );
        spp_custom_log( 'Scheduled purchasable event at ' . wp_date( 'Y-m-d H:i:s', $start_timestamp ) );
    } else {
        spp_custom_log( 'Start timestamp is not set or in the past. Event not scheduled.' );
    }

    // Schedule end event
    if ( isset( $end_timestamp ) && $end_timestamp > time() ) {
        wp_schedule_single_event( $end_timestamp, 'spp_make_products_unpurchasable' );
        spp_custom_log( 'Scheduled unpurchasable event at ' . wp_date( 'Y-m-d H:i:s', $end_timestamp ) );
    } else {
        spp_custom_log( 'End timestamp is not set or in the past. Event not scheduled.' );
    }
}

// Add action hooks for scheduled events
add_action( 'spp_make_products_purchasable', 'spp_scheduled_make_products_purchasable' );
add_action( 'spp_make_products_unpurchasable', 'spp_scheduled_make_products_unpurchasable' );

// Scheduled start event callback
function spp_scheduled_make_products_purchasable() {
    spp_custom_log( 'Executing scheduled spp_make_products_purchasable at ' . wp_date( 'Y-m-d H:i:s' ) );
    spp_make_products_purchasable_or_unpurchasable( true );
}

// Scheduled end event callback
function spp_scheduled_make_products_unpurchasable() {
    spp_custom_log( 'Executing scheduled spp_make_products_unpurchasable at ' . wp_date( 'Y-m-d H:i:s' ) );
    spp_make_products_purchasable_or_unpurchasable( false );
}

// Filter to control purchasable status
add_filter( 'woocommerce_is_purchasable', 'spp_control_purchasable_status', 10, 2 );

function spp_control_purchasable_status( $is_purchasable, $product ) {
    $status = get_post_meta( $product->get_id(), '_spp_purchasable_status', true );

    if ( $status === 'true' ) {
        return true;
    } elseif ( $status === 'false' ) {
        return false;
    }

    // If status is not set, return the original value
    return $is_purchasable;
}

// Purge cache function using litespeed_purge_url action
function spp_purge_litespeed_cache( $product_id ) {
    $product_url = get_permalink( $product_id );
    if ( $product_url ) {
        do_action( 'litespeed_purge_url', $product_url );
        spp_custom_log( 'Purged cache for product URL: ' . $product_url );
    } else {
        spp_custom_log( 'Failed to get URL for product ID: ' . $product_id );
    }
}

// Function to purge cache for categories and shop page, including child categories
function spp_purge_cache_for_categories() {
    spp_custom_log( 'Starting cache purging process. Current user: ' . wp_get_current_user()->user_login );

    $options      = get_option( 'spp_settings' );
    $category_ids = isset( $options['spp_categories'] ) ? $options['spp_categories'] : array();

    spp_custom_log( 'Selected category IDs for cache purging: ' . implode( ', ', $category_ids ) );

    if ( ! empty( $category_ids ) ) {
        // Get all child categories of selected categories
        $all_category_ids = spp_get_all_child_categories( $category_ids );

        spp_custom_log( 'All category IDs (including children) for cache purging: ' . implode( ', ', $all_category_ids ) );

        // Purge cache for category URLs
        foreach ( $all_category_ids as $category_id ) {
            $category_link = get_term_link( (int) $category_id, 'product_cat' );
            if ( ! is_wp_error( $category_link ) ) {
                do_action( 'litespeed_purge_url', $category_link );
                spp_custom_log( 'Purged cache for category URL: ' . $category_link );
            } else {
                spp_custom_log( 'Failed to get URL for category ID: ' . $category_id );
            }
        }

        // Purge shop page
        $shop_url = get_permalink( wc_get_page_id( 'shop' ) );
        if ( $shop_url ) {
            do_action( 'litespeed_purge_url', $shop_url );
            spp_custom_log( 'Purged cache for shop page URL: ' . $shop_url );
        } else {
            spp_custom_log( 'Failed to get shop page URL.' );
        }
    } else {
        spp_custom_log( 'No category IDs provided for cache purging.' );
    }
}

// Helper function to get all child categories of selected categories
function spp_get_all_child_categories( $parent_category_ids ) {
    $all_category_ids = $parent_category_ids;

    foreach ( $parent_category_ids as $parent_id ) {
        $children = get_term_children( $parent_id, 'product_cat' );
        if ( ! is_wp_error( $children ) ) {
            $all_category_ids = array_merge( $all_category_ids, $children );
        }
    }

    // Remove duplicates and return
    $all_category_ids = array_unique( $all_category_ids );
    return $all_category_ids;
}

// Helper function to get products by categories with transient caching
function spp_get_products_by_categories( $category_ids ) {
    $transient_key = 'spp_products_' . md5( implode( '_', $category_ids ) );
    $product_ids = get_transient( $transient_key );

    if ( false === $product_ids ) {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $category_ids,
                    'include_children' => true, // Include child categories
                ),
            ),
        );

        $query = new WP_Query( $args );
        $product_ids = $query->posts;
        set_transient( $transient_key, $product_ids, HOUR_IN_SECONDS );
    }

    return $product_ids;
}

// Custom logging function
function spp_custom_log( $message ) {
    $options = get_option( 'spp_settings' );

    // Check if logging is enabled
    if ( isset( $options['spp_enable_logging'] ) && $options['spp_enable_logging'] == 1 ) {
        // Define the log file path
        $log_file = WP_CONTENT_DIR . '/spp_plugin_logs.log';

        // Create a timestamp using WordPress time zone
        $time_stamp = wp_date( 'Y-m-d H:i:s' );

        // Format the log message
        $log_message = '[' . $time_stamp . '] ' . $message . PHP_EOL;

        // Append the log message to the log file
        file_put_contents( $log_file, $log_message, FILE_APPEND );
    }
}

// Activation and deactivation hooks
register_activation_hook( __FILE__, 'spp_plugin_activate' );
register_deactivation_hook( __FILE__, 'spp_plugin_deactivate' );

function spp_plugin_activate() {
    // Purge cache on activation
    spp_purge_cache_for_categories();
    spp_custom_log( 'Plugin activated and cache purged' );
}

function spp_plugin_deactivate() {
    // Clear scheduled events on deactivation
    wp_clear_scheduled_hook( 'spp_make_products_purchasable' );
    wp_clear_scheduled_hook( 'spp_make_products_unpurchasable' );

    spp_custom_log( 'Plugin deactivated and scheduled events cleared' );
}