<?php

namespace Jenssegers\ImageHash;

use Jenssegers\ImageHash\Implementations\DifferenceHash;
use RuntimeException;
use InvalidArgumentException;

class ImageHash
{
    protected Implementation $implementation;

    public function __construct(?Implementation $implementation = null)
    {
        $this->implementation = $implementation ?: new DifferenceHash();
    }

    /**
     * Calculate a perceptual hash of an image.
     *
     * @param mixed $image File path, URL, raw image data string, or \GdImage
     */
    public function hash(mixed $image): Hash
    {
        $gd = $this->loadImage($image);

        return $this->implementation->hash($gd);
    }

    /**
     * Compare 2 images and return their hamming distance.
     */
    public function compare(mixed $resource1, mixed $resource2): int
    {
        return $this->distance($this->hash($resource1), $this->hash($resource2));
    }

    public function distance(Hash $hash1, Hash $hash2): int
    {
        return $hash1->distance($hash2);
    }

    /**
     * Load any supported image type into a true-color GdImage.
     *
     * Accepts:
     *   - \GdImage instance (passed through as-is)
     *   - file path or URL (loaded via file_get_contents + imagecreatefromstring)
     *   - raw image binary string (loaded via imagecreatefromstring)
     */
    private function loadImage(mixed $image): \GdImage
    {
        if ($image instanceof \GdImage) {
            $gd = $image;
        } elseif (is_string($image)) {
            // Treat as path/URL if it doesn't look like raw binary.
            if (is_file($image) || preg_match('#^https?://#i', $image)) {
                $data = @file_get_contents($image);
                if ($data === false) {
                    throw new RuntimeException("Cannot read image from: {$image}");
                }
            } else {
                // Assume raw binary string.
                $data = $image;
            }

            $gd = @imagecreatefromstring($data);
            if ($gd === false) {
                throw new RuntimeException('Cannot create GD image from provided data.');
            }
        } else {
            throw new InvalidArgumentException('Unsupported image source type: ' . get_debug_type($image));
        }

        // Ensure true-color so imagecolorat() returns consistent packed RGB.
        imagepalettetotruecolor($gd);

        return $gd;
    }
}
