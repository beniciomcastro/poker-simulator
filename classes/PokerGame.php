<?php
require_once __DIR__ . '/Deck.php';
require_once __DIR__ . '/HandEvaluator.php';

class PokerGame
{
    private const SMALL_BLIND = 10;
    private const BIG_BLIND = 20;

    public static function start(string $name, int $chips = 1000): array
    {
        $players = [
            self::player($name ?: 'Beni', 'human', 1000),
            self::player('Luna', 'bot', 1000),
            self::player('Max', 'bot', 1000),
            self::player('Nina', 'bot', 1000),
        ];
        return self::dealNewHand($players, true, -1);
    }

    public static function nextHand(array $old): array
    {
        $players = $old['players'] ?? [];
        if (count($players) < 2) return $old;

        foreach ($players as $i => $p) {
            $players[$i]['chips'] = max(0, (int)($p['chips'] ?? 0));
            $players[$i]['eliminated'] = $players[$i]['chips'] <= 0;
        }

        if (self::aliveCount($players) <= 1) return self::finishGame($old);
        return self::dealNewHand($players, false, (int)($old['dealer'] ?? -1));
    }

    private static function player(string $name, string $type, int $chips): array
    {
        return [
            'name'=>$name,'type'=>$type,'chips'=>$chips,'cards'=>[],'bet'=>0,'totalBet'=>0,
            'folded'=>false,'allIn'=>false,'acted'=>false,'result'=>'','eliminated'=>false,
            'role'=>''
        ];
    }

    private static function resetForHand(array $p): array
    {
        $p['cards'] = [];
        $p['bet'] = 0;
        $p['totalBet'] = 0;
        $p['folded'] = !empty($p['eliminated']);
        $p['allIn'] = false;
        $p['acted'] = false;
        $p['role'] = '';
        $p['result'] = !empty($p['eliminated']) ? 'Fora do jogo' : '';
        return $p;
    }

    private static function dealNewHand(array $players, bool $fresh, int $previousDealer): array
    {
        $deck = new Deck();
        foreach ($players as $i => $p) {
            $p['eliminated'] = !empty($p['eliminated']) || (int)($p['chips'] ?? 0) <= 0;
            $players[$i] = self::resetForHand($p);
        }

        $alive = self::aliveIndexesFromPlayers($players);
        if (count($alive) <= 1) {
            $g = self::baseGame($deck, $players, [], 0, 0, 'fim', 0, [], $previousDealer, -1, -1);
            return self::finishGame($g);
        }

        for ($r=0; $r<2; $r++) {
            foreach ($alive as $i) $players[$i]['cards'][] = $deck->draw();
        }

        $dealer = self::nextAliveAfter($players, $previousDealer);
        if ($dealer < 0) $dealer = $alive[0];

        if (count($alive) === 2) {
            // Heads-up Texas Hold'em: botão/dealer é também small blind; big blind age primeiro pós-flop.
            $sb = $dealer;
            $bb = self::nextAliveAfter($players, $dealer);
            $firstToAct = $sb; // pré-flop: small blind/dealer age primeiro
        } else {
            $sb = self::nextAliveAfter($players, $dealer);
            $bb = self::nextAliveAfter($players, $sb);
            $firstToAct = self::nextPlayableIndex($players, $bb); // UTG
        }

        $players[$dealer]['role'] = 'D';
        $players[$sb]['role'] = trim(($players[$sb]['role'] ?? '') . ' SB');
        $players[$bb]['role'] = trim(($players[$bb]['role'] ?? '') . ' BB');

        // Os blinds ficam pendentes para a interface conseguir mostrar as fichas corretas
        // durante a intro/carregamento. Eles são descontados somente depois da intro.
        $pot = 0;
        $log = $fresh ? ['Jogo iniciado. Todos começam com 1000 fichas.'] : ['Nova mão iniciada.'];
        $log[] = $players[$dealer]['name'] . ' está com o botão.';

        $g = self::baseGame($deck, $players, [], $pot, 0, 'preflop', -1, $log, $dealer, $sb, $bb);
        $g['pendingBlinds'] = true;
        $g['firstToActAfterBlinds'] = $firstToAct;
        return $g;
    }

    private static function baseGame(Deck $deck, array $players, array $community, int $pot, int $currentBet, string $stage, int $turn, array $log, int $dealer, int $smallBlind, int $bigBlind): array
    {
        return [
            'deck'=>$deck->toArray(), 'players'=>$players, 'community'=>$community,
            'pot'=>$pot, 'currentBet'=>$currentBet, 'stage'=>$stage, 'turn'=>$turn,
            'dealer'=>$dealer, 'smallBlind'=>$smallBlind, 'bigBlind'=>$bigBlind,
            'finished'=>false, 'gameOver'=>false, 'winner'=>null, 'winners'=>[], 'log'=>$log,
            'lastAction'=>null, 'handResult'=>'', 'awaitingAnimation'=>false, 'handSaved'=>false,
            'pendingBlinds'=>false, 'firstToActAfterBlinds'=>$turn
        ];
    }


