<?php
/**
 * Plugin Name: Aegis Hero
 * Description: Hero carousel block supporting images, local videos, and lazy-loaded external videos.
 * Version: 1.0.0
 * Author: Aegis
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AEGIS_HERO_PATH', plugin_dir_path(__FILE__));
define('AEGIS_HERO_URL', plugin_dir_url(__FILE__));
add_action('plugins_loaded', 'aegis_hero_require_admin');
add_action('init', 'aegis_hero_register_block');

/**
 * Load admin-only requirements.
 */
function aegis_hero_require_admin()
{
    if (is_admin()) {
        require_once AEGIS_HERO_PATH . 'admin/settings.php';
    }
}

/**
 * Register block assets and block type.
 */
function aegis_hero_register_block()
{
    $editor_script = AEGIS_HERO_URL . 'blocks/hero/editor.js';
    $view_script = AEGIS_HERO_URL . 'blocks/hero/view.js';
    $style = AEGIS_HERO_URL . 'blocks/hero/style.css';

    wp_register_script(
        'aegis-hero-editor',
        $editor_script,
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'],
        filemtime(AEGIS_HERO_PATH . 'blocks/hero/editor.js')
    );

    wp_register_script(
        'aegis-hero-view',
        $view_script,
        [],
        filemtime(AEGIS_HERO_PATH . 'blocks/hero/view.js'),
        true
    );

    wp_register_style(
        'aegis-hero-style',
        $style,
        [],
        filemtime(AEGIS_HERO_PATH . 'blocks/hero/style.css')
    );

    register_block_type(
        AEGIS_HERO_PATH . 'blocks/hero',
        [
            'render_callback' => 'aegis_hero_render_block',
        ]
    );
}

/**
 * Render callback for the hero block.
 */
