<?php namespace Jenssegers\ImageHash\Implementations;

use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\Implementation;
use RuntimeException;

class AverageHash implements Implementation
{
    protected int $size;

    public function __construct(int $size = 8)
    {
        $this->size = $size;
    }

    public function hash(\GdImage $image): Hash
    {
        $resized = imagescale($image, $this->size, $this->size);
        if ($resized === false) {
            throw new RuntimeException('imagescale failed in AverageHash.');
        }

        $pixels = [];
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $pixels[] = $this->luminance($resized, $x, $y);
            }
        }

        $average = floor(array_sum($pixels) / count($pixels));

        $bits = array_map(fn ($pixel) => (int) ($pixel > $average), $pixels);

        return Hash::fromBits($bits);
    }

    private function luminance(\GdImage $image, int $x, int $y): int
    {
        $c = imagecolorat($image, $x, $y);
        return (int) floor((($c >> 16 & 0xFF) * 0.299) + (($c >> 8 & 0xFF) * 0.587) + (($c & 0xFF) * 0.114));
    }
}
