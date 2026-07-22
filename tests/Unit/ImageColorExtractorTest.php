<?php

namespace Tests\Unit;

use App\Services\ImageColorExtractor;
use PHPUnit\Framework\TestCase;

class ImageColorExtractorTest extends TestCase
{
    private function solidImagePath(int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor(64, 64);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $path = tempnam(sys_get_temp_dir(), 'ice') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function test_extracts_dominant_color_from_solid_image(): void
    {
        $path = $this->solidImagePath(210, 40, 40); // strong red
        $hex = (new ImageColorExtractor())->extract($path);
        @unlink($path);

        $this->assertNotNull($hex);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        // Red channel should dominate.
        $this->assertGreaterThan(hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 1, 2)));
    }

    public function test_returns_null_for_non_image_input(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ice');
        file_put_contents($path, 'not an image at all');
        $hex = (new ImageColorExtractor())->extract($path);
        @unlink($path);

        $this->assertNull($hex);
    }
}
