<?php
/**
 * Core functionality for ISTT ACF Contact List.
 */

if (!defined('ABSPATH')) {
    exit;
}

function istt_acl_fields()
{
    return [
        'zone'           => 'istt_contact_zone',
        'email'          => 'istt_contact_email',
        'external_phone' => 'istt_contact_external_phone',
        'internal_phone' => 'istt_contact_internal_phone',
    ];
}

function istt_acl_register_assets()
{
    wp_register_style(
        'istt-acf-contact-list',
        ISTT_ACF_CONTACT_LIST_URL . 'assets/css/style.css',
        [],
        ISTT_ACF_CONTACT_LIST_VERSION
    );

    wp_register_script(
        'istt-acf-contact-list-qrcode',
        ISTT_ACF_CONTACT_LIST_URL . 'assets/js/qrcode.min.js',
        [],
        '1.0.0',
        true
    );

    wp_register_script(
        'istt-acf-contact-list',
        ISTT_ACF_CONTACT_LIST_URL . 'assets/js/app.js',
        ['istt-acf-contact-list-qrcode'],
        ISTT_ACF_CONTACT_LIST_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'istt_acl_register_assets', 10);

function istt_acl_enqueue_assets()
{
    istt_acl_register_assets();
    wp_enqueue_style('dashicons');
    wp_enqueue_style('istt-acf-contact-list');
    wp_enqueue_script('istt-acf-contact-list-qrcode');
    wp_enqueue_script('istt-acf-contact-list');
}

function istt_acl_should_preload_assets()
{
    if (is_category('contacts')) {
        return true;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return false;
    }

    if (has_shortcode((string) $post->post_content, 'istt_phonebook')) {
        return true;
    }

    $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
    return is_string($elementor_data) && strpos($elementor_data, 'istt_phonebook') !== false;
}

function istt_acl_maybe_enqueue_assets()
{
    if (istt_acl_should_preload_assets()) {
        istt_acl_enqueue_assets();
    }
}
add_action('wp_enqueue_scripts', 'istt_acl_maybe_enqueue_assets', 20);

function istt_acl_normalize_digits($value)
{
    return strtr((string) $value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function istt_acl_phone_href($value)
{
    $phone = preg_replace('/[^0-9]/', '', istt_acl_normalize_digits($value));

    if ($phone === '' || strlen($phone) === 11) {
        return $phone;
    }

    // Local eight-digit Isfahan numbers need the 031 area code.
    if (strlen($phone) === 8) {
        return '031' . $phone;
    }

    // Also normalize common saved forms: 31xxxxxxxx and 9831xxxxxxxx.
    if (strlen($phone) === 10 && strpos($phone, '31') === 0) {
        return '0' . $phone;
    }

    if (strlen($phone) === 12 && strpos($phone, '9831') === 0) {
        return '0' . substr($phone, 2);
    }

    return $phone;
}

function istt_acl_get_meta_value($post_id, $field_name)
{
    return function_exists('get_field')
        ? get_field($field_name, $post_id)
        : get_post_meta($post_id, $field_name, true);
}

function istt_acl_format_acf_value($value)
{
    if (empty($value)) {
        return '';
    }

    if (is_array($value) && isset($value['label'])) {
        return sanitize_text_field($value['label']);
    }

    if (is_array($value)) {
        $items = [];
        foreach ($value as $item) {
            if (is_array($item) && isset($item['label'])) {
                $items[] = sanitize_text_field($item['label']);
            } elseif (is_scalar($item)) {
                $items[] = sanitize_text_field($item);
            }
        }
        return implode('، ', array_filter($items));
    }

    return sanitize_text_field($value);
}

function istt_acl_protected_email($email)
{
    $email = sanitize_email($email);
    if (!$email) {
        return '—';
    }

    return sprintf(
        '<span class="istt-pb-protected-email">%s</span>',
        esc_html(strrev($email))
    );
}

function istt_acl_contact_photo($post_id, $person_name, $size = 'thumbnail')
{
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail($post_id, $size, [
            'loading'  => 'lazy',
            'decoding' => 'async',
            'alt'      => esc_attr($person_name),
        ]);
    }

    return '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';
}

function istt_acl_get_contacts_term()
{
    $term = get_term_by('slug', 'contacts', 'category');
    return (!$term || is_wp_error($term)) ? false : $term;
}

function istt_acl_get_zones($term_id)
{
    global $wpdb;

    $term = get_term(absint($term_id), 'category');
    if (!$term || is_wp_error($term)) {
        return [];
    }

    $field_name = istt_acl_fields()['zone'];
    $cache_key  = 'zones_' . md5($term->term_taxonomy_id);
    $cached     = wp_cache_get($cache_key, 'istt_phonebook');

    if ($cached !== false) {
        return $cached;
    }

    $raw_values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->term_relationships} tr
         INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
         INNER JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID AND pm.meta_key = %s
         WHERE tr.term_taxonomy_id = %d
           AND p.post_type = 'post'
           AND p.post_status = 'publish'
           AND pm.meta_value <> ''
         ORDER BY pm.meta_value ASC",
        $field_name,
        absint($term->term_taxonomy_id)
    ));

    $field_object = null;
    if ($raw_values && function_exists('get_field_object')) {
        $sample_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = %s
             WHERE tr.term_taxonomy_id = %d
               AND p.post_type = 'post'
               AND p.post_status = 'publish'
             LIMIT 1",
            $field_name,
            absint($term->term_taxonomy_id)
        ));

        if ($sample_id) {
            $field_object = get_field_object($field_name, $sample_id, false, false);
        }
    }

    $zones = [];
    foreach ($raw_values as $raw_value) {
        $decoded = maybe_unserialize($raw_value);
        foreach ((is_array($decoded) ? $decoded : [$decoded]) as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $value = sanitize_text_field($value);
            if ($value === '') {
                continue;
            }
            $zones[$value] = (
                is_array($field_object) &&
                !empty($field_object['choices']) &&
                isset($field_object['choices'][$value])
            ) ? $field_object['choices'][$value] : $value;
        }
    }

    asort($zones, SORT_NATURAL | SORT_FLAG_CASE);
    wp_cache_set($cache_key, $zones, 'istt_phonebook', 300);
    return $zones;
}

