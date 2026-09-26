<?php
/**
 * Annual achievements chart for the archive timeline style.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Timeline_View {

    /** Use the publication date as the year, including installations storing Jalali dates. */
    public static function year_from_date( string $date ): int {
        $parts = explode( '-', substr( $date, 0, 10 ) );
        if ( count( $parts ) !== 3 ) {
            return 0;
        }

        [ $year, $month, $day ] = array_map( 'intval', $parts );
        if ( $year >= 1200 && $year < 1600 ) {
            return $year;
        }
        if ( $year < 1600 || ! checkdate( $month, $day, $year ) ) {
            return 0;
        }

        // Convert a Gregorian WP post_date to its Solar Hijri year without a date plugin.
        $gy = $year - 1600;
        $gm = $month - 1;
        $days = 365 * $gy + intdiv( $gy + 3, 4 ) - intdiv( $gy + 99, 100 ) + intdiv( $gy + 399, 400 );
        $month_days = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
        for ( $i = 0; $i < $gm; $i++ ) {
            $days += $month_days[ $i ];
        }
        if ( $gm > 1 && ( 0 === $year % 400 || ( 0 === $year % 4 && 0 !== $year % 100 ) ) ) {
            $days++;
        }
        $days += $day - 1;
        $days -= 79;
        $jy = 979 + 33 * intdiv( $days, 12053 );
        $days %= 12053;
        $jy += 4 * intdiv( $days, 1461 );
        $days %= 1461;
        if ( $days >= 366 ) {
            $jy += intdiv( $days - 1, 365 );
        }
        return $jy;
    }

    /** Count real list items and any introductory paragraphs outside the lists. */
    public static function achievements( string $content ): array {
        preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $content, $matches );
        $items = [];
        foreach ( $matches[1] as $item ) {
            $item = wp_kses_post( $item );
            if ( '' !== trim( wp_strip_all_tags( $item ) ) ) {
                $items[] = $item;
            }
        }

        $outside = preg_replace( '/<(ol|ul)\b[^>]*>.*?<\/\1>/is', '', $content );
        preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $outside, $paragraphs );
        $lead = [];
        foreach ( $paragraphs[1] as $paragraph ) {
            $paragraph = wp_kses_post( $paragraph );
            if ( '' !== trim( wp_strip_all_tags( $paragraph ) ) ) {
                $lead[] = $paragraph;
            }
        }

        // A plain introductory line can precede <ol> without a surrounding <p>.
        if ( ! $lead && $items && '' !== trim( wp_strip_all_tags( preg_replace( '/<!--.*?-->/s', '', $outside ) ) ) ) {
            $lead[] = wp_kses_post( trim( preg_replace( '/<!--.*?-->/s', '', $outside ) ) );
        }

        if ( ! $items && ! $lead && '' !== trim( wp_strip_all_tags( $content ) ) ) {
            $items[] = wp_kses_post( $content );
        }
        return [ $lead, $items ];
    }

    private static function persian_number( int $number ): string {
        return strtr( (string) $number, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
    }

    public static function render( \WP_Query $query ): string {
        $posts = [];
        $maximum = 0;
        foreach ( $query->posts as $post ) {
            [ $lead, $items ] = self::achievements( get_post_field( 'post_content', $post->ID ) );
            $count = count( $lead ) + count( $items );
            $maximum = max( $maximum, $count );
            $posts[] = [
                'post' => $post,
                'year' => self::year_from_date( get_post_field( 'post_date', $post->ID ) ),
                'lead' => $lead,
                'items' => $items,
                'count' => $count,
            ];
        }

        $scale = max( 10, (int) ceil( $maximum / 10 ) * 10 );
        $count_posts = count( $posts );
        $points = [];
        $prefix = wp_unique_id( 'rdsco-timeline-' );
        ob_start();
        ?>
        <div class="rdsco-archive-grid style-timeline">
            <div class="rdsco-timeline-chart-frame">
                <div class="rdsco-timeline-chart-heading">
                    <h2>نمودار افتخارات سالانه</h2>
                    <p>برای دیدن افتخارات هر سال، روی نقطهٔ آن کلیک کنید.</p>
                </div>
                <div class="rdsco-timeline-chart-scroll">
                    <div class="rdsco-timeline-chart" style="--timeline-count:<?php echo esc_attr( $count_posts ); ?>">
                        <div class="rdsco-timeline-guides" aria-hidden="true">
                            <?php for ( $tick = 0; $tick <= $scale; $tick += 10 ) : ?>
                                <span class="rdsco-timeline-guide <?php echo 0 === $tick ? 'is-baseline' : ''; ?>" style="bottom:<?php echo esc_attr( 44 + (int) round( $tick / $scale * 230 ) ); ?>px"><small><?php echo esc_html( self::persian_number( $tick ) ); ?></small></span>
                            <?php endfor; ?>
                        </div>
                        <?php
                        foreach ( $posts as $index => $entry ) {
                            $points[] = round( 300 * ( 1 - ( $index + .5 ) / $count_posts ) ) . ',' . ( 296 - (int) round( $entry['count'] / $scale * 230 ) );
                        }
                        ?>
                        <svg class="rdsco-timeline-connection" viewBox="0 0 300 296" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polyline points="<?php echo esc_attr( implode( ' ', $points ) ); ?>" /></svg>
                        <div class="rdsco-timeline-columns">
                            <?php foreach ( $posts as $index => $entry ) : ?>
                                <?php $id = $prefix . '-' . $entry['post']->ID; ?>
                                <button type="button" class="rdsco-timeline-point" style="--rise:<?php echo esc_attr( (int) round( $entry['count'] / $scale * 230 ) ); ?>px" data-count="<?php echo esc_attr( $entry['count'] ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-label="نمایش افتخارات سال <?php echo esc_attr( self::persian_number( $entry['year'] ) ); ?>، <?php echo esc_attr( self::persian_number( $entry['count'] ) ); ?> افتخار">
                                    <span class="rdsco-timeline-stem" aria-hidden="true"></span>
                                    <span class="rdsco-timeline-dot" aria-hidden="true"></span>
                                    <span class="rdsco-timeline-value" aria-hidden="true"><?php echo esc_html( self::persian_number( $entry['count'] ) ); ?><small>افتخار</small></span>
                                    <span class="rdsco-timeline-year-label" aria-hidden="true"><?php echo esc_html( self::persian_number( $entry['year'] ) ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rdsco-timeline-entries">
                <?php foreach ( $posts as $index => $entry ) : ?>
                    <?php
                    $post = $entry['post'];
                    $id = $prefix . '-' . $post->ID;
                    $year = self::persian_number( $entry['year'] );
                    $remaining = max( 0, count( $entry['items'] ) - 3 );
                    ?>
                    <article class="rdsco-timeline-entry" id="<?php echo esc_attr( $id ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
                        <div class="rdsco-timeline-entry-heading">
                            <?php if ( has_post_thumbnail( $post->ID ) ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="rdsco-timeline-entry-image" aria-label="<?php echo esc_attr( get_the_title( $post ) ); ?>">
                                    <?php echo get_the_post_thumbnail( $post->ID, 'thumbnail', [ 'loading' => 'lazy', 'alt' => '' ] ); ?>
                                </a>
                            <?php endif; ?>
                            <div class="rdsco-timeline-entry-title-wrap">
                                <span class="rdsco-timeline-entry-watermark" aria-hidden="true"><?php echo esc_html( $year ); ?></span>
                                <h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
                                <span class="rdsco-timeline-entry-count"><?php echo esc_html( self::persian_number( $entry['count'] ) ); ?> افتخار ثبت‌شده</span>
                            </div>
                        </div>
                        <span class="rdsco-timeline-entry-rail" aria-hidden="true"></span>
                        <div class="rdsco-timeline-entry-details">
                            <?php foreach ( $entry['lead'] as $paragraph ) : ?>
                                <p class="rdsco-timeline-entry-lead"><?php echo wp_kses_post( $paragraph ); ?></p>
                            <?php endforeach; ?>
                            <?php if ( $entry['items'] ) : ?>
                                <ol class="rdsco-timeline-achievements" id="<?php echo esc_attr( $id . '-items' ); ?>">
                                    <?php foreach ( $entry['items'] as $item_index => $item ) : ?>
                                        <li><small><?php echo esc_html( self::persian_number( $item_index + 1 ) ); ?></small><div><?php echo wp_kses_post( $item ); ?></div></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                            <?php if ( $remaining ) : ?>
                                <button type="button" class="rdsco-timeline-expand" aria-expanded="false" aria-controls="<?php echo esc_attr( $id . '-items' ); ?>" data-year="<?php echo esc_attr( $year ); ?>" data-remaining="<?php echo esc_attr( self::persian_number( $remaining ) ); ?>">نمایش <?php echo esc_html( self::persian_number( $remaining ) ); ?> مورد دیگر</button>
                            <?php endif; ?>
                            <a class="rdsco-timeline-entry-more" href="<?php echo esc_url( get_permalink( $post ) ); ?>">مشاهده مطلب ←</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
