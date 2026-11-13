<?php

namespace App\Support;

final class ChipCounts
{
    /**
     * @var list<int>
     */
    public const array DENOMINATIONS = [100, 50, 25, 10, 5];

    /**
     * @param  array<int, int>  $counts
     */
    public static function total(array $counts): int
    {
        $total = 0;

        foreach (self::DENOMINATIONS as $denomination) {
            $total += ($counts[$denomination] ?? 0) * $denomination;
        }

        return $total;
    }

    /**
     * @param  array<int, int>  $counts
     */
    public static function breakdown(array $counts): string
    {
        $parts = [];

        foreach (self::DENOMINATIONS as $denomination) {
            $count = $counts[$denomination] ?? 0;

            if ($count > 0) {
                $parts[] = $count.'×'.$denomination;
            }
        }

        return $parts === [] ? '0 fichas' : implode(', ', $parts);
    }

    /**
     * @return array<int, int>|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $counts = [];

        foreach (self::DENOMINATIONS as $denomination) {
            $value = $raw[$denomination] ?? $raw[(string) $denomination] ?? 0;
            $count = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);

            if ($count === false) {
                return null;
            }

            $counts[$denomination] = $count;
        }

        return $counts;
    }
}