    public static function applyPendingBlinds(array $g): array
    {
        if (empty($g['pendingBlinds']) || !empty($g['finished']) || !empty($g['gameOver'])) return $g;

        $sb = (int)($g['smallBlind'] ?? -1);
        $bb = (int)($g['bigBlind'] ?? -1);
        if (!isset($g['players'][$sb], $g['players'][$bb])) {
            $g['pendingBlinds'] = false;
            return $g;
        }

        self::takeBet($g['players'][$sb], self::SMALL_BLIND);
        self::takeBet($g['players'][$bb], self::BIG_BLIND);
        $g['pot'] = (int)($g['pot'] ?? 0) + $g['players'][$sb]['bet'] + $g['players'][$bb]['bet'];
        $g['currentBet'] = max($g['players'][$sb]['bet'], $g['players'][$bb]['bet']);
        $g['turn'] = (int)($g['firstToActAfterBlinds'] ?? -1);
        if ($g['turn'] < 0 || !empty($g['players'][$g['turn']]['eliminated']) || !empty($g['players'][$g['turn']]['folded']) || !empty($g['players'][$g['turn']]['allIn'])) {
            $g['turn'] = self::nextPlayableIndex($g['players'], $bb);
        }
        $g['pendingBlinds'] = false;
        $g['lastAction'] = ['player'=>-1,'type'=>'blinds','amount'=>0,'text'=>'Blinds colocados.'];
        $g['log'][] = $g['players'][$sb]['name'] . ' colocou small blind ' . self::SMALL_BLIND . '.';
        $g['log'][] = $g['players'][$bb]['name'] . ' colocou big blind ' . self::BIG_BLIND . '.';

        if ($g['turn'] < 0) return self::advanceStage($g);
        return $g;
    }

    public static function humanAction(array $g, string $action, int $raise): array
    {
        if (($g['finished'] ?? false) || ($g['gameOver'] ?? false) || ($g['turn'] ?? -1) !== 0) return $g;
        return self::applyAction($g, 0, $action, $raise);
    }

    public static function botStep(array $g): array
    {
        if (($g['finished'] ?? false) || ($g['gameOver'] ?? false) || ($g['turn'] ?? 0) === 0) return $g;
        $i = $g['turn'];
        $decision = self::botDecision($g, $i);
        return self::applyAction($g, $i, $decision['action'], $decision['raise']);
    }

    private static function applyAction(array $g, int $i, string $action, int $raise = 0): array
    {
        $p =& $g['players'][$i];
        if (!empty($p['eliminated']) || $p['folded'] || $p['allIn']) return self::nextTurn($g);
        $toCall = max(0, $g['currentBet'] - $p['bet']);
        $name = $p['name'];

        if ($action === 'fold') {
            $p['folded'] = true; $p['acted'] = true;
            $g['lastAction'] = ['player'=>$i,'type'=>'fold','amount'=>0,'text'=>"$name desistiu."];
            $g['log'][] = "$name desistiu.";
        } elseif ($action === 'raise') {
            $raise = max(self::BIG_BLIND, $raise);
            $amount = min($p['chips'], $toCall + $raise);
            self::takeBet($p, $amount); $g['pot'] += $amount;
            if ($p['bet'] > $g['currentBet']) {
                $g['currentBet'] = $p['bet'];
                foreach ($g['players'] as $k => $pl) if (empty($pl['eliminated']) && !$pl['folded'] && !$pl['allIn'] && $k !== $i) $g['players'][$k]['acted'] = false;
            }
            $p['acted'] = true;
            $type = $p['allIn'] ? 'allin' : 'raise';
            $text = $p['allIn'] ? "$name foi all-in." : "$name aumentou.";
            $g['lastAction'] = ['player'=>$i,'type'=>$type,'amount'=>$amount,'text'=>$text];
            $g['log'][] = $p['allIn'] ? "$name foi all-in com {$p['totalBet']}." : "$name aumentou para {$p['bet']}.";
        } elseif ($toCall > 0) {
            $amount = min($p['chips'], $toCall);
            self::takeBet($p, $amount); $g['pot'] += $amount; $p['acted'] = true;
            $type = $p['allIn'] ? 'allin' : 'call';
            $text = $p['allIn'] ? "$name pagou e foi all-in." : "$name pagou $amount.";
            $g['lastAction'] = ['player'=>$i,'type'=>$type,'amount'=>$amount,'text'=>$text];
            $g['log'][] = $p['allIn'] ? "$name pagou $amount e foi all-in." : "$name pagou $amount.";
        } else {
            $p['acted'] = true;
            $g['lastAction'] = ['player'=>$i,'type'=>'check','amount'=>0,'text'=>"$name deu check."];
            $g['log'][] = "$name deu check.";
        }
        return self::afterAction($g);
    }