function istt_acl_get_units($term_id, $zone = '')
{
    global $wpdb;

    $term = get_term(absint($term_id), 'category');
    if (!$term || is_wp_error($term)) {
        return [];
    }

    $zone      = sanitize_text_field($zone);
    $cache_key = 'units_' . md5($term->term_taxonomy_id . '|' . $zone);
    $cached    = wp_cache_get($cache_key, 'istt_phonebook');

    if ($cached !== false) {
        return $cached;
    }

    if ($zone !== '') {
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT TRIM(p.post_excerpt) AS unit_name
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             INNER JOIN {$wpdb->postmeta} zm
                ON zm.post_id = p.ID
               AND zm.meta_key = %s
               AND zm.meta_value = %s
             WHERE tr.term_taxonomy_id = %d
               AND p.post_type = 'post'
               AND p.post_status = 'publish'
               AND TRIM(p.post_excerpt) <> ''
             ORDER BY unit_name ASC",
            istt_acl_fields()['zone'],
            $zone,
            absint($term->term_taxonomy_id)
        ));
    } else {
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT TRIM(p.post_excerpt) AS unit_name
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d
               AND p.post_type = 'post'
               AND p.post_status = 'publish'
               AND TRIM(p.post_excerpt) <> ''
             ORDER BY unit_name ASC",
            absint($term->term_taxonomy_id)
        ));
    }

    $units = [];
    foreach ($values as $value) {
        $value = sanitize_text_field($value);
        if ($value !== '') {
            $units[$value] = $value;
        }
    }

    natcasesort($units);
    wp_cache_set($cache_key, $units, 'istt_phonebook', 300);
    return $units;
}

