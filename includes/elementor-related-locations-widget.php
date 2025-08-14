<?php
/**
 * Dynamic-Related Locations Elementor Widget
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Elementor widget for Dynamic Related Locations
 */
class Dynamic_Related_Locations_Elementor_Widget extends Base_Elementor_Widget
{
    /**
     * Get widget name
     */
    public function get_name()
    {
        return 'dynamic_related_locations';
    }

    /**
     * Get widget title
     */
    public function get_title()
    {
        return __('Related Practice Area Locations', 'dynamic-practice-areas-menu');
    }

    /**
     * Get widget icon
     */
    public function get_icon()
    {
        return 'eicon-map-pin';
    }

    /**
     * Get widget categories
     */
    public function get_categories()
    {
        return ['dynamic-practice-areas'];
    }

    /**
     * Get widget keywords
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
                'description' => __('Show a message when no related locations are found',
                    'dynamic-practice-areas-menu'),
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

        // Register Style Controls from Base Class
        $this->register_container_style_controls('.elementor-dynamic-related-locations');
        $this->register_style_controls('.related-locations-list li a');
        $this->register_hover_style_controls('.related-locations-list li a');

        // Title Styles - Enhanced version
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Title Style', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
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
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .related-locations-title',
            ]
        );

        // Title Alignment Control
        $this->add_responsive_control(
            'title_alignment',
            [
                'label' => __('Alignment', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'dynamic-practice-areas-menu'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'dynamic-practice-areas-menu'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'dynamic-practice-areas-menu'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => __('Justified', 'dynamic-practice-areas-menu'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .related-locations-title' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Margin', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .related-locations-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_padding',
            [
                'label' => __('Padding', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .related-locations-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'title_text_shadow',
                'selector' => '{{WRAPPER}} .related-locations-title',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'title_border',
                'selector' => '{{WRAPPER}} .related-locations-title',
            ]
        );

        $this->add_control(
            'title_border_radius',
            [
                'label' => __('Border Radius', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .related-locations-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'title_background',
                'label' => __('Background', 'dynamic-practice-areas-menu'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .related-locations-title',
            ]
        );

        $this->end_controls_section();

        // Empty Message Styles
        $this->start_controls_section(
            'section_empty_message_style',
            [
                'label' => __('Empty Message Style', 'dynamic-practice-areas-menu'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_empty_message' => 'yes',
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
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'empty_message_typography',
                'selector' => '{{WRAPPER}} .related-locations-list .no-related-locations',
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
        <div <?php
        echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php
            if ('yes' === $settings['show_title'] && !empty($settings['title'])) : ?>
                <h3 <?php
                echo $this->get_render_attribute_string('title'); ?> data-original-title="<?php
                echo esc_attr($settings['title']); ?>">
                    <?php
                    echo esc_html($settings['title']); ?>
                </h3>
            <?php
            endif; ?>

            <div class="dynamic-related-locations-widget" data-elementor-id="<?php
            echo esc_attr($widget_id); ?>" data-show-empty="<?php
            echo ('yes' === $settings['show_empty_message']) ? 'true' : 'false'; ?>" data-empty-message="<?php
            echo esc_attr($settings['empty_message']); ?>">
                <ul class="related-locations-list">
                    <li class="loading"><?php
                        _e('Finding related locations...', 'dynamic-practice-areas-menu'); ?></li>
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

            <div class="dynamic-related-locations-widget"
                 data-show-empty="{{ 'yes' === settings.show_empty_message ? 'true' : 'false' }}"
                 data-empty-message="{{ settings.empty_message }}">
                <ul class="related-locations-list">
                    <# if ( elementorFrontend.isEditMode() ) { #>
                    <li class="editor-preview-item"><a href="#">Atlanta</a></li>
                    <li class="editor-preview-item"><a href="#">Cumming</a></li>
                    <li class="editor-preview-item"><a href="#">Marietta</a></li>
                    <li class="editor-preview-item"><a href="#">Decatur</a></li>
                    <li class="editor-preview-note">These items are only visible in the editor for styling purposes</li>
                    <# } else { #>
                    <li class="loading">Finding related locations...</li>
                    <# } #>
                </ul>
            </div>
        </div>
        <?php
    }
}