<?php
/**
 * Elementor Search Box widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\SearchBox;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

final class Widget extends Widget_Base
{
    public function get_name()
    {
        return 'rdsco-search-box';
    }

    public function get_title()
    {
        return esc_html__('باکس جستجو', 'rdsco-elementor-widgets');
    }

    public function get_icon()
    {
        return 'eicon-search';
    }

    public function get_categories()
    {
        return ['rdsco-elementor-widgets'];
    }

    public function get_keywords()
    {
        return ['search', 'ajax', 'date', 'category', 'جستجو', 'تاریخ', 'دسته بندی'];
    }

    public function get_style_depends()
    {
        return ['rdsco-search-box'];
    }

    public function get_script_depends()
    {
        return ['rdsco-search-box'];
    }

    protected function register_controls()
    {
        $this->register_search_controls();
        $this->register_result_controls();
        $this->register_text_controls();
        $this->register_form_style_controls();
        $this->register_result_style_controls();
        $this->register_pagination_style_controls();
    }

    private function register_search_controls()
    {
        $this->start_controls_section('search_section', [
            'label' => esc_html__('تنظیمات جستجو', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('allowed_category_ids', [
            'label'       => esc_html__('دسته‌های مجاز برای جستجو', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => Search_Service::category_options(),
            'label_block' => true,
            'description' => esc_html__('فقط مطالب این دسته‌ها در نتایج قابل نمایش هستند.', 'rdsco-elementor-widgets'),
        ]);

        $this->add_control('include_children', [
            'label'        => esc_html__('جستجو در زیر‌دسته‌ها', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_category_filter', [
            'label'        => esc_html__('انتخاب دسته توسط کاربر', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('live_search', [
            'label'        => esc_html__('جستجوی زنده', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('live_delay', [
            'label'       => esc_html__('تأخیر جستجوی زنده', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 1000,
            'min'         => 300,
            'max'         => 5000,
            'step'        => 100,
            'description' => esc_html__('برحسب میلی‌ثانیه؛ مقدار پیش‌فرض ۱۰۰۰ یعنی یک ثانیه.', 'rdsco-elementor-widgets'),
            'condition'   => ['live_search' => 'yes'],
        ]);

        $this->add_control('minimum_characters', [
            'label'       => esc_html__('حداقل تعداد حروف', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 3,
            'min'         => 3,
            'max'         => 12,
            'condition'   => ['live_search' => 'yes'],
        ]);

        $this->add_control('date_range_enabled', [
            'label'        => esc_html__('نمایش بازه تاریخی', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_initial_results', [
            'label'        => esc_html__('نمایش نتایج پیش از جستجو', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ]);

        $this->end_controls_section();
    }

    private function register_result_controls()
    {
        $this->start_controls_section('results_section', [
            'label' => esc_html__('نتایج و صفحه‌بندی', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('result_style', [
            'label'   => esc_html__('حالت نمایش نتایج', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'card',
            'options' => [
                'card'    => esc_html__('کارت تصویری', 'rdsco-elementor-widgets'),
                'list'    => esc_html__('فهرست افقی', 'rdsco-elementor-widgets'),
                'minimal' => esc_html__('مینیمال متنی', 'rdsco-elementor-widgets'),
            ],
        ]);

        $this->add_control('per_page', [
            'label'   => esc_html__('تعداد نتیجه در هر صفحه', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 9,
            'min'     => 1,
            'max'     => 48,
        ]);

        $this->add_control('order', [
            'label'   => esc_html__('مرتب‌سازی', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'newest',
            'options' => [
                'newest'    => esc_html__('جدیدترین', 'rdsco-elementor-widgets'),
                'oldest'    => esc_html__('قدیمی‌ترین', 'rdsco-elementor-widgets'),
                'title_asc' => esc_html__('عنوان: الف تا ی', 'rdsco-elementor-widgets'),
                'title_desc'=> esc_html__('عنوان: ی تا الف', 'rdsco-elementor-widgets'),
            ],
        ]);

        $this->add_control('show_image', [
            'label'        => esc_html__('نمایش تصویر شاخص', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => ['result_style!' => 'minimal'],
        ]);

        $this->add_control('image_size', [
            'label'     => esc_html__('اندازه تصویر', 'rdsco-elementor-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'medium',
            'options'   => [
                'thumbnail'    => esc_html__('بندانگشتی', 'rdsco-elementor-widgets'),
                'medium'       => esc_html__('متوسط', 'rdsco-elementor-widgets'),
                'medium_large' => esc_html__('متوسط بزرگ', 'rdsco-elementor-widgets'),
                'large'        => esc_html__('بزرگ', 'rdsco-elementor-widgets'),
            ],
            'condition' => ['show_image' => 'yes', 'result_style!' => 'minimal'],
        ]);

        $this->add_control('show_excerpt', [
            'label'        => esc_html__('نمایش خلاصه', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('excerpt_length', [
            'label'     => esc_html__('تعداد کلمات خلاصه', 'rdsco-elementor-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 22,
            'min'       => 0,
            'max'       => 100,
            'condition' => ['show_excerpt' => 'yes'],
        ]);

        $this->add_control('show_date', [
            'label'        => esc_html__('نمایش تاریخ مطلب', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_category', [
            'label'        => esc_html__('نمایش دسته مطلب', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_result_count', [
            'label'        => esc_html__('نمایش تعداد نتایج', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('pagination_window', [
            'label'   => esc_html__('تعداد صفحات کنار صفحه جاری', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 2,
            'min'     => 1,
            'max'     => 4,
        ]);

        $this->add_control('target_blank', [
            'label'        => esc_html__('باز شدن نتیجه در تب جدید', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ]);

        $this->end_controls_section();
    }

    private function register_text_controls()
    {
        $this->start_controls_section('texts_section', [
            'label' => esc_html__('متن‌ها', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $controls = [
            'search_placeholder' => ['متن داخل جستجو', 'عبارت موردنظر را جستجو کنید…'],
            'all_categories_text'=> ['متن همه دسته‌ها', 'همه دسته‌ها'],
            'search_button_text' => ['متن دکمه جستجو', 'جستجو'],
            'reset_button_text'  => ['متن دکمه پاک‌سازی', 'پاک‌سازی'],
            'date_from_label'    => ['برچسب تاریخ شروع', 'از تاریخ'],
            'date_to_label'      => ['برچسب تاریخ پایان', 'تا تاریخ'],
            'button_text'        => ['متن لینک نتیجه', 'مشاهده مطلب'],
            'prompt_text'        => ['متن پیش از جستجو', 'عبارت موردنظر را جستجو کنید.'],
            'empty_text'         => ['متن نبود نتیجه', 'نتیجه‌ای پیدا نشد.'],
            'loading_text'       => ['متن در حال جستجو', 'در حال جستجو…'],
        ];

        foreach ($controls as $id => $control) {
            $this->add_control($id, [
                'label'       => esc_html__($control[0], 'rdsco-elementor-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => $control[1],
                'label_block' => true,
            ]);
        }

        $this->end_controls_section();
    }

    private function register_form_style_controls()
    {
        $this->start_controls_section('form_style_section', [
            'label' => esc_html__('فرم جستجو', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('accent_color', [
            'label'   => esc_html__('رنگ اصلی', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#02b6b5',
            'selectors' => ['{{WRAPPER}} .rdsco-search-box' => '--rdsco-sb-accent: {{VALUE}};'],
        ]);

        $this->add_control('form_background', [
            'label'   => esc_html__('پس‌زمینه فرم', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .rdsco-search-form' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'form_border',
            'selector' => '{{WRAPPER}} .rdsco-search-form',
        ]);

        $this->add_responsive_control('form_padding', [
            'label'      => esc_html__('فاصله داخلی فرم', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default'    => ['top'=>18, 'right'=>18, 'bottom'=>18, 'left'=>18, 'unit'=>'px'],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('form_radius', [
            'label'      => esc_html__('گردی فرم', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 50]],
            'default'    => ['size' => 18],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-form' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'form_shadow',
            'selector' => '{{WRAPPER}} .rdsco-search-form',
        ]);

        $this->add_control('input_background', [
            'label'   => esc_html__('پس‌زمینه فیلدها', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#f8fafb',
            'selectors' => ['{{WRAPPER}} .rdsco-search-field-control' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('input_text_color', [
            'label'   => esc_html__('رنگ متن فیلدها', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#263d4a',
            'selectors' => ['{{WRAPPER}} .rdsco-search-field-control' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('input_height', [
            'label'      => esc_html__('ارتفاع فیلدها', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 38, 'max' => 80]],
            'default'    => ['size' => 52],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-field-control, {{WRAPPER}} .rdsco-search-submit, {{WRAPPER}} .rdsco-search-reset' => 'min-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'input_typography',
            'selector' => '{{WRAPPER}} .rdsco-search-field-control, {{WRAPPER}} .rdsco-search-submit, {{WRAPPER}} .rdsco-search-reset',
        ]);

        $this->end_controls_section();
    }

    private function register_result_style_controls()
    {
        $this->start_controls_section('cards_style_section', [
            'label' => esc_html__('کارت‌های نتیجه', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('columns', [
            'label'          => esc_html__('تعداد ستون‌ها', 'rdsco-elementor-widgets'),
            'type'           => Controls_Manager::SELECT,
            'options'        => ['1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4'],
            'default'        => '3',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'selectors'      => ['{{WRAPPER}} .rdsco-search-box.mode-card .rdsco-search-results-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));'],
            'condition'      => ['result_style' => 'card'],
        ]);

        $this->add_responsive_control('result_gap', [
            'label'      => esc_html__('فاصله نتایج', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 16],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-results-grid' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_background', [
            'label'   => esc_html__('پس‌زمینه نتیجه', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .rdsco-search-result-card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .rdsco-search-result-card',
        ]);

        $this->add_responsive_control('card_radius', [
            'label'      => esc_html__('گردی نتیجه', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 50]],
            'default'    => ['size' => 16],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-result-card' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => esc_html__('فاصله داخلی محتوا', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default'    => ['top'=>18, 'right'=>18, 'bottom'=>18, 'left'=>18, 'unit'=>'px'],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-result-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .rdsco-search-result-card',
        ]);

        $this->add_control('title_color', [
            'label'   => esc_html__('رنگ عنوان', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#0a1628',
            'selectors' => ['{{WRAPPER}} .rdsco-search-result-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .rdsco-search-result-title',
        ]);

        $this->add_control('excerpt_color', [
            'label'   => esc_html__('رنگ خلاصه', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#637781',
            'selectors' => ['{{WRAPPER}} .rdsco-search-result-excerpt' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'excerpt_typography',
            'selector' => '{{WRAPPER}} .rdsco-search-result-excerpt',
        ]);

        $this->add_control('meta_color', [
            'label'   => esc_html__('رنگ اطلاعات تکمیلی', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#7e9099',
            'selectors' => ['{{WRAPPER}} .rdsco-search-result-meta' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function register_pagination_style_controls()
    {
        $this->start_controls_section('pagination_style_section', [
            'label' => esc_html__('صفحه‌بندی', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('pagination_color', [
            'label'   => esc_html__('رنگ اعداد', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#38515d',
            'selectors' => ['{{WRAPPER}} .rdsco-search-page' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('pagination_active_color', [
            'label'   => esc_html__('رنگ صفحه فعال', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#02b6b5',
            'selectors' => ['{{WRAPPER}} .rdsco-search-page.is-active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('pagination_gap', [
            'label'      => esc_html__('فاصله دکمه‌ها', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'default'    => ['size' => 7],
            'selectors'  => ['{{WRAPPER}} .rdsco-search-pagination' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    private function runtime_settings(array $settings)
    {
        return Search_Service::sanitize_runtime([
            'allowed_category_ids' => $settings['allowed_category_ids'] ?? [],
            'include_children'     => 'yes' === ($settings['include_children'] ?? 'yes'),
            'show_category_filter' => 'yes' === ($settings['show_category_filter'] ?? 'yes'),
            'date_range_enabled'   => 'yes' === ($settings['date_range_enabled'] ?? 'yes'),
            'show_initial_results' => 'yes' === ($settings['show_initial_results'] ?? ''),
            'minimum_characters'   => $settings['minimum_characters'] ?? 3,
            'per_page'             => $settings['per_page'] ?? 9,
            'result_style'         => $settings['result_style'] ?? 'card',
            'order'                => $settings['order'] ?? 'newest',
            'show_image'           => 'yes' === ($settings['show_image'] ?? 'yes'),
            'image_size'           => $settings['image_size'] ?? 'medium',
            'show_excerpt'         => 'yes' === ($settings['show_excerpt'] ?? 'yes'),
            'excerpt_length'       => $settings['excerpt_length'] ?? 22,
            'show_date'            => 'yes' === ($settings['show_date'] ?? 'yes'),
            'show_category'        => 'yes' === ($settings['show_category'] ?? 'yes'),
            'target_blank'         => 'yes' === ($settings['target_blank'] ?? ''),
            'pagination_window'    => $settings['pagination_window'] ?? 2,
            'show_result_count'    => 'yes' === ($settings['show_result_count'] ?? 'yes'),
            'button_text'          => $settings['button_text'] ?? 'مشاهده مطلب',
            'empty_text'           => $settings['empty_text'] ?? 'نتیجه‌ای پیدا نشد.',
            'prompt_text'          => $settings['prompt_text'] ?? 'عبارت موردنظر را جستجو کنید.',
        ]);
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $runtime = $this->runtime_settings($settings);

        if (empty($runtime['allowed_category_ids'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="rdsco-editor-notice">حداقل یک دسته مجاز برای جستجو انتخاب کنید.</div>';
            }
            return;
        }

        $live_search = 'yes' === ($settings['live_search'] ?? 'yes');
        $delay = max(300, min(5000, absint($settings['live_delay'] ?? 1000)));
        $minimum = max(3, min(12, absint($settings['minimum_characters'] ?? 3)));
        $query = null;

        if ($runtime['show_initial_results']) {
            $query = Search_Service::query([
                'settings' => $runtime,
                'page'     => 1,
            ]);
        }

        $category_options = Search_Service::category_options();
        $wrapper_id = 'rdsco-search-box-' . $this->get_id();
        ?>
        <div
            id="<?php echo esc_attr($wrapper_id); ?>"
            class="rdsco-search-box mode-<?php echo esc_attr($runtime['result_style']); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce(Search_Service::NONCE_ACTION)); ?>"
            data-signature="<?php echo esc_attr(Search_Service::sign_settings($runtime)); ?>"
            data-settings="<?php echo esc_attr(wp_json_encode($runtime)); ?>"
            data-live-search="<?php echo $live_search ? '1' : '0'; ?>"
            data-delay="<?php echo esc_attr($delay); ?>"
            data-minimum="<?php echo esc_attr($minimum); ?>"
        >
            <form class="rdsco-search-form" role="search" novalidate>
                <div class="rdsco-search-form-row">
                    <label class="rdsco-search-field rdsco-search-keyword-field">
                        <span class="rdsco-search-sr-only"><?php echo esc_html__('عبارت جستجو', 'rdsco-elementor-widgets'); ?></span>
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input
                            type="search"
                            class="rdsco-search-field-control rdsco-search-input"
                            placeholder="<?php echo esc_attr($settings['search_placeholder'] ?? 'عبارت موردنظر را جستجو کنید…'); ?>"
                            autocomplete="off"
                        >
                    </label>

                    <?php if ($runtime['show_category_filter']) : ?>
                        <label class="rdsco-search-field rdsco-search-category-field">
                            <span class="rdsco-search-sr-only"><?php echo esc_html__('دسته‌بندی', 'rdsco-elementor-widgets'); ?></span>
                            <select class="rdsco-search-field-control rdsco-search-category">
                                <option value="0"><?php echo esc_html($settings['all_categories_text'] ?? 'همه دسته‌ها'); ?></option>
                                <?php foreach ($runtime['allowed_category_ids'] as $category_id) :
                                    if (!isset($category_options[$category_id])) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($category_id); ?>"><?php echo esc_html($category_options[$category_id]); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ($runtime['date_range_enabled']) : ?>
                        <label class="rdsco-search-field rdsco-search-date-field">
                            <span class="rdsco-search-visible-label"><?php echo esc_html($settings['date_from_label'] ?? 'از تاریخ'); ?></span>
                            <input type="date" class="rdsco-search-field-control rdsco-search-date-from">
                        </label>
                        <label class="rdsco-search-field rdsco-search-date-field">
                            <span class="rdsco-search-visible-label"><?php echo esc_html($settings['date_to_label'] ?? 'تا تاریخ'); ?></span>
                            <input type="date" class="rdsco-search-field-control rdsco-search-date-to">
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="rdsco-search-submit">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <?php echo esc_html($settings['search_button_text'] ?? 'جستجو'); ?>
                    </button>
                    <button type="button" class="rdsco-search-reset">
                        <?php echo esc_html($settings['reset_button_text'] ?? 'پاک‌سازی'); ?>
                    </button>
                </div>
                <div class="rdsco-search-help" aria-live="polite"></div>
            </form>

            <div class="rdsco-search-loading" aria-hidden="true">
                <span class="rdsco-search-spinner"></span>
                <span><?php echo esc_html($settings['loading_text'] ?? 'در حال جستجو…'); ?></span>
            </div>

            <div class="rdsco-search-results" aria-live="polite">
                <?php
                echo $query
                    ? Search_Service::render_results($query, $runtime)
                    : Search_Service::render_message($runtime['prompt_text']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>

            <div class="rdsco-search-footer">
                <?php if ($runtime['show_result_count']) : ?>
                    <div class="rdsco-search-count"<?php echo $query ? '' : ' hidden'; ?>>
                        <strong><?php echo $query ? esc_html(number_format_i18n((int) $query->found_posts)) : '0'; ?></strong>
                        <span><?php echo esc_html__('نتیجه', 'rdsco-elementor-widgets'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="rdsco-search-pagination-wrap">
                    <?php
                    if ($query) {
                        echo Search_Service::render_pagination(1, $query->max_num_pages, $runtime['pagination_window']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
}
