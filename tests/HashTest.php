<?php

use Jenssegers\ImageHash\Hash;
use PHPUnit\Framework\TestCase;

class HashTest extends TestCase
{
    // --- fromBigInt / toBigInt round-trips ---

    public function testZeroHashRoundTrip(): void
    {
        $hash = Hash::fromBigInt(0);
        $this->assertSame(0, $hash->toBigInt());
    }

    public function testPositiveIntRoundTrip(): void
    {
        $hash = Hash::fromBigInt(123456789);
        $this->assertSame(123456789, $hash->toBigInt());
    }

    public function testPhpIntMaxRoundTrip(): void
    {
        $hash = Hash::fromBigInt(PHP_INT_MAX);
        $this->assertSame(PHP_INT_MAX, $hash->toBigInt());
    }

    public function testPhpIntMinRoundTrip(): void
    {
        // PHP_INT_MIN has MSB set — must survive the round-trip as a negative int.
        $hash = Hash::fromBigInt(PHP_INT_MIN);
        $this->assertSame(PHP_INT_MIN, $hash->toBigInt());
    }

    public function testMsbSetNegativeRoundTrip(): void
    {
        // A typical pHash value with MSB set (negative when interpreted as signed 64-bit).
        $value = -9_223_372_036_854_775_807; // PHP_INT_MIN + 1
        $hash = Hash::fromBigInt($value);
        $this->assertSame($value, $hash->toBigInt());
    }

    public function testArbitraryNegativeRoundTrip(): void
    {
        $value = -42;
        $hash = Hash::fromBigInt($value);
        $this->assertSame($value, $hash->toBigInt());
    }

    public function testDistancePreservedAfterBigIntRoundTrip(): void
    {
        // Two hashes that differ in exactly 3 bits.
        // Start from a known bit string and flip 3 bits.
        $bits1 = str_repeat('0', 61) . '111'; // bits 0–60 = 0, bits 61–63 = 1
        $bits2 = str_repeat('0', 64);         // all zeros

        $hash1 = Hash::fromBits($bits1);
        $hash2 = Hash::fromBits($bits2);

        $this->assertSame(3, $hash1->distance($hash2));

        // Round-trip via bigint and verify distance is preserved.
        $rt1 = Hash::fromBigInt($hash1->toBigInt());
        $rt2 = Hash::fromBigInt($hash2->toBigInt());

        $this->assertSame(3, $rt1->distance($rt2));
        $this->assertTrue($rt1->equals($hash1));
        $this->assertTrue($rt2->equals($hash2));
    }

    public function testAllOnesHash(): void
    {
        $hash = Hash::fromBits(str_repeat('1', 64));
        $this->assertSame(-1, $hash->toBigInt()); // 0xFFFFFFFFFFFFFFFF as signed
        $rt = Hash::fromBigInt(-1);
        $this->assertTrue($rt->equals($hash));
    }

    // --- fromHex / toHex round-trips ---

    public function testHexRoundTripAllZeros(): void
    {
        $hex = str_repeat('0', 16);
        $hash = Hash::fromHex($hex);
        $this->assertSame($hex, $hash->toHex());
    }

    public function testHexRoundTripAllFs(): void
    {
        $hex = str_repeat('f', 16);
        $hash = Hash::fromHex($hex);
        $this->assertSame($hex, $hash->toHex());
    }

    public function testHexRoundTripMixed(): void
    {
        $hex = 'deadbeefcafebabe';
        $hash = Hash::fromHex($hex);
        $this->assertSame($hex, $hash->toHex());
    }

    public function testHexAndBigIntConsistent(): void
    {
        // A hash constructed from a bigint and one from its hex value must be equal.
        $value = 0x0f0f0f0f0f0f0f0f;
        $hash = Hash::fromBigInt($value);
        $hex = $hash->toHex();
        $hashFromHex = Hash::fromHex($hex);
        $this->assertTrue($hash->equals($hashFromHex));
        $this->assertSame($value, $hashFromHex->toBigInt());
    }

    // --- toBigInt validation ---

    public function testToBigIntThrowsFor256BitHash(): void
    {
        // BlockHash produces 256-bit hashes — toBigInt() must throw.
        $hash = Hash::fromBits(str_repeat('1', 256));
        $this->expectException(\RuntimeException::class);
        $hash->toBigInt();
    }
}
