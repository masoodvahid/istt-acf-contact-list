<?php
/**
 * Admin settings for ISTT ACF Contact List.
 */

if (!defined('ABSPATH')) {
    exit;
}

function istt_acl_sanitize_custom_css($css)
{
    if (!is_string($css)) {
        return '';
    }

    $css = wp_check_invalid_utf8($css);
    $css = preg_replace('#</?style\b[^>]*>#i', '', $css);
    $css = wp_strip_all_tags($css);
    $css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css);

    return is_string($css) ? trim($css) : '';
}

function istt_acl_register_settings()
{
    register_setting(
        'istt_acl_settings_group',
        'istt_acl_custom_css',
        [
            'type' => 'string',
            'description' => 'Custom CSS loaded after the plugin stylesheet.',
            'sanitize_callback' => 'istt_acl_sanitize_custom_css',
            'default' => '',
            'show_in_rest' => false,
        ]
    );

    add_settings_section(
        'istt_acl_style_section',
        'شخصی‌سازی ظاهر',
        'istt_acl_render_style_section',
        'istt-acf-contact-list'
    );

    add_settings_field(
        'istt_acl_custom_css',
        'استایل سفارشی',
        'istt_acl_render_custom_css_field',
        'istt-acf-contact-list',
        'istt_acl_style_section'
    );
}
add_action('admin_init', 'istt_acl_register_settings');

function istt_acl_render_style_section()
{
    echo '<p>کدهای این بخش بعد از فایل اصلی <code>style.css</code> بارگذاری می‌شوند و برای شخصی‌سازی ظاهر دفتر تلفن کاربرد دارند.</p>';
}

function istt_acl_render_custom_css_field()
{
    $custom_css = get_option('istt_acl_custom_css', '');
    ?>
    <textarea
        id="istt-acl-custom-css"
        name="istt_acl_custom_css"
        class="large-text code"
        rows="22"
        dir="ltr"
        spellcheck="false"
        placeholder=".istt-pb {&#10;    --p: #07594f;&#10;}"><?php echo esc_textarea($custom_css); ?></textarea>
    <p class="description" dir="rtl">
        بهتر است تمام selectorها را با <code>.istt-pb</code> شروع کنید تا تغییرات فقط روی دفتر تلفن اعمال شوند.
    </p>
    <?php
}

function istt_acl_add_settings_page()
{
    add_options_page(
        'تنظیمات دفتر تلفن ISTT',
        'دفتر تلفن ISTT',
        'manage_options',
        'istt-acf-contact-list',
        'istt_acl_render_settings_page'
    );
}
add_action('admin_menu', 'istt_acl_add_settings_page');

function istt_acl_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap istt-acl-settings" dir="rtl">
        <h1>تنظیمات دفتر تلفن ISTT</h1>
        <p>در این صفحه می‌توانید بدون ویرایش فایل‌های افزونه، استایل دفتر تلفن را تغییر دهید.</p>

        <form method="post" action="options.php">
            <?php
            settings_fields('istt_acl_settings_group');
            do_settings_sections('istt-acf-contact-list');
            submit_button('ذخیره تغییرات');
            ?>
        </form>
    </div>
    <?php
}

function istt_acl_enqueue_settings_editor($hook_suffix)
{
    if ('settings_page_istt-acf-contact-list' !== $hook_suffix) {
        return;
    }

    $editor_settings = wp_enqueue_code_editor(['type' => 'text/css']);
    if (false === $editor_settings) {
        return;
    }

    wp_add_inline_script(
        'code-editor',
        sprintf(
            'jQuery(function(){wp.codeEditor.initialize("istt-acl-custom-css", %s);});',
            wp_json_encode($editor_settings)
        )
    );

    wp_add_inline_style(
        'wp-codemirror',
        '.istt-acl-settings .CodeMirror{direction:ltr;text-align:left;border:1px solid #ccd0d4;border-radius:6px;min-height:430px}.istt-acl-settings .form-table th{width:150px}'
    );
}
add_action('admin_enqueue_scripts', 'istt_acl_enqueue_settings_editor');

function istt_acl_plugin_settings_link($links)
{
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=istt-acf-contact-list')),
        esc_html__('تنظیمات', 'istt-acf-contact-list')
    );

    array_unshift($links, $settings_link);
    return $links;
}
add_filter(
    'plugin_action_links_' . ISTT_ACF_CONTACT_LIST_BASENAME,
    'istt_acl_plugin_settings_link'
);
