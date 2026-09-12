<?php
/**
 * Elementor Contact List widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\ContactList;

if (!defined('ABSPATH')) {
    exit;
}

final class Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'rdsco-contact-list';
    }

    public function get_title()
    {
        return esc_html__('دفتر تلفن', 'rdsco-elementor-widgets');
    }

    public function get_icon()
    {
        return 'eicon-person';
    }

    public function get_categories()
    {
        return ['rdsco-elementor-widgets'];
    }

    public function get_keywords()
    {
        return ['contact', 'directory', 'phonebook', 'دفتر تلفن', 'مخاطب'];
    }

    public function get_style_depends()
    {
        return ['rdsco-contact-list'];
    }

    public function get_script_depends()
    {
        return ['rdsco-contact-list'];
    }

    protected function register_controls()
    {
        $this->register_content_controls();
        $this->register_general_style_controls();
        $this->register_header_style_controls();
        $this->register_search_style_controls();
        $this->register_tag_style_controls();
        $this->register_row_style_controls();
        $this->register_sidebar_style_controls();
    }

    private function register_content_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('محتوا', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'eyebrow',
            [
                'label'       => esc_html__('عنوان کوچک', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'ارتباط با شهرک',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => esc_html__('عنوان اصلی', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'دنبال چه کسی هستید؟',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'description',
            [
                'label'       => esc_html__('توضیحات', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'default'     => 'نام، سمت، واحد سازمانی یا شماره داخلی موردنظر خود را جست‌وجو کنید.',
                'rows'        => 3,
            ]
        );

        $this->add_control(
            'search_placeholder',
            [
                'label'       => esc_html__('متن داخل جست‌وجو', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'نام، سمت، واحد یا داخلی را جست‌وجو کنید...',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label'   => esc_html__('تعداد مخاطب در هر صفحه', 'rdsco-elementor-widgets'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 20,
                'min'     => 1,
                'max'     => 50,
                'step'    => 1,
            ]
        );

        $this->add_control(
            'tags_limit',
            [
                'label'       => esc_html__('تعداد گزینه‌های «از کجا شروع کنم»', 'rdsco-elementor-widgets'),
                'description' => esc_html__('این گزینه‌ها از برچسب‌های نوشته‌های دسته contacts خوانده می‌شوند.', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'default'     => 8,
                'min'         => 1,
                'max'         => 20,
                'step'        => 1,
            ]
        );

        $this->end_controls_section();
    }

    private function register_general_style_controls()
    {
        $selector = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'general_style_section',
            [
                'label' => esc_html__('رنگ‌ها و پس‌زمینه', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'background',
                'types'    => ['classic', 'gradient'],
                'selector' => $selector,
            ]
        );

        $colors = [
            'primary_color'       => ['رنگ اصلی', '--p'],
            'primary_hover_color' => ['رنگ اصلی در هاور', '--ph'],
            'accent_color'        => ['رنگ مکمل', '--a'],
            'accent_light_color'  => ['پس‌زمینه مکمل روشن', '--al'],
            'title_color'         => ['رنگ عنوان', '--t'],
            'text_color'          => ['رنگ متن', '--x'],
            'muted_color'         => ['رنگ متن کم‌رنگ', '--m'],
            'border_color'        => ['رنگ خطوط', '--b'],
            'light_color'         => ['پس‌زمینه روشن', '--l'],
            'surface_color'       => ['رنگ سطح', '--w'],
        ];

        foreach ($colors as $control_id => $color) {
            $this->add_control(
                $control_id,
                [
                    'label'     => esc_html($color[0]),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        $selector => $color[1] . ': {{VALUE}};',
                    ],
                ]
            );
        }

        $this->end_controls_section();
    }

    private function register_header_style_controls()
    {
        $this->start_controls_section(
            'header_style_section',
            [
                'label' => esc_html__('سربرگ', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'hero_padding',
            [
                'label'      => esc_html__('فاصله داخلی', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-hero' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'label'    => esc_html__('تایپوگرافی عنوان', 'rdsco-elementor-widgets'),
                'selector' => '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-hero > h1',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'description_typography',
                'label'    => esc_html__('تایپوگرافی توضیحات', 'rdsco-elementor-widgets'),
                'selector' => '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-hero > p',
            ]
        );

        $this->end_controls_section();
    }

    private function register_search_style_controls()
    {
        $root = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'search_style_section',
            [
                'label' => esc_html__('جست‌وجو', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'search_height',
            [
                'label'      => esc_html__('ارتفاع', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 44, 'max' => 90]],
                'selectors'  => [$root => '--rdsco-search-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'search_background',
            [
                'label'     => esc_html__('رنگ پس‌زمینه', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-search-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'search_border',
            [
                'label'     => esc_html__('رنگ کادر', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-search-border: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'search_radius',
            [
                'label'      => esc_html__('گردی گوشه‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-search-box input' =>
                        'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_tag_style_controls()
    {
        $root = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'tag_style_section',
            [
                'label' => esc_html__('گزینه‌های شروع', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'tag_background',
            [
                'label'     => esc_html__('پس‌زمینه عادی', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-tag-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tag_active_background',
            [
                'label'     => esc_html__('پس‌زمینه انتخاب‌شده', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-tag-active-bg: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'tag_radius',
            [
                'label'      => esc_html__('گردی گوشه‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => [$root => '--rdsco-tag-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_row_style_controls()
    {
        $root = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'row_style_section',
            [
                'label' => esc_html__('ردیف مخاطبان', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'row_background',
            [
                'label'     => esc_html__('پس‌زمینه عادی', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-row-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'row_hover_background',
            [
                'label'     => esc_html__('پس‌زمینه در هاور', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-row-hover-bg: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'row_min_height',
            [
                'label'      => esc_html__('حداقل ارتفاع', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 64, 'max' => 160]],
                'selectors'  => [$root => '--rdsco-row-min-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'row_radius',
            [
                'label'      => esc_html__('گردی گوشه‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => [$root => '--rdsco-row-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_sidebar_style_controls()
    {
        $root = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'sidebar_style_section',
            [
                'label' => esc_html__('سایدبار اطلاعات تماس', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'sidebar_background',
            [
                'label'     => esc_html__('رنگ پس‌زمینه', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [$root => '--rdsco-sidebar-bg: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'sidebar_width',
            [
                'label'      => esc_html__('عرض', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'vw'],
                'range'      => [
                    'px' => ['min' => 300, 'max' => 720],
                    'vw' => ['min' => 25, 'max' => 100],
                ],
                'selectors' => [$root => '--rdsco-sidebar-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        echo rdsco_contact_list_render(
            $this->get_settings_for_display(),
            $this->get_id()
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
