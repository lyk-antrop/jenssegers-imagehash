<?php

use Jenssegers\ImageHash\ImageHash;
use PHPUnit\Framework\TestCase;

class ImageHashTest extends TestCase
{
    private ImageHash $imageHash;

    public function setUp(): void
    {
        $this->imageHash = new ImageHash();
    }

    public function testHashInvalidFile(): void
    {
        $this->expectException(RuntimeException::class);

        $this->imageHash->hash('nonImageString');
    }

    public function testHashFromFilePath(): void
    {
        $hash = $this->imageHash->hash(__DIR__ . '/images/forest/forest-high.jpg');
        $this->assertNotEmpty($hash->toHex());
    }

    public function testHashFromGdImage(): void
    {
        $gd = imagecreatefromjpeg(__DIR__ . '/images/forest/forest-high.jpg');
        $hash = $this->imageHash->hash($gd);
        $this->assertNotEmpty($hash->toHex());
    }

    public function testHashFromRawBinaryString(): void
    {
        $data = file_get_contents(__DIR__ . '/images/forest/forest-high.jpg');
        $hash = $this->imageHash->hash($data);
        $this->assertNotEmpty($hash->toHex());
    }

    public function testHashDeterministic(): void
    {
        $path = __DIR__ . '/images/forest/forest-high.jpg';
        $hash1 = $this->imageHash->hash($path);
        $hash2 = $this->imageHash->hash($path);
        $this->assertTrue($hash1->equals($hash2));
    }

    public function testHashFromFilePathMatchesGdImage(): void
    {
        $path = __DIR__ . '/images/forest/forest-high.jpg';
        $gd = imagecreatefromjpeg($path);

        $hashFile = $this->imageHash->hash($path);
        $hashGd = $this->imageHash->hash($gd);
        $this->assertTrue($hashFile->equals($hashGd));
    }

    public function testCompare(): void
    {
        $distance = $this->imageHash->compare(
            __DIR__ . '/images/forest/forest-high.jpg',
            __DIR__ . '/images/forest/forest-copyright.jpg'
        );
        $this->assertLessThanOrEqual(10, $distance);
    }

    public function testCompareDifferentImages(): void
    {
        $distance = $this->imageHash->compare(
            __DIR__ . '/images/forest/forest-high.jpg',
            __DIR__ . '/images/office/tumblr_ndyfdoR6Wp1tubinno1_1280.jpg'
        );
        $this->assertGreaterThan(10, $distance);
    }

    public function testInvalidInputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->imageHash->hash(12345);
    }
}
