<?php
/**
 * Template functions for the Dynamic Practice Areas Menu plugin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle custom page templates
 */
class Dynamic_Practice_Areas_Templates
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add custom page templates
        add_filter('theme_page_templates', array($this, 'add_page_templates'));
        add_filter('template_include', array($this, 'load_page_templates'));

        // Add display functions
        add_action('wp_footer', array($this, 'output_template_helper_functions'));
    }

    /**
     * Add page templates to the theme templates
     *
     * @param  array  $templates  Array of page templates
     * @return array Modified array of page templates
     */
    public function add_page_templates($templates)
    {
        $templates['templates/city-page-template.php'] = 'City Page';
        $templates['templates/practice-area-template.php'] = 'Practice Area';
        return $templates;
    }

    /**
     * Load page template
     *
     * @param  string  $template  Current template path
     * @return string Modified template path
     */
    public function load_page_templates($template)
    {
        global $post;

        if (!$post) {
            return $template;
        }

        // Get the template name from the post meta
        $template_name = get_post_meta($post->ID, '_wp_page_template', true);

        // Check if a plugin template is selected
        if ('templates/city-page-template.php' === $template_name) {
            $path = plugin_dir_path(dirname(__FILE__)).'templates/city-page-template.php';
            if (file_exists($path)) {
                return $path;
            }
        }

        if ('templates/practice-area-template.php' === $template_name) {
            $path = plugin_dir_path(dirname(__FILE__)).'templates/practice-area-template.php';
            if (file_exists($path)) {
                return $path;
            }
        }

        return $template;
    }

    /**
     * Output helper functions for templates
     */
    public function output_template_helper_functions()
    {
        // Only output if we're on a page using a plugin template
        global $post;

        if (!$post) {
            return;
        }

        $template_name = get_post_meta($post->ID, '_wp_page_template', true);

        if ('templates/city-page-template.php' === $template_name || 'templates/practice-area-template.php' === $template_name) {
            // Output helper functions
            ?>
            <script>
                // Initialize widgets on our template pages
                (function ($) {
                    $(document).ready(function () {
                        // For city pages, display practice areas
                        if ($('.city-header').length) {
                            // Dynamic content will be loaded via AJAX
                        }

                        // For practice area pages, display related locations
                        if ($('.practice-area-header').length) {
                            // Dynamic content will be loaded via AJAX
                        }
                    });
                })(jQuery);
            </script>
            <?php
        }
    }
}

// Initialize templates
$dynamic_practice_areas_templates = new Dynamic_Practice_Areas_Templates();

/**
 * Add custom meta box for anchor text field
 */
function dynamic_practice_areas_add_anchor_text_meta_box()
{
    add_meta_box(
        'anchor_text_meta_box',
        'Anchor Text',
        'dynamic_practice_areas_render_anchor_text_meta_box',
        'page',
        'side',
        'high'
    );
}

add_action('add_meta_boxes', 'dynamic_practice_areas_add_anchor_text_meta_box');

/**
 * Render meta box content
 *
 * @param  WP_Post  $post  The post object
 */
function dynamic_practice_areas_render_anchor_text_meta_box($post)
{
    // Add nonce for security
    wp_nonce_field('dynamic_practice_areas_anchor_text_meta_box', 'dynamic_practice_areas_anchor_text_meta_box_nonce');

    // Get current value
    $anchor_text = get_post_meta($post->ID, 'anchor_text', true);

    // Output field
    echo '<p><label for="anchor_text_field">Enter the text to be used in menus and widgets:</label></p>';
    echo '<input type="text" id="anchor_text_field" name="anchor_text" value="'.esc_attr($anchor_text).'" style="width:100%;" />';
    echo '<p class="description">If left empty, the page title will be used.</p>';
}

/**
 * Save meta box content
 *
 * @param  int  $post_id  The post ID
 */
function dynamic_practice_areas_save_anchor_text_meta_box($post_id)
{
    // Check if nonce is set
    if (!isset($_POST['dynamic_practice_areas_anchor_text_meta_box_nonce'])) {
        return;
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['dynamic_practice_areas_anchor_text_meta_box_nonce'],
        'dynamic_practice_areas_anchor_text_meta_box')) {
        return;
    }

    // Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if ('page' === $_POST['post_type'] && !current_user_can('edit_page', $post_id)) {
        return;
    }

    // Save the anchor text
    if (isset($_POST['anchor_text'])) {
        update_post_meta($post_id, 'anchor_text', sanitize_text_field($_POST['anchor_text']));
    }
}

add_action('save_post', 'dynamic_practice_areas_save_anchor_text_meta_box');

/**
 * Helper function to display practice areas list in templates
 */
function dynamic_practice_areas_display()
{
    echo '<div class="dynamic-practice-areas-widget" data-programmatic="true">';
    echo '<h3 class="widget-title practice-areas-title">Practice Areas</h3>';
    echo '<ul class="practice-areas-list">';
    echo '<li class="loading">Loading practice areas...</li>';
    echo '</ul>';
    echo '</div>';
}

/**
 * Helper function to display related locations in templates
 */
function dynamic_related_locations_display()
{
    echo '<div class="dynamic-related-locations-widget" data-programmatic="true">';
    echo '<h3 class="widget-title related-locations-title">Also Available In:</h3>';
    echo '<ul class="related-locations-list">';
    echo '<li class="loading">Finding related locations...</li>';
    echo '</ul>';
    echo '</div>';
}