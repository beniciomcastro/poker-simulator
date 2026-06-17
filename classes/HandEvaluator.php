<?php
class HandEvaluator
{
    private static array $valueMap = [
        '2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,'10'=>10,
        'J'=>11,'Q'=>12,'K'=>13,'A'=>14
    ];

    public static function evaluate(array $cards): array
    {
        if (count($cards) < 5) return self::evaluatePreFlop($cards);
        $best = null;
        foreach (self::combinations($cards, 5) as $combo) {
            $score = self::evaluateFive($combo);
            if ($best === null || self::compareScores($score, $best) > 0) $best = $score;
        }
        return $best ?? [0, [0], 'Carta Alta'];
    }

    public static function compareScores(array $a, array $b): int
    {
        if ($a[0] !== $b[0]) return $a[0] <=> $b[0];
        $max = max(count($a[1]), count($b[1]));
        for ($i=0; $i<$max; $i++) {
            $av = $a[1][$i] ?? 0; $bv = $b[1][$i] ?? 0;
            if ($av !== $bv) return $av <=> $bv;
        }
        return 0;
    }

    private static function evaluatePreFlop(array $cards): array
    {
        if (count($cards) < 2) return [0, [0], 'Carta Alta'];
        $v1 = self::$valueMap[$cards[0]['value']];
        $v2 = self::$valueMap[$cards[1]['value']];
        $suited = $cards[0]['suit'] === $cards[1]['suit'];
        if ($v1 === $v2) return [1, [$v1], 'Par'];
        $bonus = $suited ? 0.1 : 0;
        return [0, [max($v1,$v2), min($v1,$v2), $bonus], 'Carta Alta'];
    }

    private static function evaluateFive(array $cards): array
    {
        $values = array_map(fn($c) => self::$valueMap[$c['value']], $cards);
        rsort($values);
        $suits = array_map(fn($c) => $c['suit'], $cards);
        $isFlush = count(array_unique($suits)) === 1;
        $straightHigh = self::straightHigh($values);
        $counts = array_count_values($values);
        $groups = [];
        foreach ($counts as $value => $count) $groups[] = ['value'=>(int)$value, 'count'=>$count];
        usort($groups, fn($a,$b) => $a['count'] === $b['count'] ? $b['value'] <=> $a['value'] : $b['count'] <=> $a['count']);

        if ($isFlush && $straightHigh === 14) return [9, [14], 'Royal Flush'];
        if ($isFlush && $straightHigh) return [8, [$straightHigh], 'Straight Flush'];
        if ($groups[0]['count'] === 4) return [7, [$groups[0]['value'], self::highestExcept($values, [$groups[0]['value']])], 'Quadra'];
        if ($groups[0]['count'] === 3 && $groups[1]['count'] === 2) return [6, [$groups[0]['value'], $groups[1]['value']], 'Full House'];
        if ($isFlush) return [5, $values, 'Flush'];
        if ($straightHigh) return [4, [$straightHigh], 'Straight'];
        if ($groups[0]['count'] === 3) return [3, array_merge([$groups[0]['value']], array_slice(self::exceptValues($values, [$groups[0]['value']]), 0, 2)), 'Trinca'];
        if ($groups[0]['count'] === 2 && $groups[1]['count'] === 2) {
            $hp = max($groups[0]['value'], $groups[1]['value']); $lp = min($groups[0]['value'], $groups[1]['value']);
            return [2, [$hp, $lp, self::highestExcept($values, [$hp,$lp])], 'Dois Pares'];
        }
        if ($groups[0]['count'] === 2) return [1, array_merge([$groups[0]['value']], array_slice(self::exceptValues($values, [$groups[0]['value']]), 0, 3)), 'Par'];
        return [0, $values, 'Carta Alta'];
    }

    private static function straightHigh(array $values): int|false
    {
        $values = array_values(array_unique($values)); rsort($values);
        if (in_array(14, $values)) $values[] = 1;
        $run = 1;
        for ($i=0; $i<count($values)-1; $i++) {
            if ($values[$i]-1 === $values[$i+1]) { $run++; if ($run === 5) return $values[$i-3]; }
            else $run = 1;
        }
        return false;
    }

    private static function combinations(array $cards, int $size): array
    {
        if ($size === 0) return [[]];
        if (count($cards) < $size) return [];
        $result = [];
        for ($i=0; $i<=count($cards)-$size; $i++) {
            foreach (self::combinations(array_slice($cards, $i+1), $size-1) as $combo) {
                array_unshift($combo, $cards[$i]); $result[] = $combo;
            }
        }
        return $result;
    }

    private static function highestExcept(array $values, array $except): int
    {
        foreach ($values as $value) if (!in_array($value, $except)) return $value;
        return 0;
    }

    private static function exceptValues(array $values, array $except): array
    {
        return array_values(array_filter($values, fn($v) => !in_array($v, $except)));
    }
}
