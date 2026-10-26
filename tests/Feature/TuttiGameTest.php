<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TuttiGame;

class TuttiGameTest extends TestCase
{
    protected TuttiGame $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->game = new TuttiGame();
        // Clear cache
        \Illuminate\Support\Facades\Cache::store('file')->flush();
    }

    public function test_can_create_room_and_join_player_with_min_players_1(): void
    {
        // Create room with min_players = 1 (from .env)
        $room = $this->game->createRoom(
            ['nombre', 'animal', 'pais', 'fruta', 'color', 'objeto'],
            3,
            60
        );

        $this->assertNotEmpty($room['code']);
        $this->assertNotEmpty($room['host_token']);
        $this->assertEquals('lobby', $room['status']);

        // Join 1 player
        $room = $this->game->joinRoom($room['code'], 'Ana');
        $this->assertCount(1, $room['players']);
        $this->assertEquals('Ana', $room['players'][1]['name']);
        $this->assertNotEmpty($room['players'][1]['token']);

        // Get state with player token
        $token = $room['players'][1]['token'];
        $state = $this->game->getState($room['code'], $token, false);
        
        $this->assertTrue($state['ok']);
        $this->assertCount(1, $state['players']);
        $this->assertEquals('Ana', $state['players'][1]['name']);
        
        // Verify min_players is 1 from config
        $this->assertEquals(1, config('tutti.room.min_players'));
        
        // Start game with 1 player (should work with min_players=1)
        $room = $this->game->startGame($room['code'], $room['host_token']);
        $this->assertEquals('playing', $room['status']);
    }
}