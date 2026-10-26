<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RemoteStyleUpdate implements ShouldBroadcast
{
    // Las cajas públicas donde viaja la información hacia el navegador
    public string $newText;
    public string $newStyle;

    // Recibe los datos cuando presionas el botón y los guarda aquí adentro
    public function __construct(string $newText, string $newStyle)
    {
        $this->newText = $newText;
        $this->newStyle = $newStyle;
    }

    // Define el canal PÚBLICO llamado 'style-channel'
    public function broadcastOn(): array
    {
        return [new Channel('style-channel')];
    }

    // Le da un alias de identificación para el frontend
    public function broadcastAs(): string
    {
        return 'RemoteStyleUpdate';
    }
}
