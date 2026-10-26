<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TuttiApiTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::store('file')->flush();
    }

    public function test_full_flow_create_join_start(): void
    {
        // 1. Create room
        $response = $this->postJson('/tutti/salas', [
            'categories' => ['nombre', 'animal', 'pais', 'fruta', 'color', 'objeto'],
            'total_rounds' => 3,
            'round_seconds' => 60,
        ]);

        $response->assertOk();
        $code = $response->json('code');
        $hostToken = $response->json('host_token');

        // 2. Join as Ana
        $response = $this->postJson("/tutti/salas/{$code}/unirse", [
            'name' => 'Ana',
        ]);
        $response->assertOk();
        $playerToken = $response->json('token');
        $this->assertNotEmpty($playerToken);

        // 3. Join as Carlos
        $response = $this->postJson("/tutti/salas/{$code}/unirse", [
            'name' => 'Carlos',
        ]);
        $response->assertOk();
        $playerToken2 = $response->json('token');
        $this->assertNotEmpty($playerToken2);

        // 3. Check state from Ana's perspective - should have 2 players
        $response = $this->withHeaders([
            'X-Tutti-Token' => $playerToken,
        ])->getJson("/tutti/salas/{$code}/estado");
        
        $response->assertOk();
        $this->assertCount(2, $response->json('players'));
        $this->assertEquals('Ana', $response->json('players.1.name'));
        $this->assertEquals('Carlos', $response->json('players.2.name'));

        // 4. Host starts the game
        $response = $this->withHeaders([
            'X-Tutti-Token' => $hostToken,
        ])->postJson("/tutti/salas/{$code}/iniciar");
        
        $response->assertOk();
        $this->assertTrue($response->json('ok'));

        // 5. Check state after starting - should be playing
        $response = $this->withHeaders([
            'X-Tutti-Token' => $playerToken,
        ])->getJson("/tutti/salas/{$code}/estado");
        
        $response->assertOk();
        $this->assertEquals('playing', $response->json('status'));
        $this->assertNotNull($response->json('round.letter'));
    }
}