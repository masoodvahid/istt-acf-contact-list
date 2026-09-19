<?php
/**
 * Querying and rendering service for the Post Display widget.
 */

namespace RDSCO\ElementorWidgets\Widgets\PostDisplay;

if (!defined('ABSPATH')) {
    exit;
}

final class Post_Display_Service
{
    public const AJAX_ACTION  = 'rdsco_post_display_fetch';
    public const NONCE_ACTION = 'rdsco_post_display_fetch';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_fetch']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_fetch']);
    }

    public static function normalize_category_ids($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        $ids = array_map('absint', (array) $value);
        $ids = array_filter($ids);

        return array_values(array_unique($ids));
    }

    public static function get_allowed_category_ids(array $root_ids, bool $include_children): array
    {
        $allowed = self::normalize_category_ids($root_ids);

        if (!$include_children) {
            return $allowed;
        }

        foreach ($root_ids as $root_id) {
            $children = get_term_children(absint($root_id), 'category');
            if (is_wp_error($children)) {
                continue;
            }

            foreach ($children as $child_id) {
                $allowed[] = absint($child_id);
            }
        }

        return array_values(array_unique(array_filter($allowed)));
    }

    public static function get_filter_terms(array $root_ids, bool $include_children): array
    {
        $root_ids = self::normalize_category_ids($root_ids);
        if (!$root_ids) {
            return [];
        }

        $terms = [];
        $seen = [];
        $multiple_roots = count($root_ids) > 1;

        foreach ($root_ids as $root_id) {
            $root = get_term($root_id, 'category');
            if (!$root || is_wp_error($root)) {
                continue;
            }

            if ($multiple_roots || !$include_children) {
                $terms[] = $root;
                $seen[$root->term_id] = true;
            }

            if (!$include_children) {
                continue;
            }

            $children = get_terms([
                'taxonomy'   => 'category',
                'child_of'   => $root_id,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);

            if (is_wp_error($children)) {
                continue;
            }

            foreach ($children as $child) {
                if (!isset($seen[$child->term_id])) {
                    $terms[] = $child;
                    $seen[$child->term_id] = true;
                }
            }
        }

        return $terms;
    }

    public static function query(array $args): \WP_Query
    {
        $root_ids         = self::normalize_category_ids($args['root_ids'] ?? []);
        $include_children = !empty($args['include_children']);
        $active_category  = absint($args['active_category'] ?? 0);
        $search           = sanitize_text_field($args['search'] ?? '');
        $page             = max(1, absint($args['page'] ?? 1));
        $per_page         = max(1, min(60, absint($args['per_page'] ?? 12)));

        $allowed = self::get_allowed_category_ids($root_ids, $include_children);

        if ($active_category && !in_array($active_category, $allowed, true)) {
            $active_category = 0;
        }

        $terms = $active_category ? [$active_category] : $root_ids;

        $query_args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => $page,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ];

        if ($terms) {
            $query_args['tax_query'] = [
                [
                    'taxonomy'         => 'category',
                    'field'            => 'term_id',
                    'terms'            => $terms,
                    'include_children' => $include_children,
                    'operator'         => 'IN',
                ],
            ];
        } else {
            $query_args['post__in'] = [0];
        }

        if ($search !== '') {
            $query_args['s'] = $search;
        }

        return new \WP_Query($query_args);
    }

    private static function first_relevant_category(int $post_id, array $allowed_ids): ?\WP_Term
    {
        $categories = get_the_category($post_id);
        if (!$categories) {
            return null;
        }

        foreach ($categories as $category) {
            if (in_array((int) $category->term_id, $allowed_ids, true)) {
                return $category;
            }
        }

        return $categories[0] ?? null;
    }

    private static function excerpt(int $post_id, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $excerpt = trim((string) get_the_excerpt($post_id));

        if ($excerpt === '') {
            $content = get_post_field('post_content', $post_id);
            $content = strip_shortcodes($content);
            $excerpt = wp_strip_all_tags($content);
        }

        $excerpt = preg_replace('/^\s*معرفی\s*خدمت\s*[:：\-]?\s*/u', '', $excerpt);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt);

        return wp_trim_words($excerpt, $length, '…');
    }

    private static function icon_class(string $category_name): string
    {
        $name = wp_strip_all_tags($category_name);

        $map = [
            ['needles' => ['فناوری اطلاعات', 'ارتباطات', 'شبکه', 'اینترنت'], 'icon' => 'dashicons-admin-site-alt3'],
            ['needles' => ['مالی', 'سرمایه', 'تسهیلات', 'وام'], 'icon' => 'dashicons-chart-line'],
            ['needles' => ['آموزش', 'مشاوره'], 'icon' => 'dashicons-welcome-learn-more'],
            ['needles' => ['قانونی', 'حقوق', 'مالکیت'], 'icon' => 'dashicons-shield'],
            ['needles' => ['بازاریابی', 'تجاری', 'تبلیغ'], 'icon' => 'dashicons-megaphone'],
            ['needles' => ['بین الملل', 'بین‌الملل', 'همکاری علمی'], 'icon' => 'dashicons-admin-site'],
            ['needles' => ['استقرار', 'فضا', 'ساختمان'], 'icon' => 'dashicons-building'],
            ['needles' => ['آزمایش', 'فنی', 'تخصصی'], 'icon' => 'dashicons-admin-tools'],
            ['needles' => ['روابط عمومی', 'اطلاع رسانی', 'اطلاع‌رسانی'], 'icon' => 'dashicons-format-status'],
        ];

        foreach ($map as $item) {
            foreach ($item['needles'] as $needle) {
                if (false !== strpos($name, $needle)) {
                    return $item['icon'];
                }
            }
        }

        return 'dashicons-admin-post';
    }

    public static function render_posts(\WP_Query $query, array $settings): string
    {
        $root_ids         = self::normalize_category_ids($settings['root_ids'] ?? []);
        $include_children = !empty($settings['include_children']);
        $allowed_ids      = self::get_allowed_category_ids($root_ids, $include_children);
        $card_style       = in_array(($settings['card_style'] ?? 'icon'), ['icon', 'image'], true)
            ? $settings['card_style']
            : 'icon';
        $show_excerpt     = 'yes' === ($settings['show_excerpt'] ?? 'yes');
        $excerpt_length   = max(0, absint($settings['excerpt_length'] ?? 20));
        $button_text      = sanitize_text_field($settings['button_text'] ?? 'مشاهده مطلب');

        ob_start();

        if (!$query->have_posts()) {
            echo '<div class="rdsco-post-display-empty"><span class="dashicons dashicons-search" aria-hidden="true"></span><strong>'
                . esc_html($settings['empty_text'] ?? 'مطلبی پیدا نشد.')
                . '</strong></div>';
            return (string) ob_get_clean();
        }

        echo '<div class="rdsco-post-display-grid">';

        while ($query->have_posts()) {
            $query->the_post();

            $post_id   = get_the_ID();
            $title     = get_the_title();
            $permalink = get_permalink();
            $category  = self::first_relevant_category($post_id, $allowed_ids);
            $cat_name  = $category ? $category->name : '';
            $excerpt   = $show_excerpt ? self::excerpt($post_id, $excerpt_length) : '';
            $thumb     = get_the_post_thumbnail_url($post_id, 'medium_large');
            $icon      = self::icon_class($cat_name);
            ?>
            <article class="rdsco-post-display-card style-<?php echo esc_attr($card_style); ?>">
                <a class="rdsco-post-display-card-link" href="<?php echo esc_url($permalink); ?>">
                    <?php if ('image' === $card_style) : ?>
                        <div class="rdsco-post-display-image">
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                            <?php else : ?>
                                <span class="rdsco-post-display-image-placeholder dashicons dashicons-format-image" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rdsco-post-display-card-body">
                        <?php if ($cat_name) : ?>
                            <span class="rdsco-post-display-category"><?php echo esc_html($cat_name); ?></span>
                        <?php endif; ?>

                        <h3 class="rdsco-post-display-title"><?php echo esc_html($title); ?></h3>

                        <?php if ($excerpt) : ?>
                            <div class="rdsco-post-display-excerpt"><?php echo esc_html($excerpt); ?></div>
                        <?php endif; ?>

                        <span class="rdsco-post-display-more">
                            <?php echo esc_html($button_text); ?>
                            <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                        </span>
                    </div>

                    <?php if ('icon' === $card_style) : ?>
                        <div class="rdsco-post-display-visual" aria-hidden="true">
                            <div class="rdsco-post-display-icon-stage">
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </a>
            </article>
            <?php
        }

        echo '</div>';
        wp_reset_postdata();

        return (string) ob_get_clean();
    }

    public static function ajax_fetch(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
        $settings = json_decode($raw_settings, true);

        if (!is_array($settings)) {
            $settings = [];
        }

        $root_ids         = self::normalize_category_ids($settings['root_ids'] ?? []);
        $include_children = !empty($settings['include_children']);
        $allowed_ids      = self::get_allowed_category_ids($root_ids, $include_children);
        $active_category  = absint($_POST['active_category'] ?? 0);

        if ($active_category && !in_array($active_category, $allowed_ids, true)) {
            $active_category = 0;
        }

        $page     = max(1, absint($_POST['page'] ?? 1));
        $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $per_page = max(1, min(60, absint($settings['per_page'] ?? 12)));

        $runtime = [
            'root_ids'         => $root_ids,
            'include_children' => $include_children,
            'card_style'       => in_array(($settings['card_style'] ?? 'icon'), ['icon', 'image'], true) ? $settings['card_style'] : 'icon',
            'show_excerpt'     => 'yes' === ($settings['show_excerpt'] ?? 'yes') ? 'yes' : 'no',
            'excerpt_length'   => max(0, min(100, absint($settings['excerpt_length'] ?? 20))),
            'button_text'      => sanitize_text_field($settings['button_text'] ?? 'مشاهده مطلب'),
            'empty_text'       => sanitize_text_field($settings['empty_text'] ?? 'مطلبی پیدا نشد.'),
        ];

        $query = self::query([
            'root_ids'         => $root_ids,
            'include_children' => $include_children,
            'active_category'  => $active_category,
            'search'           => $search,
            'page'             => $page,
            'per_page'         => $per_page,
        ]);

        wp_send_json_success([
            'html'     => self::render_posts($query, $runtime),
            'page'     => $page,
            'maxPages' => (int) $query->max_num_pages,
            'total'    => number_format_i18n($query->found_posts),
            'totalRaw' => (int) $query->found_posts,
        ]);
    }
}
