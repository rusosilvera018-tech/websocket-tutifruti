<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TuttiChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('file')->flush();
    }

    /** @return array{code: string, playerToken: string, hostToken: string} */
    private function createRoomWithPlayer(): array
    {
        $response = $this->postJson('/tutti/salas', [
            'categories' => ['nombre', 'animal', 'pais', 'fruta', 'color', 'objeto'],
            'total_rounds' => 3,
            'round_seconds' => 60,
        ]);
        $response->assertOk();
        $code = $response->json('code');
        $hostToken = $response->json('host_token');

        $join = $this->postJson("/tutti/salas/{$code}/unirse", ['name' => 'Ana']);
        $join->assertOk();

        return [
            'code' => $code,
            'playerToken' => $join->json('token'),
            'hostToken' => $hostToken,
        ];
    }

    public function test_player_can_send_chat_message(): void
    {
        ['code' => $code, 'playerToken' => $token] = $this->createRoomWithPlayer();

        $response = $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => '¡Hola a todos!']);

        $response->assertOk();
        $this->assertTrue($response->json('ok'));

        // El mensaje queda en el historial (visibles en /estado)
        $estado = $this->withHeaders(['X-Tutti-Token' => $token])
            ->getJson("/tutti/salas/{$code}/estado");
        $estado->assertOk();
        $this->assertCount(1, $estado->json('chat'));
        $this->assertEquals('¡Hola a todos!', $estado->json('chat.0.text'));
        $this->assertEquals('Ana', $estado->json('chat.0.player.name'));
        $this->assertNotEmpty($estado->json('chat.0.time'));
    }

    public function test_chat_rejects_non_registered_token(): void
    {
        ['code' => $code] = $this->createRoomWithPlayer();

        $response = $this->withHeaders(['X-Tutti-Token' => 'token-falso-123'])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => 'Hola']);

        $response->assertStatus(403);
    }

    public function test_chat_rejects_host_token(): void
    {
        ['code' => $code, 'hostToken' => $hostToken] = $this->createRoomWithPlayer();

        $response = $this->withHeaders(['X-Tutti-Token' => $hostToken])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => 'Hola desde la pizarra']);

        $response->assertStatus(403);
    }

    public function test_chat_rejects_empty_and_oversized_messages(): void
    {
        ['code' => $code, 'playerToken' => $token] = $this->createRoomWithPlayer();

        $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => ''])
            ->assertStatus(422);

        $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => str_repeat('a', 201)])
            ->assertStatus(422);
    }

    public function test_chat_strips_html_tags(): void
    {
        ['code' => $code, 'playerToken' => $token] = $this->createRoomWithPlayer();

        $response = $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => '<b>Hola</b><script>alert(1)</script>']);

        $response->assertOk();

        $estado = $this->withHeaders(['X-Tutti-Token' => $token])
            ->getJson("/tutti/salas/{$code}/estado");
        $text = $estado->json('chat.0.text');
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringContainsString('Hola', $text);
    }

    public function test_chat_limits_one_message_per_second(): void
    {
        ['code' => $code, 'playerToken' => $token] = $this->createRoomWithPlayer();

        $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => 'Primer mensaje'])
            ->assertOk();

        // Segundo mensaje inmediato: 429
        $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => 'Segundo mensaje'])
            ->assertStatus(429);
    }

    public function test_chat_keeps_only_last_50_messages(): void
    {
        ['code' => $code, 'playerToken' => $token] = $this->createRoomWithPlayer();

        // Prefill del historial directamente en caché (evita el rate limit de 1 msg/s)
        $room = Cache::store('file')->get("tutti:room:{$code}");
        $room['chat'] = array_map(fn ($i) => [
            'player' => ['id' => 1, 'name' => 'Ana', 'emoji' => '🦊', 'color' => '#FF6FA5'],
            'text' => "Mensaje {$i}",
            'time' => '12:00',
            'ts' => $i,
        ], range(1, 55));
        Cache::store('file')->put("tutti:room:{$code}", $room, 3600);

        $this->withHeaders(['X-Tutti-Token' => $token])
            ->postJson("/tutti/salas/{$code}/chat", ['text' => 'El último'])
            ->assertOk();

        $estado = $this->withHeaders(['X-Tutti-Token' => $token])
            ->getJson("/tutti/salas/{$code}/estado");
        $chat = $estado->json('chat');
        $this->assertCount(50, $chat);
        $this->assertEquals('El último', end($chat)['text']);
        // 55 + 1 = 56 mensajes → se conservan los últimos 50 (Mensaje 7 … El último)
        $this->assertEquals('Mensaje 7', $chat[0]['text']);
    }
}
