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
        $this->register_meta_field_controls();
        $this->register_general_style_controls();
        $this->register_search_style_controls();
        $this->register_filter_style_controls();
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

    private function register_meta_field_controls()
    {
        $this->start_controls_section(
            'acf_fields_section',
            [
                'label' => esc_html__('متای فیلدهای اضافه', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'acf_fields_notice',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw'  => esc_html__(
                    'نام فیلد (Field Name) را وارد و کاربرد آن را تعیین کنید. عنوان نمایشی به‌صورت خودکار از Label همان فیلد ACF خوانده می‌شود. عنوان، چکیده و تصویر شاخص نیازی به تعریف ندارند.',
                    'rdsco-elementor-widgets'
                ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'field_slug',
            [
                'label'       => esc_html__('نام فیلد (Field Name)', 'rdsco-elementor-widgets'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'example_field',
                'label_block' => true,
                'dynamic'     => ['active' => false],
            ]
        );

        $repeater->add_control(
            'field_role',
            [
                'label'   => esc_html__('نوع کاربرد', 'rdsco-elementor-widgets'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text'           => esc_html__('متن عادی', 'rdsco-elementor-widgets'),
                    'zone'           => esc_html__('حوزه و فیلتر حوزه', 'rdsco-elementor-widgets'),
                    'email'          => esc_html__('رایانامه', 'rdsco-elementor-widgets'),
                    'external_phone' => esc_html__('شماره تماس مستقیم', 'rdsco-elementor-widgets'),
                    'internal_phone' => esc_html__('شماره داخلی', 'rdsco-elementor-widgets'),
                ],
            ]
        );

        $this->add_control(
            'meta_fields',
            [
                'label'         => esc_html__('فهرست متاها', 'rdsco-elementor-widgets'),
                'type'          => \Elementor\Controls_Manager::REPEATER,
                'fields'        => $repeater->get_controls(),
                'default'       => [
                    ['field_slug' => 'istt_contact_zone', 'field_role' => 'zone'],
                    ['field_slug' => 'istt_contact_email', 'field_role' => 'email'],
                    ['field_slug' => 'istt_contact_external_phone', 'field_role' => 'external_phone'],
                    ['field_slug' => 'istt_contact_internal_phone', 'field_role' => 'internal_phone'],
                ],
                'title_field'   => '{{{ field_slug }}}',
                'prevent_empty' => false,
                'button_text'   => esc_html__('افزودن متای جدید', 'rdsco-elementor-widgets'),
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
                'name'      => 'background',
                'label'     => esc_html__('پس‌زمینه', 'rdsco-elementor-widgets'),
                'types'     => ['classic', 'gradient'],
                'selector'  => $selector,
                'separator' => 'after',
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

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'      => 'container_border',
                'label'     => esc_html__('کادر اصلی', 'rdsco-elementor-widgets'),
                'selector'  => $selector,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label'      => esc_html__('گردی گوشه‌های اصلی', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors'  => [
                    $selector =>
                        'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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

    private function register_filter_style_controls()
    {
        $root = '{{WRAPPER}} .rdsco-contact-list';

        $this->start_controls_section(
            'tag_style_section',
            [
                'label' => esc_html__('گزینه‌های شروع و فیلترها', 'rdsco-elementor-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'tag_buttons_heading',
            [
                'label'     => esc_html__('کلیدهای گزینه‌های شروع', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'tag_typography',
                'label'    => esc_html__('تایپوگرافی کلیدها', 'rdsco-elementor-widgets'),
                'selector' => '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-link',
            ]
        );

        $this->add_responsive_control(
            'tag_padding',
            [
                'label'      => esc_html__('فاصله داخلی کلیدها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-link' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'tag_gap',
            [
                'label'      => esc_html__('فاصله بین کلیدها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 50],
                    'em' => ['min' => 0, 'max' => 5, 'step' => 0.1],
                ],
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-links' => 'gap: {{SIZE}}{{UNIT}};',
                ],
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

        $this->add_control(
            'tag_text_color',
            [
                'label'     => esc_html__('رنگ متن عادی', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-link' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tag_hover_color',
            [
                'label'     => esc_html__('رنگ متن در هاور', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-link:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tag_active_color',
            [
                'label'     => esc_html__('رنگ متن انتخاب‌شده', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-tag-link.active' => 'color: {{VALUE}};',
                ],
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

        $this->add_control(
            'filter_dropdowns_heading',
            [
                'label'     => esc_html__('دراپ‌داون‌های حوزه و واحد', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'filter_typography',
                'label'    => esc_html__('تایپوگرافی دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'selector' => '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select',
            ]
        );

        $this->add_responsive_control(
            'filter_padding',
            [
                'label'      => esc_html__('فاصله داخلی دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'filter_gap',
            [
                'label'      => esc_html__('فاصله بین فیلترها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 50],
                    'em' => ['min' => 0, 'max' => 5, 'step' => 0.1],
                ],
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-area' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'filter_background',
            [
                'label'     => esc_html__('پس‌زمینه دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_text_color',
            [
                'label'     => esc_html__('رنگ متن دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_border_color',
            [
                'label'     => esc_html__('رنگ کادر دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'filter_height',
            [
                'label'      => esc_html__('ارتفاع دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 36, 'max' => 80]],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'filter_radius',
            [
                'label'      => esc_html__('گردی دراپ‌داون‌ها', 'rdsco-elementor-widgets'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 50]],
                'selectors'  => [
                    '{{WRAPPER}} .rdsco-contact-list .rdsco-contact-list-filter-field select' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
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
