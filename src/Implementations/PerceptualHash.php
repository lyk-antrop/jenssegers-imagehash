<?php

namespace Jenssegers\ImageHash\Implementations;

use InvalidArgumentException;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\Implementation;
use RuntimeException;

class PerceptualHash implements Implementation
{
    const AVERAGE = 'average';
    const MEDIAN = 'median';

    protected int $size;
    protected string $comparisonMethod;

    public function __construct(int $size = 32, string $comparisonMethod = self::AVERAGE)
    {
        if (!in_array($comparisonMethod, [self::AVERAGE, self::MEDIAN])) {
            throw new InvalidArgumentException('Unknown comparison mode ' . $comparisonMethod);
        }

        $this->size = $size;
        $this->comparisonMethod = $comparisonMethod;
    }

    public function hash(\GdImage $image): Hash
    {
        $resized = imagescale($image, $this->size, $this->size);
        if ($resized === false) {
            throw new RuntimeException('imagescale failed in PerceptualHash.');
        }

        $matrix = [];
        $rows = [];

        for ($y = 0; $y < $this->size; $y++) {
            $row = [];
            for ($x = 0; $x < $this->size; $x++) {
                $row[$x] = $this->luminance($resized, $x, $y);
            }
            $rows[$y] = $this->calculateDCT($row);
        }

        for ($x = 0; $x < $this->size; $x++) {
            $col = [];
            for ($y = 0; $y < $this->size; $y++) {
                $col[$y] = $rows[$y][$x];
            }
            $matrix[$x] = $this->calculateDCT($col);
        }

        // Extract the top 8×8 pixels.
        $pixels = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $pixels[] = $matrix[$y][$x];
            }
        }

        $compare = $this->comparisonMethod === self::MEDIAN
            ? $this->median($pixels)
            : $this->average($pixels);

        $bits = [];
        foreach ($pixels as $pixel) {
            $bits[] = (int) ($pixel > $compare);
        }

        return Hash::fromBits($bits);
    }

    /**
     * Perform a 1-dimension Discrete Cosine Transformation.
     *
     * @param array<int|float> $matrix
     * @return array<float>
     */
    protected function calculateDCT(array $matrix): array
    {
        $transformed = [];
        $size = count($matrix);

        for ($i = 0; $i < $size; $i++) {
            $sum = 0;
            for ($j = 0; $j < $size; $j++) {
                $sum += $matrix[$j] * cos($i * M_PI * ($j + 0.5) / $size);
            }
            $sum *= sqrt(2 / $size);
            if ($i === 0) {
                $sum *= 1 / sqrt(2);
            }
            $transformed[$i] = $sum;
        }

        return $transformed;
    }

    /** @param array<int|float> $pixels */
    protected function median(array $pixels): float
    {
        sort($pixels, SORT_NUMERIC);
        $n = count($pixels);

        return $n % 2 === 0
            ? ($pixels[$n / 2 - 1] + $pixels[$n / 2]) / 2
            : $pixels[(int) floor($n / 2)];
    }

    /** @param array<int|float> $pixels */
    protected function average(array $pixels): float
    {
        // Calculate the average value from top 8×8 pixels, except for the first one.
        $n = count($pixels) - 1;

        return array_sum(array_slice($pixels, 1, $n)) / $n;
    }

    private function luminance(\GdImage $image, int $x, int $y): int
    {
        $c = imagecolorat($image, $x, $y);
        return (int) floor((($c >> 16 & 0xFF) * 0.299) + (($c >> 8 & 0xFF) * 0.587) + (($c & 0xFF) * 0.114));
    }
}
