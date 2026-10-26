<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TuttiRoomEvent implements ShouldBroadcastNow
{
    public string $code;
    public string $name;
    /** @var array */
    public array $payload;

    public function __construct(string $code, string $name, array $payload)
    {
        $this->code = $code;
        $this->name = $name;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [new Channel("tutti-{$this->code}")];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}