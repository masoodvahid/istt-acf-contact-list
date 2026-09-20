<?php
/**
 * Query, security and rendering service for the Search Box widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\SearchBox;

if (!defined('ABSPATH')) {
    exit;
}

final class Search_Service
{
    const AJAX_ACTION = 'rdsco_search_box_fetch';
    const NONCE_ACTION = 'rdsco_search_box_nonce';

    public static function init()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_fetch']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_fetch']);
    }

    public static function category_options()
    {
        $terms = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            $options[(int) $term->term_id] = $term->name;
        }

        return $options;
    }

    public static function normalize_category_ids($value)
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $value))));

        return array_values(array_filter($ids, static function ($id) {
            return $id > 0 && term_exists($id, 'category');
        }));
    }

    public static function sanitize_runtime(array $settings)
    {
        $style = sanitize_key($settings['result_style'] ?? 'card');
        $order = sanitize_key($settings['order'] ?? 'newest');
        $image_size = sanitize_key($settings['image_size'] ?? 'medium');

        return [
            'allowed_category_ids' => self::normalize_category_ids($settings['allowed_category_ids'] ?? []),
            'include_children'     => self::to_bool($settings['include_children'] ?? true),
            'show_category_filter' => self::to_bool($settings['show_category_filter'] ?? true),
            'date_range_enabled'   => self::to_bool($settings['date_range_enabled'] ?? true),
            'show_initial_results' => self::to_bool($settings['show_initial_results'] ?? false),
            'minimum_characters'   => max(3, min(12, absint($settings['minimum_characters'] ?? 3))),
            'per_page'             => max(1, min(48, absint($settings['per_page'] ?? 9))),
            'result_style'         => in_array($style, ['card', 'list', 'minimal'], true) ? $style : 'card',
            'order'                => in_array($order, ['newest', 'oldest', 'title_asc', 'title_desc'], true) ? $order : 'newest',
            'show_image'           => self::to_bool($settings['show_image'] ?? true),
            'image_size'           => in_array($image_size, ['thumbnail', 'medium', 'medium_large', 'large'], true) ? $image_size : 'medium',
            'show_excerpt'         => self::to_bool($settings['show_excerpt'] ?? true),
            'excerpt_length'       => max(0, min(100, absint($settings['excerpt_length'] ?? 22))),
            'show_date'            => self::to_bool($settings['show_date'] ?? true),
            'show_category'        => self::to_bool($settings['show_category'] ?? true),
            'target_blank'         => self::to_bool($settings['target_blank'] ?? false),
            'pagination_window'    => max(1, min(4, absint($settings['pagination_window'] ?? 2))),
            'show_result_count'    => self::to_bool($settings['show_result_count'] ?? true),
            'button_text'          => sanitize_text_field($settings['button_text'] ?? 'مشاهده مطلب'),
            'empty_text'           => sanitize_text_field($settings['empty_text'] ?? 'نتیجه‌ای پیدا نشد.'),
            'prompt_text'          => sanitize_text_field($settings['prompt_text'] ?? 'عبارت موردنظر را جستجو کنید.'),
        ];
    }

    public static function sign_settings(array $settings)
    {
        return hash_hmac('sha256', wp_json_encode(self::sanitize_runtime($settings)), wp_salt('auth'));
    }

    public static function query(array $args)
    {
        $settings = self::sanitize_runtime($args['settings'] ?? []);
        $allowed = $settings['allowed_category_ids'];
        $selected = absint($args['category_id'] ?? 0);
        $search = sanitize_text_field($args['search'] ?? '');
        $date_from = self::sanitize_date($args['date_from'] ?? '');
        $date_to = self::sanitize_date($args['date_to'] ?? '');

        $category_ids = self::expand_category_ids($allowed, $settings['include_children']);

        if ($selected && in_array($selected, $allowed, true)) {
            $category_ids = self::expand_category_ids([$selected], $settings['include_children']);
        }

        $query_args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'posts_per_page'      => $settings['per_page'],
            'paged'               => max(1, absint($args['page'] ?? 1)),
            'category__in'        => $category_ids,
        ];

        if ('' !== $search) {
            $query_args['s'] = $search;
        }

        if ($settings['date_range_enabled'] && ($date_from || $date_to)) {
            $date_query = ['inclusive' => true];

            if ($date_from) {
                $date_query['after'] = $date_from . ' 00:00:00';
            }

            if ($date_to) {
                $date_query['before'] = $date_to . ' 23:59:59';
            }

            $query_args['date_query'] = [$date_query];
        }

        switch ($settings['order']) {
            case 'oldest':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'ASC';
                break;
            case 'title_asc':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
                break;
            case 'title_desc':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'DESC';
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
        }

        if (empty($category_ids)) {
            $query_args['post__in'] = [0];
            unset($query_args['category__in']);
        }

        return new \WP_Query($query_args);
    }

    public static function render_results(\WP_Query $query, array $settings)
    {
        $settings = self::sanitize_runtime($settings);

        if (!$query->have_posts()) {
            return self::render_message($settings['empty_text'], 'empty');
        }

        ob_start();
        ?>
        <div class="rdsco-search-results-grid">
            <?php foreach ($query->posts as $post) :
                $post_id = (int) $post->ID;
                $title = get_the_title($post_id);
                $permalink = get_permalink($post_id);
                $category = self::primary_category($post_id, $settings['allowed_category_ids']);
                $excerpt = wp_trim_words(
                    wp_strip_all_tags(strip_shortcodes(get_the_excerpt($post_id))),
                    $settings['excerpt_length'],
                    '…'
                );
                $target = $settings['target_blank'] ? ' target="_blank" rel="noopener noreferrer"' : '';
                ?>
                <article class="rdsco-search-result-card">
                    <a class="rdsco-search-result-link" href="<?php echo esc_url($permalink); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <?php if ($settings['show_image'] && 'minimal' !== $settings['result_style']) : ?>
                            <div class="rdsco-search-result-image">
                                <?php
                                if (has_post_thumbnail($post_id)) {
                                    echo get_the_post_thumbnail($post_id, $settings['image_size'], ['loading' => 'lazy']);
                                } else {
                                    echo '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="rdsco-search-result-content">
                            <?php if ($settings['show_category'] || $settings['show_date']) : ?>
                                <div class="rdsco-search-result-meta">
                                    <?php if ($settings['show_category'] && $category) : ?>
                                        <span><?php echo esc_html($category->name); ?></span>
                                    <?php endif; ?>
                                    <?php if ($settings['show_date']) : ?>
                                        <time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
                                            <?php echo esc_html(get_the_date('', $post_id)); ?>
                                        </time>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="rdsco-search-result-title"><?php echo esc_html($title); ?></h3>

                            <?php if ($settings['show_excerpt'] && $settings['excerpt_length'] > 0 && $excerpt) : ?>
                                <div class="rdsco-search-result-excerpt"><?php echo esc_html($excerpt); ?></div>
                            <?php endif; ?>

                            <?php if ($settings['button_text']) : ?>
                                <span class="rdsco-search-result-more">
                                    <?php echo esc_html($settings['button_text']); ?>
                                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function render_pagination($current, $max_pages, $window = 2)
    {
        $current = max(1, absint($current));
        $max_pages = max(1, absint($max_pages));
        $window = max(1, min(4, absint($window)));

        if ($max_pages <= 1) {
            return '';
        }

        $pages = array_unique(array_filter(array_merge(
            [1, $max_pages],
            range(max(1, $current - $window), min($max_pages, $current + $window))
        )));
        sort($pages);

        ob_start();
        ?>
        <nav class="rdsco-search-pagination" aria-label="<?php echo esc_attr__('صفحه‌بندی نتایج جستجو', 'rdsco-elementor-widgets'); ?>">
            <button type="button" class="rdsco-search-page rdsco-search-page-prev" data-page="<?php echo esc_attr($current - 1); ?>" <?php disabled(1, $current); ?>>
                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                <span class="rdsco-search-sr-only"><?php echo esc_html__('صفحه قبل', 'rdsco-elementor-widgets'); ?></span>
            </button>
            <?php
            $previous = 0;
            foreach ($pages as $page) :
                if ($previous && $page > $previous + 1) {
                    echo '<span class="rdsco-search-pagination-dots" aria-hidden="true">…</span>';
                }
                ?>
                <button
                    type="button"
                    class="rdsco-search-page<?php echo $page === $current ? ' is-active' : ''; ?>"
                    data-page="<?php echo esc_attr($page); ?>"
                    <?php echo $page === $current ? 'aria-current="page"' : ''; ?>
                ><?php echo esc_html($page); ?></button>
                <?php
                $previous = $page;
            endforeach;
            ?>
            <button type="button" class="rdsco-search-page rdsco-search-page-next" data-page="<?php echo esc_attr($current + 1); ?>" <?php disabled($max_pages, $current); ?>>
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                <span class="rdsco-search-sr-only"><?php echo esc_html__('صفحه بعد', 'rdsco-elementor-widgets'); ?></span>
            </button>
        </nav>
        <?php

        return ob_get_clean();
    }

    public static function render_message($text, $type = 'prompt')
    {
        $icon = 'empty' === $type ? 'dashicons-search' : 'dashicons-info-outline';

        return sprintf(
            '<div class="rdsco-search-message is-%1$s"><span class="dashicons %2$s" aria-hidden="true"></span><span>%3$s</span></div>',
            esc_attr($type),
            esc_attr($icon),
            esc_html($text)
        );
    }

    public static function ajax_fetch()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $raw_settings = isset($_POST['settings']) ? json_decode(wp_unslash($_POST['settings']), true) : [];

        if (!is_array($raw_settings)) {
            wp_send_json_error(['message' => 'تنظیمات جستجو نامعتبر است.'], 400);
        }

        $settings = self::sanitize_runtime($raw_settings);
        $signature = isset($_POST['signature']) ? sanitize_text_field(wp_unslash($_POST['signature'])) : '';

        if (!$signature || !hash_equals(self::sign_settings($settings), $signature)) {
            wp_send_json_error(['message' => 'اعتبار تنظیمات جستجو تأیید نشد.'], 403);
        }

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $date_from = isset($_POST['date_from']) ? self::sanitize_date(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? self::sanitize_date(wp_unslash($_POST['date_to'])) : '';
        $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;

        if ('' !== $search && self::text_length($search) < $settings['minimum_characters']) {
            wp_send_json_error([
                'message' => sprintf(
                    'برای جستجو حداقل %s حرف وارد کنید.',
                    number_format_i18n($settings['minimum_characters'])
                ),
            ], 400);
        }

        if ($category_id && !in_array($category_id, $settings['allowed_category_ids'], true)) {
            $category_id = 0;
        }

        if ($date_from && $date_to && $date_from > $date_to) {
            wp_send_json_error(['message' => 'تاریخ شروع باید پیش از تاریخ پایان باشد.'], 400);
        }

        $has_criteria = '' !== $search || $category_id > 0 || '' !== $date_from || '' !== $date_to;

        if (!$settings['show_initial_results'] && !$has_criteria) {
            wp_send_json_success([
                'html'       => self::render_message($settings['prompt_text']),
                'pagination' => '',
                'total'      => 0,
                'page'       => 1,
                'maxPages'   => 1,
                'showCount'  => false,
            ]);
        }

        $query = self::query([
            'settings'    => $settings,
            'search'      => $search,
            'category_id' => $category_id,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'page'        => $page,
        ]);

        wp_send_json_success([
            'html'       => self::render_results($query, $settings),
            'pagination' => self::render_pagination($page, $query->max_num_pages, $settings['pagination_window']),
            'total'      => (int) $query->found_posts,
            'page'       => $page,
            'maxPages'   => (int) $query->max_num_pages,
            'showCount'  => true,
        ]);
    }

    private static function expand_category_ids(array $root_ids, $include_children)
    {
        $ids = $root_ids;

        if ($include_children) {
            foreach ($root_ids as $root_id) {
                $children = get_term_children($root_id, 'category');

                if (!is_wp_error($children)) {
                    $ids = array_merge($ids, array_map('absint', $children));
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private static function primary_category($post_id, array $allowed_ids)
    {
        $categories = get_the_category($post_id);

        foreach ($categories as $category) {
            if (in_array((int) $category->term_id, $allowed_ids, true)) {
                return $category;
            }
        }

        return !empty($categories) ? reset($categories) : null;
    }

    private static function sanitize_date($value)
    {
        $value = sanitize_text_field((string) $value);
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private static function text_length($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return preg_match_all('/./us', $value, $matches) ?: 0;
    }

    private static function to_bool($value)
    {
        return true === $value || 1 === $value || '1' === $value || 'yes' === $value || 'true' === $value;
    }
}
