<?php

namespace App\Services;

class ImageColorExtractor
{
    /** Reject anything above this pixel count (decompression-bomb guard). */
    private const MAX_PIXELS = 25_000_000;

    /** Downscaled sample edge length. */
    private const SAMPLE = 32;

    /**
     * Return the dominant accent color of an image as #rrggbb, or null.
     * Never throws: unreadable, non-image, or oversized input yields null.
     */
    public function extract(string $absolutePath): ?string
    {
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }

        $sample = imagecreatetruecolor(self::SAMPLE, self::SAMPLE);
        imagecopyresampled($sample, $src, 0, 0, 0, 0, self::SAMPLE, self::SAMPLE, $width, $height);
        imagedestroy($src);

        $buckets = [];
        for ($y = 0; $y < self::SAMPLE; $y++) {
            for ($x = 0; $x < self::SAMPLE; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $max = max($r, $g, $b);
                $min = min($r, $g, $b);

                if ($max > 240 && $min > 240) {
                    continue; // near-white
                }
                if ($max < 15 && $min < 15) {
                    continue; // near-black
                }
                if ($max - $min < 20) {
                    continue; // low-saturation grey
                }

                $key = intdiv($r, 32) . '-' . intdiv($g, 32) . '-' . intdiv($b, 32);
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                }
                $buckets[$key]['count']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }
        imagedestroy($sample);

        if ($buckets === []) {
            return null;
        }

        // PHP 8 sort is stable, so ties resolve by scan order — deterministic.
        uasort($buckets, fn ($a, $b) => $b['count'] <=> $a['count']);
        $top = reset($buckets);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($top['r'] / $top['count']),
            (int) round($top['g'] / $top['count']),
            (int) round($top['b'] / $top['count']),
        );
    }
}
