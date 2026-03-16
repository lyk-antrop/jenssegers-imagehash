<?php

namespace Jenssegers\ImageHash;

use JsonSerializable;

class Hash implements JsonSerializable
{
    /**
     * A string containing zeros and ones
     *
     * @var string
     */
    protected string $binaryValue;

    /**
     * Hash will be split in several integers if longer than PHP_INT_SIZE
     *
     * @var int[]|null
     */
    protected ?array $integers = null;

    /**
     * @param string $binaryValue
     */
    private function __construct(string $binaryValue)
    {
        $this->binaryValue = $binaryValue;
    }

    /**
     * Create a hash from an array of bits or a string containing a binary representation of the hash
     *
     * @param array<int>|string $bits
     *
     * @return self
     */
    public static function fromBits(array|string $bits): self
    {
        if (\is_array($bits)) {
            $bits = implode('', $bits);
        }

        return new self($bits);
    }

    /**
     * Create a hash from a signed 64-bit integer (as stored in a BIGINT column).
     * The integer is interpreted as a 64-bit two's-complement value.
     * Negative values are valid — they represent hashes with the MSB set.
     *
     * @param int $value
     *
     * @return self
     */
    public static function fromBigInt(int $value): self
    {
        // sprintf('%b') gives the unsigned binary representation on 64-bit PHP.
        // For positive ints it may be < 64 chars; for negative ints it is exactly 64 chars.
        // substr(..., -64) trims any hypothetical overflow and str_pad ensures 64-char width.
        $bits = str_pad(sprintf('%b', $value), 64, '0', STR_PAD_LEFT);

        return new self(substr($bits, -64));
    }

    /**
     * Create a hash from a hexadecimal string (as returned by toHex()).
     *
     * @param string $hex
     *
     * @return self
     */
    public static function fromHex(string $hex): self
    {
        $bits = '';
        foreach (str_split($hex) as $char) {
            $bits .= sprintf('%04b', hexdec($char));
        }

        return new self($bits);
    }

    /**
     * Return the hash as a single signed 64-bit integer, suitable for storage in a BIGINT column.
     * Only valid for 64-bit hashes (pHash, aHash, dHash). BlockHash (256-bit) will throw.
     *
     * Uses GMP when available to avoid float-precision loss: PHP's bindec() routes through
     * IEEE 754 double (53-bit mantissa), silently corrupting bits 53–62 of the 64-bit hash.
     * Two visually distinct images whose hashes share bits 0–52 and bit 63 would be stored
     * identically without GMP, producing false duplicate matches in BIT_COUNT(XOR) queries.
     *
     * @return int
     *
     * @throws \RuntimeException if the hash is not exactly 64 bits
     */
    public function toBigInt(): int
    {
        if (\strlen($this->binaryValue) !== 64) {
            throw new \RuntimeException(sprintf(
                'toBigInt() requires a 64-bit hash, but this hash is %d bits.',
                \strlen($this->binaryValue),
            ));
        }

        if (\extension_loaded('gmp')) {
            // Parse as unsigned 64-bit integer, then sign-extend into PHP's signed int.
            $unsigned = gmp_init('0b' . $this->binaryValue);
            $signed   = gmp_cmp($unsigned, gmp_init(PHP_INT_MAX)) > 0
                ? gmp_sub($unsigned, gmp_pow(gmp_init(2), 64))
                : $unsigned;

            return gmp_intval($signed);
        }

        // Fallback without GMP: precision is limited to 53+1 bits (bindec uses double
        // internally). Bits 53–62 may be corrupted for large hash values.
        $integers = $this->getIntegers();

        if (\count($integers) !== 1) {
            throw new \RuntimeException(sprintf(
                'toBigInt() requires a 64-bit hash, but this hash is %d bits (%d integers).',
                \strlen($this->binaryValue),
                \count($integers),
            ));
        }

        return $integers[0];
    }

    /**
     * Use integers representation and concatenate their hexadecimal representation.
     * Always returns a zero-padded string of length (bits / 4) so that round-trips
     * via fromHex() are lossless.
     *
     * @return string
     */
    public function toHex(): string
    {
        // Number of hex characters required for this hash (4 bits per hex digit).
        $hexLength = (int) ceil(\strlen($this->binaryValue) / 4);

        if (\extension_loaded('gmp')) {
            $gmp = gmp_init('0b' . $this->binaryValue);

            return str_pad(bin2hex(gmp_export($gmp)), $hexLength, '0', STR_PAD_LEFT);
        }

        return str_pad(
            implode(
                '',
                array_map(
                    static function (int $int) {
                        return dechex($int);
                    },
                    $this->getIntegers()
                )
            ),
            $hexLength,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function toBits(): string
    {
        return $this->binaryValue;
    }

    /**
     * Used to compute hexadecimal value and can be used to store the hash in database as an integer
     *
     * @return int[]
     */
    public function getIntegers(): array
    {
        if (null !== $this->integers) {
            return $this->integers;
        }

        $maxIntSize = PHP_INT_SIZE * 8; // 8 bytes (a byte is 8 bits)

        // Fixing binary if it doesn't fit an exact multiple of max int size
        $fixedSizeBinary = str_pad(
            $this->binaryValue,
            ((int) ceil(\strlen($this->binaryValue) / $maxIntSize)) * $maxIntSize, // Is there a better way?
            '0',
            STR_PAD_LEFT
        );

        $this->integers = [];
        foreach (str_split($fixedSizeBinary, $maxIntSize) as $split) {
            $sign = $split[0]; // Extract sign
            $int = bindec(substr($split, 1)); // Convert to decimal without first bit
            $int |= ((bool) $sign) << ($maxIntSize - 1); // Reapply last bit with bitwise operation
            $this->integers[] = $int;
        }

        return $this->integers;
    }

    /**
     * Super simple distance computation algorithm, we don't need anything else
     *
     * @param Hash $hash
     *
     * @return int
     */
    public function distance(Hash $hash): int
    {
        if (\extension_loaded('gmp')) {
            return gmp_hamdist('0b' . $this->toBits(), '0b' . $hash->toBits());
        }

        $bits1 = $this->toBits();
        $bits2 = $hash->toBits();
        $length = max(\strlen($bits1), \strlen($bits2));

        // Add leading zeros so the bit strings are the same length.
        $bits1 = str_pad($bits1, $length, '0', STR_PAD_LEFT);
        $bits2 = str_pad($bits2, $length, '0', STR_PAD_LEFT);

        return \count(array_diff_assoc(str_split($bits1), str_split($bits2)));
    }

    /**
     * @param Hash $hash
     *
     * @return bool
     */
    public function equals(Hash $hash): bool
    {
        return ltrim($this->binaryValue, '0') === ltrim($hash->binaryValue, '0');
    }

    public function __toString(): string
    {
        return $this->toHex();
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