function istt_acl_get_contact_tags($term_id, $limit = 8)
{
    global $wpdb;

    $category = get_term(absint($term_id), 'category');
    if (!$category || is_wp_error($category)) {
        return [];
    }

    $limit     = min(20, max(1, absint($limit)));
    $cache_key = 'tags_' . md5($category->term_taxonomy_id . '|' . $limit);
    $cached    = wp_cache_get($cache_key, 'istt_phonebook');

    if ($cached !== false) {
        return $cached;
    }

    $tags = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT t.term_id, t.name, t.slug
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->term_relationships} cr
            ON cr.object_id = p.ID AND cr.term_taxonomy_id = %d
         INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
         INNER JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_taxonomy_id = tr.term_taxonomy_id
           AND tt.taxonomy = 'post_tag'
         INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
         WHERE p.post_type = 'post' AND p.post_status = 'publish'
         ORDER BY t.name ASC
         LIMIT %d",
        absint($category->term_taxonomy_id),
        $limit
    ));

    wp_cache_set($cache_key, $tags, 'istt_phonebook', 300);
    return $tags;
}

function istt_acl_posts_where($where, $query)
{
    global $wpdb;

    if (!$query->get('istt_acl_query')) {
        return $where;
    }

    $search = trim((string) $query->get('istt_acl_search'));
    $unit   = trim((string) $query->get('istt_acl_unit'));

    if ($search !== '') {
        $like      = '%' . $wpdb->esc_like(istt_acl_normalize_digits($search)) . '%';
        $meta_keys = array_values(istt_acl_fields());
        $holders   = implode(', ', array_fill(0, count($meta_keys), '%s'));
        $sql       = " AND (
            {$wpdb->posts}.post_title LIKE %s
            OR {$wpdb->posts}.post_excerpt LIKE %s
            OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} sm
                WHERE sm.post_id = {$wpdb->posts}.ID
                  AND sm.meta_key IN ({$holders})
                  AND sm.meta_value LIKE %s
            )
        )";
        $where .= $wpdb->prepare($sql, array_merge([$like, $like], $meta_keys, [$like]));
    }

    if ($unit !== '') {
        $where .= $wpdb->prepare(
            " AND TRIM({$wpdb->posts}.post_excerpt) = %s",
            $unit
        );
    }

    return $where;
}

function istt_acl_query($args = [])
{
    $args = wp_parse_args($args, [
        'term_id'        => 0,
        'search'         => '',
        'zone'           => '',
        'unit'           => '',
        'tag_id'         => 0,
        'paged'          => 1,
        'posts_per_page' => 20,
    ]);

    $tax_query = [
        'relation' => 'AND',
        [
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => [absint($args['term_id'])],
        ],
    ];

    if ($args['tag_id']) {
        $tax_query[] = [
            'taxonomy' => 'post_tag',
            'field'    => 'term_id',
            'terms'    => [absint($args['tag_id'])],
        ];
    }

    $query_args = [
        'post_type'              => 'post',
        'post_status'            => 'publish',
        'posts_per_page'         => min(50, max(1, absint($args['posts_per_page']))),
        'paged'                  => max(1, absint($args['paged'])),
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'tax_query'              => $tax_query,
        'istt_acl_query'          => true,
        'istt_acl_search'         => sanitize_text_field($args['search']),
        'istt_acl_unit'           => sanitize_text_field($args['unit']),
    ];

    if ($args['zone'] !== '') {
        $query_args['meta_query'] = [[
            'key'     => istt_acl_fields()['zone'],
            'value'   => sanitize_text_field($args['zone']),
            'compare' => '=',
        ]];
    }

    add_filter('posts_where', 'istt_acl_posts_where', 10, 2);
    $query = new WP_Query($query_args);
    remove_filter('posts_where', 'istt_acl_posts_where', 10);
    return $query;
}

