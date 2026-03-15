# ImageHash — Pure GD Fork

[![PHP](https://img.shields.io/badge/php-%3E%3D8.3-blue)](https://php.net)

A perceptual image hashing library for PHP. Compute and compare image fingerprints to detect
visually similar or identical images.

This is a **vendor fork** of [jenssegers/imagehash](https://github.com/jenssegers/imagehash)
(v0.11.0, Sep 2025), rewritten to use **pure GD** — no `intervention/image`, no `phpseclib`,
no external dependencies beyond `ext-gd`.

## What Changed vs Upstream

| Area                     | Upstream                                                                          | This fork                                                                   |
| ------------------------ | --------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Image loading            | `intervention/image` (GD or Imagick backend)                                    | Direct GD:`imagecreatefromstring()`, `imagescale()`, `imagecolorat()` |
| Dependencies             | `intervention/image` ^3.3, `intervention/gif` (transitive)                    | **None** — only `ext-gd`                                           |
| Input types              | File path only (`ImageManager::read()`)                                         | File path, URL, raw binary string, or `\GdImage` instance                 |
| Implementation interface | `hash(Intervention\Image\Image $image): Hash` | `hash(\GdImage $image): Hash` |                                                                             |
| PHP version              | ^8.2                                                                              | >=8.3                                                                       |
| Algorithms               | AverageHash, DifferenceHash, PerceptualHash, BlockHash                            | Same four, same logic, GD pixel access                                      |

### Why fork?

- **`intervention/image` is unnecessary overhead** for the trivial operations needed (resize to
  8×8 or 32×32, read pixel RGB). Direct GD is ~4x faster per
  [benchmarks](https://github.com/jenssegers/imagehash/issues/50).
- **No in-memory hashing** — upstream only accepts file paths. We need to hash `\GdImage`
  resources that are already in memory during import pipelines.
- **Upstream is semi-maintained** — 35+ open issues, no release since 2023, known integer
  overflow bugs ([#62](https://github.com/jenssegers/imagehash/issues/62),
  [#90](https://github.com/jenssegers/imagehash/issues/90),
  [#93](https://github.com/jenssegers/imagehash/issues/93)).

## Credits

- **[Jens Segers](https://github.com/jenssegers)** — original `jenssegers/imagehash` library,
  algorithm implementations, `Hash` value object, and test image corpus
- **[Kenneth Rapp / kennethrapp](https://github.com/kennethrapp/phasher)** — original pHasher
  project that inspired the upstream library
- **[VincentChalnot](https://github.com/VincentChalnot/imagehash)** — upstream fork that removed
  `phpseclib` dependency and introduced `getIntegers()` for pure-PHP integer representation
  (merged into upstream `Hash.php` which this fork inherits)
- **Algorithm references**:
  - http://www.phash.org — pHash algorithm specification
  - http://blockhash.io — BlockHash algorithm
  - http://www.hackerfactor.com/blog/?/archives/529-Kind-of-Like-That.html — DifferenceHash
  - http://www.hackerfactor.com/blog/?/archives/432-Looks-Like-It.html — AverageHash

## Requirements

- PHP >= 8.3
- ext-gd
- Optionally ext-gmp (faster Hamming distance computation)

## Installation

This library was originally, but not necessarilly, intended as a Composer VCS fork:

```json
{
    "repositories": {
        "jenssegers/imagehash": {
            "type": "vcs",
            "url": "git@github.com:lyk-antrop/jenssegers-imagehash.git"
        }
    },
    "require": {
        "jenssegers/imagehash": "dev-master"
    }
}
```

## Usage

```php
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

// Create a hasher with any implementation
$hasher = new ImageHash(new DifferenceHash());

// Hash from a file path
$hash = $hasher->hash('/path/to/image.jpg');
echo $hash->toHex();  // e.g. "3c3e0e1a3a1e1e0e"

// Hash from a URL
$hash = $hasher->hash('https://example.com/photo.jpg');

// Hash from raw image bytes (e.g. HTTP response body)
$hash = $hasher->hash($rawImageData);

// Hash from an already-loaded GdImage
$gd = imagecreatefromjpeg('/path/to/image.jpg');
$hash = $hasher->hash($gd);

// Compare two images
$distance = $hasher->compare('/path/to/image1.jpg', '/path/to/image2.jpg');
// distance 0 = identical, distance <= 5 = very similar, distance > 10 = different

// Compare two hashes directly
$distance = $hash1->distance($hash2);
```

## Algorithms

| Algorithm          | Default Size | Hash Bits | Speed             | Discrimination                                    |
| ------------------ | ------------ | --------- | ----------------- | ------------------------------------------------- |
| `AverageHash`    | 8x8          | 64        | Fastest           | Low — sensitive to gamma changes                 |
| `DifferenceHash` | 9x8          | 64        | Fast              | Good for general use                              |
| `PerceptualHash` | 32x32 + DCT  | 64        | Slower (~10-50ms) | Best — robust to resize, compression, watermarks |
| `BlockHash`      | 16x16 blocks | 256       | Medium            | Good — works on full-resolution image            |

**Recommendation**: Use `PerceptualHash` for image deduplication. Use `DifferenceHash` when
speed matters more than discrimination.

## Hash Object

```php
$hash->toHex();        // Hexadecimal string (e.g. "3c3e0e1a3a1e1e0e")
$hash->toBits();       // Binary string of 0s and 1s
$hash->getIntegers();  // Array of PHP integers (splits hash to fit PHP_INT_SIZE)
$hash->distance($other); // Hamming distance to another Hash
$hash->equals($other);   // Exact equality check
echo $hash;            // Implicit toHex() via __toString
json_encode($hash);    // Serializes as hex string
```

## License

MIT — same as the original library. See [LICENSE.md](LICENSE.md).
