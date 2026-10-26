<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Events\TuttiRoomEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class TuttiWheelTest extends TestCase
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

    // ==================== HELPERS ====================

    private function crearSala(): array
    {
        $response = $this->postJson('/tutti/salas', [
            'categories' => ['nombre', 'animal', 'pais', 'fruta'],
            'total_rounds' => 5,
            'round_seconds' => 60,
        ]);
        $response->assertOk();

        return [
            'code' => $response->json('code'),
            'host_token' => $response->json('host_token'),
        ];
    }

    private function unirse(string $code, string $name): string
    {
        $response = $this->postJson("/tutti/salas/{$code}/unirse", ['name' => $name]);
        $response->assertOk();
        return $response->json('token');
    }

    private function girar(string $code, string $hostToken)
    {
        return $this->withHeaders([
            'X-Tutti-Token' => $hostToken,
        ])->postJson("/tutti/salas/{$code}/girar");
    }

    private function letrasExcluidas(): array
    {
        return str_split(config('tutti.room.excluded_letters', 'ÑKWXY'));
    }

    /**
     * Deja la sala en estado 'review' para poder sortear la ronda siguiente.
     */
    private function forzarReview(string $code, string $letter): void
    {
        $key = "tutti:room:{$code}";
        $room = Cache::store('file')->get($key);

        $room['status'] = 'review';
        $room['round']['review'] = [
            'round' => $room['round']['number'],
            'total_rounds' => $room['settings']['total_rounds'],
            'letter' => $letter,
            'categories' => [],
            'round_points' => [],
            'totals' => $room['totals'],
            'is_last_round' => false,
        ];

        Cache::store('file')->put($key, $room, 21600);
    }

    // ==================== TESTS ====================

    /**
     * El servidor sortea la letra, emite wheel.spun y round.started
     * con starts_at = server_now + duration_ms + 1000.
     */
    public function test_girar_sortea_letra_y_emite_wheel_spun(): void
    {
        Event::fake();

        $sala = $this->crearSala();
        $this->unirse($sala['code'], 'Ana');

        $response = $this->girar($sala['code'], $sala['host_token']);
        $response->assertOk();
        $response->assertJsonPath('ok', true);

        $letter = $response->json('letter');
        $spinId = $response->json('spin_id');
        $durationMs = $response->json('duration_ms');
        $serverNow = $response->json('server_now');
        $startsAt = $response->json('starts_at');

        $this->assertIsString($letter);
        $this->assertSame(1, mb_strlen($letter));
        $this->assertNotEmpty($spinId);
        $this->assertGreaterThan(0, $durationMs);
        $this->assertFalse($response->json('replayed'));

        // La letra sorteada nunca es una de las excluidas (Ñ, K, W, X, Y)
        $this->assertNotContains($letter, $this->letrasExcluidas());

        // starts_at = server_now + duration_ms + 1000
        $this->assertEquals($serverNow + $durationMs + 1000, $startsAt);

        // Evento wheel.spun con el payload pedido
        Event::assertDispatched(TuttiRoomEvent::class, function ($event) use ($letter, $spinId, $durationMs) {
            return $event->name === 'wheel.spun'
                && $event->payload['letter'] === $letter
                && $event->payload['spin_id'] === $spinId
                && $event->payload['duration_ms'] == $durationMs
                && array_key_exists('server_now', $event->payload);
        });

        // Evento round.started con la misma letra y ese starts_at
        Event::assertDispatched(TuttiRoomEvent::class, function ($event) use ($letter, $startsAt) {
            return $event->name === 'round.started'
                && $event->payload['letter'] === $letter
                && $event->payload['starts_at'] === $startsAt;
        });
    }

    /**
     * Dos giros seguidos (doble clic) no crean dos sorteos:
     * el segundo devuelve el MISMO spin_id y no emite eventos nuevos.
     */
    public function test_doble_clic_en_girar_no_duplica_sorteo(): void
    {
        Event::fake();

        $sala = $this->crearSala();
        $this->unirse($sala['code'], 'Ana');

        $first = $this->girar($sala['code'], $sala['host_token']);
        $first->assertOk();

        $second = $this->girar($sala['code'], $sala['host_token']);
        $second->assertOk();

        // Mismo sorteo
        $this->assertSame($first->json('spin_id'), $second->json('spin_id'));
        $this->assertSame($first->json('letter'), $second->json('letter'));
        $this->assertFalse($first->json('replayed'));
        $this->assertTrue($second->json('replayed'));

        // Solo un wheel.spun y un round.started en total
        Event::assertDispatchedTimes(TuttiRoomEvent::class, 2);

        $wheelSpins = 0;
        $roundStarted = 0;
        Event::assertDispatched(TuttiRoomEvent::class, function ($event) use (&$wheelSpins, &$roundStarted) {
            if ($event->name === 'wheel.spun') $wheelSpins++;
            if ($event->name === 'round.started') $roundStarted++;
            return true;
        });
        $this->assertSame(1, $wheelSpins);
        $this->assertSame(1, $roundStarted);
    }

    /**
     * Solo el host puede girar: sin token o con token de jugador -> 403.
     */
    public function test_solo_el_host_puede_girar(): void
    {
        Event::fake();

        $sala = $this->crearSala();
        $playerToken = $this->unirse($sala['code'], 'Ana');

        // Sin token de host
        $response = $this->postJson("/tutti/salas/{$sala['code']}/girar");
        $response->assertStatus(403);

        // Con token de jugador (no es el host)
        $response = $this->withHeaders([
            'X-Tutti-Token' => $playerToken,
        ])->postJson("/tutti/salas/{$sala['code']}/girar");
        $response->assertStatus(403);

        // No se emitió ningún wheel.spun
        Event::assertNotDispatched(TuttiRoomEvent::class, function ($event) {
            return $event->name === 'wheel.spun';
        });

        // El host sí puede
        $response = $this->girar($sala['code'], $sala['host_token']);
        $response->assertOk();
    }

    /**
     * Las letras sorteadas en rondas distintas no se repiten.
     */
    public function test_letras_no_se_repiten_entre_rondas(): void
    {
        $sala = $this->crearSala();
        $this->unirse($sala['code'], 'Ana');

        // Ronda 1
        $first = $this->girar($sala['code'], $sala['host_token']);
        $first->assertOk();
        $letter1 = $first->json('letter');

        // Cerrar la ronda para poder sortear la siguiente
        $this->forzarReview($sala['code'], $letter1);

        // Ronda 2
        $second = $this->girar($sala['code'], $sala['host_token']);
        $second->assertOk();
        $letter2 = $second->json('letter');

        $this->assertNotEquals($letter1, $letter2, 'La letra no debe repetirse entre rondas');
        $this->assertNotContains($letter2, $this->letrasExcluidas());

        // La ronda 2 es la número 2 y arranca su propio temporizador
        $this->assertEquals(2, $second->json('round'));
        $this->assertEquals(
            $second->json('server_now') + $second->json('duration_ms') + 1000,
            $second->json('starts_at')
        );
    }
}