function istt_acl_pagination($query, $page)
{
    $total = absint($query->max_num_pages);
    $page  = max(1, absint($page));
    if ($total <= 1) {
        return '';
    }

    ob_start(); ?>
    <nav class="istt-pb-pagination" aria-label="صفحه‌بندی دفتر تلفن">
        <button type="button" class="istt-pb-page-button" data-page="<?php echo esc_attr(max(1, $page - 1)); ?>" <?php disabled($page <= 1); ?>>
            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span> قبلی
        </button>
        <span>صفحه <?php echo esc_html(number_format_i18n($page)); ?> از <?php echo esc_html(number_format_i18n($total)); ?></span>
        <button type="button" class="istt-pb-page-button" data-page="<?php echo esc_attr(min($total, $page + 1)); ?>" <?php disabled($page >= $total); ?>>
            بعدی <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        </button>
    </nav>
    <?php return ob_get_clean();
}

function istt_acl_render_results($args = [])
{
    $args = wp_parse_args($args, [
        'term_id' => 0, 'search' => '', 'zone' => '', 'unit' => '',
        'tag_id' => 0, 'paged' => 1, 'posts_per_page' => 20,
    ]);

    $fields = istt_acl_fields();
    $zones  = istt_acl_get_zones($args['term_id']);
    $query  = istt_acl_query($args);
    $title  = ($args['zone'] !== '' && isset($zones[$args['zone']]))
        ? $zones[$args['zone']]
        : 'همه مخاطبان';

    ob_start();
    if (!$query->have_posts()) { ?>
        <div class="istt-pb-empty">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <p>نتیجه‌ای مطابق جست‌وجوی شما پیدا نشد.</p>
        </div>
        <?php return ob_get_clean();
    } ?>

    <div class="istt-pb-results-head">
        <div>
            <span><?php echo esc_html(number_format_i18n($query->found_posts)); ?> نفر در سازمان یافت شد</span>
            <h2><?php echo esc_html($title); ?></h2>
        </div>
        <?php if ($args['unit'] !== '') : ?>
            <span class="istt-pb-current-unit"><?php echo esc_html($args['unit']); ?></span>
        <?php endif; ?>
    </div>

    <div class="istt-pb-contacts-list">
        <?php $index = 0; while ($query->have_posts()) : $query->the_post();
            $index++;
            $post_id = get_the_ID();
            $name    = get_the_title();
            $unit    = trim(wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));
            $raw_zone = get_post_meta($post_id, $fields['zone'], true);
            $zone = isset($zones[$raw_zone]) ? $zones[$raw_zone] : istt_acl_format_acf_value(istt_acl_get_meta_value($post_id, $fields['zone']));
            $email    = istt_acl_get_meta_value($post_id, $fields['email']);
            $external = istt_acl_phone_href(istt_acl_get_meta_value($post_id, $fields['external_phone']));
            $internal = istt_acl_get_meta_value($post_id, $fields['internal_phone']);
        ?>
            <article class="istt-pb-contact-row" style="--pb-row-index:<?php echo esc_attr($index); ?>">
                <div class="istt-pb-contact-photo"><?php echo istt_acl_contact_photo($post_id, $name); ?></div>
                <div class="istt-pb-contact-identity">
                    <h3><?php echo esc_html($name); ?></h3>
                    <p><?php echo $unit !== '' ? esc_html($unit) : 'واحد سازمانی ثبت نشده'; ?></p>
                </div>
                <div class="istt-pb-contact-zone"><?php echo $zone !== '' ? esc_html($zone) : '—'; ?></div>
                <div class="istt-pb-contact-phone">
                    <span class="dashicons dashicons-phone" aria-hidden="true"></span>
                    <span><?php echo $internal ? esc_html($internal) : '—'; ?></span>
                </div>
                <div class="istt-pb-contact-email-status">
                    <?php if ($email) : ?><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><?php else : ?>—<?php endif; ?>
                </div>
                <button type="button" class="istt-pb-details-button" data-contact-id="<?php echo esc_attr($post_id); ?>">
                    اطلاعات بیشتر <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </button>

                <template id="istt-pb-contact-<?php echo esc_attr($post_id); ?>">
                    <div class="istt-pb-sidebar-profile">
                        <div class="istt-pb-sidebar-photo"><?php echo istt_acl_contact_photo($post_id, $name, 'medium'); ?></div>
                        <h2><?php echo esc_html($name); ?></h2>
                        <p class="istt-pb-sidebar-position"><?php echo $unit !== '' ? esc_html($unit) : '—'; ?></p>
                        <div class="istt-pb-sidebar-divider"></div>
                        <dl class="istt-pb-sidebar-data">
                            <div><dt><span class="dashicons dashicons-building"></span> حوزه</dt><dd><?php echo $zone !== '' ? esc_html($zone) : '—'; ?></dd></div>
                            <div><dt><span class="dashicons dashicons-networking"></span> واحد سازمانی</dt><dd><?php echo $unit !== '' ? esc_html($unit) : '—'; ?></dd></div>
                            <div><dt><span class="dashicons dashicons-phone"></span> شماره داخلی</dt><dd><?php echo $internal ? esc_html($internal) : '—'; ?></dd></div>
                            <div><dt><span class="dashicons dashicons-phone"></span> شماره مستقیم</dt><dd><?php echo $external ? esc_html($external) : '—'; ?></dd></div>
                            <div><dt><span class="dashicons dashicons-email-alt"></span> رایانامه</dt><dd><?php echo istt_acl_protected_email($email); ?></dd></div>
                        </dl>
                        <?php if ($external) : ?>
                            <a class="istt-pb-sidebar-call" href="tel:<?php echo esc_attr(istt_acl_phone_href($external)); ?>">
                                <span class="dashicons dashicons-phone"></span> تماس
                            </a>
                        <?php endif; ?>
                        <section class="istt-pb-qr-section" data-qr-contact="<?php echo esc_attr($post_id); ?>" data-qr-token="<?php echo esc_attr(istt_acl_contact_token($post_id)); ?>">
                            <div class="istt-pb-qr-title">
                                <span class="dashicons dashicons-smartphone"></span>
                                <div><h3>ذخیره مخاطب</h3><p>کد را با دوربین تلفن همراه اسکن کنید یا فایل مخاطب را دریافت کنید.</p></div>
                            </div>
                            <div class="istt-pb-qr-box">
                                <span class="dashicons dashicons-update istt-pb-qr-loader"></span>
                                <span>در حال ساخت QR Code...</span>
                            </div>
                            <a href="#" class="istt-pb-vcard-download" hidden download>
                                <span class="dashicons dashicons-download"></span> دانلود فایل مخاطب
                            </a>
                            <div class="istt-pb-qr-error" hidden>ساخت QR Code ممکن نشد.</div>
                        </section>
                    </div>
                </template>
            </article>
        <?php endwhile; ?>
    </div>
    <?php echo istt_acl_pagination($query, $args['paged']);
    wp_reset_postdata();
    return ob_get_clean();
}

