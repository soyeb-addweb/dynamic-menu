<?php
/**
 * Template Name: City Page
 * Template Post Type: page
 *
 * A template for city pages that support custom anchor text display
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

while (have_posts()) :
    the_post();
    ?>
    <main id="content" <?php
    post_class('site-main'); ?>>
        <?php
        if (apply_filters('hello_elementor_page_title', true)) : ?>
            <div class="page-header city-header">
                <?php
                the_title('<h1 class="entry-title city-title">', '</h1>'); ?>
                <?php
                if (!empty($anchor_text)) : ?>
                    <div class="city-anchor-text" style="display:none;" data-anchor-text="<?php
                    echo esc_attr($anchor_text); ?>"></div>
                <?php
                endif; ?>
            </div>
        <?php
        endif; ?>

        <div class="page-content city-content">
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