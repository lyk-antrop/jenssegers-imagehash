<?php

use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\AverageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: verifies that our GD-based fork produces stable, known hashes
 * for the bundled test images. Reference values were computed 2026-03-15 with
 * PHP 8.4 + ext-gd using imagescale() for resize and imagecolorat() for pixel access.
 */
class CompatibilityTest extends TestCase
{
    public static function preCalculatedImageHashes(): array
    {
        return [
            [AverageHash::class, __DIR__ . '/images/forest/forest-copyright.jpg', '382c281c3a30387c'],
            [AverageHash::class, __DIR__ . '/images/forest/forest-cropped.jpg', '382c081c3a30387c'],
            [AverageHash::class, __DIR__ . '/images/forest/forest-high.jpg', '382c081c3a30387c'],
            [AverageHash::class, __DIR__ . '/images/forest/forest-low.jpg', '3a2c001c3a30383f'],
            [AverageHash::class, __DIR__ . '/images/forest/forest-thumb.jpg', '3828243c3a30783f'],

            [DifferenceHash::class, __DIR__ . '/images/forest/forest-copyright.jpg', '2636aa252c2ab61a'],
            [DifferenceHash::class, __DIR__ . '/images/forest/forest-cropped.jpg', '2636aa252d2ab61a'],
            [DifferenceHash::class, __DIR__ . '/images/forest/forest-high.jpg', '2636aa252d2ab61a'],
            [DifferenceHash::class, __DIR__ . '/images/forest/forest-low.jpg', 'a636aaaf25aa361a'],
            [DifferenceHash::class, __DIR__ . '/images/forest/forest-thumb.jpg', '16369a2d27271202'],

            [PerceptualHash::class, __DIR__ . '/images/forest/forest-copyright.jpg', 'bdd6520b2b2db5fd'],
            [PerceptualHash::class, __DIR__ . '/images/forest/forest-cropped.jpg', 'a5d6520b2b2d35fd'],
            [PerceptualHash::class, __DIR__ . '/images/forest/forest-high.jpg', 'a5d6520b2b2d35fd'],
            [PerceptualHash::class, __DIR__ . '/images/forest/forest-low.jpg', 'a5d6520b2b2d35fd'],
            [PerceptualHash::class, __DIR__ . '/images/forest/forest-thumb.jpg', 'a5d6520b0b2f3dff'],
        ];
    }

    #[DataProvider('preCalculatedImageHashes')]
    public function testPreCalculatedHashes(string $implementation, string $path, string $expectedHex): void
    {
        $hasher = new ImageHash(new $implementation());
        $hash = $hasher->hash($path);
        $this->assertSame($expectedHex, $hash->toHex(), sprintf(
            '%s hash mismatch for %s',
            class_basename($implementation),
            basename($path),
        ));
    }
}

function class_basename(string $class): string
{
    return basename(str_replace('\\', '/', $class));
}
