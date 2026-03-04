<?php

namespace WCO\Starter\Core;

class Acf
{
    public static function save_json(string $path): string
    {
        $path = self::json_dir();

        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        return $path;
    }

    public static function load_json(array $paths): array
    {
        $paths[] = self::json_dir();

        return array_values(array_unique($paths));
    }

    private static function json_dir(): string
    {
        return get_template_directory() . '/acf-json';
    }
}
