<?php

namespace WCO\Starter\Core;

use Timber\Timber;
use Twig\TwigFunction;

class Templating
{
    public static function add_to_context($context)
    {
        // 1. URL do szablonu
        $context['theme_url'] = get_template_directory_uri();

        // 2. URL do assetów (public)
        $context['assets_url'] = get_template_directory_uri() . '/public';

        // 3. (Opcjonalnie) mix-manifest
        $manifest_path = get_template_directory() . '/public/mix-manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            $context['mix'] = function ($path) use ($manifest) {
                $path = ltrim($path, '/');
                return get_template_directory_uri() . '/public' . ($manifest[$path] ?? $path);
            };
        }

        return $context;
    }

    public static function extend_twig($twig)
    {
        // Rejestruj funkcję Twig: {{ template_url('images/logo.png') }}
        $twig->addFunction(new TwigFunction('template_url', function ($path = '') {
            return get_template_directory_uri() . '/' . ltrim($path, '/');
        }));

        // Rejestruj mix() – jeśli używasz manifestu
        $manifest_path = get_template_directory() . '/public/mix-manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            $twig->addFunction(new TwigFunction('mix', function ($path) use ($manifest) {
                $path = ltrim($path, '/');
                $asset_path = $manifest[$path] ?? $path;
                return get_template_directory_uri() . '/public' . $asset_path;
            }));
        }

        $twig->addFunction(new \Twig\TwigFunction('do_shortcode', function ($content) {
            return do_shortcode($content);
        }, ['is_safe' => ['html']]));
        

        return $twig;
    }
}