    private static function takeBet(array &$p, int $amount): void
    {
        $amount = max(0, min($amount, (int)$p['chips']));
        $p['chips'] -= $amount; $p['bet'] += $amount; $p['totalBet'] += $amount;
        if ($p['chips'] <= 0) { $p['chips'] = 0; $p['allIn'] = true; }
    }

    private static function afterAction(array $g): array
    {
        $active = self::activeIndexes($g);
        if (count($active) === 1) return self::awardSingle($g, $active[0]);
        if (self::roundComplete($g)) return self::advanceStage($g);
        return self::nextTurn($g);
    }

    private static function nextTurn(array $g): array
    {
        $n = self::nextPlayableIndex($g['players'], $g['turn']);
        if ($n >= 0) { $g['turn'] = $n; return $g; }
        return self::advanceStage($g);
    }

    private static function nextPlayableIndex(array $players, int $from): int
    {
        $count = count($players);
        for ($step=1; $step<=$count; $step++) {
            $n = ($from + $step) % $count;
            $p = $players[$n];
            if (empty($p['eliminated']) && !$p['folded'] && !$p['allIn']) return $n;
        }
        return -1;
    }

    private static function nextAliveAfter(array $players, int $from): int
    {
        $count = count($players);
        if ($count === 0) return -1;
        for ($step=1; $step<=$count; $step++) {
            $n = (($from % $count) + $step + $count) % $count;
            $p = $players[$n];
            if (empty($p['eliminated']) && (int)($p['chips'] ?? 0) > 0) return $n;
        }
        return -1;
    }

    private static function roundComplete(array $g): bool
    {
        foreach ($g['players'] as $p) {
            if (!empty($p['eliminated']) || $p['folded'] || $p['allIn']) continue;
            if (!$p['acted']) return false;
            if ($p['bet'] !== $g['currentBet']) return false;
        }
        return true;
    }

    private static function advanceStage(array $g): array
    {
        foreach ($g['players'] as $i=>$p) { $g['players'][$i]['bet']=0; $g['players'][$i]['acted']=false; }
        $g['currentBet'] = 0;
        $deck = Deck::fromArray($g['deck']);
        if ($g['stage'] === 'preflop') { for ($i=0;$i<3;$i++) $g['community'][] = $deck->draw(); $g['stage']='flop'; $g['log'][]='Flop revelado.'; }
        elseif ($g['stage'] === 'flop') { $g['community'][] = $deck->draw(); $g['stage']='turn'; $g['log'][]='Turn revelado.'; }
        elseif ($g['stage'] === 'turn') { $g['community'][] = $deck->draw(); $g['stage']='river'; $g['log'][]='River revelado.'; }
        elseif ($g['stage'] === 'river') { return self::showdown($g); }
        $g['deck'] = $deck->toArray();
        $g['lastAction'] = ['player'=>-1,'type'=>'reveal','amount'=>0,'text'=>'Cartas da mesa reveladas.'];
        $g['turn'] = self::firstActiveAfterDealer($g);
        if ($g['turn'] < 0) return self::advanceStage($g);
        return $g;
    }

    private static function firstActiveAfterDealer(array $g): int
    {
        // Pós-flop: a ação começa no primeiro jogador ativo à esquerda do botão.
        return self::nextPlayableIndex($g['players'], (int)($g['dealer'] ?? 0));
    }

    private static function activeIndexes(array $g): array
    {
        $a=[]; foreach ($g['players'] as $i=>$p) if (empty($p['eliminated']) && !$p['folded']) $a[]=$i; return $a;
    }

    private static function aliveIndexesFromPlayers(array $players): array
    {
        $a=[]; foreach ($players as $i=>$p) if (empty($p['eliminated']) && (int)($p['chips'] ?? 0) > 0) $a[]=$i; return $a;
    }

    private static function aliveCount(array $players): int
    {
        return count(self::aliveIndexesFromPlayers($players));
    }

    private static function markEliminated(array $g): array
    {
        foreach ($g['players'] as $i => $p) {
            if ((int)$p['chips'] <= 0) {
                $g['players'][$i]['chips'] = 0;
                $g['players'][$i]['eliminated'] = true;
                $g['players'][$i]['result'] = $g['players'][$i]['result'] ?: 'Fora do jogo';
            }
        }
        return $g;
    }

