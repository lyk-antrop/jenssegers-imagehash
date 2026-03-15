<?php namespace Jenssegers\ImageHash\Implementations;

use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\Implementation;
use RuntimeException;

class DifferenceHash implements Implementation
{
    protected int $size;

    public function __construct(int $size = 8)
    {
        $this->size = $size;
    }

    public function hash(\GdImage $image): Hash
    {
        // For this implementation we create an (size+1) × size image.
        $width = $this->size + 1;
        $height = $this->size;

        $resized = imagescale($image, $width, $height);
        if ($resized === false) {
            throw new RuntimeException('imagescale failed in DifferenceHash.');
        }

        $bits = [];
        for ($y = 0; $y < $height; $y++) {
            $left = $this->luminance($resized, 0, $y);

            for ($x = 1; $x < $width; $x++) {
                $right = $this->luminance($resized, $x, $y);

                // Each hash bit is set based on whether the left pixel is brighter than the right pixel.
                // http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
                $bits[] = (int) ($left > $right);

                $left = $right;
            }
        }

        return Hash::fromBits($bits);
    }

    private function luminance(\GdImage $image, int $x, int $y): int
    {
        $c = imagecolorat($image, $x, $y);
        return (int) floor((($c >> 16 & 0xFF) * 0.299) + (($c >> 8 & 0xFF) * 0.587) + (($c & 0xFF) * 0.114));
    }
}
