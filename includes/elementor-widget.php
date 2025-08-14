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
class Dynamic_Practice_Areas_Elementor_Widget extends Base_Elementor_Widget
{
    /**
     * Get widget name
     */
    public function get_name()
    {
        return 'dynamic_practice_areas';
    }

    /**
     * Get widget title
     */
    public function get_title()
    {
        return __('Dynamic Practice Areas', 'dynamic-practice-areas-menu');
    }

    /**
     * Get widget icon
     */
    public function get_icon()
    {
        return 'eicon-bullet-list';
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
        return ['practice', 'areas', 'menu', 'dynamic', 'city'];
    }

    /**
     * Register widget controls
     */
    protected function _register_controls()
    {
        // Content Section
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

        // Register Style Controls from Base Class
        $this->register_container_style_controls('.elementor-dynamic-practice-areas');
        $this->register_style_controls('.practice-areas-list li a');
        $this->register_hover_style_controls('.practice-areas-list li a');

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
                    '{{WRAPPER}} .practice-areas-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .practice-areas-title',
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
                    '{{WRAPPER}} .practice-areas-title' => 'text-align: {{VALUE}};',
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
                    '{{WRAPPER}} .practice-areas-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .practice-areas-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'title_text_shadow',
                'selector' => '{{WRAPPER}} .practice-areas-title',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'title_border',
                'selector' => '{{WRAPPER}} .practice-areas-title',
            ]
        );

        $this->add_control(
            'title_border_radius',
            [
                'label' => __('Border Radius', 'dynamic-practice-areas-menu'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .practice-areas-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'title_background',
                'label' => __('Background', 'dynamic-practice-areas-menu'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .practice-areas-title',
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

        $this->add_render_attribute('wrapper', 'class', 'elementor-dynamic-practice-areas');
        $this->add_render_attribute('title', 'class', 'practice-areas-title');

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

            <div class="dynamic-practice-areas-widget" data-elementor-id="<?php
            echo esc_attr($widget_id); ?>">
                <ul class="practice-areas-list">
                    <li class="select-city-message"><?php
                        _e('Please select a city to view practice areas', 'dynamic-practice-areas-menu'); ?></li>
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
        <div class="elementor-dynamic-practice-areas">
            <# if ('yes' === settings.show_title && settings.title) { #>
            <h3 class="practice-areas-title" data-original-title="{{{ settings.title }}}">
                {{{ settings.title }}}
            </h3>
            <# } #>

            <div class="dynamic-practice-areas-widget">
                <# if ( elementorFrontend.isEditMode() ) { #>
                <ul class="practice-areas-list editor-preview">
                    <li class="practice-area-item"><a href="#">Car Accidents</a></li>
                    <li class="practice-area-item"><a href="#">Dog Bites</a></li>
                    <li class="practice-area-item"><a href="#">Truck Accidents</a></li>
                    <li class="practice-area-item"><a href="#">Workers' Compensation</a></li>
                    <li class="editor-preview-note">Preview items for styling purposes only</li>
                </ul>
                <# } else { #>
                <ul class="practice-areas-list">
                    <li class="select-city-message">Please select a city to view practice areas</li>
                </ul>
                <# } #>
            </div>
        </div>
        <?php
    }
}