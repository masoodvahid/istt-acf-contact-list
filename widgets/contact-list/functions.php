<?php
/**
 * Data access, rendering and AJAX endpoints for the Contact List widget.
 *
 * Elementor integration lives in class-widget.php. This separation keeps each
 * widget self-contained and makes the plugin easy to extend.
 */

if (!defined('ABSPATH')) {
    exit;
}

function rdsco_contact_list_default_meta_fields()
{
    return [
        ['field_slug' => 'istt_contact_zone', 'field_role' => 'zone'],
        ['field_slug' => 'istt_contact_email', 'field_role' => 'email'],
        ['field_slug' => 'istt_contact_external_phone', 'field_role' => 'external_phone'],
        ['field_slug' => 'istt_contact_internal_phone', 'field_role' => 'internal_phone'],
    ];
}

function rdsco_contact_list_sanitize_meta_fields($fields)
{
    $fields = is_array($fields) ? $fields : [];
    $allowed_roles = ['text', 'zone', 'email', 'external_phone', 'internal_phone'];
    $sanitized = [];
    $seen = [];

    foreach (array_slice($fields, 0, 20) as $field) {
        if (!is_array($field)) {
            continue;
        }

        $slug = isset($field['field_slug'])
            ? sanitize_key($field['field_slug'])
            : '';
        $role = isset($field['field_role'])
            ? sanitize_key($field['field_role'])
            : 'text';

        if ($slug === '' || isset($seen[$slug])) {
            continue;
        }

        if (!in_array($role, $allowed_roles, true)) {
            $role = 'text';
        }

        $seen[$slug] = true;
        $sanitized[] = [
            'field_slug' => $slug,
            'field_role' => $role,
        ];
    }

    return $sanitized;
}

function rdsco_contact_list_meta_fields($settings = [])
{
    $settings = is_array($settings) ? $settings : [];

    if (array_key_exists('meta_fields', $settings) && is_array($settings['meta_fields'])) {
        return rdsco_contact_list_sanitize_meta_fields($settings['meta_fields']);
    }

    // One-time compatibility for Elementor documents saved with version 2.1.0.
    $legacy = [
        ['field_slug' => isset($settings['zone_field_slug']) ? $settings['zone_field_slug'] : '', 'field_role' => 'zone'],
        ['field_slug' => isset($settings['email_field_slug']) ? $settings['email_field_slug'] : '', 'field_role' => 'email'],
        ['field_slug' => isset($settings['external_phone_field_slug']) ? $settings['external_phone_field_slug'] : '', 'field_role' => 'external_phone'],
        ['field_slug' => isset($settings['internal_phone_field_slug']) ? $settings['internal_phone_field_slug'] : '', 'field_role' => 'internal_phone'],
    ];

    $legacy = rdsco_contact_list_sanitize_meta_fields($legacy);
    return $legacy ?: rdsco_contact_list_default_meta_fields();
}

function rdsco_contact_list_role_fields($meta_fields)
{
    $roles = [
        'zone' => '',
        'email' => '',
        'external_phone' => '',
        'internal_phone' => '',
    ];

    foreach (rdsco_contact_list_sanitize_meta_fields($meta_fields) as $field) {
        $role = $field['field_role'];
        if (isset($roles[$role]) && $roles[$role] === '') {
            $roles[$role] = $field['field_slug'];
        }
    }

    return $roles;
}

function rdsco_contact_list_meta_slugs($meta_fields)
{
    $slugs = [];
    foreach (rdsco_contact_list_sanitize_meta_fields($meta_fields) as $field) {
        $slugs[] = $field['field_slug'];
    }

    return array_values(array_unique($slugs));
}

function rdsco_contact_list_encode_field_config($meta_fields)
{
    $payload = rtrim(
        strtr(
            base64_encode(
                wp_json_encode(rdsco_contact_list_sanitize_meta_fields($meta_fields))
            ),
            '+/',
            '-_'
        ),
        '='
    );

    return [
        'payload'   => $payload,
        'signature' => hash_hmac(
            'sha256',
            'rdsco-contact-list-fields|' . $payload,
            wp_salt('nonce')
        ),
    ];
}