function istt_acl_phonebook_shortcode($atts)
{
    istt_acl_enqueue_assets();
    $atts = shortcode_atts(['posts_per_page' => 20, 'tags_limit' => 8], $atts, 'istt_phonebook');
    $term = istt_acl_get_contacts_term();
    if (!$term) {
        return '<div class="istt-pb-error">دسته‌بندی با نامک contacts پیدا نشد.</div>';
    }

    $term_id = absint($term->term_id);
    $per_page = min(50, max(1, absint($atts['posts_per_page'])));
    $tags_limit = min(20, max(1, absint($atts['tags_limit'])));
    $zones = istt_acl_get_zones($term_id);
    $units = istt_acl_get_units($term_id);
    $tags  = istt_acl_get_contact_tags($term_id, $tags_limit);
    $id    = wp_unique_id('istt-phonebook-');
    $nonce = wp_create_nonce('istt_phonebook_filter');

    ob_start(); ?>
    <section id="<?php echo esc_attr($id); ?>" class="istt-pb" dir="rtl" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <div class="istt-pb-hero">
            <div class="istt-pb-eyebrow">ارتباط با شهرک</div>
            <h1>دنبال چه کسی هستید؟</h1>
            <p>نام، سمت، واحد سازمانی یا شماره داخلی موردنظر خود را جست‌وجو کنید.</p>

            <form class="istt-pb-form">
                <input type="hidden" name="action" value="istt_phonebook_filter">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="term_id" value="<?php echo esc_attr($term_id); ?>">
                <input type="hidden" name="tag_id" value="0">
                <input type="hidden" name="paged" value="1">
                <input type="hidden" name="posts_per_page" value="<?php echo esc_attr($per_page); ?>">

                <div class="istt-pb-search-box">
                    <input type="search" name="search" placeholder="نام، سمت، واحد یا داخلی را جست‌وجو کنید..." autocomplete="off">
                    <button type="submit" aria-label="جست‌وجو"><span class="dashicons dashicons-search"></span></button>
                </div>

                <?php if ($tags) : ?>
                    <div class="istt-pb-start-section">
                        <h2>از کجا شروع کنم؟</h2>
                        <p>موضوع موردنظر خود را انتخاب کنید تا گزینه‌های مرتبط نمایش داده شوند.</p>
                        <div class="istt-pb-tag-links">
                            <?php $tag_index = 0; foreach ($tags as $tag) : $tag_index++; ?>
                                <button type="button" class="istt-pb-tag-link" data-tag-id="<?php echo esc_attr($tag->term_id); ?>" aria-pressed="false" style="--pb-tag-index:<?php echo esc_attr($tag_index); ?>">
                                    <?php echo esc_html($tag->name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="istt-pb-filter-area">
                    <div class="istt-pb-zone-filters">
                        <button type="button" class="istt-pb-zone active" data-zone="">همه</button>
                        <?php foreach ($zones as $value => $label) : ?>
                            <button type="button" class="istt-pb-zone" data-zone="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="zone" value="">
                    <div class="istt-pb-unit-filter">
                        <label for="<?php echo esc_attr($id . '-unit'); ?>">واحد سازمانی</label>
                        <select id="<?php echo esc_attr($id . '-unit'); ?>" name="unit">
                            <option value="">همه واحدهای سازمانی</option>
                            <?php foreach ($units as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="istt-pb-reset"><span class="dashicons dashicons-image-rotate"></span> پاک‌کردن فیلترها</button>
                </div>
            </form>
        </div>

        <div class="istt-pb-loading" hidden><span class="dashicons dashicons-update"></span> در حال دریافت اطلاعات...</div>
        <div class="istt-pb-results"><?php echo istt_acl_render_results(['term_id' => $term_id, 'paged' => 1, 'posts_per_page' => $per_page]); ?></div>

        <div class="istt-pb-sidebar-layer" aria-hidden="true">
            <button type="button" class="istt-pb-sidebar-backdrop" aria-label="بستن اطلاعات مخاطب"></button>
            <aside class="istt-pb-sidebar" role="dialog" aria-modal="true" aria-label="جزئیات اطلاعات تماس">
                <div class="istt-pb-sidebar-top">
                    <span>اطلاعات تماس</span>
                    <button type="button" class="istt-pb-sidebar-close" aria-label="بستن"><span class="dashicons dashicons-no-alt"></span></button>
                </div>
                <div class="istt-pb-sidebar-content"></div>
            </aside>
        </div>
    </section>


    <?php return ob_get_clean();
}

add_shortcode('istt_phonebook', 'istt_acl_phonebook_shortcode');

function istt_acl_ajax_filter()
{
    check_ajax_referer('istt_phonebook_filter', 'nonce');
    $term = istt_acl_get_contacts_term();
    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;

    if (!$term || $term_id !== absint($term->term_id)) {
        wp_send_json_error(['message' => 'Invalid category.']);
    }

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $zone   = isset($_POST['zone']) ? sanitize_text_field(wp_unslash($_POST['zone'])) : '';
    $unit   = isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : '';
    $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;
    $paged  = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
    $per_page = isset($_POST['posts_per_page']) ? min(50, max(1, absint($_POST['posts_per_page']))) : 20;

    if ($tag_id && !term_exists($tag_id, 'post_tag')) {
        $tag_id = 0;
    }

    $response = ['html' => istt_acl_render_results([
        'term_id' => $term_id, 'search' => $search, 'zone' => $zone,
        'unit' => $unit, 'tag_id' => $tag_id, 'paged' => $paged,
        'posts_per_page' => $per_page,
    ])];

    if (!empty($_POST['update_units'])) {
        $response['units'] = istt_acl_get_units($term_id, $zone);
    }

    wp_send_json_success($response);
}

add_action('wp_ajax_istt_phonebook_filter', 'istt_acl_ajax_filter');
add_action('wp_ajax_nopriv_istt_phonebook_filter', 'istt_acl_ajax_filter');

function istt_acl_contact_token($post_id)
{
    return substr(
        hash_hmac(
            'sha256',
            'istt-phonebook-vcard|' . absint($post_id),
            wp_salt('nonce')
        ),
        0,
        32
    );
}

function istt_acl_vcard_escape($value)
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\r", "\n"],
        ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"],
        wp_strip_all_tags((string) $value)
    );
}

