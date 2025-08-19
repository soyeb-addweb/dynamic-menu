<?php
/*
 * Copyright (c) 2025. David Saul Rodriguez <david@enyutech.com>
 *
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of David Saul Rodriguez and his suppliers,
 * if any. The intellectual and technical concepts contained
 * herein are proprietary to David Saul Rodriguez and his
 * suppliers and may be covered by U.S. and Foreign Patents,
 * patents in process, and are protected by trade secret or copyright law.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from David Saul Rodriguez.
 */

/**
 * Plugin Name: Dynamic Practice Areas Menu and Widget
 * Plugin URI: https://enyutech.com/shop/wordpress/plugins/ec-dynamic-practice-areas-menu
 * Description: Creates a dynamically changing menu and sidebar widget based on city and practice area page selection. This version of the plugin is built specifically for Ever Convert, Inc and is Elementor Compatible.
 * Version: 0.3.0
 * Author: Enyutech LLC
 * Author URI: https://enyutech.com
 * Text Domain: dynamic-practice-areas-menu
 * Requires at least: 6.0
 * Tested up to: 6.8.1
 * Requires PHP: 8.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Include the plugin update checker library
$puc_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require_once $puc_path;
}

// Include template functions
require_once plugin_dir_path(__FILE__) . 'includes/template-functions.php';

class Dynamic_Practice_Areas_Menu
{

    private $product_id = 'ec_dynamic_practice_areas';
    private $api_url = 'https://enyutech.com/wp-json/plugin-license/v1/';
    private $verify_ssl = false; // Set to true in production
    private $update_checker = null;

    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        // Set SSL verification based on environment
        $this->verify_ssl = $this->is_production_environment();

        // Add license-related hooks (these are needed for activation/deactivation, etc.)
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_activate_license', array($this, 'activate_license'));
        add_action('wp_ajax_deactivate_license', array($this, 'deactivate_license'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_init', array($this, 'check_license'));
        register_activation_hook(__FILE__, array($this, 'plugin_activated'));
        add_action('deactivate_' . plugin_basename(__FILE__), 'dynamic_practice_areas_deactivated');
        register_uninstall_hook(__FILE__, 'dynamic_practice_areas_uninstall');
        add_action('init', array($this, 'setup_update_checker'));
        add_action('init', array($this, 'handle_remote_license_deactivation'));
        add_action('init', array($this, 'register_menu_locations'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_elementor_editor_preview_styles'));

        // Register license-independent actions (admin actions for license activation, etc.) are already added above.
        // Now, register front-end functionality only if license is valid.
        if ($this->is_license_valid()) {
            add_action('wp_ajax_get_sub_practice_areas', 'get_sub_practice_areas_callback');
            add_action('wp_ajax_nopriv_get_sub_practice_areas', 'get_sub_practice_areas_callback');
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_footer', array($this, 'add_menu_js'));
            add_action('rest_api_init', array($this, 'register_api_endpoints'));
            add_action('widgets_init', array($this, 'register_widgets'));
            add_action('init', array($this, 'register_blocks'));
            add_action('init', 'enyutech_register_anchor_text_meta');
            add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
            add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));
        } else {
            // Optionally, for front-end pages, you could display a notice or disable functionality.
            add_action('wp_footer', function () {
                echo '<p style="color:red; text-align:center;">Dynamic Practice Areas functionality is disabled because your license is not activated.</p>';
            });
        }
    }

    /**
     * Determine if we're in a production environment
     *
     * @return bool True if in production, false if in development
     */
    private function is_production_environment()
    {
        // Check for common development domains
        $hostname = $_SERVER['HTTP_HOST'] ?? '';
        $dev_domains = [
            'localhost',
            '.test',
            '.local',
            '.dev',
            '127.0.0.1',
            '.example'
        ];

        // Check if hostname contains any development domains
        foreach ($dev_domains as $dev_domain) {
            if (strpos($hostname, $dev_domain) !== false) {
                return false; // Development environment
            }
        }

        // If WP_DEBUG is defined and true, consider it a development environment
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            return false;
        }

        return true; // Production environment
    }

    /**
     * Check if the plugin license is valid.
     *
     * @return bool True if the license is active and valid, false otherwise.
     */
    private function is_license_valid()
    {
        $status = get_option($this->product_id . '_license_status', '');
        // Optionally, check for expiration as well.
        // return ($status === 'valid');
        return true;
    }

    /**
     * Enqueue editor preview styles for Elementor
     */
    public function enqueue_elementor_editor_preview_styles()
    {
        // Only load in admin area
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            wp_enqueue_style(
                'dynamic-practice-areas-editor-preview',
                plugin_dir_url(__FILE__) . 'css/elementor-editor-styles.css',
                array(),
                '0.3.0'
            );
        }
    }

    /**
     * AJAX handler for getting sub-practice areas
     */
    function get_sub_practice_areas_callback()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
            wp_send_json_error('Invalid nonce');
        }

        $practice_area_id = isset($_POST['practice_area_id']) ? intval($_POST['practice_area_id']) : 0;
        $all_children = get_pages(['child_of' => $practice_area_id]);
        $city_slug = isset($_POST['city_slug']) ? sanitize_text_field($_POST['city_slug']) : '';
        $practice_area_slug = isset($_POST['practice_area_slug']) ? sanitize_text_field($_POST['practice_area_slug']) : '';

        if (!$practice_area_id) {
            wp_send_json_error('Invalid practice area ID');
        }

        // Get the practice area details
        $practice_area = get_post($practice_area_id);
        if (!$practice_area) {
            wp_send_json_error('Practice area not found');
        }

        // Get sub-practice areas (child pages of this practice area)
        $sub_practice_areas = get_pages(array(
            'child_of' => $practice_area_id,
            'parent' => $practice_area_id,
            'sort_column' => 'menu_order,post_title',
            'post_status' => 'publish',
            'hierarchical' => 1,
            'depth' => 1
        ));

        $sub_practice_area_data = array();
        foreach ($sub_practice_areas as $sub_practice_area) {
            // Get anchor text if available
            $anchor_text = get_post_meta($sub_practice_area->ID, 'anchor_text', true);

            $sub_practice_area_data[] = array(
                'id' => $sub_practice_area->ID,
                'title' => $sub_practice_area->post_title,
                'url' => get_permalink($sub_practice_area->ID),
                'slug' => $sub_practice_area->post_name,
                'anchor_text' => $anchor_text
            );
        }

        wp_send_json_success(array(
            'success' => true,
            'practice_area_id' => $practice_area_id,
            'practice_area_title' => $practice_area->post_title,
            'practice_area_slug' => $practice_area_slug,
            'sub_practice_areas' => $sub_practice_area_data
        ));
    }

    public function register_settings()
    {
        register_setting('dynamic_practice_areas_settings', 'dynamic_practice_areas_uppercase_menu');
        register_setting('dynamic_practice_areas_settings', 'dynamic_practice_areas_default_city');
        register_setting('dynamic_practice_areas_settings', 'dynamic_practice_areas_state_layer_enabled');
        register_setting('dynamic_practice_areas_settings', 'dynamic_practice_areas_default_state');
    }

    /**
     * Register menu locations for the plugin
     */
    public function register_menu_locations()
    {
        register_nav_menu('primary', __('Primary Menu', 'dynamic-practice-areas-menu'));
    }

    /**
     * Add admin menu for license management
     */
    public function add_admin_menu()
    {
        // Add a top-level menu
        add_menu_page(
            'Dynamic Practice Areas',    // Page title
            'Practice Areas',           // Menu title
            'manage_options',           // Capability
            $this->product_id,                  // Menu slug
            array($this, 'render_admin_page'),  // Callback
            'dashicons-location',        // Icon
            30                          // Position
        );

        // Add submenu items - main page
        add_submenu_page(
            $this->product_id,   // Parent slug
            'Settings',                 // Page title
            'Settings',                 // Menu title
            'manage_options',           // Capability
            $this->product_id,   // Menu slug (same as parent)
            array($this, 'render_admin_page')
        );

        // Add license page as submenu
        add_submenu_page(
            $this->product_id,   // Parent slug
            'License',                  // Page title
            'License',                  // Menu title
            'manage_options',           // Capability
            $this->product_id . '_license', // Menu slug
            array($this, 'render_license_page')
        );
    }

    /**
     * Handle remote license deactivation requests
     */
    public function handle_remote_license_deactivation()
    {
        if (isset($_GET['enyutech_license_action']) && $_GET['enyutech_license_action'] === 'remote_deactivate') {
            // Verify required parameters
            if (!isset($_GET['license_key']) || !isset($_GET['instance']) || !isset($_GET['product_id']) || !isset($_GET['nonce'])) {
                wp_send_json_error(array('message' => 'Missing _required parameters'));
                exit;
            }

            $license_key = sanitize_text_field($_GET['license_key']);
            $product_id = sanitize_text_field($_GET['product_id']);
            $instance = sanitize_text_field($_GET['instance']);
            $nonce = sanitize_text_field($_GET['nonce']);
            $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : 'unknown';

            // Get the stored license data
            $license_status = get_option($this->product_id . '_license_status');

            // Check if we have a valid license to deactivate
            if ($license_status === 'valid') {
                // Clear the license data but retain the key for potential reactivation
                update_option($this->product_id . '_license_status', 'inactive');

                // Update stored activation count if provided
                if (isset($_GET['activation_count'])) {
                    $activation_count = intval($_GET['activation_count']);
                    update_option($this->product_id . '_activation_count', $activation_count);
                }

                // Return a success response
                wp_send_json_success(array(
                    'message' => 'License deactivated successfully',
                    'product_id' => $this->product_id,
                    'site_url' => home_url(),
                    'instance' => $instance,
                    'status' => 'inactive',
                    'timestamp' => current_time('timestamp')
                ));
                exit;
            } else {
                // If we get here, return an error
                wp_send_json_error(array(
                    'message' => 'License deactivation failed',
                    'reason' => 'Invalid license status: ' . $license_status,
                    'product_id' => $this->product_id,
                    'site_url' => home_url()
                ));
                exit;
            }
        }
    }

    /**
     * Render the license page
     */
    public function render_license_page()
    {
        $license_key = get_option($this->product_id . '_license_key', '');
        $license_status = get_option($this->product_id . '_license_status', '');
        $activation_count = get_option($this->product_id . '_activation_count', 0);
        $max_sites = get_option($this->product_id . '_max_sites', 0);
?>
        <div class="wrap">
            <h1>License Activation</h1>
            <form id="license-form" method="post">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">License Key</th>
                            <td>
                                <input type="text" id="license-key" name="license_key" class="regular-text" value="<?php
                                                                                                                    echo esc_attr($license_key); ?>" <?php
                                                                                                                                                        echo ($license_status === 'valid') ? 'readonly' : ''; ?>>
                                <p class="description">Enter your license key to activate this plugin.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">License Status</th>
                            <td>
                                <?php
                                if ($license_status === 'valid') : ?>
                                    <span style="color: green; font-weight: bold;">Active</span>
                                <?php
                                elseif ($license_status === 'invalid') : ?>
                                    <span style="color: red; font-weight: bold;">Invalid</span>
                                <?php
                                else : ?>
                                    <span style="color: orange; font-weight: bold;">Inactive</span>
                                <?php
                                endif; ?>
                            </td>
                        </tr>
                        <?php
                        if ($license_status === 'valid') : ?>
                            <tr>
                                <th scope="row">Activation Info</th>
                                <td>
                                    <p>This license is active on <strong><?php
                                                                            echo intval($activation_count); ?></strong> site(s).</p>
                                    <?php
                                    if ($max_sites > 0) : ?>
                                        <p>Maximum allowed sites: <strong><?php
                                                                            echo intval($max_sites); ?></strong></p>
                                    <?php
                                    endif; ?>
                                </td>
                            </tr>
                        <?php
                        endif; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <?php
                    if ($license_status !== 'valid') : ?>
                        <button id="activate-license" class="button button-primary">Activate License</button>
                    <?php
                    else : ?>
                        <button id="deactivate-license" class="button">Deactivate License</button>
                    <?php
                    endif; ?>
                    <span id="license-message" style="display: none; margin-left: 10px;"></span>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Activate license
                $('#activate-license').on('click', function(e) {
                    e.preventDefault();

                    var licenseKey = $('#license-key').val().trim();
                    if (!licenseKey) {
                        alert('Please enter a license key.');
                        return;
                    }

                    $(this).prop('disabled', true).text('Activating...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'activate_license',
                            license_key: licenseKey,
                            nonce: '<?php echo wp_create_nonce($this->product_id . '_license_nonce'); ?>'
                        },
                        beforeSend: function(xhr) {
                            // For development only
                            xhr.setRequestHeader('X-Local-Development', 'true');
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#license-message').html('<span style="color: green;">' + response.data.message + '</span>').show();
                                window.location.reload();
                            } else {
                                $('#license-message').html('<span style="color: red;">' + response.data.message + '</span>').show();
                                $('#activate-license').prop('disabled', false).text('Activate License');
                            }
                        },
                        error: function() {
                            $('#license-message').html('<span style="color: red;">An error occurred while activating the license.</span>').show();
                            $('#activate-license').prop('disabled', false).text('Activate License');
                        }
                    });
                });

                // Deactivate license
                $('#deactivate-license').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Are you sure you want to deactivate your license? The plugin will no longer be functional.')) {
                        return;
                    }

                    $(this).prop('disabled', true).text('Deactivating...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'deactivate_license',
                            nonce: '<?php echo wp_create_nonce($this->product_id . '_license_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#license-message').html('<span style="color: green;">' + response.data.message + '</span>').show();
                                window.location.reload();
                            } else {
                                $('#license-message').html('<span style="color: red;">' + response.data.message + '</span>').show();
                                $('#deactivate-license').prop('disabled', false).text('Deactivate License');
                            }
                        },
                        error: function() {
                            $('#license-message').html('<span style="color: red;">An error occurred while deactivating the license.</span>').show();
                            $('#deactivate-license').prop('disabled', false).text('Deactivate License');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler for license activation
     */
    public function activate_license()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->product_id . '_license_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (empty($license_key)) {
            wp_send_json_error(array('message' => 'Please enter a valid license key.'));
        }

        // Site URL for tracking
        $site_url = home_url();

        // Make API request to activate license
        $response = wp_remote_post($this->api_url . 'activate', array(
            'timeout' => 15,
            'sslverify' => $this->verify_ssl,
            'body' => array(
                'license_key' => $license_key,
                'site_url' => $site_url,
                'product_id' => $this->product_id
            )
        ));

        // Check for API errors
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection error: ' . $response->get_error_message()));
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        if (!isset($license_data->success)) {
            wp_send_json_error(array('message' => 'Invalid response from license server.'));
        }

        if ($license_data->success) {
            // Save license data
            update_option($this->product_id . '_license_key', $license_key);
            update_option($this->product_id . '_license_status', 'valid');
            update_option(
                $this->product_id . '_activation_count',
                isset($license_data->activation_count) ? intval($license_data->activation_count) : 1
            );
            update_option(
                $this->product_id . '_max_sites',
                isset($license_data->max_sites) ? intval($license_data->max_sites) : 0
            );
            update_option(
                $this->product_id . '_expiration',
                isset($license_data->expiration) ? sanitize_text_field($license_data->expiration) : ''
            );

            // Initialize the update checker now that we have a valid license
            $this->setup_update_checker();

            wp_send_json_success(array('message' => 'License activated successfully!'));
        } else {
            update_option($this->product_id . '_license_status', 'invalid');

            $error_message = isset($license_data->message) ? $license_data->message : 'License activation failed.';
            wp_send_json_error(array('message' => $error_message));
        }
    }

    /**
     * Set up the plugin update checker
     */
    public function setup_update_checker()
    {
        // Only disable SSL verification if explicitly in development
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            add_filter('http_request_args', function ($args) {
                $args['sslverify'] = false;
                return $args;
            });
        }

        $license_key = get_option($this->product_id . '_license_key', '');
        $license_status = get_option($this->product_id . '_license_status', '');

        if ($license_status == 'valid' && !empty($license_key)) {
            $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5p5\PucFactory::buildUpdateChecker(
                $this->api_url . 'updates',
                __FILE__,
                $this->product_id
            );

            // Add the license key to the query
            $this->update_checker->addQueryArgFilter(array($this, 'filter_update_checks'));
        }
    }

    /**
     * Add the license key to update checks
     */
    public function filter_update_checks($query_args)
    {
        $license_key = get_option($this->product_id . '_license_key', '');

        if (!empty($license_key)) {
            $query_args['license_key'] = $license_key;
            $query_args['site_url'] = home_url();
            $query_args['product_id'] = $this->product_id;
        }

        return $query_args;
    }

    /**
     * AJAX handler for license deactivation
     */
    public function deactivate_license()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->product_id . '_license_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        $license_key = get_option($this->product_id . '_license_key', '');

        if (empty($license_key)) {
            wp_send_json_error(array('message' => 'No license key found.'));
        }

        // Site URL for tracking
        $site_url = home_url();

        // Make API request to deactivate license
        $response = wp_remote_post($this->api_url . 'deactivate', array(
            'timeout' => 15,
            'sslverify' => $this->verify_ssl,
            'body' => array(
                'license_key' => $license_key,
                'site_url' => $site_url,
                'product_id' => $this->product_id
            )
        ));

        // Check for API errors
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection error: ' . $response->get_error_message()));
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        // Even if the API call fails, we still want to deactivate locally
        update_option($this->product_id . '_license_status', '');

        wp_send_json_success(array('message' => 'License deactivated successfully!'));
    }

    /**
     * Check license status periodically
     */
    public function check_license()
    {
        // Only check once a week
        $last_check = get_option($this->product_id . '_last_license_check');

        if ($last_check && (time() - $last_check < 604800)) {
            return;
        }

        update_option($this->product_id . '_last_license_check', time());

        $license_key = get_option($this->product_id . '_license_key', '');
        $license_status = get_option($this->product_id . '_license_status', '');

        if (empty($license_key) || $license_status !== 'valid') {
            return;
        }

        // Site URL for tracking
        $site_url = home_url();

        // Make API request to check license
        $response = wp_remote_post($this->api_url . 'check', array(
            'timeout' => 15,
            'sslverify' => $this->verify_ssl,
            'body' => array(
                'license_key' => $license_key,
                'site_url' => $site_url,
                'product_id' => $this->product_id
            )
        ));

        // If API is unreachable, keep using the plugin
        if (is_wp_error($response)) {
            return;
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        if (!isset($license_data->success)) {
            return;
        }

        if ($license_data->success) {
            // Update license data
            update_option($this->product_id . '_license_status', 'valid');
            update_option(
                $this->product_id . '_activation_count',
                isset($license_data->activation_count) ? intval($license_data->activation_count) : 1
            );
            update_option(
                $this->product_id . '_max_sites',
                isset($license_data->max_sites) ? intval($license_data->max_sites) : 0
            );
            update_option(
                $this->product_id . '_expiration',
                isset($license_data->expiration) ? sanitize_text_field($license_data->expiration) : ''
            );
        } else {
            // License is no longer valid
            update_option($this->product_id . '_license_status', 'invalid');
        }
    }

    /**
     * Show admin notices
     */
    public function admin_notices()
    {
        // Only show on our pages or dashboard
        $screen = get_current_screen();
        if (!in_array(
            $screen->id,
            array(
                'dashboard',
                'toplevel_page_dynamic-practice-areas',
                'practice-areas_page_dynamic-practice-areas-license'
            )
        )) {
            return;
        }

        // Add reactivation notice
        if (get_option($this->product_id . '_show_reactivation_notice') === '1') {
        ?>
            <div class="notice notice-warning is-dismissible">
                <p>The Dynamic Practice Areas plugin has been reactivated and requires license validation. Please <a
                        href="<?php
                                echo admin_url('admin.php?page=dynamic-practice-areas-license'); ?>">validate your
                        license</a> to continue using all features.</p>
            </div>
        <?php
            // Remove the notice flag after displaying once
            delete_option($this->product_id . '_show_reactivation_notice');
        }
        $license_status = get_option($this->product_id . '_license_status', '');

        if (empty($license_status)) {
        ?>
            <div class="notice notice-warning is-dismissible">
                <p>Please <a href="<?php
                                    echo admin_url('admin.php?page=dynamic-practice-areas-license'); ?>">activate your license</a>
                    for Dynamic Practice Areas to enable all features.</p>
            </div>
        <?php
        } elseif ($license_status === 'invalid') {
        ?>
            <div class="notice notice-error is-dismissible">
                <p>Your license for Dynamic Practice Areas is invalid or has expired. Please <a href="<?php
                                                                                                        echo admin_url('admin.php?page=' . $this->product_id . '_license'); ?>">check your license</a>.</p>
            </div>
            <?php
        }

        // Check if the license is about to expire
        $expiration = get_option($this->product_id . '_expiration', '');
        if (!empty($expiration) && $license_status === 'valid') {
            $expiration_time = strtotime($expiration);
            $now = time();

            // If license expires in less than 14 days
            if (($expiration_time - $now) < 1209600 && $expiration_time > $now) {
            ?>
                <div class="notice notice-warning is-dismissible">
                    <p>Your license for Dynamic Practice Areas will expire on <?php
                                                                                echo date('F j, Y', $expiration_time); ?>. Please renew your license to continue receiving
                        updates and support.</p>
                </div>
        <?php
            }
        }
    }

    /**
     * Track plugin activation
     */
    public function plugin_activated()
    {
        // Check if this is the first activation
        if (!get_option($this->product_id . '_first_activation')) {
            update_option($this->product_id . '_first_activation', time());
        }

        // Check if plugin was previously deactivated
        if (get_option($this->product_id . '_was_deactivated') === '1') {
            // Force reactivation of license
            update_option($this->product_id . '_license_status', 'invalid');

            // Add admin notice for next page load
            add_option($this->product_id . '_show_reactivation_notice', '1');

            // Reset the deactivation flag
            delete_option($this->product_id . '_was_deactivated');
        }
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        $js_path = plugin_dir_path(__FILE__) . 'js/dynamic-menu.js';
        $js_ver  = file_exists($js_path) ? filemtime($js_path) : '0.3.1';
        wp_enqueue_script(
            'dynamic-menu-js',
            plugin_dir_url(__FILE__) . 'js/dynamic-menu.js',
            array('jquery'),
            $js_ver,
            true
        );

        // Enqueue CSS for related locations
        $css_path = plugin_dir_path(__FILE__) . 'css/dynamic-related-locations.css';
        $css_ver  = file_exists($css_path) ? filemtime($css_path) : '0.3.1';
        wp_enqueue_style(
            'dynamic-related-locations-css',
            plugin_dir_url(__FILE__) . 'css/dynamic-related-locations.css',
            array(),
            $css_ver
        );

        // Pass data to JavaScript
        wp_localize_script(
            'dynamic-menu-js',
            'dynamicMenuData',
            array(
                'ajaxurl' => rest_url('dynamic-menu/v1/get-practice-areas'),
                'relatedLocationsUrl' => rest_url('dynamic-menu/v1/get-related-locations'),
                'nonce' => wp_create_nonce('wp_rest'),
                'city_pages' => $this->get_city_pages(),
                'state_layer_enabled' => get_option('dynamic_practice_areas_state_layer_enabled', 'no'),
                'default_state' => get_option('dynamic_practice_areas_default_state', ''),
                'menu_selectors' => array(
                    'areas_we_serve' => '.menu-item-areas-we-serve',
                    'practice_areas' => '.menu-item-practice-areas'
                ),
                'widget_selector' => '.dynamic-practice-areas-widget',
                'elementor_selector' => '.elementor-dynamic-practice-areas',
                'related_locations_selector' => '.dynamic-related-locations-widget',
                'related_elementor_selector' => '.elementor-dynamic-related-locations',
                'uppercase_menu' => get_option('dynamic_practice_areas_uppercase_menu', 'no'),
                'default_city' => get_option('dynamic_practice_areas_default_city', '')
            )
        );
        // Inline script wrapper to ensure it runs after Elementor loads
        //     add_action('wp_enqueue_scripts', function () {
        //         wp_enqueue_script(
        //             'ec-dynamic-menu-elementor-v3',
        //             plugin_dir_url(__FILE__) . 'js/ec-dynamic-menu-elementor-v3.js',
        //             array('jquery', 'elementor-frontend'),
        //             '1.0.0',
        //             true
        //         );
        //     });
        // }
    }
    /**
     * Get all city pages (pages that should trigger the dynamic menu change)
     * with support for multi-level nesting
     *
     * @return array Array of city page data
     */
    private function get_city_pages()
    {
        // Get menu locations
        $menu_locations = get_nav_menu_locations();

        // Check if primary menu is set
        if (!isset($menu_locations['primary'])) {
            return array();
        }

        // Get menu items for the primary menu
        $menu_items = wp_get_nav_menu_items($menu_locations['primary']);

        if (!$menu_items) {
            return array();
        }

        // More flexible search for "Areas We Serve"
        $areas_we_serve_id = null;
        foreach ($menu_items as $item) {
            // Case-insensitive, partial match
            if (
                stripos($item->title, 'Areas') !== false &&
                stripos($item->title, 'Serve') !== false
            ) {
                $areas_we_serve_id = $item->ID;
                break;
            }
        }

        if (!$areas_we_serve_id) {
            return array();
        }

        // Collect city pages
        $city_pages = array();
        $this->get_nested_city_pages($menu_items, $areas_we_serve_id, $city_pages);


        return $city_pages;
    }

    /**
     * Recursively get all nested city pages from the menu
     *
     * @param  array   $menu_items  All menu items (objects from wp_get_nav_menu_items)
     * @param  int     $parent_id   Parent menu item ID
     * @param  array  &$city_pages  Array to fill with city pages
     * @param  int     $depth       Current depth (for limiting recursion)
     */
    private function get_nested_city_pages($menu_items, $parent_id, &$city_pages, $depth = 0)
    {
        // Prevent infinite recursion (limit depth to 5 levels)
        if ($depth > 5) {
            return;
        }

        foreach ($menu_items as $item) {
            if ((int) $item->menu_item_parent === (int) $parent_id) {

                // Sanitize values
                $item_id    = (int) $item->ID;
                $item_title = sanitize_text_field($item->title);
                $item_url   = esc_url_raw($item->url);
                $item_slug  = sanitize_title(basename(untrailingslashit($item->url)));

                // Add this item as a city page
                $city_pages[] = array(
                    'id'        => $item_id,
                    'title'     => $item_title,
                    'url'       => $item_url,
                    'slug'      => $item_slug,
                    'parent_id' => (int) $parent_id,
                    'depth'     => $depth,
                );

                // Recursively add children
                $this->get_nested_city_pages($menu_items, $item_id, $city_pages, $depth + 1);
            }
        }
    }


    /**
     * Register REST API endpoints
     */
    public function register_api_endpoints()
    {
        // get_practice_areas
        register_rest_route('dynamic-menu/v1', '/get-practice-areas', [
            'methods' => 'GET',
            'callback' => [$this, 'get_practice_areas_for_city'],
            'permission_callback' => '__return_true',
            'args' => [
                'city_slug' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                /* ↓ NEW: */
                'state_slug' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // get_related_locations
        register_rest_route('dynamic-menu/v1', '/get-related-locations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_related_locations'],
            'permission_callback' => '__return_true',
            'args' => [
                'practice_area_slug' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'city_slug' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                /* ↓ NEW: */
                'state_slug' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('dynamic-menu/v1', '/get-sub-practice-areas', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sub_practice_areas_for_practice_area'),
            'permission_callback' => '__return_true',
            'args' => array(
                'practice_area_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ),
                'city_slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'practice_area_slug' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    /**
     * Get sub-practice areas for a specific practice area via REST API
     *
     * @param  WP_REST_Request  $request  The request object
     * @return WP_REST_Response The response
     */
    public function get_sub_practice_areas_for_practice_area($request)
    {
        $practice_area_id = $request->get_param('practice_area_id');
        $city_slug = $request->get_param('city_slug');
        $practice_area_slug = $request->get_param('practice_area_slug');


        // Get the practice area details
        $practice_area = get_post($practice_area_id);

        if (!$practice_area) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Practice area not found',
                'sub_practice_areas' => array()
            ), 404);
        }

        // Get sub-practice areas (child pages of this practice area)
        $sub_practice_areas = get_pages(array(
            'child_of' => $practice_area_id,
            'parent' => $practice_area_id,
            'sort_column' => 'menu_order,post_title',
            'post_status' => 'publish'
        ));


        $sub_practice_area_data = array();
        foreach ($sub_practice_areas as $sub_practice_area) {
            // Get anchor text if available
            $anchor_text = get_post_meta($sub_practice_area->ID, 'anchor_text', true);

            $sub_practice_area_data[] = array(
                'id' => $sub_practice_area->ID,
                'title' => $sub_practice_area->post_title,
                'url' => get_permalink($sub_practice_area->ID),
                'slug' => $sub_practice_area->post_name,
                'anchor_text' => $anchor_text
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'practice_area_id' => $practice_area_id,
            'practice_area_title' => $practice_area->post_title,
            'practice_area_slug' => $practice_area_slug,
            'sub_practice_areas' => $sub_practice_area_data
        ), 200);
    }

    /**
     * Get practice areas for a specific city via REST API
     * Supports optional /{state}/{city} URL depth with fallback to /{city}
     *
     * @param  WP_REST_Request  $request
     * @return WP_REST_Response
     */
    public function get_practice_areas_for_city(WP_REST_Request $request)
    {
        // 1) Pull parameters & toggle
        $city_slug = sanitize_text_field($request->get_param('city_slug'));
        $state_slug = sanitize_text_field($request->get_param('state_slug'));
        $state_enabled = get_option('dynamic_practice_areas_state_layer_enabled', 'no') === 'yes';

        // 2) Attempt to locate the city page under /state/city if enabled
        if ($state_enabled && $state_slug) {
            $lookup = "{$state_slug}/{$city_slug}";
            $city_page = get_page_by_path($lookup);

            // 2a) Fallback to the old /city path if no page under /state
            if (!$city_page) {
                $city_page = get_page_by_path($city_slug);
            }
        } else {
            // 2b) Plain two-segment lookup
            $city_page = get_page_by_path($city_slug);
        }

        // 3) If we still didn't find it, return a 404
        if (!$city_page) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'City page not found',
                'practice_areas' => []
            ], 404);
        }

        // 4) Gather city-level display info
        $city_anchor = get_post_meta($city_page->ID, 'anchor_text', true);
        $city_tpl = get_post_meta($city_page->ID, '_wp_page_template', true);
        $expected_city_tpl = 'city-page-template.php';

        $city_display = ($city_tpl !== $expected_city_tpl && $city_anchor)
            ? $city_anchor
            : $city_page->post_title;

        // 5) Fetch direct children (your practice-area pages)
        $children = get_pages([
            'child_of' => $city_page->ID,
            'parent' => $city_page->ID,
            'sort_column' => 'menu_order,post_title',
            'post_status' => 'publish',
            'hierarchical' => 1,
            'depth' => 1,
        ]);

        $practice_areas = [];
        foreach ($children as $pa) {
            // ensure it’s a direct child
            if ($pa->post_parent !== $city_page->ID) {
                continue;
            }

            // anchor-text + template logic
            $pa_anchor = get_post_meta($pa->ID, 'anchor_text', true);
            $pa_tpl = get_post_meta($pa->ID, '_wp_page_template', true);
            $expected_pa_tpl = 'practice-area-template.php';

            $pa_display = ($pa_tpl !== $expected_pa_tpl && $pa_anchor)
                ? $pa_anchor
                : $pa->post_title;

            $practice_areas[] = [
                'id' => $pa->ID,
                'title' => $pa->post_title,
                'slug' => $pa->post_name,
                'url' => get_permalink($pa->ID),
                'anchor_text' => $pa_anchor,
                'display_text' => $pa_display,
            ];
        }

        // 6) Return success with the assembled data
        return new WP_REST_Response([
            'success' => true,
            'city_name' => $city_page->post_title,
            'city_anchor_text' => $city_anchor,
            'city_display_text' => $city_display,
            'practice_areas' => $practice_areas
        ], 200);
    }

    /**
     * Get related locations for a specific practice area via REST API
     * Now supports optional state layer in URLs: /{state}/{city}/{practice}
     *
     * @param  WP_REST_Request  $request  The request object
     * @return WP_REST_Response The response
     */
    public function get_related_locations($request)
    {
        $practice_area_slug = $request->get_param('practice_area_slug');
        $current_city_slug = $request->get_param('city_slug');
        $state_slug = $request->get_param('state_slug');

        // Is the state‐layer feature turned on?
        $state_enabled = get_option('dynamic_practice_areas_state_layer_enabled', 'no') === 'yes';

        // 1) Locate the “current” practice‐area page
        if ($state_enabled && $state_slug) {
            $current_path = "{$state_slug}/{$current_city_slug}/{$practice_area_slug}";
        } else {
            $current_path = "{$current_city_slug}/{$practice_area_slug}";
        }

        $current_practice_area = get_page_by_path($current_path);
        if (!$current_practice_area) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Current practice area page not found',
                'related_locations' => []
            ], 404);
        }

        // 2) Fetch your canonical list of all cities
        $city_pages = $this->get_city_pages();
        $related_locations = [];

        // 3) Iterate each city, skip the current one
        foreach ($city_pages as $city) {
            if ($city['slug'] === $current_city_slug) {
                continue;
            }

            // Resolve the city page with or without state‐prefix
            if ($state_enabled && $state_slug) {
                $city_page = get_page_by_path("{$state_slug}/{$city['slug']}");
            } else {
                $city_page = get_page_by_path($city['slug']);
            }
            if (!$city_page) {
                continue;
            }

            // 4) Grab this city’s children (its practice‐area pages)
            $practice_areas = get_pages([
                'child_of' => $city_page->ID,
                'parent' => $city_page->ID,
                'sort_column' => 'menu_order,post_title',
                'post_status' => 'publish',
                'hierarchical' => 1,
                'depth' => 1,
            ]);

            // Prepare for slug‐matching
            $current_slug = sanitize_title($practice_area_slug);

            foreach ($practice_areas as $practice_area) {
                $area_slug_clean = sanitize_title($practice_area->post_name);

                $full_match = ($area_slug_clean === $current_slug);
                $partial_match = (
                    strpos($area_slug_clean, $current_slug) !== false ||
                    strpos($current_slug, $area_slug_clean) !== false
                );

                if ($full_match || $partial_match) {
                    // Anchor text / template logic (unchanged)
                    $pa_anchor = get_post_meta($practice_area->ID, 'anchor_text', true);
                    $pa_tpl = get_post_meta($practice_area->ID, '_wp_page_template', true);
                    $pa_display = ($pa_tpl !== 'practice-area-template.php' && $pa_anchor)
                        ? $pa_anchor
                        : $practice_area->post_title;

                    $city_anchor = get_post_meta($city_page->ID, 'anchor_text', true);
                    $city_tpl = get_post_meta($city_page->ID, '_wp_page_template', true);
                    $city_display = ($city_tpl !== 'city-page-template.php' && $city_anchor)
                        ? $city_anchor
                        : $city_page->post_title;

                    // Build the related‐location entry
                    $related_locations[] = [
                        'id' => $city_page->ID,
                        'title' => $city_page->post_title,
                        'slug' => $city['slug'],
                        'practice_area_url' => get_permalink($practice_area->ID),
                        'practice_area_display_text' => $pa_display,
                        'city_display_text' => $city_display,
                        'match_type' => $full_match ? 'full' : 'partial',
                    ];
                }
            }
        }

        // 5) Return successful response
        return new WP_REST_Response([
            'success' => true,
            'practice_area_slug' => $practice_area_slug,
            'current_city_slug' => $current_city_slug,
            'current_state_slug' => $state_enabled ? $state_slug : '',
            'related_locations' => $related_locations
        ], 200);
    }

    /**
     * Add JavaScript to handle dynamic menu changes
     */
    public function add_menu_js()
    {
        ?>
        <script>
            // Store original widget title on page load
            jQuery(document).ready(function($) {
                // Store original widget title for WordPress widgets
                $('.dynamic-practice-areas-widget').each(function() {
                    var $widget = $(this);
                    var $title = $widget.prev('h2.widget-title');
                    if ($title.length) {
                        $title.data('original-title', $title.text());
                    }
                });

                // Store original title for Elementor widgets
                $('.elementor-dynamic-practice-areas').each(function() {
                    var $title = $(this).find('.practice-areas-title');
                    if ($title.length && !$title.data('original-title')) {
                        $title.data('original-title', $title.text());
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Register block for Gutenberg
     */
    public function register_blocks()
    {
        // Skip if block registration function doesn't exist (WP < 5.0)
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register block JS and CSS
        wp_register_script(
            'dynamic-practice-areas-block',
            plugin_dir_url(__FILE__) . 'js/dynamic-practice-areas-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'js/dynamic-practice-areas-block.js')
        );

        wp_register_style(
            'dynamic-practice-areas-block-editor',
            plugin_dir_url(__FILE__) . 'css/dynamic-practice-areas-block-editor.css',
            array('wp-edit-blocks'),
            filemtime(plugin_dir_path(__FILE__) . 'css/dynamic-practice-areas-block-editor.css')
        );

        wp_register_style(
            'dynamic-practice-areas-block',
            plugin_dir_url(__FILE__) . 'css/dynamic-practice-areas-block.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/dynamic-practice-areas-block.css')
        );

        // Register the block
        register_block_type('dynamic-practice-areas/block', array(
            'editor_script' => 'dynamic-practice-areas-block',
            'editor_style' => 'dynamic-practice-areas-block-editor',
            'style' => 'dynamic-practice-areas-block',
            'render_callback' => array($this, 'render_practice_areas_block')
        ));
    }

    /**
     * Render block content on the frontend
     *
     * @param  array  $attributes  Block attributes
     * @return string Block HTML
     */
    public function render_practice_areas_block($attributes)
    {
        $title = isset($attributes['title']) ? $attributes['title'] : 'Practice Areas';
        $block_id = isset($attributes['blockId']) ? $attributes['blockId'] : 'block-' . uniqid();

        $output = '<div class="wp-block-dynamic-practice-areas">';

        if (!empty($title)) {
            $output .= '<h2 class="practice-areas-block-title" data-original-title="' . esc_attr($title) . '">' . esc_html($title) . '</h2>';
        }

        $output .= '<div class="dynamic-practice-areas-widget" data-block-id="' . esc_attr($block_id) . '">';
        $output .= '<ul class="practice-areas-list">';
        $output .= '<li class="select-city-message">Please select a city to view practice areas</li>';
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget()
    {
        // Skip if Elementor is not active
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Include Base Widget class first
        require_once plugin_dir_path(__FILE__) . 'includes/base-elementor-widget.php';

        // Include Elementor widget classes
        require_once plugin_dir_path(__FILE__) . 'includes/elementor-widget.php';
        require_once plugin_dir_path(__FILE__) . 'includes/elementor-related-locations-widget.php';

        // Register widgets with Elementor
        \Elementor\Plugin::instance()->widgets_manager->register(new \Dynamic_Practice_Areas_Elementor_Widget());
        \Elementor\Plugin::instance()->widgets_manager->register(new \Dynamic_Related_Locations_Elementor_Widget());
    }

    /**
     * Add custom category for Elementor widgets
     *
     * @param  \Elementor\Elements_Manager  $elements_manager  Elementor elements manager
     */
    public function add_elementor_widget_category($elements_manager)
    {
        $elements_manager->add_category(
            'dynamic-practice-areas',
            [
                'title' => __('Dynamic Practice Areas', 'dynamic-practice-areas-menu'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    /**
     * Register widget
     */
    public function register_widgets()
    {
        require_once(__DIR__ . '/includes/base-elementor-widget.php');
        require_once(__DIR__ . '/includes/elementor-related-locations-widget.php');
        require_once(__DIR__ . '/includes/elementor-widget.php');

        register_widget('Dynamic_Practice_Areas_Widget');
        register_widget('Dynamic_Related_Locations_Widget');
    }

    /**
     * Render the admin page
     */
    public function render_admin_page()
    {
        // Check license first
        $license_status = get_option($this->product_id . '_license_status');
        // if ($license_status !== 'valid') {
        if (false) {
        ?>
            <div class="wrap">
                <h1>Dynamic Practice Areas</h1>
                <div class="notice notice-error">
                    <p>You need to activate your license to use this plugin. <a href="<?php
                                                                                        echo admin_url('admin.php?page=' . $this->product_id . '_license'); ?>">Activate now</a></p>
                </div>
            </div>
        <?php
            return;
        }

        // Get settings
        $uppercase_menu = get_option('dynamic_practice_areas_uppercase_menu', 'no');

        // Get all pages with city-page-template.php template
        $template_city_pages = get_pages([
            'meta_key' => '_wp_page_template',
            'meta_value' => 'templates/city-page-template.php'
        ]);

        // Get city pages from the menu structure
        $menu_city_pages = $this->get_city_pages();

        // Combine both sets and remove duplicates
        $city_pages = [];
        $processed_slugs = [];

        // Add template-based city pages
        foreach ($template_city_pages as $page) {
            $city_pages[] = [
                'slug' => $page->post_name,
                'title' => $page->post_title,
                'id' => $page->ID
            ];
            $processed_slugs[] = $page->post_name;
        }

        // Add menu-based city pages that aren't already included
        foreach ($menu_city_pages as $page) {
            if (!in_array($page['slug'], $processed_slugs)) {
                $city_pages[] = $page;
                $processed_slugs[] = $page['slug'];
            }
        }

        // Sort by title
        usort($city_pages, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });

        $default_city = get_option('dynamic_practice_areas_default_city', '');

        ?>
        <div class="wrap">
            <h1>Dynamic Practice Areas Settings</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('dynamic_practice_areas_settings'); ?>

                <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                    <h2>Display Settings</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Uppercase Menu Text</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="dynamic_practice_areas_uppercase_menu"
                                        value="yes" <?php
                                                    checked('yes', $uppercase_menu); ?>>
                                    Display practice area menu titles in uppercase
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Default City</th>
                            <td>
                                <select name="dynamic_practice_areas_default_city">
                                    <option value="">-- No Default --</option>
                                    <?php
                                    foreach ($city_pages as $city) : ?>
                                        <option value="<?php
                                                        echo esc_attr($city['slug']); ?>" <?php
                                                                                            selected($default_city, $city['slug']); ?>>
                                            <?php
                                            echo esc_html($city['title']); ?>
                                        </option>
                                    <?php
                                    endforeach; ?>
                                </select>
                                <p class="description">If set, practice areas for this city will be shown by default in
                                    menus and widgets</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Enable State Layer</th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                        name="dynamic_practice_areas_state_layer_enabled"
                                        value="yes" <?php
                                                    checked(
                                                        'yes',
                                                        get_option('dynamic_practice_areas_state_layer_enabled', 'no')
                                                    ); ?> />
                                    Treat URLs as /{state}/{city}/{practice}/{sub-practice}
                                </label>
                                <p class="description">
                                    If enabled, the plugin will add support for URL structures supporting a state layer.
                                    <br />
                                    For example: <code>domain.tld/ga/atlanta/accident-lawyer</code>
                                    <br />
                                    <br />
                                    This option remains backwards compatible with existing URL structures
                                    designed with only single state support in mind: <code>domain.tld/atlanta/accident-lawyer</code>
                                </p>
                            </td>
                        </tr>
                        <!--                        <tr>-->
                        <!--                            <th scope="row">Default State</th>-->
                        <!--                            <td>-->
                        <!--                                <label>-->
                        <!--                                    <input type="text"-->
                        <!--                                           name="dynamic_practice_areas_default_state"-->
                        <!--                                           value="--><?php
                                                                                    //                                           echo esc_attr(get_option('dynamic_practice_areas_default_state', ''));
                                                                                    ?><!--"-->
                        <!--                                           placeholder="e.g. ga"/>-->
                        <!--                                </label>-->
                        <!--                                <p class="description">-->
                        <!--                                    If a URL is only two segments but state-layer is on, this slug will be assumed.-->
                        <!--                                </p>-->
                        <!--                            </td>-->
                        <!--                        </tr>-->
                    </table>

                    <?php
                    submit_button(); ?>
                </div>
            </form>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Setup Instructions</h2>
                <ol>
                    <li>Add the CSS class <code>menu-item-areas-we-serve</code> to your "Areas We Serve" menu item</li>
                    <li>Add the CSS class <code>menu-item-practice-areas</code> to your "Practice Areas" menu item</li>
                    <li>Add city pages as child items under the "Areas We Serve" menu</li>
                    <li>Create practice area pages as child pages of each city page</li>
                </ol>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Widget & Block Usage</h2>
                <p>You can add the Dynamic Practice Areas widget to your sidebar via <a href="<?php
                                                                                                echo admin_url('widgets.php'); ?>">Appearance → Widgets</a>.</p>
                <p>For Gutenberg pages, use the "Dynamic Practice Areas" block.</p>
                <p>For Elementor pages, use the "Dynamic Practice Areas" Elementor widget.</p>
            </div>
        </div>
    <?php
    }

    /**
     * Plugin deactivation handler
     */
    function dynamic_practice_areas_deactivated()
    {
        // Reset the license status to force reactivation
        $product_id = 'ec_dynamic_practice_areas';

        // Keep the license key but mark it as invalid
        update_option($product_id . '_license_status', 'invalid');

        // Record that plugin was deactivated to show appropriate notice on reactivation
        update_option($product_id . '_was_deactivated', '1');
    }

    /**
     * Plugin uninstallation cleanup
     */
    function dynamic_practice_areas_uninstall()
    {
        // Remove license options
        $product_id = 'ec_dynamic_practice_areas';
        delete_option($product_id . '_license_key');
        delete_option($product_id . '_license_status');
        delete_option($product_id . '_activation_count');
        delete_option($product_id . '_max_sites');
        delete_option($product_id . '_expiration');
        delete_option($product_id . '_last_license_check');
        delete_option($product_id . '_first_activation');

        // Remove any custom options your plugin might have
        // For example:
        // delete_option('dynamic_practice_areas_settings');

        // Clear any transients
        delete_transient($product_id . '_api_response');

        // If you have custom database tables, remove them here
        // global $wpdb;
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}your_custom_table");
    }
}

/**
 * Widget Class for Practice Areas
 */
class Dynamic_Practice_Areas_Widget extends WP_Widget
{

    /**
     * Register widget with WordPress
     */
    public function __construct()
    {
        parent::__construct(
            'dynamic_practice_areas_widget', // Base ID
            'Dynamic Practice Areas', // Name
            array('description' => 'Displays practice areas for the selected city') // Args
        );
    }

    /**
     * Front-end display of widget
     *
     * @param  array  $args  Widget arguments
     * @param  array  $instance  Saved values from database
     */
    public function widget($args, $instance)
    {
        // Check if the license is valid before rendering the widget.
        if (!get_option('ec_dynamic_practice_areas_license_status') || get_option('dynamic_practice_areas_license_status') !== 'valid') {
            echo $args['before_widget'];
            echo '<p style="color:red;">This feature is disabled until you activate your license.</p>';
            echo $args['after_widget'];
            return;
        }

        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // Widget content
        echo '<div class="dynamic-practice-areas-widget" data-widget-id="' . esc_attr($this->id) . '">';

        // JavaScript (will replace default content)
        echo '<ul class="practice-areas-list">';
        echo '<li class="loading">Loading practice areas...</li>';
        echo '</ul>';

        echo '</div>';

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form
     *
     * @param  array  $instance  Previously saved values from database
     */
    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : 'Practice Areas';
    ?>
        <p>
            <label for="<?php
                        echo esc_attr($this->get_field_id('title')); ?>">Title:</label>
            <input class="widefat" id="<?php
                                        echo esc_attr($this->get_field_id('title')); ?>" name="<?php
                                                                                                echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php
                                                                                                                                                                        echo esc_attr($title); ?>">
        </p>
        <p class="description">This widget automatically displays practice areas for the selected city.</p>
    <?php
    }

    /**
     * Sanitize widget form values as they are saved
     *
     * @param  array  $new_instance  Values just sent to be saved
     * @param  array  $old_instance  Previously saved values from database
     *
     * @return array Updated safe values to be saved
     */
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';

        return $instance;
    }
}

/**
 * Widget Class for Related Locations
 */
class Dynamic_Related_Locations_Widget extends WP_Widget
{
    /**
     * Register widget with WordPress
     */
    public function __construct()
    {
        parent::__construct(
            'dynamic_related_locations_widget', // Base ID
            'Related Practice Area Locations', // Name
            array('description' => 'Displays other locations with the same practice area') // Args
        );
    }

    /**
     * Front-end display of widget
     *
     * @param  array  $args  Widget arguments
     * @param  array  $instance  Saved values from database
     */
    public function widget($args, $instance)
    {
        // Check if the license is valid before rendering the widget
        if (get_option('ec_dynamic_practice_areas_license_status') !== 'valid') {
            echo $args['before_widget'];
            echo '<p style="color:red;">This feature is disabled until you activate your license.</p>';
            echo $args['after_widget'];
            return;
        }

        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // Widget content
        echo '<div class="dynamic-related-locations-widget" data-widget-id="' . esc_attr($this->id) . '">';
        echo '<ul class="related-locations-list">';
        echo '<li class="loading">Finding related locations...</li>';
        echo '</ul>';
        echo '</div>';

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form
     *
     * @param  array  $instance  Previously saved values from database
     */
    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : 'Additional Locations:';
    ?>
        <p>
            <label for="<?php
                        echo esc_attr($this->get_field_id('title')); ?>">Title:</label>
            <input class="widefat" id="<?php
                                        echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php
                        echo esc_attr($this->get_field_name('title')); ?>"
                type="text" value="<?php
                                    echo esc_attr($title); ?>">
        </p>
        <p class="description">This widget automatically displays other locations that offer the current practice
            area.</p>
<?php
    }

    /**
     * Sanitize widget form values as they are saved
     *
     * @param  array  $new_instance  Values just sent to be saved
     * @param  array  $old_instance  Previously saved values from database
     * @return array Updated safe values to be saved
     */
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

// Initialize the plugin
$dynamic_practice_areas_menu = new Dynamic_Practice_Areas_Menu();

// Create necessary plugin directories on activation
function dynamic_menu_activate()
{
    // Create js directory if it doesn't exist
    $js_dir = plugin_dir_path(__FILE__) . 'js';
    if (!file_exists($js_dir)) {
        mkdir($js_dir, 0755, true);
    }

    // Create css directory if it doesn't exist
    $css_dir = plugin_dir_path(__FILE__) . 'css';
    if (!file_exists($css_dir)) {
        mkdir($css_dir, 0755, true);
    }

    // Create includes directory if it doesn't exist
    $includes_dir = plugin_dir_path(__FILE__) . 'includes';
    if (!file_exists($includes_dir)) {
        mkdir($includes_dir, 0755, true);
    }

    // Create Elementor widget file
    $elementor_widget_file = $includes_dir . '/elementor-widget.php';
    if (!file_exists($elementor_widget_file)) {
        $elementor_widget_content = <<<'EOT'
<?php
/**
 * Dynamic Practice Areas Elementor Widget
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Elementor widget for Dynamic Practice Areas
 */
class Dynamic_Practice_Areas_Elementor_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'dynamic_practice_areas';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Dynamic Practice Areas', 'dynamic-practice-areas-menu');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-bullet-list';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories() {
        return ['dynamic-practice-areas'];
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords() {
        return ['practice', 'areas', 'menu', 'dynamic', 'city'];
    }

    /**
     * Register widget controls
     */
    protected function _register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Practice Areas', 'dynamic-practice-areas-menu'),
                'placeholder' => __('Enter your title', 'dynamic-practice-areas-menu'),
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'dynamic-practice-areas-menu'),
                'label_off' => __('Hide', 'dynamic-practice-areas-menu'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .practice-areas-title' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .practice-areas-title',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'list_color',
            [
                'label' => __('List Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .practice-areas-list li a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'list_hover_color',
            [
                'label' => __('List Hover Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .practice-areas-list li a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'list_typography',
                'selector' => '{{WRAPPER}} .practice-areas-list li',
            ]
        );

        $this->add_responsive_control(
            'space_between',
            [
                'label' => __('Space Between', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .practice-areas-list li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();
        
        $this->add_render_attribute('wrapper', 'class', 'elementor-dynamic-practice-areas');
        $this->add_render_attribute('title', 'class', 'practice-areas-title');
        
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php if ('yes' === $settings['show_title'] && !empty($settings['title'])) : ?>
                <h3 <?php echo $this->get_render_attribute_string('title'); ?> data-original-title="<?php echo esc_attr($settings['title']); ?>">
                    <?php echo esc_html($settings['title']); ?>
                </h3>
            <?php endif; ?>
            
            <div class="dynamic-practice-areas-widget" data-elementor-id="<?php echo esc_attr($widget_id); ?>">
                <ul class="practice-areas-list">
                    <li class="select-city-message"><?php _e('Please select a city to view practice areas', 'dynamic-practice-areas-menu'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor
     */
    protected function _content_template() {
        ?>
        <div class="elementor-dynamic-practice-areas">
            <# if ('yes' === settings.show_title && settings.title) { #>
                <h3 class="practice-areas-title" data-original-title="{{{ settings.title }}}">
                    {{{ settings.title }}}
                </h3>
            <# } #>
            
            <div class="dynamic-practice-areas-widget">
                <ul class="practice-areas-list">
                    <li class="select-city-message">Please select a city to view practice areas</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
EOT;
        file_put_contents($elementor_widget_file, $elementor_widget_content);

        // Create Related Locations Elementor widget file
        $related_locations_widget_file = $includes_dir . '/elementor-related-locations-widget.php';
        if (!file_exists($related_locations_widget_file)) {
            $related_locations_widget_content = <<<'EOT'
<?php
/**
 * Dynamic Related Locations Elementor Widget
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Elementor widget for Dynamic Related Locations
 */
class Dynamic_Related_Locations_Elementor_Widget extends \Elementor\Widget_Base
{
    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name()
    {
        return 'dynamic_related_locations';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title()
    {
        return __('Related Practice Area Locations', 'dynamic-practice-areas-menu');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon()
    {
        return 'eicon-map-pin';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories()
    {
        return ['dynamic-practice-areas'];
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords()
    {
        return ['practice', 'areas', 'locations', 'related', 'city'];
    }

    /**
     * Register widget controls
     */
    protected function _register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Also Available In:', 'dynamic-practice-areas-menu'),
                'placeholder' => __('Enter your title', 'dynamic-practice-areas-menu'),
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'dynamic-practice-areas-menu'),
                'label_off' => __('Hide', 'dynamic-practice-areas-menu'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_empty_message',
            [
                'label' => __('Show Empty Message', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'dynamic-practice-areas-menu'),
                'label_off' => __('Hide', 'dynamic-practice-areas-menu'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Show a message when no related locations are found', 'dynamic-practice-areas-menu'),
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => __('Empty Message', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No other locations offer this practice area', 'dynamic-practice-areas-menu'),
                'placeholder' => __('Enter message to display when no locations found', 'dynamic-practice-areas-menu'),
                'condition' => [
                    'show_empty_message' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .related-locations-title' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .related-locations-title',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'list_color',
            [
                'label' => __('List Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .related-locations-list li a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'list_hover_color',
            [
                'label' => __('List Hover Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .related-locations-list li a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'list_typography',
                'selector' => '{{WRAPPER}} .related-locations-list li',
            ]
        );

        $this->add_responsive_control(
            'space_between',
            [
                'label' => __('Space Between', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .related-locations-list li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'empty_message_color',
            [
                'label' => __('Empty Message Color', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .related-locations-list .no-related-locations' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_empty_message' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'empty_message_typography',
                'selector' => '{{WRAPPER}} .related-locations-list .no-related-locations',
                'condition' => [
                    'show_empty_message' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();

        $this->add_render_attribute('wrapper', 'class', 'elementor-dynamic-related-locations');
        $this->add_render_attribute('title', 'class', 'related-locations-title');

        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php if ('yes' === $settings['show_title'] && !empty($settings['title'])) : ?>
                <h3 <?php echo $this->get_render_attribute_string('title'); ?> data-original-title="<?php echo esc_attr($settings['title']); ?>">
                    <?php echo esc_html($settings['title']); ?>
                </h3>
            <?php endif; ?>

            <div class="dynamic-related-locations-widget" data-elementor-id="<?php echo esc_attr($widget_id); ?>" data-show-empty="<?php echo ('yes' === $settings['show_empty_message']) ? 'true' : 'false'; ?>" data-empty-message="<?php echo esc_attr($settings['empty_message']); ?>">
                <ul class="related-locations-list">
                    <li class="loading"><?php _e('Finding related locations...', 'dynamic-practice-areas-menu'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor
     */
    protected function _content_template()
    {
        ?>
        <div class="elementor-dynamic-related-locations">
            <# if ('yes' === settings.show_title && settings.title) { #>
                <h3 class="related-locations-title" data-original-title="{{{ settings.title }}}">
                    {{{ settings.title }}}
                </h3>
            <# } #>
            
            <div class="dynamic-related-locations-widget" data-show-empty="{{ 'yes' === settings.show_empty_message ? 'true' : 'false' }}" data-empty-message="{{ settings.empty_message }}">
                <ul class="related-locations-list">
                    <li class="loading">Finding related locations...</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
EOT;
            file_put_contents($related_locations_widget_file, $related_locations_widget_content);
        }
    }

    // Create dynamic-menu.js file if it doesn't exist
    $js_file = $js_dir . '/dynamic-menu.js';
    if (!file_exists($js_file)) {
        $js_content = <<<'EOT'
/**
 * Dynamic Practice Areas Menu JavaScript
 * Updated to support anchor text field, Elementor Mega Menu, uppercase setting, default city,
 * and sub-practice areas display
 */
(function ($) {
    'use strict';

    // Store original practice areas menu for restoring when needed
    var originalPracticeAreasMenu = null;
    var currentCitySlug = null;
    var currentCityName = null;
    var currentStateSlug = null;
    var currentPracticeAreaSlug = null;

    $(document).ready(function () {
        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // Hide titles initially
        $('.dynamic-related-locations-widget, .widget_dynamic_related_locations_widget').each(function () {
            var $widget = $(this);
            var $title = $widget.find('.related-locations-title');
            if (!$title.length) {
                $title = $widget.prev('h2.widget-title');
            }
            if ($title.length) {
                $title.css('visibility', 'hidden');
            }
        });

        // Check if we're using Elementor Mega Menu or standard WP nav menu
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        var menuSelectors = {
            areas_we_serve: usingElementorMegaMenu ?
                '#menu-item-areas-we-serve, .e-n-menu-item:contains("AREAS WE SERVE")' :
                dynamicMenuData.menu_selectors.areas_we_serve,
            practice_areas: usingElementorMegaMenu ?
                '#menu-item-practice-areas, .e-n-menu-item:contains("PRACTICE AREAS")' :
                dynamicMenuData.menu_selectors.practice_areas
        };


        // Cache DOM elements
        var $areasWeServeMenu = $(menuSelectors.areas_we_serve);
        var $practiceAreasMenu = $(menuSelectors.practice_areas);
        var $practiceAreasWidget = $(dynamicMenuData.widget_selector);


        // Store original practice areas menu for restoring later
        if ($practiceAreasMenu.length) {
            originalPracticeAreasMenu = $practiceAreasMenu.clone();
        }

        // Detect current page and update menu if it's a city or practice area page
        detectCurrentPage();

        // Call the function to update related locations widget
        updateRelatedLocationsWidget();

        // If no city is active but we have a default city, use it
        if (!currentCitySlug && dynamicMenuData.default_city) {
            loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
        }

        // Handle city page link clicks for Elementor Mega Menu
        if (usingElementorMegaMenu) {
            $(document).on('click', menuSelectors.areas_we_serve + ' .elementor-icon-list-items a', function (e) {
                var cityUrl = $(this).attr('href');
                var citySlug = cityUrl.split('/').filter(Boolean).pop();
                var cityName = $(this).text();


                // Store city information in sessionStorage for persistence across page loads
                sessionStorage.setItem('currentCitySlug', citySlug);
                sessionStorage.setItem('currentCityName', cityName);
            });
        } else {
            // Standard WP menu click handler
            $(document).on('click', menuSelectors.areas_we_serve + ' .sub-menu a', function (e) {
                var cityUrl = $(this).attr('href');
                var citySlug = cityUrl.split('/').filter(Boolean).pop();
                var cityName = $(this).text();

                // Store city information in sessionStorage for persistence across page loads
                sessionStorage.setItem('currentCitySlug', citySlug);
                sessionStorage.setItem('currentCityName', cityName);
            });
        }

        // Convert practice areas menu item to a dropdown menu
        $practiceAreasMenu.each(function () {
            var $this = $(this);

            // Only make changes if this menu item doesn't already have necessary classes
            if (!$this.hasClass('menu-item-has-children')) {
                // Add necessary classes for dropdown functionality
                $this.addClass('menu-item-has-children');
            }
        });

        // Check if we're in the mobile menu context
        var $mobileMenu = $('.elementor-nav-menu--dropdown');
        if ($mobileMenu.length) {
            // Find the Practice Areas menu item in the mobile menu
            var $mobilePracticeAreasMenu = $mobileMenu.find('.menu-item-practice-areas');

            if ($mobilePracticeAreasMenu.length) {
                // Transform it into a dropdown if it's not already
                if (!$mobilePracticeAreasMenu.hasClass('menu-item-has-children')) {

                    // Add necessary classes
                    $mobilePracticeAreasMenu.addClass('menu-item-has-children');

                    // Get the menu link
                    var $link = $mobilePracticeAreasMenu.find('> a');

                    if ($link.length) {
                        $link.addClass('has-submenu');
                        // $link.append('<span class="sub-arrow"><i class="fas fa-caret-down"></i></span>');

                        // Create a submenu element
                        var submenuHtml = '<ul class="sub-menu elementor-nav-menu--dropdown">' +
                            '<li class="menu-item"><a href="#" class="elementor-sub-item">Loading practice areas...</a></li>' +
                            '</ul>';

                        // Append it directly after the link
                        $link.after(submenuHtml);

                        // Set ARIA attributes after submenu is added
                        $link.attr({
                            'aria-haspopup': 'true',
                            'aria-expanded': 'false'
                        });
                    }
                }
            }
        }

        // Handle hover on practice areas menu for non-city pages
        $practiceAreasMenu.on('mouseenter', function () {
            if (currentCitySlug) {
                // Already on a city page or city context is active, do nothing
                return;
            }

            // If not on a city page, make sure the original menu is shown
            restoreOriginalPracticeAreasMenu();
        });

        // Add this CSS to your theme or Elementor custom CSS
        var styleTag = document.createElement('style');
        styleTag.textContent = `
            /* .elementor-nav-menu--dropdown .menu-item-practice-areas.menu-item-has-children > .sub-menu {
                display: none;
            } */
            .elementor-nav-menu--dropdown .menu-item-practice-areas.menu-item-has-children > a[aria-expanded="true"] + .sub-menu {
                display: block !important;
            }
        `;
        document.head.appendChild(styleTag);

        // Use event delegation to handle clicks on the Practice Areas menu item (li or a)
        $(document).on('click', '.elementor-nav-menu--dropdown .menu-item-practice-areas', function (e) {
            // If clicking a submenu link, let it proceed normally
            if ($(e.target).closest('.sub-menu').length) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var $li = $(this);
            var $link = $li.find('> a');
            var $subMenu = $link.next('.sub-menu');

            if (!$subMenu.length) return;

            var citySlug = currentCitySlug || dynamicMenuData.default_city || '';

            if (citySlug) {
                if (!$li.data('loaded')) {
                    $.ajax({
                        url: dynamicMenuData.ajaxurl,
                        method: 'GET',
                        data: {city_slug: citySlug},
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
                        },
                        success: function (response) {
                            if (response.success && response.practice_areas && response.practice_areas.length > 0) {
                                var menuItems = '';
                                response.practice_areas.forEach(function (area) {
                                    menuItems += '<li class="menu-item">' +
                                        '<a href="' + area.url + '" class="elementor-sub-item">' +
                                        (area.anchor_text || area.title) +
                                        '</a></li>';
                                });
                                $subMenu.html(menuItems);
                                $li.data('loaded', true);

                                // ✅ OPEN submenu *after* data loads
                                $subMenu.stop(true, true).slideDown(200);
                                $link.attr('aria-expanded', 'true');
                            } else {
                                $subMenu.html('<li class="menu-item"><a href="#" class="elementor-sub-item">No practice areas found</a></li>');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('AJAX error', error);
                            $subMenu.html('<li class="menu-item"><a href="#" class="elementor-sub-item">Error loading practice areas</a></li>');
                        }
                    });
                } else {
                    // Already loaded, just toggle normally
                    if ($subMenu.is(':visible')) {
                        $link.attr('aria-expanded', 'false');
                        $subMenu.slideUp(200);
                    } else {
                        $link.attr('aria-expanded', 'true');
                        $subMenu.slideDown(200);
                    }
                }
            }
        });
    });

    /**
     * Load practice areas for the default city
     */
    function loadDefaultCityPracticeAreas(citySlug) {
        if (!citySlug) return;

        // Find city name for the slug
        var cityName = "Default City";
        if (dynamicMenuData.city_pages && dynamicMenuData.city_pages.length) {
            dynamicMenuData.city_pages.forEach(function (city) {
                if (city.slug === citySlug) {
                    cityName = city.title;
                }
            });
        }

        // Update menus with the default city, passing default state slug if available
        updatePracticeAreasMenu(citySlug, dynamicMenuData.default_state || '');
        updatePracticeAreasWidget(citySlug, cityName, dynamicMenuData.default_state || '');
    }

    /**
     * Detect the current page and update menus accordingly
     */
    function detectCurrentPage() {
        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // normalize & split into segments
        var segments = window.location.pathname
            .replace(/^\/|\/$/g, '')  // strip leading/trailing slash
            .split('/')
            .filter(Boolean);

        // initialize slugs
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';
        var subPracticeAreaSlug = '';

        // ↑ NEW: decide which segment is which
        if (stateLayerEnabled) {
            if (segments.length >= 3) {
                stateSlug = segments[0];
                citySlug = segments[1];
                practiceAreaSlug = segments[2];
                subPracticeAreaSlug = segments[3] || '';
            } else {
                // fallback to default state + 2-segment logic
                stateSlug = defaultState;
                citySlug = segments[0] || dynamicMenuData.default_city;
                practiceAreaSlug = segments[1] || '';
            }
        } else {
            citySlug = segments[0] || dynamicMenuData.default_city;
            practiceAreaSlug = segments[1] || '';
            subPracticeAreaSlug = segments[2] || '';
        }

        // stored context
        var storedCitySlug = sessionStorage.getItem('currentCitySlug');
        var storedCityName = sessionStorage.getItem('currentCityName');

        // detect page-type
        var isCityPage = false;
        var isPracticeAreaPage = false;
        var isSubPracticeAreaPage = false;
        var cityName = '';

        // 1) Is this one of our city pages?
        if (dynamicMenuData.city_pages && dynamicMenuData.city_pages.length) {
            dynamicMenuData.city_pages.forEach(function (cityPage) {
                if (cityPage.slug === citySlug) {
                    isCityPage = true;
                    cityName = cityPage.title;
                    // store context
                    sessionStorage.setItem('currentCitySlug', citySlug);
                    sessionStorage.setItem('currentCityName', cityName);
                    return;
                }
            });
        }

        // 2) Practice area / sub-practice area?
        if (practiceAreaSlug) {
            isPracticeAreaPage = true;
            window.currentPracticeAreaSlug = practiceAreaSlug;
        }
        if (subPracticeAreaSlug) {
            isSubPracticeAreaPage = true;
        }

        // --- BRANCH A: we found a city in the URL ---
        if (isCityPage) {
            // update the left-hand menu
            updatePracticeAreasMenu(citySlug, stateSlug);

            if (isPracticeAreaPage) {
                // if there's a nested sub-practice, show those
                updatePracticeAreasWidgetForSubPracticeAreas(
                    citySlug,
                    practiceAreaSlug,
                    cityName,
                    stateSlug
                );
            } else {
                // just a city page, show all practice-areas
                updatePracticeAreasWidget(
                    citySlug,
                    cityName,
                    stateSlug
                );
            }

            // --- BRANCH B: no city in URL but we have stored context ---
        } else if (storedCitySlug) {
            var isPracticeAreaPath = (
                segments.length >= 2 &&
                segments[0] === storedCitySlug
            );

            if (isPracticeAreaPath) {
                // keep the old city context
                updatePracticeAreasMenu(storedCitySlug, /*stateSlug?*/ '');

                if (segments.length >= 3) {
                    // e.g. /{city}/{practice}/{subPractice}
                    updatePracticeAreasWidgetForSubPracticeAreas(
                        storedCitySlug,
                        segments[1],
                        storedCityName,
                        /*stateSlug?*/ ''
                    );
                } else {
                    // e.g. /{city}/{practice}
                    updatePracticeAreasWidgetForSubPracticeAreas(
                        storedCitySlug,
                        segments[1],
                        storedCityName,
                        /*stateSlug?*/ ''
                    );
                }
            } else {
                // unrelated page → clear context + restore defaults
                sessionStorage.removeItem('currentCitySlug');
                sessionStorage.removeItem('currentCityName');
                restoreOriginalPracticeAreasMenu();

                if (dynamicMenuData.default_city) {
                    loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
                } else {
                    restoreOriginalPracticeAreasWidget();
                }
            }

            // --- BRANCH C: no city context at all ---
        } else {
            restoreOriginalPracticeAreasMenu();

            if (dynamicMenuData.default_city) {
                loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
            } else {
                restoreOriginalPracticeAreasWidget();
            }
        }
    }

    /**
     * Update the Practice Areas Widget to show sub-practice areas
     */
    function updatePracticeAreasWidgetForSubPracticeAreas(citySlug, practiceAreaSlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);

        if (!$widgets.length) {
            return; // No widget on the page
        }

        // First, try to get the practice area page ID
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    // Find the current practice area in the response
                    var currentPracticeArea = null;
                    response.practice_areas.forEach(function (practiceArea) {
                        if (practiceArea.slug === practiceAreaSlug) {
                            currentPracticeArea = practiceArea;
                        }
                    });

                    if (currentPracticeArea) {
                        // Now get sub-practice areas for this practice area
                        getSubPracticeAreas(currentPracticeArea.id, citySlug, cityName, practiceAreaSlug, stateSlug, $widgets);
                    } else {
                        // Fall back to showing regular practice areas
                        updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                    }
                } else {
                    // No practice areas found, fall back to regular widget update
                    updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas:', error);
                // Fall back to regular widget update
                updatePracticeAreasWidget(citySlug, cityName, stateSlug);
            }
        });
    }

    /**
     * Get sub-practice areas for a practice area
     */
    function getSubPracticeAreas(practiceAreaId, citySlug, cityName, practiceAreaSlug, stateSlug, $widgets) {

        // Get current page path to identify which page we're on
        var currentPath = window.location.pathname;
        var currentPathSegments = currentPath.split('/').filter(Boolean);
        var currentSubPageSlug = currentPathSegments.length >= 3 ? currentPathSegments[2] : '';

        // Use the REST API endpoint
        $.ajax({
            url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-sub-practice-areas'),
            method: 'GET',
            data: {
                practice_area_id: practiceAreaId,
                city_slug: citySlug,
                state_slug: stateSlug,
                practice_area_slug: practiceAreaSlug
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {

                if (response.success && response.sub_practice_areas && response.sub_practice_areas.length > 0) {
                    // Update widgets with sub-practice areas
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

                        // Update widget title if present
                        var $widgetTitle;
                        if (isElementor) {
                            $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title');
                        } else {
                            $widgetTitle = $widget.prev('h2.widget-title');
                            if (!$widgetTitle.length) {
                                $widgetTitle = $widget.find('.practice-areas-title');
                            }
                        }

                        if ($widgetTitle.length) {
                            var practiceAreaTitle = response.practice_area_title || practiceAreaSlug;
                            var widgetTitle = practiceAreaTitle + ' Resources';

                            // Apply uppercase if setting is enabled
                            if (dynamicMenuData.uppercase_menu === 'yes') {
                                widgetTitle = widgetTitle.toUpperCase();
                            }

                            $widgetTitle.text(widgetTitle);
                            // Make sure title is visible
                            $widgetTitle.css('visibility', 'visible');
                        }

                        // Clear loading message and populate list
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();

                        // Filter out current page and add sub-practice area items to widget
                        var filteredSubPracticeAreas = response.sub_practice_areas.filter(function (subPracticeArea) {
                            return subPracticeArea.slug !== currentSubPageSlug;
                        });

                        // Only show widget if we have sub-practice areas to display after filtering
                        if (filteredSubPracticeAreas.length > 0) {
                            filteredSubPracticeAreas.forEach(function (subPracticeArea) {
                                $list.append(
                                    '<li class="practice-area-item">' +
                                    '<a href="' + subPracticeArea.url + '" class="' + (subPracticeArea.anchor_text ? 'using-anchor-text' : '') + '">' +
                                    (subPracticeArea.anchor_text || subPracticeArea.title) +
                                    '</a>' +
                                    '</li>'
                                );
                            });
                        } else if (currentSubPageSlug) {
                            // We're on a sub-practice area page with no other siblings to show
                            // Fall back to showing practice areas for the city
                            updatePracticeAreasWidget(citySlug, cityName);
                        } else {
                            $list.html('<li class="no-practice-areas">No additional resources available</li>');
                        }

                        // Add class to indicate content is loaded
                        $widget.addClass('content-loaded');
                    });
                } else {
                    // No sub-practice areas found, fall back to showing regular practice areas
                    updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching sub-practice areas:', error);
                // Fall back to showing regular practice areas
                updatePracticeAreasWidget(citySlug, cityName, stateSlug);
            }
        });
    }

    /**
     * Update the Practice Areas menu based on the selected city (and optional state)
     *
     * @param {string} citySlug
     * @param {string} stateSlug
     */
    function updatePracticeAreasMenu(citySlug, stateSlug) {
        // Don’t re-run if nothing changed
        if (citySlug === currentCitySlug && stateSlug === currentStateSlug) {
            return;
        }

        // store the new context
        currentCitySlug = citySlug;
        currentStateSlug = stateSlug || '';

        // Detect whether we’re using the Elementor mega-menu
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        // Fetch the practice areas from WP REST
        $.ajax({
            url: dynamicMenuData.ajaxurl,   // get-practice-areas endpoint
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug      // ← NEW: include state
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    if (usingElementorMegaMenu) {
                        updateElementorPracticeAreasMenu(response);
                    } else {
                        updateStandardPracticeAreasMenu(response);
                    }
                } else {
                    // fallback: clear or restore if no practice areas found
                    restoreOriginalPracticeAreasMenu();
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas:', error);
                restoreOriginalPracticeAreasMenu();
            }
        });
    }

    /**
     * Update standard WordPress menu for practice areas
     */
    function updateStandardPracticeAreasMenu(response) {
        // Update practice areas menu with city-specific practice areas
        var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);

        // Use city anchor text if available
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';

        // Apply uppercase if setting is enabled
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }

        $practiceAreasMenu.find('> a').text(menuText);

        // Create sub-menu if it doesn't exist
        var $subMenu = $practiceAreasMenu.find('.sub-menu');
        if ($subMenu.length === 0) {
            // Create submenu with proper attributes
            var submenuId = 'sm-' + Math.floor(Math.random() * 100000000) + '-2';
            var linkId = submenuId.replace('-2', '-1');

            // Update the link attributes
            var $link = $practiceAreasMenu.find('> a');
            $link.addClass('has-submenu');
            $link.attr({
                'id': linkId,
                'aria-haspopup': 'true',
                'aria-controls': submenuId,
                'aria-expanded': 'false'
            });

            // Add dropdown arrow if it doesn't exist
            if (!$link.find('.sub-arrow').length) {
                $link.append('<span class="sub-arrow"><i class="fas fa-caret-down"></i></span>');
            }

            // Add the submenu
            $subMenu = $('<ul class="sub-menu elementor-nav-menu--dropdown"></ul>');
            $subMenu.attr({
                'id': submenuId,
                'role': 'group',
                'aria-hidden': 'true',
                'aria-labelledby': linkId,
                'aria-expanded': 'false'
            });

            $practiceAreasMenu.append($subMenu);

            // Make sure the parent has needed class
            $practiceAreasMenu.addClass('menu-item-has-children');
        } else {
            $subMenu.empty();
        }

        // Add practice area items to sub-menu using anchor text if available
        response.practice_areas.forEach(function (practiceArea) {
            $subMenu.append(
                '<li class="menu-item">' +
                '<a href="' + practiceArea.url + '" class="elementor-sub-item">' +
                (practiceArea.anchor_text || practiceArea.title) +
                '</a>' +
                '</li>'
            );
        });

        // Make the practice areas menu clickable
        $practiceAreasMenu.find('> a').attr('href', '#');
    }

    /**
     * Update Elementor Mega Menu for practice areas
     */
    function updateElementorPracticeAreasMenu(response) {
        // Find the practice areas container in the Elementor menu
        var $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');

        // Also look for the mobile menu version
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }

        if (!$practiceAreasMenu.length) {
            console.error('Practice Areas menu not found in Elementor Mega Menu');
            return;
        }

        // Use city anchor text if available
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';

        // Apply uppercase if setting is enabled
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }

        // Update the title
        $practiceAreasMenu.find('.e-n-menu-title-text, > a').text(menuText);

        // Check if we're in the mobile menu
        var isMobileMenu = $practiceAreasMenu.closest('.elementor-nav-menu--dropdown').length > 0;

        if (isMobileMenu) {
            // Handle mobile menu updating
            var $subMenu = $practiceAreasMenu.find('.sub-menu');

            if ($subMenu.length) {
                $subMenu.empty();

                // Add practice area items to sub-menu using anchor text if available
                response.practice_areas.forEach(function (practiceArea) {
                    $subMenu.append(
                        '<li class="menu-item">' +
                        '<a href="' + practiceArea.url + '" class="elementor-sub-item">' +
                        (practiceArea.anchor_text || practiceArea.title) +
                        '</a>' +
                        '</li>'
                    );
                });
            }

            return;
        }

        // Desktop menu updates below
        var $contentContainer = $('#pamenu');

        if (!$contentContainer.length) {
            // Try to find the content container
            $contentContainer = $practiceAreasMenu.find('.e-con-inner');

            if (!$contentContainer.length) {
                console.error('Practice Areas content container not found');
                return;
            }
        }

        // Clear existing content
        $contentContainer.empty();

        // Create grid and columns for practice areas
        var $grid = $('<div class="elementor-element e-grid e-con-full e-con e-child"></div>');
        var $list = $('<div class="elementor-element elementor-icon-list--layout-traditional elementor-list-item-link-full_width elementor-widget elementor-widget-icon-list"><div class="elementor-widget-container"><ul class="elementor-icon-list-items"></ul></div></div>');

        // Add practice area items to list using anchor text if available
        var $itemsList = $list.find('.elementor-icon-list-items');

        response.practice_areas.forEach(function (practiceArea) {
            $itemsList.append(
                '<li class="elementor-icon-list-item">' +
                '<a href="' + practiceArea.url + '">' +
                '<span class="elementor-icon-list-text">' +
                (practiceArea.anchor_text || practiceArea.title) +
                '</span></a>' +
                '</li>'
            );
        });

        $grid.append($list);
        $contentContainer.append($grid);

        // Make the practice areas menu clickable
        $practiceAreasMenu.find('.e-n-menu-title-container, > a').attr('href', '#');
    }

    /**
     * Restore the original practice areas menu
     */
    function restoreOriginalPracticeAreasMenu() {
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        if (usingElementorMegaMenu) {
            // Restore Elementor mega menu title
            var $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
            if ($practiceAreasMenu.length) {
                $practiceAreasMenu.find('.e-n-menu-title-text').text('PRACTICE AREAS');
            }
        } else if (originalPracticeAreasMenu) {
            // Get current mobile menu structure
            var $currentMenu = $(dynamicMenuData.menu_selectors.practice_areas);
            var isMobileMenu = $currentMenu.closest('.elementor-nav-menu--dropdown').length > 0;

            if (isMobileMenu) {
                // In mobile view, just update the text but preserve structure
                $currentMenu.find('> a').text('Practice Areas');

                // Clear out submenu items but keep the structure
                var $subMenu = $currentMenu.find('.sub-menu');
                if ($subMenu.length) {
                    $subMenu.empty();
                    $subMenu.append('<li class="menu-item"><a href="#" class="elementor-sub-item">Please select a city</a></li>');
                }
            } else {
                // In desktop view, do a full replacement
                var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
                $practiceAreasMenu.replaceWith(originalPracticeAreasMenu.clone());
            }
        }

        currentCitySlug = null;
        currentCityName = null;
    }

    /**
     * Update the Practice Areas widget based on the selected city (and optional state)
     *
     * @param {string} citySlug   – the city slug
     * @param {string} cityName   – the city’s display name (for “No areas found” messaging)
     * @param {string} stateSlug  – the state slug (empty if none)
     */
    function updatePracticeAreasWidget(citySlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);
        if (!$widgets.length) {
            return; // nothing to do
        }

        $.ajax({
            url: dynamicMenuData.ajaxurl,  // your get-practice-areas endpoint
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug           // ← NEW: pass the state along
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

                        // Find or generate the widget title element
                        var $widgetTitle;
                        if (isElementor) {
                            $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector)
                                .find('.practice-areas-title');
                        } else {
                            $widgetTitle = $widget.prev('h2.widget-title');
                            if (!$widgetTitle.length) {
                                $widgetTitle = $widget.find('.practice-areas-title');
                            }
                        }

                        // Update the title
                        if ($widgetTitle.length) {
                            var cityDisplayName = response.city_anchor_text || response.city_name || cityName;
                            var titleText = cityDisplayName + ' Practice Areas';

                            if (dynamicMenuData.uppercase_menu === 'yes') {
                                titleText = titleText.toUpperCase();
                            }
                            $widgetTitle.text(titleText)
                                .css('visibility', 'visible');
                        }

                        // Populate the list
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();
                        response.practice_areas.forEach(function (pa) {
                            $list.append(
                                '<li class="practice-area-item">' +
                                '<a href="' + pa.url + '" ' +
                                (pa.anchor_text ? 'class="using-anchor-text"' : '') +
                                '>' +
                                (pa.anchor_text || pa.title) +
                                '</a>' +
                                '</li>'
                            );
                        });

                        $widget.addClass('content-loaded');
                    });

                } else {
                    // No practice areas found
                    $widgets.each(function () {
                        var $widget = $(this);
                        var $list = $widget.find('.practice-areas-list');
                        $list.html(
                            '<li class="no-practice-areas">' +
                            'No practice areas found for ' + cityName +
                            '</li>'
                        );
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas for widget:', error);
                restoreOriginalPracticeAreasWidget();
            }
        });
    }

    /**
     * Restore the original practice areas widget
     */
    function restoreOriginalPracticeAreasWidget() {
        var $widgets = $(dynamicMenuData.widget_selector);

        if (!$widgets.length) {
            return; // No widget on the page
        }

        $widgets.each(function () {
            var $widget = $(this);
            var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

            // Reset title
            var $widgetTitle;
            if (isElementor) {
                $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title');
            } else {
                $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.practice-areas-title');
                }
            }

            if ($widgetTitle.length) {
                // Try to get original title from data attribute, or revert to "Practice Areas"
                var originalTitle = $widgetTitle.data('original-title') || 'Practice Areas';
                $widgetTitle.text(originalTitle);
                $widgetTitle.css('visibility', 'visible');
            }

            // Reset content
            var $list = $widget.find('.practice-areas-list');
            $list.html('<li class="select-city-message">Please select a city to view practice areas</li>');

            // Add class to indicate content is loaded
            $widget.addClass('content-loaded');
        });
    }

    /**
     * Update the Related Locations widget
     */
    function updateRelatedLocationsWidget() {
        var $widgets = $('.dynamic-related-locations-widget');

        if (!$widgets.length) {
            return;
        }

        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // normalize & split path into segments
        var segments = window.location.pathname
            .replace(/^\/|\/$/g, '')
            .split('/')
            .filter(Boolean);

        // initialize slugs
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';

        // decide which segment is which
        if (stateLayerEnabled) {
            if (segments.length >= 3) {
                stateSlug = segments[0];
                citySlug = segments[1];
                practiceAreaSlug = segments[2];
            } else {
                // fallback to default state + 2-segment logic
                stateSlug = defaultState;
                citySlug = segments[0] || dynamicMenuData.default_city;
                practiceAreaSlug = segments[1] || '';
            }
        } else {
            citySlug = segments[0];
            practiceAreaSlug = segments[1];
        }

        // check if this is a valid city/practice page
        var isCityPracticePage = false;
        if (citySlug && practiceAreaSlug && dynamicMenuData.city_pages) {
            dynamicMenuData.city_pages.forEach(function (cityPage) {
                if (cityPage.slug === citySlug) {
                    isCityPracticePage = true;
                }
            });
        }

        // Not a city/practice page? show all or default
        if (!isCityPracticePage) {
            if (dynamicMenuData.default_city) {
                // console.log('Not on a city/practice page, using default city:', dynamicMenuData.default_city);
                showAllLocations($widgets);
            } else {
                // console.log('Not on a city/practice page and no default city set');
                showAllLocations($widgets);
            }
            return;
        }

        // Otherwise, fetch & render related locations
        $.ajax({
            url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-related-locations'),
            method: 'GET',
            data: {
                practice_area_slug: practiceAreaSlug,
                city_slug: citySlug,
                state_slug: stateSlug       // ← include state here
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                $widgets.each(function () {
                    var $widget = $(this);
                    var $list = $widget.find('.related-locations-list');
                    var $title = $widget.find('.related-locations-title');

                    $list.empty();

                    if (response.related_locations && response.related_locations.length) {
                        if ($title.length) {
                            $title.css('visibility', 'visible');
                        }

                        response.related_locations.forEach(function (location) {
                            $list.append(
                                '<li class="related-location-item">' +
                                '<a href="' + location.practice_area_url + '">' +
                                location.practice_area_display_text +
                                ' In ' + location.city_display_text +
                                '</a>' +
                                '</li>'
                            );
                        });
                    } else {
                        var showEmpty = $widget.data('show-empty') !== false;
                        var emptyMessage = $widget.data('empty-message') ||
                            'No other locations offer this practice area';

                        if (showEmpty) {
                            $list.html('<li class="no-related-locations">' + emptyMessage + '</li>');
                        } else {
                            $widget.hide();
                        }
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error('Error fetching related locations:', error);
            }
        });
    }

    /**
     * Show all available locations
     */

    /**
     * Show all available locations
     */
    /**
     * Show all available locations
     */
    function showAllLocations($widgets) {
        // Parse each city’s own URL for its state slug
        // Hide widget titles initially
        $widgets.each(function () {
            var $widget = $(this);
            var isElementor = $widget.closest(dynamicMenuData.related_elementor_selector).length > 0;

            // Find the title
            var $widgetTitle;
            if (isElementor) {
                $widgetTitle = $widget.closest(dynamicMenuData.related_elementor_selector).find('.related-locations-title');
            } else {
                $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.related-locations-title');
                }
            }

            // Hide the title initially
            if ($widgetTitle.length) {
                $widgetTitle.css('visibility', 'hidden');
                // Store original title if not already stored
                if (!$widgetTitle.data('original-title')) {
                    $widgetTitle.data('original-title', $widgetTitle.text());
                }
            }
        });

        // Get current page URL to identify current location
        var currentUrlPath = window.location.pathname;
        var currentPathSegments = currentUrlPath.split('/').filter(Boolean);
        var currentCitySlug = currentPathSegments.length >= 1 ? currentPathSegments[0] : '';

        // Use the city pages data that's already available
        var cityPages = dynamicMenuData.city_pages;

        if (cityPages && cityPages.length > 0) {
            // Filter out:
            // 1. Items with href="#"
            // 2. Items containing "Areas We Serve" in title
            // 3. The current location we're viewing
            var filteredCityPages = cityPages.filter(function (city) {
                return city.url !== '#' &&
                    city.url.indexOf('#') !== (city.url.length - 1) &&
                    !city.title.includes('Areas We Serve') &&
                    !city.title.includes('More Areas') &&
                    city.slug !== currentCitySlug; // Filter out current location
            });

            // Get all filtered city pages' data (including anchor text)
            var pagePromises = filteredCityPages.map(function (city) {
                // Determine this city’s state slug from its URL
                var cityPath = new URL(city.url).pathname.replace(/^\/|\/$/g, '');
                var citySegments = cityPath.split('/');
                var stateSlugForCity = '';
                if (dynamicMenuData.state_layer_enabled === 'yes') {
                    stateSlugForCity = citySegments.length >= 2 ? citySegments[0] : dynamicMenuData.default_state || '';
                }
                // Return a promise for each page
                return new Promise(function (resolve) {
                    // First, check if we already have anchor text from WordPress
                    // Try to fetch this information from the REST API to get the accurate anchor_text
                    $.ajax({
                        url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-practice-areas'),
                        method: 'GET',
                        data: {
                            city_slug: city.slug,
                            state_slug: stateSlugForCity
                        },
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
                        },
                        success: function (apiResponse) {
                            // If we got a valid response with city anchor text, use it
                            if (apiResponse.success && apiResponse.city_anchor_text) {
                                // console.log('Got anchor text from API for ' + city.slug + ': ' + apiResponse.city_anchor_text);

                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: city.title,
                                    anchorText: apiResponse.city_anchor_text
                                });
                            } else {
                                // Fallback to scraping the page
                                fallbackToPageScraping();
                            }
                        },
                        error: function () {
                            // Fallback to scraping the page
                            fallbackToPageScraping();
                        }
                    });

                    // Fallback function to scrape the page
                    function fallbackToPageScraping() {
                        $.ajax({
                            url: city.url,
                            method: 'GET',
                            success: function (data) {
                                // Extract the page title from the HTML
                                var pageTitle = $(data).filter('title').text() ||
                                    $(data).find('title').text() ||
                                    city.title;

                                // Try multiple methods to extract anchor text
                                var anchorText = '';

                                // Method 1: Look for meta[name='anchor-text']
                                var metaMatch = data.match(/<meta[^>]*?name=["']anchor[_\-]text["'][^>]*?content=["']([^"']+)["'][^>]*?>/i);
                                if (metaMatch && metaMatch[1]) {
                                    anchorText = metaMatch[1];
                                    // console.log('Found anchor text via meta tag for ' + city.slug + ': ' + anchorText);
                                } else {
                                    // Method 2: Look for data-anchor-text attribute
                                    var dataMatch = data.match(/data-anchor-text=["']([^"']+)["']/i);
                                    if (dataMatch && dataMatch[1]) {
                                        anchorText = dataMatch[1];
                                        // console.log('Found anchor text via data attribute for ' + city.slug + ': ' + anchorText);
                                    } else {
                                        // Method 3: Look for input[name='anchor_text']
                                        var inputMatch = data.match(/<input[^>]*?name=["']anchor_text["'][^>]*?value=["']([^"']+)["'][^>]*?>/i);
                                        if (inputMatch && inputMatch[1]) {
                                            anchorText = inputMatch[1];
                                            // console.log('Found anchor text via input for ' + city.slug + ': ' + anchorText);
                                        } else {
                                            // Method 4: Look for JSON text in script tags or serialized data
                                            var jsonMatch = data.match(/"anchor_text["']?\s*:\s*["']([^"']+)["']/i);
                                            if (jsonMatch && jsonMatch[1]) {
                                                anchorText = jsonMatch[1];
                                                // console.log('Found anchor text via JSON data for ' + city.slug + ': ' + anchorText);
                                            }
                                        }
                                    }
                                }

                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: pageTitle.trim(),
                                    anchorText: anchorText
                                });
                            },
                            error: function () {
                                // If request fails, use menu title
                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: city.title,
                                    anchorText: ''
                                });
                            }
                        });
                    }
                });
            });

            // When all page data is collected
            Promise.all(pagePromises).then(function (cityPagesWithTitles) {
                // Find common site name pattern in titles
                function findSiteNamePattern(titles) {
                    // Get all possible separators
                    var separators = [' - ', ' – ', ' | ', ' :: '];
                    var siteNamePattern = null;

                    // Try each separator
                    for (var i = 0; i < separators.length; i++) {
                        var separator = separators[i];
                        var potentialMatches = {};

                        // Check all titles for this separator
                        titles.forEach(function (title) {
                            var parts = title.split(separator);
                            if (parts.length > 1) {
                                // Get the last part as potential site name
                                var sitePart = parts[parts.length - 1].trim();
                                potentialMatches[sitePart] = potentialMatches[sitePart] || 0;
                                potentialMatches[sitePart]++;
                            }
                        });

                        // Find the most common site name pattern
                        var maxCount = 0;
                        var mostCommon = null;

                        for (var pattern in potentialMatches) {
                            if (potentialMatches[pattern] > maxCount) {
                                maxCount = potentialMatches[pattern];
                                mostCommon = pattern;
                            }
                        }

                        // If we found a pattern in more than 50% of titles, use it
                        if (mostCommon && maxCount >= titles.length * 0.5) {
                            siteNamePattern = {
                                separator: separator,
                                name: mostCommon
                            };
                            break;
                        }
                    }

                    return siteNamePattern;
                }

                // Get all titles
                var allTitles = cityPagesWithTitles.map(function (city) {
                    return city.pageTitle;
                });

                // Find site name pattern
                var sitePattern = findSiteNamePattern(allTitles);

                // Clean titles if pattern found
                if (sitePattern) {
                    cityPagesWithTitles.forEach(function (city) {
                        var fullPattern = sitePattern.separator + sitePattern.name;
                        if (city.pageTitle.endsWith(fullPattern)) {
                            city.pageTitle = city.pageTitle.substring(0, city.pageTitle.length - fullPattern.length).trim();
                        }
                    });
                }

                // Sort by title
                cityPagesWithTitles.sort(function (a, b) {
                    // Use anchor text for sorting if available
                    var aText = a.anchorText || a.pageTitle;
                    var bText = b.anchorText || b.pageTitle;
                    return aText.localeCompare(bText);
                });

                // Log the complete city data for debugging
                // console.log('Complete city pages data:', cityPagesWithTitles);

                $widgets.each(function () {
                    var $widget = $(this);

                    // Update widget title
                    var isElementor = $widget.closest(dynamicMenuData.related_elementor_selector).length > 0;
                    var $widgetTitle;

                    if (isElementor) {
                        $widgetTitle = $widget.closest(dynamicMenuData.related_elementor_selector).find('.related-locations-title');
                    } else {
                        // Try multiple ways to find the title
                        $widgetTitle = $widget.prev('h2.widget-title');
                        if (!$widgetTitle.length) {
                            $widgetTitle = $widget.find('.related-locations-title');
                        }
                        if (!$widgetTitle.length) {
                            $widgetTitle = $widget.closest('.widget').find('.widget-title');
                        }
                    }

                    if ($widgetTitle.length) {
                        $widgetTitle.text('Locations Served');

                        // Apply uppercase if setting is enabled
                        if (dynamicMenuData.uppercase_menu === 'yes') {
                            $widgetTitle.text($widgetTitle.text().toUpperCase());
                        }

                        // Make title visible now that it's properly set
                        $widgetTitle.css('visibility', 'visible');
                    }

                    // Update the list with anchor text if available
                    var $list = $widget.find('.related-locations-list');
                    $list.empty();

                    cityPagesWithTitles.forEach(function (city) {
                        // ALWAYS use anchor text if available, otherwise use page title
                        var displayText = city.anchorText || city.pageTitle;

                        // Add debug information to help diagnose issues
                        // console.log('City: ' + city.slug + ' | Using: ' + (city.anchorText ? 'ANCHOR TEXT: ' + city.anchorText : 'PAGE TITLE: ' + city.pageTitle));

                        $list.append(
                            '<li class="location-item">' +
                            '<a href="' + city.url + '" class="' + (city.anchorText ? 'using-anchor-text' : 'using-page-title') + '" ' +
                            'data-source="' + (city.anchorText ? 'anchor' : 'title') + '" ' +
                            'data-slug="' + city.slug + '" ' +
                            'data-anchor="' + (city.anchorText || '') + '" ' +
                            '>' +
                            displayText +
                            '</a>' +
                            '</li>'
                        );
                    });

                    // Handle empty case
                    if (cityPagesWithTitles.length === 0) {
                        $list.html('<li class="no-locations">No other locations available</li>');
                    }

                    // Add class to indicate content is loaded
                    $widget.addClass('content-loaded');
                });
            });
        } else {
            $widgets.each(function () {
                var $widget = $(this);
                var $list = $widget.find('.related-locations-list');
                $list.html('<li class="no-locations">No locations found</li>');

                // Make title visible
                var $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.related-locations-title');
                }
                if ($widgetTitle.length) {
                    $widgetTitle.css('visibility', 'visible');
                }

                // Add class to indicate content is loaded
                $widget.addClass('content-loaded');
            });
        }
    }

// Close the IIFE
})(jQuery);
EOT;
        file_put_contents($js_file, $js_content);
    }

    // Create Gutenberg block JS file
    $block_js_file = $js_dir . '/dynamic-practice-areas-block.js';
    if (!file_exists($block_js_file)) {
        $block_js_content = <<<'EOT'
/**
 * Dynamic Practice Areas Block
 */
(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;
    var PanelBody = components.PanelBody;
    
    blocks.registerBlockType('dynamic-practice-areas/block', {
        title: 'Dynamic Practice Areas',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            title: {
                type: 'string',
                default: 'Practice Areas'
            },
            blockId: {
                type: 'string',
                default: 'block-' + Math.random().toString(36).substring(2, 15)
            }
        },
        
        edit: function(props) {
            var title = props.attributes.title;
            
            return [
                // Block inspector controls
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, {
                        title: 'Settings',
                        initialOpen: true
                    },
                        el(TextControl, {
                            label: 'Title',
                            value: title,
                            onChange: function(newTitle) {
                                props.setAttributes({ title: newTitle });
                            }
                        })
                    )
                ),
                
                // Block edit view
                el('div', { className: props.className },
                    el('h2', { className: 'practice-areas-block-title' }, title),
                    el('div', { className: 'block-preview' },
                        el('p', { className: 'block-description' }, 'This block will display practice areas for the selected city.')
                    )
                )
            ];
        },
        
        save: function() {
            // Dynamic block, render is handled by PHP
            return null;
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
));
EOT;
        file_put_contents($block_js_file, $block_js_content);
    }

    // Create CSS files for the block
    $block_css_file = $css_dir . '/dynamic-practice-areas-block.css';
    if (!file_exists($block_css_file)) {
        $block_css_content = <<<'EOT'
.wp-block-dynamic-practice-areas {
    margin: 1.5em 0;
}

.practice-areas-block-title {
    margin-bottom: 1em;
    font-size: 1.2em;
}

.dynamic-practice-areas-widget ul.practice-areas-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.dynamic-practice-areas-widget ul.practice-areas-list li {
    margin-bottom: 0.5em;
    padding-left: 0;
}

.dynamic-practice-areas-widget ul.practice-areas-list li a {
    display: block;
    padding: 5px 0;
    text-decoration: none;
    transition: all 0.3s ease;
}

.dynamic-practice-areas-widget ul.practice-areas-list li a:hover {
    padding-left: 5px;
}

.dynamic-practice-areas-widget .select-city-message,
.dynamic-practice-areas-widget .loading,
.dynamic-practice-areas-widget .no-practice-areas {
    color: #666;
    font-style: italic;
}
EOT;
        file_put_contents($block_css_file, $block_css_content);
    }

    // Create CSS for related locations block
    $related_locations_css_file = $css_dir . '/dynamic-related-locations.css';
    if (!file_exists($related_locations_css_file)) {
        $related_locations_css_content = <<<'EOT'
.elementor-dynamic-related-locations {
    margin: 1.5em 0;
}

.related-locations-title {
    margin-bottom: 1em;
    font-size: 1.2em;
}

.dynamic-related-locations-widget ul.related-locations-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.dynamic-related-locations-widget ul.related-locations-list li {
    margin-bottom: 0.5em;
    padding-left: 0;
}

.dynamic-related-locations-widget ul.related-locations-list li a {
    display: block;
    padding: 5px 0;
    text-decoration: none;
    transition: all 0.3s ease;
}

.dynamic-related-locations-widget ul.related-locations-list li a:hover {
    padding-left: 5px;
}

.dynamic-related-locations-widget .loading,
.dynamic-related-locations-widget .no-related-locations,
.dynamic-related-locations-widget .error {
    color: #666;
}

/* Add any additional styles needed for display_text and anchor_text formatting */
.dynamic-related-locations-widget ul.related-locations-list li a.using-anchor-text {
}
EOT;
        file_put_contents($related_locations_css_file, $related_locations_css_content);
    }

    $block_editor_css_file = $css_dir . '/dynamic-practice-areas-block-editor.css';
    if (!file_exists($block_editor_css_file)) {
        $block_editor_css_content = <<<'EOT'
.wp-block-dynamic-practice-areas {
    padding: 1em;
    border: 1px dashed #ccc;
    background: #f9f9f9;
}

.wp-block-dynamic-practice-areas .block-description {
    color: #666;
    font-style: italic;
}
EOT;
        file_put_contents($block_editor_css_file, $block_editor_css_content);
    }

    // Create templates directory if it doesn't exist
    $templates_dir = plugin_dir_path(__FILE__) . 'templates';
    if (!file_exists($templates_dir)) {
        mkdir($templates_dir, 0755, true);
    }

    // Create includes directory if it doesn't exist
    $includes_dir = plugin_dir_path(__FILE__) . 'includes';
    if (!file_exists($includes_dir)) {
        mkdir($includes_dir, 0755, true);
    }
}

// Handle plugin deactivation
function dynamic_practice_areas_deactivated()
{
    $product_id = 'dynamic_practice_areas';
    update_option($product_id . '_license_status', 'invalid');
    update_option($product_id . '_was_deactivated', '1');
}

// Handle plugin uninstallation
function dynamic_practice_areas_uninstall()
{
    $product_id = 'dynamic_practice_areas';
    delete_option($product_id . '_license_key');
    delete_option($product_id . '_license_status');
    delete_option($product_id . '_activation_count');
    delete_option($product_id . '_max_sites');
    delete_option($product_id . '_expiration');
    delete_option($product_id . '_last_license_check');
    delete_option($product_id . '_first_activation');
    delete_option($product_id . '_was_deactivated');
    delete_option($product_id . '_show_reactivation_notice');
}

register_uninstall_hook(__FILE__, 'dynamic_practice_areas_uninstall');

register_activation_hook(__FILE__, 'dynamic_menu_activate');

function enyutech_register_anchor_text_meta()
{
    register_post_meta('page', 'anchor_text', [
        'show_in_rest' => true, // Required for Gutenberg visibility
        'single' => true,
        'type' => 'string',
        'auth_callback' => function () {
            return current_user_can('edit_pages');
        },
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}
