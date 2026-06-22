<?php

require_once __DIR__ . '/HandEvaluator.php';

class BotStrategy
{
    public static function decide(array $game, int $botIndex): array
    {
        $bot = $game['players'][$botIndex];

        $currentBet = (int)($game['currentBet'] ?? 0);
        $callAmount = max(0, $currentBet - (int)$bot['bet']);
        $pot = max(1, (int)$game['pot']);
        $stage = $game['stage'] ?? 'preflop';

        $cards = array_merge($bot['cards'], $game['community']);
        $score = HandEvaluator::evaluate($cards);

        $rank = (int)$score[0];
        $mode = $game['botMode'] ?? 'casual';
$personality = self::personality($botIndex, $mode);
        $strength = self::handStrength($rank, $stage, $bot['cards'], $game['community']);

        $potOdds = $callAmount > 0 ? $callAmount / ($pot + $callAmount) : 0;
        $random = mt_rand(1, 100) / 100;

        if ($strength >= 0.82) {
            return self::raiseOrCall($bot, $callAmount, $pot, $personality, 0.75);
        }

        if ($strength >= 0.62) {
            return self::raiseOrCall($bot, $callAmount, $pot, $personality, 0.35);
        }

        if ($strength >= 0.42) {
            if ($callAmount === 0) {
                return ['action' => 'check', 'raise' => 0];
            }

            if ($potOdds <= 0.28 || $random < $personality['curiosity']) {
                return ['action' => 'call', 'raise' => 0];
            }

            return ['action' => 'fold', 'raise' => 0];
        }

        if ($callAmount === 0 && $random < $personality['bluff']) {
            return [
                'action' => 'raise',
                'raise' => self::raiseSize($bot, $pot, 'small')
            ];
        }

        if ($callAmount === 0) {
            return ['action' => 'check', 'raise' => 0];
        }

        if ($potOdds <= 0.16 && $random < 0.35) {
            return ['action' => 'call', 'raise' => 0];
        }

        return ['action' => 'fold', 'raise' => 0];
    }

    private static function personality(int $botIndex, string $mode = 'casual'): array
{
    if ($mode === 'legendary') {
        return match ($botIndex) {
            1 => ['aggression' => 0.50, 'bluff' => 0.10, 'curiosity' => 0.16],
            2 => ['aggression' => 0.62, 'bluff' => 0.14, 'curiosity' => 0.20],
            3 => ['aggression' => 0.74, 'bluff' => 0.18, 'curiosity' => 0.24],
            default => ['aggression' => 0.60, 'bluff' => 0.13, 'curiosity' => 0.18],
        };
    }

    return match ($botIndex) {
        1 => ['aggression' => 0.18, 'bluff' => 0.03, 'curiosity' => 0.38],
        2 => ['aggression' => 0.26, 'bluff' => 0.05, 'curiosity' => 0.46],
        3 => ['aggression' => 0.34, 'bluff' => 0.07, 'curiosity' => 0.54],
        default => ['aggression' => 0.25, 'bluff' => 0.05, 'curiosity' => 0.45],
    };
}

    private static function handStrength(int $rank, string $stage, array $holeCards, array $community): float
    {
        if ($stage === 'preflop') {
            return self::preflopStrength($holeCards);
        }

        return match ($rank) {
            9 => 1.00,
            8 => 0.98,
            7 => 0.95,
            6 => 0.90,
            5 => 0.78,
            4 => 0.70,
            3 => 0.62,
            2 => 0.50,
            1 => 0.34,
            default => self::drawStrength($holeCards, $community),
        };
    }

    private static function preflopStrength(array $cards): float
    {
        if (count($cards) < 2) {
            return 0.10;
        }

        $values = [
            '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
            '7' => 7, '8' => 8, '9' => 9, '10' => 10,
            'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14
        ];

        $v1 = $values[$cards[0]['value']];
        $v2 = $values[$cards[1]['value']];

        $high = max($v1, $v2);
        $low = min($v1, $v2);
        $suited = $cards[0]['suit'] === $cards[1]['suit'];
        $gap = abs($v1 - $v2);

        if ($v1 === $v2) {
            if ($high >= 11) return 0.90;
            if ($high >= 8) return 0.74;
            return 0.58;
        }

        $strength = 0.18;

        if ($high === 14) $strength += 0.28;
        if ($high >= 12) $strength += 0.18;
        if ($low >= 10) $strength += 0.16;
        if ($suited) $strength += 0.10;
        if ($gap === 1) $strength += 0.10;
        if ($gap === 2) $strength += 0.06;
        if ($gap >= 5) $strength -= 0.10;

        return max(0.05, min(0.88, $strength));
    }

    private static function drawStrength(array $holeCards, array $community): float
    {
        $cards = array_merge($holeCards, $community);

        $suits = [];
        $values = [];

        $map = [
            '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
            '7' => 7, '8' => 8, '9' => 9, '10' => 10,
            'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14
        ];

        foreach ($cards as $card) {
            $suits[] = $card['suit'];
            $values[] = $map[$card['value']];
        }

        $flushDraw = !empty($suits) && max(array_count_values($suits)) >= 4;

        $values = array_values(array_unique($values));
        sort($values);

        $straightDraw = false;

        for ($i = 0; $i < count($values); $i++) {
            $window = array_slice($values, $i, 4);

            if (count($window) === 4 && max($window) - min($window) <= 4) {
                $straightDraw = true;
            }
        }

        if ($flushDraw && $straightDraw) return 0.48;
        if ($flushDraw) return 0.40;
        if ($straightDraw) return 0.36;

        return 0.22;
    }

    private static function raiseOrCall(array $bot, int $callAmount, int $pot, array $personality, float $baseRaiseChance): array
    {
        $chance = $baseRaiseChance + $personality['aggression'] * 0.25;
        $random = mt_rand(1, 100) / 100;

        if ($random <= $chance && (int)$bot['chips'] > $callAmount) {
            return [
                'action' => 'raise',
                'raise' => self::raiseSize($bot, $pot, 'normal')
            ];
        }

        if ($callAmount > 0) {
            return ['action' => 'call', 'raise' => 0];
        }

        return ['action' => 'check', 'raise' => 0];
    }

    private static function raiseSize(array $bot, int $pot, string $mode): int
    {
        $base = match ($mode) {
            'small' => (int) round($pot * 0.35),
            default => (int) round($pot * mt_rand(45, 75) / 100),
        };

        $raise = max(20, $base);
        $raise = min($raise, (int)$bot['chips']);

        return (int)(ceil($raise / 10) * 10);
    }
}