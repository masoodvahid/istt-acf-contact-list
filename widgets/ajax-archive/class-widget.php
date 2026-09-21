<?php
/**
 * Elementor AJAX Archive widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

final class Widget extends Widget_Base
{
    public function get_name()
    {
        return 'rdsco-ajax-archive';
    }

    public function get_title()
    {
        return esc_html__('آرشیو AJAX نوشته‌ها', 'rdsco-elementor-widgets');
    }

    public function get_icon()
    {
        return 'eicon-post-list';
    }

    public function get_categories()
    {
        return ['rdsco-elementor-widgets'];
    }

    public function get_keywords()
    {
        return ['archive', 'ajax', 'filter', 'search', 'posts', 'آرشیو', 'فیلتر', 'جستجو'];
    }

    public function get_style_depends()
    {
        return ['rdsco-ajax-archive'];
    }

    public function get_script_depends()
    {
        return ['rdsco-ajax-archive'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('archive_content', [
            'label' => esc_html__('آرشیو و نمایش', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('per_page', [
            'label'   => esc_html__('تعداد نوشته در هر صفحه', 'rdsco-elementor-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 16,
            'min'     => 1,
            'max'     => 48,
        ]);

        $this->add_responsive_control('columns', [
            'label'          => esc_html__('تعداد ستون‌ها', 'rdsco-elementor-widgets'),
            'type'           => Controls_Manager::SELECT,
            'options'        => ['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6'],
            'default'        => '4',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'selectors'      => [
                '{{WRAPPER}} .rdsco-archive-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
            ],
        ]);

        $switches = [
            'show_search'          => 'جستجوی لایو',
            'show_filters'         => 'نمایش فیلترها',
            'show_subcategories'   => 'نمایش زیر‌دسته‌ها',
            'show_tags'            => 'نمایش برچسب‌ها',
            'show_category_images' => 'نمایش تصویر زیر‌دسته',
            'show_pagination'      => 'صفحه‌بندی AJAX',
            'show_load_more'       => 'دکمه نمایش مطالب بیشتر',
            'show_result_count'    => 'تعداد مطالب یافته‌شده',
        ];

        foreach ($switches as $id => $label) {
            $this->add_control($id, [
                'label'        => esc_html__($label, 'rdsco-elementor-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]);
        }

        $this->add_control('search_placeholder', [
            'label'       => esc_html__('متن داخل جستجو', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'جستجو در مطالب…',
            'label_block' => true,
            'condition'   => ['show_search' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('card_content', [
            'label' => esc_html__('محتوای کارت', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        foreach ([
            'show_date'     => 'نمایش تاریخ',
            'show_category' => 'نمایش دسته‌بندی',
            'show_excerpt'  => 'نمایش خلاصه متن',
        ] as $id => $label) {
            $this->add_control($id, [
                'label'        => esc_html__($label, 'rdsco-elementor-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]);
        }

        $this->add_control('excerpt_length', [
            'label'     => esc_html__('تعداد کلمات خلاصه', 'rdsco-elementor-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 18,
            'min'       => 0,
            'max'       => 100,
            'condition' => ['show_excerpt' => 'yes'],
        ]);

        $this->add_control('empty_text', [
            'label'       => esc_html__('متن نبود نتیجه', 'rdsco-elementor-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'مطلبی پیدا نشد.',
            'label_block' => true,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('layout_style', [
            'label' => esc_html__('چیدمان', 'rdsco-elementor-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('grid_gap', [
            'label'      => esc_html__('فاصله کارت‌ها', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 18],
            'selectors'  => [
                '{{WRAPPER}} .rdsco-archive-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('sidebar_width', [
            'label'      => esc_html__('عرض فیلتر سمت راست', 'rdsco-elementor-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 170, 'max' => 420]],
            'default'    => ['size' => 230],
            'selectors'  => [
                '{{WRAPPER}} .rdsco-archive-sidebar' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function runtime_settings(array $settings)
    {
        return [
            'per_page'             => max(1, min(48, absint($settings['per_page'] ?? 16))),
            'show_date'            => $settings['show_date'] ?? 'yes',
            'show_category'        => $settings['show_category'] ?? 'yes',
            'show_excerpt'         => $settings['show_excerpt'] ?? 'yes',
            'excerpt_length'       => max(0, absint($settings['excerpt_length'] ?? 18)),
            'empty_text'           => $settings['empty_text'] ?? 'مطلبی پیدا نشد.',
            'show_filters'         => $settings['show_filters'] ?? 'yes',
            'show_subcategories'   => $settings['show_subcategories'] ?? 'yes',
            'show_tags'            => $settings['show_tags'] ?? 'yes',
            'show_category_images' => $settings['show_category_images'] ?? 'yes',
            'show_pagination'      => $settings['show_pagination'] ?? 'yes',
            'show_load_more'       => $settings['show_load_more'] ?? 'yes',
            'show_result_count'    => $settings['show_result_count'] ?? 'yes',
        ];
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $term     = Archive_Service::get_current_category();

        if (!$term) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="rdsco-editor-notice">این ویجت را داخل قالب Archive دسته‌بندی قرار دهید و از Preview Settings یک دسته را انتخاب کنید.</div>';
            }
            return;
        }

        $archive_id      = absint($term->term_id);
        $filter_root_id  = Archive_Service::get_filter_root_id($archive_id);
        $query_root_id   = $filter_root_id ?: $archive_id;
        $selected_cat_id = ($filter_root_id && $archive_id !== $filter_root_id) ? $archive_id : 0;
        $runtime         = $this->runtime_settings($settings);
        $top_html        = Category_Meta::get_top_html($archive_id);

        $query = Archive_Service::query([
            'root_id'     => $query_root_id,
            'category_id' => $selected_cat_id,
            'page'        => 1,
            'per_page'    => $runtime['per_page'],
        ]);

        $sidebar = Archive_Service::render_filter_sidebar($filter_root_id, $selected_cat_id, $archive_id, $runtime);
        $classes = 'rdsco-ajax-archive ' . ($sidebar ? 'has-sidebar' : 'no-sidebar');
        ?>
        <?php if ('' !== trim($top_html)) : ?>
            <div class="rdsco-archive-top-html">
                <?php echo $top_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <div
            id="rdsco-archive-<?php echo esc_attr($this->get_id()); ?>"
            class="<?php echo esc_attr($classes); ?>"
            data-root-id="<?php echo esc_attr($archive_id); ?>"
            data-filter-root="<?php echo esc_attr($filter_root_id); ?>"
            data-category-id="<?php echo esc_attr($selected_cat_id); ?>"
            data-tag-id="0"
            data-current-page="1"
            data-max-pages="<?php echo esc_attr((int) $query->max_num_pages); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce(Archive_Service::NONCE_ACTION)); ?>"
            data-settings="<?php echo esc_attr(wp_json_encode($runtime)); ?>"
        >
            <?php echo $sidebar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <main class="rdsco-archive-content">
                <?php if ('yes' === ($settings['show_search'] ?? 'yes')) : ?>
                    <div class="rdsco-live-search">
                        <span class="rdsco-search-icon" aria-hidden="true">⌕</span>
                        <input type="search" class="rdsco-search-input" placeholder="<?php echo esc_attr($settings['search_placeholder'] ?? 'جستجو در مطالب…'); ?>" autocomplete="off">
                        <button type="button" class="rdsco-search-clear" aria-label="پاک کردن جستجو">×</button>
                    </div>
                <?php endif; ?>

                <div class="rdsco-archive-loading" aria-live="polite">
                    <span class="rdsco-spinner" aria-hidden="true"></span>
                    <span>در حال دریافت مطالب…</span>
                </div>

                <div class="rdsco-archive-results">
                    <?php echo Archive_Service::render_posts($query, $runtime); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>

                <?php if ('yes' === $runtime['show_result_count']) : ?>
                    <div class="rdsco-result-count"><strong><?php echo esc_html(number_format_i18n($query->found_posts)); ?></strong> مطلب یافت شد</div>
                <?php endif; ?>

                <?php if ('yes' === $runtime['show_load_more']) : ?>
                    <div class="rdsco-load-more-wrap" <?php echo $query->max_num_pages <= 1 ? 'hidden' : ''; ?>>
                        <button type="button" class="rdsco-load-more">نمایش مطالب بیشتر</button>
                    </div>
                <?php endif; ?>

                <?php if ('yes' === $runtime['show_pagination']) : ?>
                    <div class="rdsco-pagination-wrap">
                        <?php echo Archive_Service::render_pagination(1, (int) $query->max_num_pages); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
        <?php
    }
}
