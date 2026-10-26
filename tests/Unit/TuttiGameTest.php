<?php

namespace Tests\Unit;

use App\Services\TuttiGame;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TuttiGameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();
        parent::tearDown();
    }

    // ============================================================
    // HELPER
    // ============================================================

    private function createTestRoom(array $categories, int $playerCount = 2): array
    {
        $room = [
            'code' => 'TEST01',
            'status' => 'playing',
            'host_token' => 'host_token_123',
            'settings' => [
                'categories' => $categories,
                'total_rounds' => 3,
                'round_seconds' => 60,
            ],
            'players' => [],
            'next_player_id' => 1,
            'letters_used' => [],
            'round' => null,
            'history' => [],
            'totals' => [],
        ];

        for ($i = 1; $i <= $playerCount; $i++) {
            $token = 'token_'.$i;
            $room['players'][$i] = [
                'id' => $i,
                'token' => 'token_'.$i,
                'name' => 'Player'.$i,
                'emoji' => '🦊',
                'color' => '#FF6FA5',
            ];
            $room['next_player_id']++;
            $room['totals'][$i] = 0;
        }

        return $room;
    }

    private function getTestGame(): object
    {
        return new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }

            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }

            public function runIsValid(string $text, string $letter): bool
            {
                return $this->isValid($text, $letter);
            }
        };
    }

    // ============================================================
    // TESTS DE NORMALIZACIÓN
    // ============================================================

    /** @test */
    public function test_normalize_removes_accents(): void
    {
        $game = new class extends TuttiGame
        {
            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }
        };
        $this->assertEquals('cafe', $game->runNormalize('café'));
        $this->assertEquals('nino', $game->runNormalize('niño'));
        $this->assertEquals('manana', $game->runNormalize('mañana'));
    }

    /** @test */
    public function test_normalize_lowercase(): void
    {
        $game = new class extends TuttiGame
        {
            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }
        };
        $this->assertEquals('hola', $game->runNormalize('HOLA'));
        $this->assertEquals('hola', $game->runNormalize('HoLa'));
    }

    /** @test */
    public function test_normalize_removes_non_letters(): void
    {
        $game = new class extends TuttiGame
        {
            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }
        };
        $this->assertEquals('hola mundo', $game->runNormalize('Hola, Mundo!'));
        $this->assertEquals('hola', $game->runNormalize('Hola123'));
        $this->assertEquals('hola', $game->runNormalize('Hola!@#$%'));
    }

    /** @test */
    public function test_normalize_collapses_spaces(): void
    {
        $game = new class extends TuttiGame
        {
            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }
        };
        $this->assertEquals('hola mundo', $game->runNormalize('hola    mundo'));
        $this->assertEquals('hola mundo', $game->runNormalize('  hola   mundo  '));
    }

    /** @test */
    public function test_normalize_trim(): void
    {
        $game = new class extends TuttiGame
        {
            public function runNormalize(string $text): string
            {
                return $this->normalize($text);
            }
        };
        $this->assertEquals('hola', $game->runNormalize('  hola  '));
    }

    // ============================================================
    // TESTS DE VALIDACIÓN DE RESPUESTAS (isValid)
    // ============================================================

    /** @test */
    public function test_is_valid_empty_returns_false(): void
    {
        $game = new class extends TuttiGame
        {
            public function runIsValid(string $text, string $letter): bool
            {
                return $this->isValid($text, $letter);
            }
        };
        $this->assertFalse($game->isValid('', 'a'));
        $this->assertFalse($game->isValid('a', 'a'));
    }

    /** @test */
    public function test_is_valid_wrong_letter_returns_false(): void
    {
        $game = new class extends TuttiGame
        {
            public function runIsValid(string $text, string $letter): bool
            {
                return $this->isValid($text, $letter);
            }
        };
        $this->assertFalse($game->isValid('banana', 'a'));
        $this->assertFalse($game->isValid('casa', 'b'));
    }

    /** @test */
    public function test_is_valid_correct_letter_returns_true(): void
    {
        $game = new class extends TuttiGame
        {
            public function runIsValid(string $text, string $letter): bool
            {
                return $this->isValid($text, $letter);
            }
        };
        $this->assertTrue($game->isValid('avion', 'a'));
        $this->assertTrue($game->isValid('  Avion  ', 'a'));
        $this->assertTrue($game->isValid('Águila', 'a'));
    }

    /** @test */
    public function test_is_valid_min_length(): void
    {
        $game = new class extends TuttiGame
        {
            public function runIsValid(string $text, string $letter): bool
            {
                return $this->isValid($text, $letter);
            }
        };
        $this->assertFalse($game->isValid('a', 'a'));
        $this->assertTrue($game->isValid('as', 'a'));
    }

    // ============================================================
    // TESTS DE CÁLCULO DE PUNTAJES (calculateRound)
    // ============================================================

    /** @test */
    public function test_calculate_round_scores_basic(): void
    {
        $game = $this->getTestGame();
        $room = $this->createTestRoom(['nombre', 'animal']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'P',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['nombre' => 'Pedro', 'animal' => 'Perro'],
                2 => ['nombre' => 'Pablo', 'animal' => 'Pato'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(20, $review['round_points'][1]);
        $this->assertEquals(20, $review['round_points'][2]);
    }

    /** @test */
    public function test_calculate_round_shared_answers_5_pts(): void
    {
        $game = $this->getTestGame();
        $room = $this->createTestRoom(['nombre']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'M',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['nombre' => 'Maria'],
                2 => ['nombre' => 'Maria'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(5, $review['round_points'][1]);
        $this->assertEquals(5, $review['round_points'][2]);
        $this->assertTrue($review['categories'][0]['entries'][0]['shared']);
        $this->assertTrue($review['categories'][0]['entries'][1]['shared']);
    }

    /** @test */
    public function test_calculate_round_unique_answer_10_pts(): void
    {
        $game = $this->getTestGame();
        $room = $this->createTestRoom(['animal']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'G',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['animal' => 'Gato'],
                2 => ['animal' => 'Perro'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        // Única respuesta válida = 10 pts (sin bonus de "solo")
        $this->assertEquals(10, $review['round_points'][1] ?? 0);
        $this->assertEquals(0, $review['round_points'][2] ?? 0);
        $this->assertFalse($review['categories'][0]['entries'][0]['shared']);
        $this->assertFalse($review['categories'][0]['entries'][0]['solo']);
    }

    /** @test */
    public function test_calculate_round_single_player_answer_10_pts(): void
    {
        $game = $this->getTestGame();
        $room = $this->createTestRoom(['fruta']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'M',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['fruta' => 'Manzana'],
                2 => ['fruta' => ''],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(10, $review['round_points'][1]);
        $this->assertTrue($review['categories'][0]['entries'][0]['valid']);
        $this->assertFalse($review['categories'][0]['entries'][0]['solo']);
    }

    /** @test */
    public function test_calculate_round_invalid_empty_zero_pts(): void
    {
        $game = $this->getTestGame();

        $room = $this->createTestRoom(['color']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'R',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['color' => ''],
                2 => ['color' => 'Azul'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(0, $review['round_points'][1] ?? 0);
        $this->assertEquals(0, $review['round_points'][2] ?? 0);
    }

    /** @test */
    public function test_calculate_round_void_answer_zero_pts(): void
    {
        $game = $this->getTestGame();

        $room = $this->createTestRoom(['ciudad']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'B',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['ciudad' => 'Buenos Aires'],
                2 => ['ciudad' => 'Barcelona'],
            ],
            'voids' => ['1:ciudad'],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        // Player 1 voided = 0 pts; player 2 = única respuesta válida = 10 pts
        $this->assertEquals(0, $review['round_points'][1] ?? 0);
        $this->assertEquals(10, $review['round_points'][2] ?? 0);
        $this->assertTrue($review['categories'][0]['entries'][0]['voided']);
        $this->assertFalse($review['categories'][0]['entries'][1]['solo']);
    }

    /** @test */
    public function test_calculate_round_accent_insensitive(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['pais']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'E',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['pais' => 'España'],
                2 => ['pais' => 'Egipto'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(10, $review['round_points'][1]);
        $this->assertEquals(10, $review['round_points'][2]);
    }

    /** @test */
    public function test_calculate_round_case_insensitive(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['profesion']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'M',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['profesion' => 'MEDICO'],
                2 => ['profesion' => 'medico'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(5, $review['round_points'][1]);
        $this->assertEquals(5, $review['round_points'][2]);
        $this->assertTrue($review['categories'][0]['entries'][0]['shared']);
    }

    /** @test */
    public function test_calculate_round_three_players_same_answer(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['color'], 3);
        $room['round'] = [
            'number' => 1,
            'letter' => 'R',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['color' => 'Rojo'],
                2 => ['color' => 'Rojo'],
                3 => ['color' => 'Rojo'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(5, $review['round_points'][1]);
        $this->assertEquals(5, $review['round_points'][2]);
        $this->assertEquals(5, $review['round_points'][3]);
        foreach ($review['categories'][0]['entries'] as $entry) {
            $this->assertTrue($entry['shared']);
        }
    }

    /** @test */
    public function test_calculate_round_wrong_letter_zero_pts(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['pais']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'A',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['pais' => 'Argentina'],
                2 => ['pais' => 'Brasil'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(10, $review['round_points'][1]);
        $this->assertEquals(0, $review['round_points'][2] ?? 0);
        $this->assertFalse($review['categories'][0]['entries'][1]['valid']);
    }

    /** @test */
    public function test_calculate_round_normalizes_accents_case_and_spaces(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['color'], 3);
        $room['round'] = [
            'number' => 1,
            'letter' => 'M',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['color' => '  Morado '],   // espacios de más
                2 => ['color' => 'MORADO'],      // mayúsculas
                3 => ['color' => 'Mòrado'],      // acento
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        // Las tres variantes normalizan a "morado" => se repiten entre las 3
        $this->assertEquals(5, $review['round_points'][1]);
        $this->assertEquals(5, $review['round_points'][2]);
        $this->assertEquals(5, $review['round_points'][3]);
        foreach ($review['categories'][0]['entries'] as $entry) {
            $this->assertTrue($entry['shared']);
        }
    }

    /** @test */
    public function test_calculate_round_totals_accumulate(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['nombre']);
        $room['totals'] = [1 => 10, 2 => 5];
        $room['round'] = [
            'number' => 2,
            'letter' => 'P',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [
                1 => ['nombre' => 'Pedro'],
                2 => ['nombre' => 'Pablo'],
            ],
            'voids' => [],
            'review' => null,
        ];

        $review = $game->runCalculateRound($room);

        $this->assertEquals(20, $review['totals'][1]);
        $this->assertEquals(15, $review['totals'][2]);
    }

    /** @test */
    public function test_calculate_round_idempotent(): void
    {
        $game = new class extends TuttiGame
        {
            public function runCalculateRound(array $room): array
            {
                return $this->calculateRound($room);
            }
        };

        $room = $this->createTestRoom(['animal']);
        $room['round'] = [
            'number' => 1,
            'letter' => 'C',
            'starts_at' => 0,
            'ends_at' => 9999999999999,
            'stopped_by' => null,
            'answers' => [],
            'voids' => [],
            'review' => null,
        ];

        $review1 = $game->runCalculateRound($room);
        $review2 = $game->runCalculateRound($room);

        $this->assertEquals($review1, $review2);
    }
}
