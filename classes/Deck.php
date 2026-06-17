<?php
class Deck
{
    private array $cards = [];

    public function __construct()
    {
        $suits = ['♠', '♥', '♦', '♣'];
        $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
        foreach ($suits as $suit) {
            foreach ($values as $value) {
                $this->cards[] = ['value' => $value, 'suit' => $suit];
            }
        }
        shuffle($this->cards);
    }

    public function draw(): array
    {
        return array_pop($this->cards);
    }

    public function toArray(): array
    {
        return $this->cards;
    }

    public static function fromArray(array $cards): self
    {
        $deck = new self();
        $deck->cards = $cards;
        return $deck;
    }
}