function rdsco_contact_list_decode_field_config($payload, $signature)
{
    $payload = sanitize_text_field((string) $payload);
    $signature = sanitize_text_field((string) $signature);

    if ($payload === '' || $signature === '') {
        return false;
    }

    $expected = hash_hmac(
        'sha256',
        'rdsco-contact-list-fields|' . $payload,
        wp_salt('nonce')
    );

    if (!hash_equals($expected, $signature)) {
        return false;
    }

    $encoded = strtr($payload, '-_', '+/');
    $remainder = strlen($encoded) % 4;
    if ($remainder) {
        $encoded .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode($encoded, true);
    $fields = $decoded !== false ? json_decode($decoded, true) : null;

    return is_array($fields)
        ? rdsco_contact_list_sanitize_meta_fields($fields)
        : false;
}

function rdsco_contact_list_get_field_label($field_name, $post_id, $fallback)
{
    static $labels = [];

    $field_name = sanitize_key($field_name);
    if ($field_name === '') {
        return $fallback;
    }

    if (array_key_exists($field_name, $labels)) {
        return $labels[$field_name] !== '' ? $labels[$field_name] : $fallback;
    }

    $label = '';
    if (function_exists('get_field_object')) {
        $field = get_field_object($field_name, absint($post_id), false, false);
        if (is_array($field) && !empty($field['label'])) {
            $label = sanitize_text_field($field['label']);
        }
    }

    $labels[$field_name] = $label;
    return $label !== '' ? $label : $fallback;
}

function rdsco_contact_list_role_label($role, $field_slug)
{
    $labels = [
        'zone' => 'حوزه',
        'email' => 'رایانامه',
        'external_phone' => 'شماره مستقیم',
        'internal_phone' => 'شماره داخلی',
        'text' => $field_slug,
    ];

    return isset($labels[$role]) ? $labels[$role] : $field_slug;
}

function rdsco_contact_list_role_icon($role)
{
    $icons = [
        'zone' => 'dashicons-building',
        'email' => 'dashicons-email-alt',
        'external_phone' => 'dashicons-phone',
        'internal_phone' => 'dashicons-phone',
        'text' => 'dashicons-info-outline',
    ];

    return isset($icons[$role]) ? $icons[$role] : $icons['text'];
}

function rdsco_contact_list_prepare_meta_items($post_id, $meta_fields, $zones)
{
    $items = [];

    foreach (rdsco_contact_list_sanitize_meta_fields($meta_fields) as $field) {
        $slug = $field['field_slug'];
        $role = $field['field_role'];
        $raw_value = rdsco_contact_list_get_meta_value($post_id, $slug);
        $value = rdsco_contact_list_format_acf_value($raw_value);

        if ($role === 'zone') {
            $stored_value = get_post_meta($post_id, $slug, true);
            if (is_scalar($stored_value) && isset($zones[$stored_value])) {
                $value = $zones[$stored_value];
            }
        } elseif ($role === 'email') {
            $value = sanitize_email($value);
        } elseif ($role === 'external_phone') {
            $value = rdsco_contact_list_phone_href($value);
        }

        $items[] = [
            'slug'  => $slug,
            'role'  => $role,
            'label' => rdsco_contact_list_get_field_label(
                $slug,
                $post_id,
                rdsco_contact_list_role_label($role, $slug)
            ),
            'value' => $value,
            'icon'  => rdsco_contact_list_role_icon($role),
        ];
    }

    return $items;
}

function rdsco_contact_list_normalize_digits($value)
{
    return strtr((string) $value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function rdsco_contact_list_phone_href($value)
{
    $phone = preg_replace('/[^0-9]/', '', rdsco_contact_list_normalize_digits($value));

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

function rdsco_contact_list_get_meta_value($post_id, $field_name)
{
    return function_exists('get_field')
        ? get_field($field_name, $post_id)
        : get_post_meta($post_id, $field_name, true);
}

function rdsco_contact_list_format_acf_value($value)
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

function rdsco_contact_list_protected_email($email)
{
    $email = sanitize_email($email);
    if (!$email) {
        return '—';
    }

    return sprintf(
        '<span class="rdsco-contact-list-protected-email">%s</span>',
        esc_html(strrev($email))
    );
}

function rdsco_contact_list_contact_photo($post_id, $person_name, $size = 'thumbnail')
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

function rdsco_contact_list_get_contacts_term()
{
    $term = get_term_by('slug', 'contacts', 'category');
    return (!$term || is_wp_error($term)) ? false : $term;
}

function rdsco_contact_list_get_zones($term_id, $field_name)
{
    global $wpdb;

    $term = get_term(absint($term_id), 'category');
    if (!$term || is_wp_error($term)) {
        return [];
    }

    $field_name = sanitize_key($field_name);
    if ($field_name === '') {
        return [];
    }

    $cache_key  = 'zones_' . md5($term->term_taxonomy_id . '|' . $field_name);
    $cached     = wp_cache_get($cache_key, 'rdsco_contact_list');

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
    wp_cache_set($cache_key, $zones, 'rdsco_contact_list', 300);
    return $zones;
}

function rdsco_contact_list_get_units($term_id, $zone = '', $zone_field = '')
{
    global $wpdb;

    $term = get_term(absint($term_id), 'category');
    if (!$term || is_wp_error($term)) {
        return [];
    }

    $zone       = sanitize_text_field($zone);
    $zone_field = sanitize_key($zone_field);
    $cache_key  = 'units_' . md5(
        $term->term_taxonomy_id . '|' . $zone . '|' . $zone_field
    );
    $cached    = wp_cache_get($cache_key, 'rdsco_contact_list');

    if ($cached !== false) {
        return $cached;
    }

    if ($zone !== '' && $zone_field !== '') {
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
            $zone_field,
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
    wp_cache_set($cache_key, $units, 'rdsco_contact_list', 300);
    return $units;
}

function rdsco_contact_list_get_contact_tags($term_id, $limit = 8)
{
    global $wpdb;

    $category = get_term(absint($term_id), 'category');
    if (!$category || is_wp_error($category)) {
        return [];
    }

    $limit     = min(20, max(1, absint($limit)));
    $cache_key = 'tags_' . md5($category->term_taxonomy_id . '|' . $limit);
    $cached    = wp_cache_get($cache_key, 'rdsco_contact_list');

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

    wp_cache_set($cache_key, $tags, 'rdsco_contact_list', 300);
    return $tags;
}

function rdsco_contact_list_posts_where($where, $query)
{
    global $wpdb;

    if (!$query->get('rdsco_contact_list_query')) {
        return $where;
    }

    $search = trim((string) $query->get('rdsco_contact_list_search'));
    $unit   = trim((string) $query->get('rdsco_contact_list_unit'));

    if ($search !== '') {
        $like      = '%' . $wpdb->esc_like(rdsco_contact_list_normalize_digits($search)) . '%';
        $meta_keys = rdsco_contact_list_meta_slugs(
            $query->get('rdsco_contact_list_meta_fields')
        );
        $sql = " AND (
            {$wpdb->posts}.post_title LIKE %s
            OR {$wpdb->posts}.post_excerpt LIKE %s";
        $parameters = [$like, $like];

        if ($meta_keys) {
            $holders = implode(', ', array_fill(0, count($meta_keys), '%s'));
            $sql .= " OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} sm
                WHERE sm.post_id = {$wpdb->posts}.ID
                  AND sm.meta_key IN ({$holders})
                  AND sm.meta_value LIKE %s
            )";
            $parameters = array_merge($parameters, $meta_keys, [$like]);
        }

        $sql .= ')';
        $where .= $wpdb->prepare($sql, $parameters);
    }

    if ($unit !== '') {
        $where .= $wpdb->prepare(
            " AND TRIM({$wpdb->posts}.post_excerpt) = %s",
            $unit
        );
    }

    return $where;
}

function rdsco_contact_list_query($args = [])
{
    $args = wp_parse_args($args, [
        'term_id'        => 0,
        'search'         => '',
        'zone'           => '',
        'unit'           => '',
        'tag_id'         => 0,
        'paged'          => 1,
        'posts_per_page' => 20,
        'meta_fields'     => rdsco_contact_list_default_meta_fields(),
    ]);

    $meta_fields = rdsco_contact_list_sanitize_meta_fields($args['meta_fields']);
    $role_fields = rdsco_contact_list_role_fields($meta_fields);

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
        'rdsco_contact_list_query'  => true,
        'rdsco_contact_list_search' => sanitize_text_field($args['search']),
        'rdsco_contact_list_unit'   => sanitize_text_field($args['unit']),
        'rdsco_contact_list_meta_fields' => $meta_fields,
    ];

    if ($args['zone'] !== '' && $role_fields['zone'] !== '') {
        $query_args['meta_query'] = [[
            'key'     => $role_fields['zone'],
            'value'   => sanitize_text_field($args['zone']),
            'compare' => '=',
        ]];
    }

    add_filter('posts_where', 'rdsco_contact_list_posts_where', 10, 2);
    $query = new WP_Query($query_args);
    remove_filter('posts_where', 'rdsco_contact_list_posts_where', 10);
    return $query;
}

function rdsco_contact_list_pagination($query, $page)
{
    $total = absint($query->max_num_pages);
    $page  = max(1, absint($page));
    if ($total <= 1) {
        return '';
    }

    ob_start(); ?>
    <nav class="rdsco-contact-list-pagination" aria-label="صفحه‌بندی دفتر تلفن">
        <button type="button" class="rdsco-contact-list-page-button" data-page="<?php echo esc_attr(max(1, $page - 1)); ?>" <?php disabled($page <= 1); ?>>
            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span> قبلی
        </button>
        <span>صفحه <?php echo esc_html(number_format_i18n($page)); ?> از <?php echo esc_html(number_format_i18n($total)); ?></span>
        <button type="button" class="rdsco-contact-list-page-button" data-page="<?php echo esc_attr(min($total, $page + 1)); ?>" <?php disabled($page >= $total); ?>>
            بعدی <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        </button>
    </nav>
    <?php return ob_get_clean();
}

function rdsco_contact_list_render_results($args = [])
{
    $args = wp_parse_args($args, [
        'term_id' => 0, 'search' => '', 'zone' => '', 'unit' => '',
        'tag_id' => 0, 'paged' => 1, 'posts_per_page' => 20,
        'meta_fields' => rdsco_contact_list_default_meta_fields(),
    ]);

    $meta_fields = rdsco_contact_list_sanitize_meta_fields($args['meta_fields']);
    $role_fields = rdsco_contact_list_role_fields($meta_fields);
    $field_config = rdsco_contact_list_encode_field_config($meta_fields);
    $zones = $role_fields['zone'] !== ''
        ? rdsco_contact_list_get_zones($args['term_id'], $role_fields['zone'])
        : [];
    $args['meta_fields'] = $meta_fields;
    $query  = rdsco_contact_list_query($args);
    $title  = ($args['zone'] !== '' && isset($zones[$args['zone']]))
        ? $zones[$args['zone']]
        : 'همه مخاطبان';

    ob_start();
    if (!$query->have_posts()) { ?>
        <div class="rdsco-contact-list-empty">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <p>نتیجه‌ای مطابق جست‌وجوی شما پیدا نشد.</p>
        </div>
        <?php return ob_get_clean();
    } ?>

    <div class="rdsco-contact-list-results-head">
        <div>
            <span><?php echo esc_html(number_format_i18n($query->found_posts)); ?> نفر در سازمان یافت شد</span>
            <h2><?php echo esc_html($title); ?></h2>
        </div>
        <?php if ($args['unit'] !== '') : ?>
            <span class="rdsco-contact-list-current-unit"><?php echo esc_html($args['unit']); ?></span>
        <?php endif; ?>
    </div>

    <div class="rdsco-contact-list-contacts-list">
        <?php $index = 0; while ($query->have_posts()) : $query->the_post();
            $index++;
            $post_id = get_the_ID();
            $name    = get_the_title();
            $unit    = trim(wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));
            $meta_items = rdsco_contact_list_prepare_meta_items(
                $post_id,
                $meta_fields,
                $zones
            );
            $primary = [
                'zone' => '',
                'email' => '',
                'external_phone' => '',
                'internal_phone' => '',
            ];
            foreach ($meta_items as $meta_item) {
                $role = $meta_item['role'];
                if (isset($primary[$role]) && $primary[$role] === '') {
                    $primary[$role] = $meta_item['value'];
                }
            }
            $zone = $primary['zone'];
            $email = $primary['email'];
            $external = $primary['external_phone'];
            $internal = $primary['internal_phone'];
        ?>
            <article class="rdsco-contact-list-contact-row" style="--rdsco-contact-list-row-index:<?php echo esc_attr($index); ?>">
                <div class="rdsco-contact-list-contact-photo"><?php echo rdsco_contact_list_contact_photo($post_id, $name); ?></div>
                <div class="rdsco-contact-list-contact-identity">
                    <h3><?php echo esc_html($name); ?></h3>
                    <p><?php echo $unit !== '' ? esc_html($unit) : 'واحد سازمانی ثبت نشده'; ?></p>
                </div>
                <div class="rdsco-contact-list-contact-zone"><?php echo $zone !== '' ? esc_html($zone) : '—'; ?></div>
                <div class="rdsco-contact-list-contact-phone">
                    <span class="dashicons dashicons-phone" aria-hidden="true"></span>
                    <span><?php echo $internal ? esc_html($internal) : '—'; ?></span>
                </div>
                <div class="rdsco-contact-list-contact-email-status">
                    <?php if ($email) : ?><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><?php else : ?>—<?php endif; ?>
                </div>
                <button type="button" class="rdsco-contact-list-details-button" data-contact-id="<?php echo esc_attr($post_id); ?>">
                    اطلاعات بیشتر <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </button>

                <template data-contact-template="<?php echo esc_attr($post_id); ?>">
                    <div class="rdsco-contact-list-sidebar-profile">
                        <div class="rdsco-contact-list-sidebar-photo"><?php echo rdsco_contact_list_contact_photo($post_id, $name, 'medium'); ?></div>
                        <h2><?php echo esc_html($name); ?></h2>
                        <p class="rdsco-contact-list-sidebar-position"><?php echo $unit !== '' ? esc_html($unit) : '—'; ?></p>
                        <div class="rdsco-contact-list-sidebar-divider"></div>
                        <dl class="rdsco-contact-list-sidebar-data">
                            <div><dt><span class="dashicons dashicons-networking"></span> واحد سازمانی</dt><dd><?php echo $unit !== '' ? esc_html($unit) : '—'; ?></dd></div>
                            <?php foreach ($meta_items as $meta_item) : ?>
                                <div>
                                    <dt>
                                        <span class="dashicons <?php echo esc_attr($meta_item['icon']); ?>"></span>
                                        <?php echo esc_html($meta_item['label']); ?>
                                    </dt>
                                    <dd>
                                        <?php
                                        echo $meta_item['role'] === 'email'
                                            ? rdsco_contact_list_protected_email($meta_item['value'])
                                            : ($meta_item['value'] !== '' ? esc_html($meta_item['value']) : '—');
                                        ?>
                                    </dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                        <?php if ($external) : ?>
                            <a class="rdsco-contact-list-sidebar-call" href="tel:<?php echo esc_attr(rdsco_contact_list_phone_href($external)); ?>">
                                <span class="dashicons dashicons-phone"></span> تماس
                            </a>
                        <?php endif; ?>
                        <section
                            class="rdsco-contact-list-qr-section"
                            data-qr-contact="<?php echo esc_attr($post_id); ?>"
                            data-qr-token="<?php echo esc_attr(rdsco_contact_list_contact_token($post_id, $field_config['signature'])); ?>"
                            data-fields-config="<?php echo esc_attr($field_config['payload']); ?>"
                            data-fields-signature="<?php echo esc_attr($field_config['signature']); ?>"
                        >
                            <div class="rdsco-contact-list-qr-title">
                                <span class="dashicons dashicons-smartphone"></span>
                                <div><h3>ذخیره مخاطب</h3><p>کد را با دوربین تلفن همراه اسکن کنید یا فایل مخاطب را دریافت کنید.</p></div>
                            </div>
                            <div class="rdsco-contact-list-qr-box">
                                <span class="dashicons dashicons-update rdsco-contact-list-qr-loader"></span>
                                <span>در حال ساخت QR Code...</span>
                            </div>
                            <a href="#" class="rdsco-contact-list-vcard-download" hidden download>
                                <span class="dashicons dashicons-download"></span> دانلود فایل مخاطب
                            </a>
                            <div class="rdsco-contact-list-qr-error" hidden>ساخت QR Code ممکن نشد.</div>
                        </section>
                    </div>
                </template>
            </article>
        <?php endwhile; ?>
    </div>
    <?php echo rdsco_contact_list_pagination($query, $args['paged']);
    wp_reset_postdata();
    return ob_get_clean();
}

function rdsco_contact_list_render($settings = [], $widget_id = '')
{
    $settings = wp_parse_args($settings, [
        'eyebrow'            => 'ارتباط با شهرک',
        'title'              => 'دنبال چه کسی هستید؟',
        'description'        => 'نام، سمت، واحد سازمانی یا شماره داخلی موردنظر خود را جست‌وجو کنید.',
        'search_placeholder' => 'نام، سمت، واحد یا داخلی را جست‌وجو کنید...',
        'posts_per_page'     => 20,
        'tags_limit'         => 8,
        'start_title'        => 'از کجا شروع کنم؟',
        'start_description'  => 'موضوع موردنظر خود را انتخاب کنید تا گزینه‌های مرتبط نمایش داده شوند.',
        'meta_fields'        => rdsco_contact_list_default_meta_fields(),
    ]);

    $term = rdsco_contact_list_get_contacts_term();
    if (!$term) {
        return '<div class="rdsco-contact-list-error">دسته‌بندی با نامک contacts پیدا نشد.</div>';
    }

    $term_id = absint($term->term_id);
    $per_page = min(50, max(1, absint($settings['posts_per_page'])));
    $tags_limit = min(20, max(1, absint($settings['tags_limit'])));
    $meta_fields = rdsco_contact_list_meta_fields($settings);
    $role_fields = rdsco_contact_list_role_fields($meta_fields);
    $field_config = rdsco_contact_list_encode_field_config($meta_fields);
    $zones = $role_fields['zone'] !== ''
        ? rdsco_contact_list_get_zones($term_id, $role_fields['zone'])
        : [];
    $units = rdsco_contact_list_get_units($term_id, '', $role_fields['zone']);
    $tags  = rdsco_contact_list_get_contact_tags($term_id, $tags_limit);
    $id    = $widget_id !== ''
        ? 'rdsco-contact-list-' . sanitize_html_class($widget_id)
        : wp_unique_id('rdsco-contact-list-');
    $nonce = wp_create_nonce('rdsco_contact_list_filter');

    ob_start(); ?>
    <section id="<?php echo esc_attr($id); ?>" class="rdsco-contact-list" dir="rtl" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <div class="rdsco-contact-list-hero">
            <div class="rdsco-contact-list-eyebrow"><?php echo esc_html($settings['eyebrow']); ?></div>
            <h1><?php echo esc_html($settings['title']); ?></h1>
            <p><?php echo esc_html($settings['description']); ?></p>

            <form class="rdsco-contact-list-form">
                <input type="hidden" name="action" value="rdsco_contact_list_filter">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="term_id" value="<?php echo esc_attr($term_id); ?>">
                <input type="hidden" name="tag_id" value="0">
                <input type="hidden" name="paged" value="1">
                <input type="hidden" name="posts_per_page" value="<?php echo esc_attr($per_page); ?>">
                <input type="hidden" name="fields_config" value="<?php echo esc_attr($field_config['payload']); ?>">
                <input type="hidden" name="fields_signature" value="<?php echo esc_attr($field_config['signature']); ?>">

                <div class="rdsco-contact-list-search-box">
                    <input type="search" name="search" placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>" autocomplete="off">
                    <button type="submit" aria-label="جست‌وجو"><span class="dashicons dashicons-search"></span></button>
                </div>

                <?php if ($tags) : ?>
                    <div class="rdsco-contact-list-start-section">
                        <?php if (trim((string) $settings['start_title']) !== '') : ?>
                            <h2><?php echo esc_html($settings['start_title']); ?></h2>
                        <?php endif; ?>
                        <?php if (trim((string) $settings['start_description']) !== '') : ?>
                            <p><?php echo esc_html($settings['start_description']); ?></p>
                        <?php endif; ?>
                        <div class="rdsco-contact-list-tag-links">
                            <?php $tag_index = 0; foreach ($tags as $tag) : $tag_index++; ?>
                                <button type="button" class="rdsco-contact-list-tag-link" data-tag-id="<?php echo esc_attr($tag->term_id); ?>" aria-pressed="false" style="--rdsco-contact-list-tag-index:<?php echo esc_attr($tag_index); ?>">
                                    <?php echo esc_html($tag->name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rdsco-contact-list-filter-area">
                    <div class="rdsco-contact-list-zone-filters">
                        <button type="button" class="rdsco-contact-list-zone active" data-zone="">همه</button>
                        <?php foreach ($zones as $value => $label) : ?>
                            <button type="button" class="rdsco-contact-list-zone" data-zone="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="zone" value="">
                    <div class="rdsco-contact-list-unit-filter">
                        <label for="<?php echo esc_attr($id . '-unit'); ?>">واحد سازمانی</label>
                        <select id="<?php echo esc_attr($id . '-unit'); ?>" name="unit">
                            <option value="">همه واحدهای سازمانی</option>
                            <?php foreach ($units as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="rdsco-contact-list-reset"><span class="dashicons dashicons-image-rotate"></span> پاک‌کردن فیلترها</button>
                </div>
            </form>
        </div>

        <div class="rdsco-contact-list-loading" hidden><span class="dashicons dashicons-update"></span> در حال دریافت اطلاعات...</div>
        <div class="rdsco-contact-list-results"><?php echo rdsco_contact_list_render_results([
            'term_id' => $term_id,
            'paged' => 1,
            'posts_per_page' => $per_page,
            'meta_fields' => $meta_fields,
        ]); ?></div>

        <div class="rdsco-contact-list-sidebar-layer" aria-hidden="true">
            <button type="button" class="rdsco-contact-list-sidebar-backdrop" aria-label="بستن اطلاعات مخاطب"></button>
            <aside class="rdsco-contact-list-sidebar" role="dialog" aria-modal="true" aria-label="جزئیات اطلاعات تماس">
                <div class="rdsco-contact-list-sidebar-top">
                    <span>اطلاعات تماس</span>
                    <button type="button" class="rdsco-contact-list-sidebar-close" aria-label="بستن"><span class="dashicons dashicons-no-alt"></span></button>
                </div>
                <div class="rdsco-contact-list-sidebar-content"></div>
            </aside>
        </div>
    </section>


    <?php
    return ob_get_clean();
}

function rdsco_contact_list_ajax_filter()
{
    check_ajax_referer('rdsco_contact_list_filter', 'nonce');
    $term = rdsco_contact_list_get_contacts_term();
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
    $fields_payload = isset($_POST['fields_config']) && is_string($_POST['fields_config'])
        ? wp_unslash($_POST['fields_config'])
        : '';
    $fields_signature = isset($_POST['fields_signature']) && is_string($_POST['fields_signature'])
        ? wp_unslash($_POST['fields_signature'])
        : '';
    $meta_fields = rdsco_contact_list_decode_field_config(
        $fields_payload,
        $fields_signature
    );

    if ($meta_fields === false) {
        wp_send_json_error(
            ['message' => 'پیکربندی متای فیلدهای اضافه معتبر نیست.'],
            403
        );
    }

    if ($tag_id && !term_exists($tag_id, 'post_tag')) {
        $tag_id = 0;
    }

    $response = ['html' => rdsco_contact_list_render_results([
        'term_id' => $term_id, 'search' => $search, 'zone' => $zone,
        'unit' => $unit, 'tag_id' => $tag_id, 'paged' => $paged,
        'posts_per_page' => $per_page,
        'meta_fields' => $meta_fields,
    ])];

    if (!empty($_POST['update_units'])) {
        $role_fields = rdsco_contact_list_role_fields($meta_fields);
        $response['units'] = rdsco_contact_list_get_units(
            $term_id,
            $zone,
            $role_fields['zone']
        );
    }

    wp_send_json_success($response);
}

add_action('wp_ajax_rdsco_contact_list_filter', 'rdsco_contact_list_ajax_filter');
add_action('wp_ajax_nopriv_rdsco_contact_list_filter', 'rdsco_contact_list_ajax_filter');

function rdsco_contact_list_contact_token($post_id, $fields_signature)
{
    return substr(
        hash_hmac(
            'sha256',
            'rdsco-contact-list-vcard|'
                . absint($post_id)
                . '|'
                . sanitize_text_field($fields_signature),
            wp_salt('nonce')
        ),
        0,
        32
    );
}

function rdsco_contact_list_vcard_escape($value)
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\r", "\n"],
        ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"],
        wp_strip_all_tags((string) $value)
    );
}

function rdsco_contact_list_build_vcard($post_id, $meta_fields)
{
    $meta_fields = rdsco_contact_list_sanitize_meta_fields($meta_fields);
    $role_fields = rdsco_contact_list_role_fields($meta_fields);
    $name = trim(wp_strip_all_tags(get_the_title($post_id)));
    $unit = trim(wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));
    $email = $role_fields['email'] !== ''
        ? sanitize_email(rdsco_contact_list_format_acf_value(
            rdsco_contact_list_get_meta_value($post_id, $role_fields['email'])
        ))
        : '';
    $external = $role_fields['external_phone'] !== ''
        ? rdsco_contact_list_phone_href(rdsco_contact_list_format_acf_value(
            rdsco_contact_list_get_meta_value($post_id, $role_fields['external_phone'])
        ))
        : '';
    $internal = $role_fields['internal_phone'] !== ''
        ? rdsco_contact_list_format_acf_value(
            rdsco_contact_list_get_meta_value($post_id, $role_fields['internal_phone'])
        )
        : '';

    // Keep the QR payload compact and predictable, especially for Persian UTF-8 text.
    if ($unit !== '') {
        $unit = wp_html_excerpt($unit, 60, '');
    }

    $lines = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:' . rdsco_contact_list_vcard_escape($name),
        'N:;;;;',
        'ORG:ISTT',
    ];
    if ($unit !== '')     { $lines[] = 'TITLE:' . rdsco_contact_list_vcard_escape($unit); }
    if ($external !== '') { $lines[] = 'TEL;TYPE=WORK:' . rdsco_contact_list_vcard_escape($external); }
    if ($email !== '')    { $lines[] = 'EMAIL:' . rdsco_contact_list_vcard_escape($email); }
    if ($internal !== '') { $lines[] = 'NOTE:Ext ' . rdsco_contact_list_vcard_escape($internal); }
    $lines[] = 'END:VCARD';

    return implode("\r\n", $lines);
}

function rdsco_contact_list_ajax_vcard()
{
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $token   = isset($_POST['token'])
        ? sanitize_text_field(wp_unslash($_POST['token']))
        : '';
    $fields_payload = isset($_POST['fields_config']) && is_string($_POST['fields_config'])
        ? wp_unslash($_POST['fields_config'])
        : '';
    $fields_signature = isset($_POST['fields_signature']) && is_string($_POST['fields_signature'])
        ? wp_unslash($_POST['fields_signature'])
        : '';
    $meta_fields = rdsco_contact_list_decode_field_config(
        $fields_payload,
        $fields_signature
    );

    if (
        !$post_id ||
        !$token ||
        $meta_fields === false ||
        !hash_equals(
            rdsco_contact_list_contact_token($post_id, $fields_signature),
            $token
        ) ||
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
        'vcard'   => base64_encode(rdsco_contact_list_build_vcard($post_id, $meta_fields)),
        'filename' => ($filename ?: 'contact') . '.vcf',
    ]);
}

add_action('wp_ajax_rdsco_contact_list_vcard', 'rdsco_contact_list_ajax_vcard');
add_action('wp_ajax_nopriv_rdsco_contact_list_vcard', 'rdsco_contact_list_ajax_vcard');