function aegis_hero_render_block($attributes)
{
    $defaults = [
        'slides' => [],
        'heightDesktop' => 520,
        'heightMobile' => 320,
        'showArrows' => true,
        'showDots' => true,
        'autoplay' => false,
        'intervalMs' => 6000,
    ];

    $attributes = wp_parse_args($attributes, $defaults);
    $slides = is_array($attributes['slides']) ? $attributes['slides'] : [];
    $align = isset($attributes['align']) ? $attributes['align'] : '';

    if (empty($slides)) {
        return '';
    }

    $allow_external = (bool) get_option('aegis_hero_allow_external', false);
    $allowlist_raw = (string) get_option('aegis_hero_allowlist', '');
    $allowlist_domains = array_filter(array_map('trim', explode("\n", $allowlist_raw)));

    $height_style = sprintf(
        '--aegis-hero-h:%dpx; --aegis-hero-h-m:%dpx;',
        (int) $attributes['heightDesktop'],
        (int) $attributes['heightMobile']
    );

    $align_class = '';
    if (in_array($align, ['wide', 'full'], true)) {
        $align_class = ' align' . $align;
    }

    $settings_data = [
        'autoplay' => (bool) $attributes['autoplay'],
        'intervalMs' => max(1000, (int) $attributes['intervalMs']),
        'showArrows' => (bool) $attributes['showArrows'],
        'showDots' => (bool) $attributes['showDots'],
    ];

    ob_start();
    ?>
    <div class="aegis-hero<?php echo esc_attr($align_class); ?>" style="<?php echo esc_attr($height_style); ?>" data-settings='<?php echo esc_attr(wp_json_encode($settings_data)); ?>'>
        <div class="aegis-hero__track">
            <?php foreach ($slides as $index => $slide) :
                $type = isset($slide['type']) ? $slide['type'] : 'image';
                $type = in_array($type, ['image', 'video', 'external'], true) ? $type : 'image';
                $is_active = $index === 0 ? ' is-active' : '';
                ?>
                <div class="aegis-hero__slide<?php echo esc_attr($is_active); ?>" data-slide="<?php echo esc_attr($index); ?>" data-type="<?php echo esc_attr($type); ?>">
                    <?php
                    if ($type === 'image') {
                        aegis_hero_render_image_slide($slide);
                    } elseif ($type === 'video') {
                        aegis_hero_render_video_slide($slide);
                    } elseif ($type === 'external') {
                        aegis_hero_render_external_slide($slide, $allow_external, $allowlist_domains);
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1 && $attributes['showArrows']) : ?>
            <button class="aegis-hero__arrow aegis-hero__arrow--prev" type="button" aria-label="Previous slide"></button>
            <button class="aegis-hero__arrow aegis-hero__arrow--next" type="button" aria-label="Next slide"></button>
        <?php endif; ?>
        <?php if (count($slides) > 1 && $attributes['showDots']) : ?>
            <div class="aegis-hero__dots" role="tablist">
                <?php foreach ($slides as $index => $_slide) :
                    $is_active = $index === 0 ? ' is-active' : '';
                    ?>
                    <button class="aegis-hero__dot<?php echo esc_attr($is_active); ?>" type="button" data-target="<?php echo esc_attr($index); ?>" aria-label="Go to slide <?php echo esc_attr($index + 1); ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render image slide.
 */
function aegis_hero_render_image_slide($slide)
{
    $image_id = isset($slide['image_id']) ? (int) $slide['image_id'] : 0;
    if (!$image_id) {
        return;
    }

    $mobile_id = isset($slide['mobile_image_id']) ? (int) $slide['mobile_image_id'] : 0;
    $desktop_src = wp_get_attachment_image_url($image_id, 'full');
    $mobile_src = $mobile_id ? wp_get_attachment_image_url($mobile_id, 'full') : '';
    $alt = trim(get_post_meta($image_id, '_wp_attachment_image_alt', true));
    $alt = $alt !== '' ? $alt : get_the_title($image_id);

    $link_url = isset($slide['link_url']) ? esc_url($slide['link_url']) : '';

    $picture = '<picture class="aegis-hero__picture">';
    if ($mobile_src) {
        $picture .= '<source media="(max-width: 767px)" srcset="' . esc_url($mobile_src) . '">';
    }
    $picture .= '<img src="' . esc_url($desktop_src) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
    $picture .= '</picture>';

    if ($link_url) {
        echo '<a class="aegis-hero__link" href="' . $link_url . '">' . $picture . '</a>';
        return;
    }

    echo $picture;
}

/**
 * Render local video slide.
 */
function aegis_hero_render_video_slide($slide)
{
    $video_id = isset($slide['video_id']) ? (int) $slide['video_id'] : 0;
    if (!$video_id) {
        return;
    }

    $video_url = wp_get_attachment_url($video_id);
    if (!$video_url) {
        return;
    }

    $poster_id = isset($slide['poster_image_id']) ? (int) $slide['poster_image_id'] : 0;
    $poster = $poster_id ? wp_get_attachment_image_url($poster_id, 'full') : '';

    $controls = isset($slide['controls']) ? (bool) $slide['controls'] : true;
    $autoplay = !empty($slide['autoplay']);
    $loop = !empty($slide['loop']);
    $muted = $autoplay ? true : !empty($slide['muted']);

    $attrs = [
        'class' => 'aegis-hero__video',
        'playsinline' => true,
    ];

    if ($poster) {
        $attrs['poster'] = $poster;
    }

    if ($controls) {
        $attrs['controls'] = 'controls';
    }

    if ($autoplay) {
        $attrs['autoplay'] = 'autoplay';
    }

    if ($muted) {
        $attrs['muted'] = 'muted';
    }

    if ($loop) {
        $attrs['loop'] = 'loop';
    }

    $attr_string = '';
    foreach ($attrs as $key => $value) {
        if ($value === true) {
            $attr_string .= sprintf(' %s', esc_attr($key));
        } else {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
    }

    $type = wp_check_filetype($video_url);
    $mime = isset($type['type']) ? $type['type'] : '';

    echo '<video' . $attr_string . '>';
    echo '<source src="' . esc_url($video_url) . '"' . ($mime ? ' type="' . esc_attr($mime) . '"' : '') . ' />';
    echo '</video>';
}

/**
 * Render external video slide (YouTube only for v1).
 */
function aegis_hero_render_external_slide($slide, $allow_external, $allowlist_domains)
{
    $provider = isset($slide['provider']) ? $slide['provider'] : '';
    $url = isset($slide['url']) ? $slide['url'] : '';
    $poster_id = isset($slide['poster_image_id']) ? (int) $slide['poster_image_id'] : 0;

    if ($provider !== 'youtube' || !$url || !$poster_id) {
        return;
    }

    $poster = wp_get_attachment_image_url($poster_id, 'full');
    if (!$poster) {
        return;
    }

    $video_id = aegis_hero_parse_youtube_id($url);
    $host = aegis_hero_parse_host($url);
    $is_allowed = $allow_external && $video_id && $host && aegis_hero_host_allowed($host, $allowlist_domains);

    $autoplay = !empty($slide['autoplay']);
    ?>
    <div class="aegis-hero__external" data-provider="youtube" data-video-id="<?php echo esc_attr($video_id); ?>" data-embed-host="<?php echo esc_attr($host); ?>" data-allowed="<?php echo $is_allowed ? 'true' : 'false'; ?>" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>">
        <picture class="aegis-hero__picture">
            <img src="<?php echo esc_url($poster); ?>" alt="<?php echo esc_attr(get_the_title($poster_id)); ?>" loading="lazy" />
        </picture>
        <button class="aegis-hero__play" type="button" aria-label="Play external video">
            <span class="aegis-hero__play-icon" aria-hidden="true"></span>
            <span class="aegis-hero__play-label">Play</span>
        </button>
    </div>
    <?php
}

/**
 * Extract hostname from URL.
 */
function aegis_hero_parse_host($url)
{
    $parts = wp_parse_url($url);
    if (empty($parts['host'])) {
        return '';
    }
    $host = strtolower($parts['host']);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

/**
 * Check if host is allowed by list.
 */
function aegis_hero_host_allowed($host, $allowlist)
{
    foreach ($allowlist as $allowed) {
        $allowed = trim(strtolower($allowed));
        if ($allowed === '') {
            continue;
        }
        if (
            $host === $allowed ||
            (strlen($host) > strlen($allowed) && substr($host, -strlen($allowed) - 1) === '.' . $allowed)
        ) {
            return true;
        }
    }
    return false;
}

/**
 * Parse YouTube video ID from a URL.
 */
function aegis_hero_parse_youtube_id($url)
{
    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);
    $path = isset($parts['path']) ? $parts['path'] : '';
    $query = isset($parts['query']) ? $parts['query'] : '';

    if (strpos($host, 'youtube.') !== false) {
        parse_str($query, $qv);
        if (!empty($qv['v'])) {
            return sanitize_text_field($qv['v']);
        }

        $segments = array_values(array_filter(explode('/', $path)));
        if (!empty($segments)) {
            $last = end($segments);
            return sanitize_text_field($last);
        }
    }

    if ($host === 'youtu.be') {
        $segments = array_values(array_filter(explode('/', $path)));
        if (!empty($segments)) {
            return sanitize_text_field($segments[0]);
        }
    }

    return '';
}
