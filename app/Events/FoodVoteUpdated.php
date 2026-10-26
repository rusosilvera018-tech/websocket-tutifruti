<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FoodVoteUpdated implements ShouldBroadcastNow
{
    /**
     * @var array<string, int> Votos por opción (pizza, hamburguesa, tacos, sushi)
     */
    public array $votes;

    /**
     * @var string Estado del evento: 'voto' o 'ganador'
     */
    public string $status;

    /**
     * @var string|null Nombre de la opción ganadora (solo cuando status === 'ganador')
     */
    public ?string $winner = null;

    /**
     * @param array<string, int> $votes
     * @param string $status Valores permitidos: 'voto', 'ganador'
     * @param string|null $winner
     */
    public function __construct(array $votes, string $status, ?string $winner = null)
    {
        $this->votes = $votes;
        $this->status = $status;
        $this->winner = $winner;
    }

    public function broadcastOn(): array
    {
        return [new Channel('menu-channel')];
    }

    public function broadcastAs(): string
    {
        return 'FoodVoteUpdated';
    }

    /**
     * @return array{votes: array<string, int>, status: string, winner: string|null, total: int}
     */
    public function broadcastWith(): array
    {
        return [
            'votes' => $this->votes,
            'status' => $this->status,
            'winner' => $this->winner,
            'total' => array_sum($this->votes),
        ];
    }
}