    private static function finishHand(array $g): array
    {
        $g = self::markEliminated($g);
        $g['finished'] = true;
        $g['gameOver'] = self::aliveCount($g['players']) <= 1;
        if ($g['gameOver']) {
            $winner = self::aliveIndexesFromPlayers($g['players'])[0] ?? null;
            if ($winner !== null) {
                $g['winner'] = $g['players'][$winner]['name'];
                $g['winners'] = [$winner];
                $g['handResult'] .= ' Fim do jogo: ' . $g['winner'] . ' venceu tudo.';
            }
        }
        return $g;
    }

    private static function finishGame(array $g): array
    {
        $g = self::markEliminated($g);
        $winner = self::aliveIndexesFromPlayers($g['players'])[0] ?? 0;
        $g['finished'] = true;
        $g['gameOver'] = true;
        $g['winner'] = $g['players'][$winner]['name'] ?? 'Vencedor';
        $g['winners'] = [$winner];
        $g['handResult'] = 'Fim do jogo: ' . $g['winner'] . ' venceu tudo.';
        $g['lastAction'] = ['player'=>-1,'type'=>'gameover','amount'=>0,'text'=>$g['handResult']];
        $g['log'][] = $g['handResult'];
        return $g;
    }

    private static function awardSingle(array $g, int $winner): array
    {
        $g['players'][$winner]['chips'] += $g['pot'];
        $g['winner'] = $g['players'][$winner]['name']; $g['winners'] = [$winner];
        $g['handResult'] = $g['winner'] . ' levou o pote.'; $g['log'][] = $g['handResult'];
        return self::finishHand($g);
    }

    private static function showdown(array $g): array
    {
        $best = null; $winners=[];
        foreach ($g['players'] as $i=>$p) {
            if (!empty($p['eliminated'])) { $g['players'][$i]['result']='Fora do jogo'; continue; }
            if ($p['folded']) { $g['players'][$i]['result']='Fold'; continue; }
            $score = HandEvaluator::evaluate(array_merge($p['cards'], $g['community']));
            $g['players'][$i]['result'] = $score[2];
            $cmp = $best === null ? 1 : HandEvaluator::compareScores($score, $best);
            if ($cmp > 0) { $best = $score; $winners = [$i]; }
            elseif ($cmp === 0) $winners[] = $i;
        }
        $share = intdiv($g['pot'], max(1, count($winners)));
        $remainder = $g['pot'] - ($share * max(1, count($winners)));
        foreach ($winners as $idx => $i) $g['players'][$i]['chips'] += $share + ($idx === 0 ? $remainder : 0);
        $g['winners'] = $winners; $g['winner'] = implode(', ', array_map(fn($i)=>$g['players'][$i]['name'], $winners));
        $g['stage']='showdown';
        $g['handResult'] = count($winners)>1 ? 'Pote dividido.' : $g['winner'] . ' venceu com ' . $g['players'][$winners[0]]['result'] . '.';
        $g['lastAction'] = ['player'=>-1,'type'=>'showdown','amount'=>0,'text'=>$g['handResult']];
        $g['log'][] = $g['handResult'];
        return self::finishHand($g);
    }

    private static function botDecision(array $g, int $i): array
    {
        $p = $g['players'][$i];
        $score = HandEvaluator::evaluate(array_merge($p['cards'], $g['community']));
        $rank = $score[0]; $toCall = max(0, $g['currentBet'] - $p['bet']);
        $highCards = array_map(fn($c)=>['J'=>11,'Q'=>12,'K'=>13,'A'=>14][$c['value']] ?? (int)$c['value'], $p['cards']);
        rsort($highCards); $strongPre = $g['stage']==='preflop' && ($rank>=1 || $highCards[0]>=13 || ($highCards[0]>=11 && $highCards[1]>=10));
        $r = rand(1,100);
        if ($rank >= 5 && $r <= 22) return ['action'=>'raise', 'raise'=>max(20, (int)$p['chips'] - $toCall)];
        if (($rank >= 3 || $strongPre) && $r <= 10) return ['action'=>'raise', 'raise'=>max(20, (int)$p['chips'] - $toCall)];
        if ($rank >= 3 || $strongPre) return ['action'=>$r<=35 ? 'raise' : ($toCall>0?'call':'check'), 'raise'=>rand(1,3)*20];
        if ($rank >= 1) return ['action'=>$r<=18 ? 'raise' : ($toCall>0?'call':'check'), 'raise'=>20];
        if ($toCall === 0) return ['action'=>'check','raise'=>0];
        $chance = $g['stage']==='preflop' ? 62 : 38;
        return ['action'=>$r <= $chance ? 'call' : 'fold', 'raise'=>0];
    }
}
