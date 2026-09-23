<?php
/**
 * Category filter and image metadata.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Category_Meta {

    public const FILTER_META_KEY        = '_rdsco_has_filter';
    public const IMAGE_META_KEY         = '_rdsco_category_image_id';
    public const TOP_HTML_META_KEY      = '_rdsco_archive_top_html';
    public const SIDEBAR_HTML_META_KEY  = '_rdsco_archive_sidebar_html';
    public const DISPLAY_STYLE_META_KEY = '_rdsco_archive_display_style';
    public const SHOW_TAGS_META_KEY     = '_rdsco_archive_show_tags';
    public const SHOW_ACF_META_KEY      = '_rdsco_archive_show_acf_filters';
    public const ACF_FIELDS_META_KEY    = '_rdsco_archive_acf_filter_fields';
    public const LEGACY_FILTER_META_KEY = '_istt_has_filter';
    public const LEGACY_IMAGE_META_KEY  = '_istt_category_image_id';
    public const NONCE_ACTION    = 'rdsco_category_meta_action';
    public const NONCE_NAME      = 'rdsco_category_meta_nonce';

    public static function init(): void {
        add_action( 'category_add_form_fields', [ __CLASS__, 'render_add_fields' ] );
        add_action( 'category_edit_form_fields', [ __CLASS__, 'render_edit_fields' ] );
        add_action( 'created_category', [ __CLASS__, 'save' ] );
        add_action( 'edited_category', [ __CLASS__, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_filter( 'manage_edit-category_columns', [ __CLASS__, 'add_image_column' ] );
        add_filter( 'manage_category_custom_column', [ __CLASS__, 'render_image_column' ], 10, 3 );
    }

    public static function render_add_fields(): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        ?>
        <div class="form-field">
            <label for="rdsco_has_filter">دارای فیلتر</label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="rdsco_has_filter" id="rdsco_has_filter" value="1">
                نمایش فیلتر در آرشیو این دسته
            </label>
        </div>

        <div class="form-field">
            <label>تصویر دسته</label>
            <?php self::render_image_picker( 0 ); ?>
        </div>

        <div class="form-field">
            <label for="rdsco_archive_display_style">استایل نمایش دسته‌بندی</label>
            <?php self::render_display_style_select( 'card' ); ?>
            <p class="description">نوع نمایش نوشته‌های این دسته در ویجت آرشیو را تعیین می‌کند.</p>
        </div>

        <div class="form-field">
            <label for="rdsco_archive_show_tags">نمایش تگ‌ها در لاین بالا</label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="rdsco_archive_show_tags" id="rdsco_archive_show_tags" value="1" checked>
                نمایش کلیدهای تگ زیر باکس جستجو
            </label>
        </div>

        <div class="form-field">
            <label for="rdsco_archive_show_acf_filters">فیلتر بر اساس فیلدهای ACF</label>
            <?php self::render_acf_filter_fields( [], false ); ?>
        </div>

        <div class="form-field">
            <label for="rdsco_archive_top_html">کد بالای صفحه (فقط HTML)</label>
            <textarea name="rdsco_archive_top_html" id="rdsco_archive_top_html" rows="7"></textarea>
            <p class="description">در بالای ویجت آرشیو این دسته نمایش داده می‌شود. PHP، JavaScript و شورت‌کد اجرا نمی‌شوند.</p>
        </div>

        <div class="form-field">
            <label for="rdsco_archive_sidebar_html">کد زیر ستون فیلترها (فقط HTML)</label>
            <textarea name="rdsco_archive_sidebar_html" id="rdsco_archive_sidebar_html" rows="7"></textarea>
            <p class="description">پس از فیلترهای ستون کناری نمایش داده می‌شود. PHP، JavaScript و شورت‌کد اجرا نمی‌شوند.</p>
        </div>
        <?php
    }

    public static function render_edit_fields( \WP_Term $term ): void {
        $has_filter   = self::has_filter( $term->term_id ) ? '1' : '';
        $image_id     = self::get_image_id( $term->term_id );
        $display_style = self::get_display_style( $term->term_id );
        $show_tags    = self::should_show_tags( $term->term_id );
        $show_acf     = self::should_show_acf_filters( $term->term_id );
        $acf_fields   = self::get_selected_acf_field_keys( $term->term_id );
        $top_html     = self::get_top_html( $term->term_id );
        $sidebar_html = self::get_sidebar_html( $term->term_id );

        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="rdsco_has_filter">دارای فیلتر</label></th>
            <td>
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="rdsco_has_filter" id="rdsco_has_filter" value="1" <?php checked( $has_filter, '1' ); ?>>
                    نمایش فیلتر در آرشیو این دسته
                </label>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label>تصویر دسته</label></th>
            <td><?php self::render_image_picker( $image_id ); ?></td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="rdsco_archive_display_style">استایل نمایش دسته‌بندی</label></th>
            <td>
                <?php self::render_display_style_select( $display_style ); ?>
                <p class="description">نوع نمایش نوشته‌های این دسته در ویجت آرشیو را تعیین می‌کند.</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="rdsco_archive_show_tags">نمایش تگ‌ها در لاین بالا</label></th>
            <td>
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="rdsco_archive_show_tags" id="rdsco_archive_show_tags" value="1" <?php checked( $show_tags ); ?>>
                    نمایش کلیدهای تگ زیر باکس جستجو
                </label>
                <p class="description">این گزینه به‌صورت پیش‌فرض فعال است.</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="rdsco_archive_show_acf_filters">فیلتر بر اساس فیلدهای ACF</label></th>
            <td><?php self::render_acf_filter_fields( $acf_fields, $show_acf ); ?></td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="rdsco_archive_top_html">کد بالای صفحه (فقط HTML)</label></th>
            <td>
                <textarea name="rdsco_archive_top_html" id="rdsco_archive_top_html" rows="8" class="large-text code"><?php echo esc_textarea( $top_html ); ?></textarea>
                <p class="description">در بالای ویجت آرشیو این دسته نمایش داده می‌شود. PHP، JavaScript و شورت‌کد اجرا نمی‌شوند.</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="rdsco_archive_sidebar_html">کد زیر ستون فیلترها (فقط HTML)</label></th>
            <td>
                <textarea name="rdsco_archive_sidebar_html" id="rdsco_archive_sidebar_html" rows="8" class="large-text code"><?php echo esc_textarea( $sidebar_html ); ?></textarea>
                <p class="description">پس از فیلترهای ستون کناری نمایش داده می‌شود. PHP، JavaScript و شورت‌کد اجرا نمی‌شوند.</p>
            </td>
        </tr>
        <?php
    }

    private static function render_image_picker( int $image_id ): void {
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        ?>
        <div class="rdsco-category-image-picker">
            <input type="hidden" class="rdsco-category-image-id" name="rdsco_category_image_id" value="<?php echo esc_attr( $image_id ); ?>">

            <div class="rdsco-category-image-preview" style="width:150px;height:100px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;overflow:hidden;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <?php if ( $image_url ) : ?>
                    <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php endif; ?>
            </div>

            <button type="button" class="button rdsco-category-image-select">انتخاب / تغییر تصویر</button>
            <button type="button" class="button rdsco-category-image-remove">حذف تصویر</button>
        </div>
        <?php
    }

    private static function render_acf_filter_fields( array $selected_keys, bool $enabled ): void {
        ?>
        <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="rdsco_archive_show_acf_filters" id="rdsco_archive_show_acf_filters" value="1" <?php checked( $enabled ); ?>>
            نمایش فیلتر فیلدهای انتخاب‌شده در ستون کناری این دسته
        </label>
        <?php
        $fields = self::get_filterable_acf_fields();
        if ( empty( $fields ) ) {
            echo '<p class="description">فیلد انتخابی ACF برای نوشته‌ها یافت نشد. ابتدا فیلدی از نوع Select، Radio، Checkbox یا True/False بسازید.</p>';
            return;
        }
        ?>
        <fieldset style="margin-top:12px;max-height:220px;overflow:auto;">
            <legend class="screen-reader-text">فیلدهای ACF قابل نمایش</legend>
            <?php foreach ( $fields as $key => $field ) : ?>
                <label style="display:block;margin:5px 0;">
                    <input type="checkbox" name="rdsco_archive_acf_fields[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_keys, true ) ); ?>>
                    <?php echo esc_html( $field['label'] ?: $field['name'] ); ?>
                    <code><?php echo esc_html( $field['name'] ); ?></code>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">حداکثر ۱۰ فیلد؛ فقط گزینه‌های تعریف‌شده در ACF قابل فیلتر هستند. برای نمایش ستون، «نمایش فیلترها» در ویجت نیز باید روشن باشد.</p>
        <?php
    }

    public static function get_filterable_acf_fields(): array {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return [];
        }

        static $cached_fields = null;
        if ( null !== $cached_fields ) {
            return $cached_fields;
        }

        $fields = [];
        foreach ( (array) acf_get_field_groups() as $group ) {
            if ( empty( $group['key'] ) ) {
                continue;
            }
            foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
                if ( ! is_array( $field ) || ! in_array( $field['type'] ?? '', [ 'select', 'radio', 'checkbox', 'true_false' ], true ) ) {
                    continue;
                }

                if ( empty( $field['key'] ) || empty( $field['name'] ) || empty( self::get_acf_filter_options( $field ) ) ) {
                    continue;
                }

                $fields[ $field['key'] ] = $field;
            }
        }

        $cached_fields = $fields;
        return $cached_fields;
    }

    public static function get_acf_filter_options( array $field ): array {
        if ( 'true_false' === ( $field['type'] ?? '' ) ) {
            return [
                '1' => (string) ( ! empty( $field['ui_on_text'] ) ? $field['ui_on_text'] : 'بله' ),
                '0' => (string) ( ! empty( $field['ui_off_text'] ) ? $field['ui_off_text'] : 'خیر' ),
            ];
        }

        $options = [];
        foreach ( (array) ( $field['choices'] ?? [] ) as $value => $label ) {
            if ( is_scalar( $value ) && is_scalar( $label ) && '' !== (string) $value ) {
                $options[ (string) $value ] = (string) $label;
            }
        }
        return $options;
    }

    public static function should_show_acf_filters( int $term_id ): bool {
        return '1' === (string) get_term_meta( $term_id, self::SHOW_ACF_META_KEY, true );
    }

    public static function get_selected_acf_field_keys( int $term_id ): array {
        $keys = get_term_meta( $term_id, self::ACF_FIELDS_META_KEY, true );
        return is_array( $keys ) ? array_slice( array_values( array_filter( $keys, 'is_string' ) ), 0, 10 ) : [];
    }

    public static function get_acf_filter_fields( int $term_id ): array {
        if ( ! self::should_show_acf_filters( $term_id ) ) {
            return [];
        }

        $available = self::get_filterable_acf_fields();
        $selected  = [];
        foreach ( self::get_selected_acf_field_keys( $term_id ) as $key ) {
            if ( isset( $available[ $key ] ) ) {
                $selected[ $key ] = $available[ $key ];
            }
        }
        return $selected;
    }

    private static function render_display_style_select( string $selected_style ): void {
        $styles = [
            'card'           => 'کارت ۱',
            'card_2'         => 'کارت ۲',
            'news_card'      => 'کارت ۳',
            'timeline'       => 'تایم لاین',
            'icon'           => 'آیکن باکس ۱',
            'featured_icon'  => 'آیکن باکس ۲',
            'icon_card_2'    => 'آیکن باکس ۳',
            'hover_box_1'    => 'هاور باکس ۱',
        ];
        ?>
        <select name="rdsco_archive_display_style" id="rdsco_archive_display_style">
            <?php foreach ( $styles as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_style, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function save( int $term_id ): void {
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        if ( isset( $_POST['rdsco_has_filter'] ) ) {
            update_term_meta( $term_id, self::FILTER_META_KEY, '1' );
        } else {
            delete_term_meta( $term_id, self::FILTER_META_KEY );
            delete_term_meta( $term_id, self::LEGACY_FILTER_META_KEY );
        }

        $image_id = isset( $_POST['rdsco_category_image_id'] )
            ? absint( $_POST['rdsco_category_image_id'] )
            : 0;

        if ( $image_id ) {
            update_term_meta( $term_id, self::IMAGE_META_KEY, $image_id );
        } else {
            delete_term_meta( $term_id, self::IMAGE_META_KEY );
            delete_term_meta( $term_id, self::LEGACY_IMAGE_META_KEY );
        }

        $display_style = isset( $_POST['rdsco_archive_display_style'] )
            ? sanitize_key( wp_unslash( $_POST['rdsco_archive_display_style'] ) )
            : 'card';

        if ( ! in_array( $display_style, [ 'card', 'card_2', 'news_card', 'timeline', 'icon', 'featured_icon', 'icon_card_2', 'hover_box_1' ], true ) ) {
            $display_style = 'card';
        }

        update_term_meta( $term_id, self::DISPLAY_STYLE_META_KEY, $display_style );
        update_term_meta( $term_id, self::SHOW_TAGS_META_KEY, isset( $_POST['rdsco_archive_show_tags'] ) ? '1' : '0' );
        update_term_meta( $term_id, self::SHOW_ACF_META_KEY, isset( $_POST['rdsco_archive_show_acf_filters'] ) ? '1' : '0' );

        $raw_keys  = isset( $_POST['rdsco_archive_acf_fields'] ) && is_array( $_POST['rdsco_archive_acf_fields'] )
            ? wp_unslash( $_POST['rdsco_archive_acf_fields'] ) : [];
        $available = self::get_filterable_acf_fields();
        $selected  = [];
        foreach ( $raw_keys as $key ) {
            if ( is_string( $key ) && isset( $available[ $key ] ) && ! in_array( $key, $selected, true ) ) {
                $selected[] = $key;
            }
            if ( count( $selected ) >= 10 ) {
                break;
            }
        }
        update_term_meta( $term_id, self::ACF_FIELDS_META_KEY, $selected );

        self::save_html_meta( $term_id, self::TOP_HTML_META_KEY, 'rdsco_archive_top_html' );
        self::save_html_meta( $term_id, self::SIDEBAR_HTML_META_KEY, 'rdsco_archive_sidebar_html' );
    }

    private static function save_html_meta( int $term_id, string $meta_key, string $field_name ): void {
        $html = isset( $_POST[ $field_name ] )
            ? self::sanitize_html( (string) wp_unslash( $_POST[ $field_name ] ) )
            : '';

        if ( '' !== trim( $html ) ) {
            update_term_meta( $term_id, $meta_key, $html );
        } else {
            delete_term_meta( $term_id, $meta_key );
        }
    }

    public static function has_filter( int $term_id ): bool {
        $value = get_term_meta( $term_id, self::FILTER_META_KEY, true );
        if ( '1' === (string) $value ) {
            return true;
        }

        return '1' === (string) get_term_meta( $term_id, self::LEGACY_FILTER_META_KEY, true );
    }

    public static function get_image_id( int $term_id ): int {
        $image_id = absint( get_term_meta( $term_id, self::IMAGE_META_KEY, true ) );
        if ( $image_id ) {
            return $image_id;
        }

        return absint( get_term_meta( $term_id, self::LEGACY_IMAGE_META_KEY, true ) );
    }

    public static function get_display_style( int $term_id ): string {
        $style = sanitize_key( (string) get_term_meta( $term_id, self::DISPLAY_STYLE_META_KEY, true ) );

        return in_array( $style, [ 'card', 'card_2', 'news_card', 'timeline', 'icon', 'featured_icon', 'icon_card_2', 'hover_box_1' ], true ) ? $style : 'card';
    }

    public static function should_show_tags( int $term_id ): bool {
        if ( ! metadata_exists( 'term', $term_id, self::SHOW_TAGS_META_KEY ) ) {
            return true;
        }

        return '1' === (string) get_term_meta( $term_id, self::SHOW_TAGS_META_KEY, true );
    }

    private static function sanitize_html( string $html ): string {
        $allowed_html = wp_kses_allowed_html( 'post' );

        // wp_kses_post() removes <style> but leaves its CSS visible as plain text.
        // These fields accept HTML/CSS snippets, while scripts remain disallowed.
        $allowed_html['style'] = [
            'type'  => true,
            'media' => true,
        ];

        return wp_kses( $html, $allowed_html );
    }

    public static function get_top_html( int $term_id ): string {
        return self::sanitize_html( (string) get_term_meta( $term_id, self::TOP_HTML_META_KEY, true ) );
    }

    public static function get_sidebar_html( int $term_id ): string {
        return self::sanitize_html( (string) get_term_meta( $term_id, self::SIDEBAR_HTML_META_KEY, true ) );
    }

    public static function add_image_column( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            if ( 'name' === $key ) {
                $new_columns['rdsco_category_image'] = 'تصویر';
            }
            $new_columns[ $key ] = $label;
        }
        return $new_columns;
    }

    public static function render_image_column( string $content, string $column_name, int $term_id ): string {
        if ( 'rdsco_category_image' !== $column_name ) {
            return $content;
        }

        $image_id = self::get_image_id( $term_id );
        if ( ! $image_id ) {
            return '<span style="color:#a7aaad;">—</span>';
        }

        return wp_get_attachment_image(
            $image_id,
            [ 48, 48 ],
            false,
            [
                'style' => 'width:48px;height:48px;object-fit:cover;border-radius:6px;',
                'alt'   => '',
            ]
        );
    }

    public static function enqueue_admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'category' !== $screen->taxonomy ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'rdsco-category-meta',
            RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/ajax-archive/assets/js/category-meta.js',
            [ 'jquery' ],
            RDSCO_ELEMENTOR_WIDGETS_VERSION,
            true
        );
    }
}
