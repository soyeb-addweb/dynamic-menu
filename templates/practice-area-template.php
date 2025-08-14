<?php
/**
 * Template Name: Practice Area
 * Template Post Type: page
 *
 * A template for practice area pages that support custom anchor text display
 * Compatible with Hello Elementor theme
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Get the current theme's header
get_header();

// Get the anchor text if it exists
$anchor_text = get_post_meta(get_the_ID(), 'anchor_text', true);

// Get city information (parent page)
$parent_id = wp_get_post_parent_id(get_the_ID());
$city_name = get_the_title($parent_id);
$city_anchor_text = get_post_meta($parent_id, 'anchor_text', true);
$city_display_name = !empty($city_anchor_text) ? $city_anchor_text : $city_name;

while (have_posts()) :
    the_post();
    ?>
    <main id="content" <?php
    post_class('site-main'); ?>>
        <?php
        if (apply_filters('hello_elementor_page_title', true)) : ?>
            <div class="page-header practice-area-header">
                <?php
                the_title('<h1 class="entry-title practice-area-title">', '</h1>'); ?>
                <?php
                if (!empty($anchor_text)) : ?>
                    <div class="practice-area-anchor-text" style="display:none;" data-anchor-text="<?php
                    echo esc_attr($anchor_text); ?>"></div>
                <?php
                endif; ?>
                <div class="practice-area-city">Serving <?php
                    echo esc_html($city_display_name); ?></div>
            </div>
        <?php
        endif; ?>

        <div class="page-content practice-area-content">
            <?php
            the_content(); ?>
            <?php
            wp_link_pages(); ?>
        </div>

        <?php
        comments_template(); ?>
    </main>
<?php
endwhile;

// Get the current theme's footer
get_footer();