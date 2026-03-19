<?php

namespace WCO\Starter\Core;

class Media
{
    public static function boot(): void
    {
        add_filter('upload_mimes', [self::class, 'allow_svg_upload']);
        add_filter('wp_check_filetype_and_ext', [self::class, 'fix_svg_filetype'], 10, 5);
        add_action('admin_head', [self::class, 'print_svg_admin_styles']);
    }

    public static function allow_svg_upload(array $mimes): array
    {
        $mimes['svg'] = 'image/svg+xml';

        return $mimes;
    }

    public static function fix_svg_filetype(array $data, string $file, string $filename, ?array $mimes, $realMime): array
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== 'svg') {
            return $data;
        }

        if ($filename === '' || !is_file($file)) {
            return $data;
        }

        $allowedMimes = is_array($mimes) ? $mimes : [];
        if ($allowedMimes !== [] && !isset($allowedMimes['svg'])) {
            return $data;
        }

        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';
        $data['proper_filename'] = $filename;

        return $data;
    }

    public static function print_svg_admin_styles(): void
    {
        echo '<style>
            .attachment .thumbnail img[src$=".svg"],
            .attachment .thumbnail img[src*=".svg?"],
            .media-frame-content .thumbnail img[src$=".svg"],
            .media-frame-content .thumbnail img[src*=".svg?"] {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
        </style>';
    }

    public static function image($image, array $args = []): string
    {
        $options = array_merge(
            [
                'size' => 'large',
                'loading' => 'lazy',
                'decoding' => 'async',
                'class' => '',
                'alt' => null,
                'srcset' => null,
                'sizes' => null,
                'width' => null,
                'height' => null,
                'placeholder' => null,
                'preload' => false,
            ],
            $args
        );

        $payload = self::normalize_image($image, (string) $options['size']);
        if (!$payload) {
            return '';
        }

        $payload['alt'] = self::resolve_alt($payload, $options['alt']);
        $payload['class'] = trim((string) $options['class']);
        $payload['loading'] = $options['loading'];
        $payload['decoding'] = (string) $options['decoding'];
        $payload['sizes'] = $options['sizes'];
        $payload['width'] = $options['width'] !== null ? (int) $options['width'] : null;
        $payload['height'] = $options['height'] !== null ? (int) $options['height'] : null;

        if (!$payload['src']) {
            return '';
        }

        $srcset = (string) ($payload['srcset'] ?: '');
        if (!empty($options['srcset'])) {
            $srcset = (string) $options['srcset'];
        }

        $payload['srcset'] = trim($srcset);
        $payload['sizes'] = $options['sizes'];

        return self::render_picture_tag($payload, (bool) $options['preload']);
    }

    private static function render_picture_tag(array $payload, bool $shouldPreload): string
    {
        $loading = $payload['loading'];
        $isLazy = in_array($loading, ['lazy', 'auto'], true);

        $imgClass = trim('wco-lazy-image' . ($payload['class'] ? ' ' . $payload['class'] : ''));
        $placeholder = self::placeholder($payload['class']);
        $width = $payload['width'];
        $height = $payload['height'];
        $imgAttr = [
            'class' => $imgClass,
            'alt' => $payload['alt'],
            'decoding' => $payload['decoding'],
            'width' => $width ? (string) $width : '',
            'height' => $height ? (string) $height : '',
            'sizes' => is_string($payload['sizes']) ? $payload['sizes'] : '',
            'fetchpriority' => $shouldPreload ? 'high' : '',
        ];

        if ($isLazy) {
            $imgAttr['loading'] = 'lazy';
            $imgAttr['data-src'] = $payload['src'];
            if ($payload['srcset']) {
                $imgAttr['data-srcset'] = $payload['srcset'];
            }
            if ($imgAttr['sizes']) {
                $imgAttr['data-sizes'] = $imgAttr['sizes'];
                $imgAttr['sizes'] = '';
            }
            $imgAttr['src'] = $placeholder;
            $imgTag = '<img' . self::html_attributes($imgAttr) . '>';
        } else {
            $imgAttr['loading'] = $loading;
            $imgAttr['src'] = $payload['src'];
            if ($payload['srcset']) {
                $imgTag = '<img' . self::html_attributes(array_merge($imgAttr, ['srcset' => $payload['srcset']])) . '>';
            } else {
                $imgTag = '<img' . self::html_attributes($imgAttr) . '>';
            }
        }

        $webpSource = self::format_source($payload['srcset'], $payload['src'], 'webp');
        $avifSource = self::format_source($payload['srcset'], $payload['src'], 'avif');

        if (!$isLazy && !$webpSource && !$avifSource) {
            return '<picture>' . $imgTag . '</picture>';
        }

        $sources = '';
        foreach ([
            ['type' => 'image/avif', 'payload' => $avifSource],
            ['type' => 'image/webp', 'payload' => $webpSource],
        ] as $source) {
            if (empty($source['payload'])) {
                continue;
            }

            $sourceAttributes = ['type' => $source['type']];
            if ($isLazy) {
                $sourceAttributes['data-srcset'] = $source['payload'];
            } else {
                $sourceAttributes['srcset'] = $source['payload'];
            }

            if ($imgAttr['sizes']) {
                $sourceAttributes['sizes'] = $imgAttr['sizes'];
            }

            $sources .= '<source' . self::html_attributes($sourceAttributes) . '>';
        }

        if (!$sources) {
            return '<picture>' . $imgTag . '</picture>';
        }

        return '<picture class="wco-lazy-media" data-format-lazy="true">' . $sources . $imgTag . '</picture>';
    }

    private static function format_source(string $srcset, string $base, string $format): string
    {
        if (!$srcset) {
            return '';
        }

        $sources = [];
        foreach (self::parse_srcset($srcset) as [$sourceUrl, $descriptor]) {
            $variant = self::replace_extension_for_fallback($sourceUrl, $format);
            if (!self::has_file($variant)) {
                continue;
            }
            $sources[] = trim($variant . ($descriptor ? ' ' . $descriptor : ''));
        }

        if ($sources === []) {
            $fallback = self::replace_extension_for_fallback($base, $format);
            if (self::has_file($fallback)) {
                return $fallback;
            }
            return '';
        }

        return implode(', ', $sources);
    }

    private static function parse_srcset(string $srcset): array
    {
        $result = [];
        foreach (explode(',', $srcset) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $entry);
            if (!$parts) {
                continue;
            }

            $url = array_shift($parts);
            $descriptor = $parts[0] ?? '';
            $result[] = [$url, $descriptor];
        }

        return $result;
    }

    private static function placeholder(string $className): string
    {
        $className = trim($className);
        if (str_contains($className, 'post-card__media')) {
            $color = 'f5f5f5';
        } else {
            $color = 'ececec';
        }

        return sprintf(
            'data:image/svg+xml,%s',
            rawurlencode(sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1" viewBox="0 0 1 1"><rect width="1" height="1" fill="#%s"/></svg>',
                $color
            ))
        );
    }

    private static function normalize_image($image, string $size): ?array
    {
        if (is_array($image) && (int) ($image['ID'] ?? 0) > 0) {
            $image = $image['ID'];
        }

        if (is_object($image) && property_exists($image, 'id')) {
            $image = $image->id;
        }

        if (is_numeric($image)) {
            $id = (int) $image;
            if ($id <= 0 || !function_exists('wp_get_attachment_image_url')) {
                return null;
            }

            $src = wp_get_attachment_image_url($id, $size);
            if (!$src) {
                return null;
            }

            return [
                'src' => (string) $src,
                'srcset' => (string) (wp_get_attachment_image_srcset($id, $size) ?: ''),
                'width' => (int) wp_get_attachment_image_width($id),
                'height' => (int) wp_get_attachment_image_height($id),
                'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            ];
        }

        if (is_array($image) && isset($image['url'])) {
            return [
                'src' => (string) $image['url'],
                'srcset' => (string) ($image['srcset'] ?? ''),
                'width' => isset($image['width']) ? (int) $image['width'] : null,
                'height' => isset($image['height']) ? (int) $image['height'] : null,
                'alt' => (string) ($image['alt'] ?? ''),
            ];
        }

        if (is_object($image) && method_exists($image, '__get')) {
            return [
                'src' => (string) ($image->src ?? ''),
                'srcset' => (string) ($image->srcset ?? ''),
                'width' => (int) ($image->width ?? 0),
                'height' => (int) ($image->height ?? 0),
                'alt' => (string) ($image->alt ?? ''),
            ];
        }

        if (is_string($image) && $image !== '') {
            return [
                'src' => $image,
                'srcset' => '',
                'width' => null,
                'height' => null,
                'alt' => '',
            ];
        }

        return null;
    }

    private static function resolve_alt(array $payload, $customAlt): string
    {
        if ($customAlt !== null && $customAlt !== '') {
            return (string) $customAlt;
        }

        return $payload['alt'] ? (string) $payload['alt'] : '';
    }

    private static function replace_extension_for_fallback(string $url, string $extension): string
    {
        return (string) preg_replace('/\.(jpg|jpeg|png|webp|avif|gif|svg)(\?.*)?$/i', '.' . $extension . '$2', $url);
    }

    private static function has_file(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (!function_exists('wp_get_upload_dir')) {
            return false;
        }

        $upload = wp_get_upload_dir();
        if (empty($upload['baseurl']) || empty($upload['basedir'])) {
            return false;
        }

        if (!str_starts_with($url, $upload['baseurl'])) {
            return false;
        }

        $path = str_replace($upload['baseurl'], $upload['basedir'], $url);
        return file_exists($path);
    }

    private static function html_attributes(array $attrs): string
    {
        $output = '';
        foreach ($attrs as $name => $value) {
            if ($value === '' || $value === null || $value === false) {
                continue;
            }
            $output .= sprintf(
                ' %s="%s"',
                esc_attr((string) $name),
                esc_attr((string) $value)
            );
        }

        return $output;
    }
}