function istt_acl_build_vcard($post_id)
{
    $fields = istt_acl_fields();
    $name = trim(wp_strip_all_tags(get_the_title($post_id)));
    $unit = trim(wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));
    $email = sanitize_email(istt_acl_get_meta_value($post_id, $fields['email']));
    $external = istt_acl_phone_href(istt_acl_get_meta_value($post_id, $fields['external_phone']));
    $internal = sanitize_text_field(istt_acl_get_meta_value($post_id, $fields['internal_phone']));

    // Keep the QR payload compact and predictable, especially for Persian UTF-8 text.
    if ($unit !== '') {
        $unit = wp_html_excerpt($unit, 60, '');
    }

    $lines = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:' . istt_acl_vcard_escape($name),
        'N:;;;;',
        'ORG:ISTT',
    ];
    if ($unit !== '')     { $lines[] = 'TITLE:' . istt_acl_vcard_escape($unit); }
    if ($external !== '') { $lines[] = 'TEL;TYPE=WORK:' . istt_acl_vcard_escape($external); }
    if ($email !== '')    { $lines[] = 'EMAIL:' . istt_acl_vcard_escape($email); }
    if ($internal !== '') { $lines[] = 'NOTE:Ext ' . istt_acl_vcard_escape($internal); }
    $lines[] = 'END:VCARD';

    return implode("\r\n", $lines);
}

function istt_acl_ajax_vcard()
{
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $token   = isset($_POST['token'])
        ? sanitize_text_field(wp_unslash($_POST['token']))
        : '';

    if (
        !$post_id ||
        !$token ||
        !hash_equals(istt_acl_contact_token($post_id), $token) ||
        get_post_type($post_id) !== 'post' ||
        get_post_status($post_id) !== 'publish' ||
        !has_category('contacts', $post_id)
    ) {
        wp_send_json_error(
            ['message' => 'مجوز دریافت اطلاعات این مخاطب معتبر نیست.'],
            403
        );
    }

    $filename = sanitize_file_name(get_the_title($post_id));
    wp_send_json_success([
        'vcard'   => base64_encode(istt_acl_build_vcard($post_id)),
        'filename' => ($filename ?: 'contact') . '.vcf',
    ]);
}

add_action('wp_ajax_istt_phonebook_vcard', 'istt_acl_ajax_vcard');
add_action('wp_ajax_nopriv_istt_phonebook_vcard', 'istt_acl_ajax_vcard');
