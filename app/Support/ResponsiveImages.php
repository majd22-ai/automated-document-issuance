<?php

namespace App\Support;

final class ResponsiveImages
{
    public static function image(string $source): array
    {
        return config('responsive_images', [])[$source] ?? [];
    }

    public static function srcset(string $source, string $format = 'webp'): string
    {
        $parts = [];
        foreach (self::image($source)[$format] ?? [] as $width => $path) {
            $parts[] = asset($path).' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    public static function preferredFormat(string $source): string
    {
        return empty(self::image($source)['avif']) ? 'webp' : 'avif';
    }
}
