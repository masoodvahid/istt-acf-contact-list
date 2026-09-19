<?php
/**
 * Elementor Post Display widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\PostDisplay;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

final class Widget extends Widget_Base
{
    public function get_name()
    {
        return 'rdsco-post-display';
    }

    public function get_title()
    {
        return esc_html__('نمایش مطالب', 'rdsco-elementor-widgets');
    }

    public function get_icon()
    {
        return 'eicon-posts-grid';
    }

    public function get_categories()
    {
        return ['rdsco-elementor-widgets'];
    }

    public function get_keywords()
    {
        return ['posts', 'category', 'ajax', 'search', 'نمایش مطالب', 'دسته بندی', 'جستجو'];
    }

    public function get_style_depends()
    {
        return ['rdsco-post-display'];
    }

    public function get_script_depends()
    {
        return ['rdsco-post-display'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('query_section', [
            'label' => esc_html__('منبع مطالب', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('category_ids', [
            'label'       => esc_html__('آیدی دسته‌بندی‌ها', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '9',
            'placeholder' => '9 یا 9,12,18',
            'description' => esc_html__('یک یا چند ID را با ویرگول جدا کنید.', 'rdsco-elementor-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('include_children', [
            'label'        => esc_html__('نمایش مطالب زیرمجموعه‌ها', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_category_filter', [
            'label'        => esc_html__('نمایش ستون فیلتر دسته‌بندی', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_top_category_filter', [
            'label'        => esc_html__('نمایش فیلتر تگ‌ها در ردیف بالا', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'description'  => esc_html__('فقط تگ‌های مرتبط با مطالب دسته انتخاب‌شده و زیرمجموعه‌های آن نمایش داده می‌شوند.', 'rdsco-elementor-widgets'),
        ]);

        $this->add_control('show_search', [
            'label'        => esc_html__('فعال بودن جستجو', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('search_placeholder', [
            'label'       => esc_html__('متن داخل جستجو', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'جستجو در مطالب…',
            'label_block' => true,
            'condition'   => ['show_search' => 'yes'],
        ]);

        $this->add_control('per_page', [
            'label'   => esc_html__('تعداد مطلب در هر بار', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 12,
            'min'     => 1,
            'max'     => 60,
        ]);

        $this->add_control('show_load_more', [
            'label'        => esc_html__('نمایش دکمه مطالب بیشتر', 'rdsco-elementor-widgets'),
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

        $this->end_controls_section();

        $this->start_controls_section('card_section', [
            'label' => esc_html__('کارت مطلب', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('card_style', [
            'label'   => esc_html__('استایل نمایش', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'icon',
            'options' => [
                'icon'  => esc_html__('آیکن', 'rdsco-elementor-widgets'),
                'image' => esc_html__('تصویر شاخص', 'rdsco-elementor-widgets'),
            ],
        ]);

        $this->add_control('show_excerpt', [
            'label'        => esc_html__('نمایش خلاصه متن', 'rdsco-elementor-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('excerpt_length', [
            'label'     => esc_html__('تعداد کلمات خلاصه', 'rdsco-elementor-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 20,
            'min'       => 0,
            'max'       => 100,
            'condition' => ['show_excerpt' => 'yes'],
        ]);

        $this->add_control('button_text', [
            'label'       => esc_html__('متن لینک', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'مشاهده مطلب',
            'label_block' => true,
        ]);

        $this->add_control('empty_text', [
            'label'       => esc_html__('متن نبود نتیجه', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'مطلبی پیدا نشد.',
            'label_block' => true,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('layout_section', [
            'label' => esc_html__('چیدمان', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('columns', [
            'label'          => esc_html__('تعداد ستون‌ها', 'rdsco-elementor-widgets'),
            'type'           => Controls_Manager::SELECT,
            'options'        => ['1'=>'1','2'=>'2','3'=>'3','4'=>'4'],
            'default'        => '3',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'selectors'      => [
                '{{WRAPPER}} .rdsco-post-display-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
            ],
        ]);

        $this->add_responsive_control('grid_gap', [
            'label'      => esc_html__('فاصله کارت‌ها', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 12],
            'selectors'  => [
                '{{WRAPPER}} .rdsco-post-display-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('sidebar_width', [
            'label'      => esc_html__('عرض ستون فیلتر', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 190, 'max' => 420]],
            'default'    => ['size' => 290],
            'selectors'  => [
                '{{WRAPPER}} .rdsco-post-display-layout.has-sidebar' => 'grid-template-columns: {{SIZE}}{{UNIT}} minmax(0, 1fr);',
            ],
            'condition' => ['show_category_filter' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function runtime_settings(array $settings): array
    {
        $root_ids = Post_Display_Service::normalize_category_ids($settings['category_ids'] ?? '');

        return [
            'root_ids'         => $root_ids,
            'include_children' => 'yes' === ($settings['include_children'] ?? 'yes'),
            'per_page'         => max(1, min(60, absint($settings['per_page'] ?? 12))),
            'card_style'       => in_array(($settings['card_style'] ?? 'icon'), ['icon', 'image'], true) ? $settings['card_style'] : 'icon',
            'show_excerpt'     => $settings['show_excerpt'] ?? 'yes',
            'excerpt_length'   => max(0, min(100, absint($settings['excerpt_length'] ?? 20))),
            'button_text'      => sanitize_text_field($settings['button_text'] ?? 'مشاهده مطلب'),
            'empty_text'       => sanitize_text_field($settings['empty_text'] ?? 'مطلبی پیدا نشد.'),
        ];
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $runtime  = $this->runtime_settings($settings);

        if (empty($runtime['root_ids'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="rdsco-editor-notice">حداقل یک آیدی دسته‌بندی وارد کنید.</div>';
            }
            return;
        }

        $show_sidebar = 'yes' === ($settings['show_category_filter'] ?? 'yes');

        // Keep the original control ID for backward compatibility with
        // widgets saved before v2.5.2. The meaning of the top row is now
        // "related tags", but the stored Elementor setting remains valid.
        $show_tag_filters = 'yes' === ($settings['show_top_category_filter'] ?? 'yes');

        $filter_terms = $show_sidebar
            ? Post_Display_Service::get_filter_terms($runtime['root_ids'], $runtime['include_children'])
            : [];

        $related_tags = $show_tag_filters
            ? Post_Display_Service::get_related_tags($runtime['root_ids'], $runtime['include_children'], 0)
            : [];

        $has_sidebar = $show_sidebar && !empty($filter_terms);

        $query = Post_Display_Service::query([
            'root_ids'         => $runtime['root_ids'],
            'include_children' => $runtime['include_children'],
            'active_category'  => 0,
            'tag_id'           => 0,
            'page'             => 1,
            'per_page'         => $runtime['per_page'],
        ]);

        $wrapper_id = 'rdsco-post-display-' . $this->get_id();
        ?>
        <div
            id="<?php echo esc_attr($wrapper_id); ?>"
            class="rdsco-post-display style-<?php echo esc_attr($runtime['card_style']); ?>"
            data-current-page="1"
            data-max-pages="<?php echo esc_attr((int) $query->max_num_pages); ?>"
            data-active-category="0"
            data-active-tag="0"
            data-nonce="<?php echo esc_attr(wp_create_nonce(Post_Display_Service::NONCE_ACTION)); ?>"
            data-settings="<?php echo esc_attr(wp_json_encode($runtime)); ?>"
        >
            <?php if ('yes' === ($settings['show_search'] ?? 'yes') || $show_tag_filters) : ?>
                <div class="rdsco-post-display-toolbar">
                    <?php if ('yes' === ($settings['show_search'] ?? 'yes')) : ?>
                        <div class="rdsco-post-display-search">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <input
                                type="search"
                                class="rdsco-post-display-search-input"
                                placeholder="<?php echo esc_attr($settings['search_placeholder'] ?? 'جستجو در مطالب…'); ?>"
                                autocomplete="off"
                            >
                            <button type="button" class="rdsco-post-display-search-clear" aria-label="پاک کردن جستجو">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_tag_filters) : ?>
                        <div class="rdsco-post-display-filters-wrap rdsco-post-display-tags-wrap" <?php echo empty($related_tags) ? 'hidden' : ''; ?>>
                            <div class="rdsco-post-display-filters rdsco-post-display-tag-filters">
                                <?php echo Post_Display_Service::render_tag_filters($related_tags, 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="rdsco-post-display-layout <?php echo $has_sidebar ? 'has-sidebar' : 'no-sidebar'; ?>">

                <?php if ($has_sidebar) : ?>
                    <aside class="rdsco-post-display-sidebar">
                        <div class="rdsco-post-display-sidebar-heading">
                            <strong>دسته‌بندی</strong>
                            <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                        </div>

                        <div class="rdsco-post-display-sidebar-list">
                            <button
                                type="button"
                                class="rdsco-post-display-filter rdsco-post-display-sidebar-filter is-active"
                                data-category="0"
                                aria-pressed="true"
                            >
                                <span>همه مطالب</span>
                                <small><?php echo esc_html(number_format_i18n($query->found_posts)); ?></small>
                            </button>

                            <?php foreach ($filter_terms as $term) : ?>
                                <button
                                    type="button"
                                    class="rdsco-post-display-filter rdsco-post-display-sidebar-filter"
                                    data-category="<?php echo esc_attr($term->term_id); ?>"
                                    aria-pressed="false"
                                >
                                    <span><?php echo esc_html($term->name); ?></span>
                                    <small><?php echo esc_html(number_format_i18n($term->count)); ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </aside>
                <?php endif; ?>

                <main class="rdsco-post-display-main">
                    <div class="rdsco-post-display-loading" aria-live="polite">
                        <span class="rdsco-post-display-spinner" aria-hidden="true"></span>
                        <span>در حال دریافت مطالب…</span>
                    </div>

                    <div class="rdsco-post-display-results">
                        <?php echo Post_Display_Service::render_posts($query, $runtime); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>

                    <div class="rdsco-post-display-footer">
                        <?php if ('yes' === ($settings['show_result_count'] ?? 'yes')) : ?>
                            <div class="rdsco-post-display-count">
                                <strong><?php echo esc_html(number_format_i18n($query->found_posts)); ?></strong>
                                <span>مطلب یافت شد</span>
                            </div>
                        <?php endif; ?>

                        <?php if ('yes' === ($settings['show_load_more'] ?? 'yes')) : ?>
                            <button
                                type="button"
                                class="rdsco-post-display-load-more"
                                <?php echo $query->max_num_pages <= 1 ? 'hidden' : ''; ?>
                            >
                                نمایش مطالب بیشتر
                            </button>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
        <?php
    }
}
