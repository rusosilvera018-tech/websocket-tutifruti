<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TuttiGame
{
    private string $store;

    private int $ttl;

    private array $config;

    public function __construct()
    {
        $this->store = config('tutti.store', 'file');
        $this->ttl = config('tutti.room.ttl_seconds', 21600);
        $this->config = [
            'categories' => config('tutti.categories', []),
            'defaults' => config('tutti.defaults', []),
            'rounds_allowed' => config('tutti.rounds_allowed', [3, 5, 7]),
            'seconds_allowed' => config('tutti.seconds_allowed', [60, 90, 120]),
            'player_emojis' => config('tutti.player_emojis', []),
            'player_colors' => config('tutti.player_colors', []),
            'room' => config('tutti.room', []),
            'timing' => config('tutti.timing', []),
            'chat' => config('tutti.chat', []),
        ];
    }

    // ==================== CACHE HELPERS ====================

    private function cache(): Repository
    {
        return Cache::store($this->store);
    }

    private function roomKey(string $code): string
    {
        return "tutti:room:{$code}";
    }

    private function lockKey(string $code): string
    {
        return "tutti:lock:{$code}";
    }

    private function getRoom(string $code): ?array
    {
        return $this->cache()->get($this->roomKey($code));
    }

    private function setRoom(string $code, array $room): void
    {
        $this->cache()->put($this->roomKey($code), $room, $this->ttl);
    }

    private function withLock(string $code, callable $callback): mixed
    {
        $lock = $this->cache()->lock($this->lockKey($code), 5);

        return $lock->block(3, $callback);
    }

    // ==================== GENERACIÓN DE CÓDIGOS ====================

    public function generateCode(): string
    {
        $charset = $this->config['room']['code_charset'] ?? 'BCDFGHJKLMNPRSTVZ';
        $length = $this->config['room']['code_length'] ?? 4;

        for ($attempts = 0; $attempts < 10; $attempts++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $charset[random_int(0, strlen($charset) - 1)];
            }
            if (! $this->getRoom($code)) {
                return $code;
            }
        }
        throw new \RuntimeException('No se pudo generar código único');
    }

    // ==================== CREAR SALA ====================

    public function createRoom(array $categories, int $totalRounds, int $roundSeconds): array
    {
        $code = $this->generateCode();
        $hostToken = Str::random(32);

        $room = [
            'code' => $code,
            'status' => 'lobby',
            'host_token' => $hostToken,
            'settings' => [
                'categories' => $categories,
                'total_rounds' => $totalRounds,
                'round_seconds' => $roundSeconds,
            ],
            'players' => [],
            'next_player_id' => 1,
            'letters_used' => [],
            'round' => null,
            'history' => [],
            'totals' => [],
            'chat' => [],
        ];

        $this->setRoom($code, $room);

        return $room;
    }

    // ==================== UNIRSE A SALA ====================

    public function joinRoom(string $code, string $name): array
    {
        return $this->withLock($code, function () use ($code, $name) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['status'] !== 'lobby') {
                throw new \Exception('La sala ya no acepta jugadores', 409);
            }
            if (count($room['players']) >= ($this->config['room']['max_players'] ?? 12)) {
                throw new \Exception('Sala llena', 409);
            }

            $name = trim($name);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 16) {
                throw new \Exception('Nombre debe tener 2-16 caracteres', 422);
            }
            if (preg_match('/[\r\n]/', $name)) {
                throw new \Exception('Nombre inválido', 422);
            }

            // Verificar nombre duplicado (case-insensitive)
            foreach ($room['players'] as $p) {
                if (strcasecmp($p['name'], $name) === 0) {
                    throw new \Exception('Ese nombre ya está en uso', 409);
                }
            }

            $playerId = $room['next_player_id']++;
            $token = Str::random(32);
            $idx = ($playerId - 1) % count($this->config['player_emojis']);

            $player = [
                'id' => $playerId,
                'token' => $token,
                'name' => $name,
                'emoji' => $this->config['player_emojis'][$idx],
                'color' => $this->config['player_colors'][$idx],
            ];

            $room['players'][$playerId] = $player;
            $room['totals'][$playerId] = 0;

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== OBTENER ESTADO ====================

    public function getState(string $code, ?string $token = null, bool $isHost = false): array
    {
        $room = $this->getRoom($code);
        if (! $room) {
            throw new \Exception('Sala inexistente', 404);
        }

        $me = null;
        if ($token) {
            foreach ($room['players'] as $p) {
                if ($p['token'] === $token) {
                    $me = $p;
                    break;
                }
            }
        } elseif ($isHost && $room['host_token']) {
            $me = ['id' => 'host', 'name' => 'Host', 'is_host' => true];
        }

        $serverNow = (int) (microtime(true) * 1000);

        $state = [
            'ok' => true,
            'code' => $room['code'],
            'status' => $room['status'],
            'server_now' => $serverNow,
            'settings' => [
                'categories' => array_map(fn ($id) => [
                    'id' => $id,
                    'label' => $this->config['categories'][$id]['label'] ?? $id,
                    'emoji' => $this->config['categories'][$id]['emoji'] ?? '📝',
                ], $room['settings']['categories']),
                'total_rounds' => $room['settings']['total_rounds'],
                'round_seconds' => $room['settings']['round_seconds'],
            ],
            'players' => array_map(function ($p) use ($room) {
                $filled = 0;
                if ($room['round'] && isset($room['round']['answers'][$p['id']])) {
                    $filled = count(array_filter($room['round']['answers'][$p['id']], fn ($v) => $v !== ''));
                }

                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'emoji' => $p['emoji'],
                    'color' => $p['color'],
                    'filled' => $filled,
                ];
            }, $room['players']),
            'round' => $room['round'] ? [
                'number' => $room['round']['number'],
                'letter' => $room['round']['letter'],
                'starts_at' => $room['round']['starts_at'] ?? 0,
                'ends_at' => $room['round']['ends_at'] ?? 0,
                'stopped_by' => $room['round']['stopped_by'] ?? null,
            ] : null,
            'review' => $room['round']['review'] ?? null,
            'ranking' => $room['status'] === 'finished' ? $this->buildRanking($room) : null,
            'chat' => array_values($room['chat'] ?? []),
            'me' => $me ? [
                'id' => $me['id'],
                'name' => $me['name'],
                'emoji' => $me['emoji'] ?? null,
                'color' => $me['color'] ?? null,
                'answers' => $room['round'] && $me['id'] !== 'host' && isset($room['round']['answers'][$me['id']])
                    ? $room['round']['answers'][$me['id']]
                    : null,
            ] : null,
            'is_host' => $isHost,
        ];

        return $state;
    }

    // ==================== INICIAR JUEGO ====================

    public function startGame(string $code, string $hostToken): array
    {
        return $this->withLock($code, function () use ($code, $hostToken) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['host_token'] !== $hostToken) {
                throw new \Exception('Token de host inválido', 403);
            }
            if ($room['status'] !== 'lobby') {
                throw new \Exception('El juego ya comenzó', 409);
            }
            if (count($room['players']) < $this->config['room']['min_players']) {
                throw new \Exception('Se necesitan al menos '.$this->config['room']['min_players'].' jugadores', 409);
            }

            $letter = $this->pickLetter($room);
            $now = (int) (microtime(true) * 1000);
            $animationMs = $this->config['timing']['animation_ms'] ?? 3500;

            $room['round'] = $this->newRound($room, $letter, 1, $now + $animationMs);
            $room['status'] = 'playing';
            $room['letters_used'][] = $letter;

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== RUEDA: GIRAR (SORTEO DE LETRA) ====================

    /**
     * Gira la rueda: el servidor sortea la letra y prepara la ronda.
     *
     * - Idempotente: si ya hay un sorteo vigente para la ronda actual,
     *   devuelve el MISMO sorteo (mismo spin_id) sin emitir eventos nuevos.
     * - La letra se elige con pickLetter() (misma lógica que startGame/nextRound).
     *
     * @return array{room: array, spin: array}
     */
    public function spinWheel(string $code, string $hostToken): array
    {
        return $this->withLock($code, function () use ($code, $hostToken) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['host_token'] !== $hostToken) {
                throw new \Exception('Token de host inválido', 403);
            }

            $status = $room['status'];

            // --- Idempotencia: ya hay un sorteo en curso, devolver el mismo ---
            if (in_array($status, ['playing', 'stopping']) && ! empty($room['round']['spin_id'])) {
                return [
                    'room' => $room,
                    'spin' => [
                        'letter' => $room['round']['letter'],
                        'spin_id' => $room['round']['spin_id'],
                        'duration_ms' => (int) ($room['round']['spin_duration_ms'] ?? $this->spinDurationMs()),
                        'server_now' => (int) (microtime(true) * 1000),
                        'starts_at' => $room['round']['starts_at'],
                        'ends_at' => $room['round']['ends_at'],
                        'round' => $room['round']['number'],
                        'replayed' => true,
                    ],
                ];
            }

            if (! in_array($status, ['lobby', 'review'])) {
                throw new \Exception('No se puede girar la rueda ahora', 409);
            }

            $number = 1;
            if ($status === 'review') {
                // Confirmar los puntos de la ronda anterior antes de abrir la nueva
                $room = $this->confirmRound($room);
                $number = $room['round']['number'] + 1;
            } else {
                if (count($room['players']) < $this->config['room']['min_players']) {
                    throw new \Exception('Se necesitan al menos '.$this->config['room']['min_players'].' jugadores', 409);
                }
            }

            // Sortear la letra (misma lógica que startGame/nextRound)
            $letter = $this->pickLetter($room);

            $durationMs = $this->spinDurationMs();
            $now = (int) (microtime(true) * 1000);
            $startsAt = $now + $durationMs + 1000;
            $spinId = (string) Str::uuid();

            $room['round'] = $this->newRound($room, $letter, $number, $startsAt);
            $room['round']['spin_id'] = $spinId;
            $room['round']['spin_duration_ms'] = $durationMs;
            $room['status'] = 'playing';
            $room['letters_used'][] = $letter;

            $this->setRoom($code, $room);

            return [
                'room' => $room,
                'spin' => [
                    'letter' => $letter,
                    'spin_id' => $spinId,
                    'duration_ms' => $durationMs,
                    'server_now' => $now,
                    'starts_at' => $startsAt,
                    'ends_at' => $room['round']['ends_at'],
                    'round' => $number,
                    'replayed' => false,
                ],
            ];
        });
    }

    private function pickLetter(array $room): string
    {
        $pool = str_split($this->config['room']['letter_pool'] ?? 'ABCDEFGHIJLMNOPRSTUV');
        $excluded = str_split($this->config['room']['excluded_letters'] ?? 'ÑKWXY');
        $available = array_diff($pool, $room['letters_used'], $excluded);
        if (empty($available)) {
            $room['letters_used'] = [];
            $available = array_diff($pool, $excluded);
            if (empty($available)) {
                $available = $pool;
            }
        }

        return $available[array_rand($available)];
    }

    /**
     * Construye la estructura de una ronda nueva.
     * Compartida por startGame(), nextRound() y spinWheel().
     */
    private function newRound(array $room, string $letter, int $number, int $startsAt): array
    {
        $roundSeconds = $room['settings']['round_seconds'];

        return [
            'number' => $number,
            'letter' => $letter,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt + ($roundSeconds * 1000),
            'stopped_by' => null,
            'answers' => [],
            'voids' => [],
            'review' => null,
        ];
    }

    /**
     * Confirma los puntos de la ronda revisada: los pasa a history/totales.
     */
    private function confirmRound(array $room): array
    {
        $review = $room['round']['review'] ?? null;
        if ($review) {
            $room['history'][] = [
                'number' => $room['round']['number'],
                'letter' => $room['round']['letter'],
                'points' => $review['round_points'],
            ];
            $room['totals'] = $review['totals'];
        }

        return $room;
    }

    /**
     * Duración del giro de la rueda (ms).
     */
    private function spinDurationMs(): int
    {
        return (int) ($this->config['timing']['wheel_spin_ms'] ?? 5000);
    }

    // ==================== GUARDAR RESPUESTAS (AUTOSAVE) ====================

    public function saveAnswers(string $code, string $token, array $answers): array
    {
        return $this->withLock($code, function () use ($code, $token, $answers) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            // Permitir 'playing' y 'stopping' (gracia tras BASTA/tiempo)
            if (! in_array($room['status'], ['playing', 'stopping'])) {
                throw new \Exception('No se puede responder ahora', 409);
            }

            $player = $this->findPlayerByToken($room, $token);
            if (! $player) {
                throw new \Exception('Jugador no encontrado', 403);
            }

            $now = (int) (microtime(true) * 1000);
            $startsAt = $room['round']['starts_at'] ?? 0;
            $endsAt = $room['round']['ends_at'] ?? 0;
            // Ventana de respuestas: durante la ronda y hasta answers_grace_ms después del cierre
            $grace = $this->config['timing']['answers_grace_ms'] ?? 2000;

            if ($now < $startsAt || $now > $endsAt + $grace) {
                throw new \Exception('Fuera de tiempo', 409);
            }

            // Limpiar y validar respuestas
            $cleanAnswers = [];
            foreach ($room['settings']['categories'] as $catId) {
                $text = trim($answers[$catId] ?? '');
                if (mb_strlen($text) > 30) {
                    $text = mb_substr($text, 0, 30);
                }
                $cleanAnswers[$catId] = $text;
            }

            $room['round']['answers'][$player['id']] = $cleanAnswers;
            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== BASTA ====================

    public function basta(string $code, string $token, array $answers): array
    {
        return $this->withLock($code, function () use ($code, $token, $answers) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['status'] !== 'playing') {
                throw new \Exception('No se puede gritar BASTA ahora', 409);
            }

            $player = $this->findPlayerByToken($room, $token);
            if (! $player) {
                throw new \Exception('Jugador no encontrado', 403);
            }

            if ($room['round']['stopped_by'] !== null) {
                throw new \Exception('Alguien ya gritó BASTA', 409);
            }

            // Validar que TODAS las categorías estén completas y válidas
            $letter = $room['round']['letter'];
            foreach ($room['settings']['categories'] as $catId) {
                $text = trim($answers[$catId] ?? '');
                if ($text === '') {
                    throw new \Exception("Falta completar: {$catId}", 422);
                }
                $norm = $this->normalize($text);
                if (mb_strlen($norm) < 2 || $norm[0] !== mb_strtolower($letter)) {
                    throw new \Exception("Respuesta inválida en {$catId}: debe empezar con {$letter}", 422);
                }
            }

            $now = (int) (microtime(true) * 1000);
            $grace = $this->config['timing']['grace_after_basta'] ?? 10000;
            $newEndsAt = min($room['round']['ends_at'], $now + $grace);

            $room['round']['answers'][$player['id']] = $answers;
            $room['round']['stopped_by'] = $player['id'];
            $room['round']['ends_at'] = $newEndsAt;
            $room['status'] = 'stopping';

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== CERRAR RONDA ====================

    public function closeRound(string $code): array
    {
        return $this->withLock($code, function () use ($code) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if (! in_array($room['status'], ['playing', 'stopping'])) {
                throw new \Exception('No hay ronda para cerrar', 409);
            }

            $now = (int) (microtime(true) * 1000);
            $endsAt = $room['round']['ends_at'] ?? 0;
            $tolerance = $this->config['timing']['close_tolerance_ms'] ?? 2500;

            if ($now < $endsAt + $tolerance) {
                throw new \Exception('Aún no terminó el tiempo', 409);
            }

            // Idempotente: si ya tiene review, devolverla
            if ($room['round']['review'] !== null) {
                return $room;
            }

            $review = $this->calculateRound($room);
            $room['round']['review'] = $review;
            $room['status'] = 'review';

            $this->setRoom($code, $room);

            return $room;
        });
    }

    protected function calculateRound(array $room): array
    {
        $letter = $room['round']['letter'];
        $categories = $room['settings']['categories'];
        $answers = $room['round']['answers'];
        $voids = $room['round']['voids'] ?? [];

        $rows = [];
        $roundPoints = [];
        $totals = $room['totals'] ?? [];

        foreach ($categories as $catId) {
            $catLabel = $this->config['categories'][$catId]['label'] ?? $catId;
            $catEmoji = $this->config['categories'][$catId]['emoji'] ?? '📝';

            // Recopilar respuestas válidas por jugador
            $validByPlayer = [];
            foreach ($answers as $pid => $playerAnswers) {
                $text = $playerAnswers[$catId] ?? '';
                if ($text === '') {
                    continue;
                }

                $voidKey = "{$pid}:{$catId}";
                if (in_array($voidKey, $voids)) {
                    continue;
                }

                $norm = $this->normalize($text);
                if (mb_strlen($norm) < 2 || $norm[0] !== mb_strtolower($letter)) {
                    continue;
                }

                $validByPlayer[$pid] = $norm;
            }

            // Detectar repeticiones
            $counts = array_count_values($validByPlayer);
            $entries = [];

            foreach ($room['players'] as $pid => $player) {
                $text = $answers[$pid][$catId] ?? '';
                $voidKey = "{$pid}:{$catId}";
                $voided = in_array($voidKey, $voids);

                if ($text === '' || $voided) {
                    $entries[] = [
                        'player_id' => $pid,
                        'text' => $text,
                        'valid' => false,
                        'voided' => $voided,
                        'shared' => false,
                        'solo' => false,
                        'points' => 0,
                    ];

                    continue;
                }

                $norm = $this->normalize($text);
                if (mb_strlen($norm) < 2 || $norm[0] !== mb_strtolower($letter)) {
                    $entries[] = [
                        'player_id' => $pid,
                        'text' => $text,
                        'valid' => false,
                        'voided' => false,
                        'shared' => false,
                        'solo' => false,
                        'points' => 0,
                    ];

                    continue;
                }

                $shared = $counts[$norm] > 1;
                // 10 pts si la respuesta es única, 5 si se repite con otro jugador
                $points = $shared ? 5 : 10;

                $entries[] = [
                    'player_id' => $pid,
                    'text' => $text,
                    'valid' => true,
                    'voided' => false,
                    'shared' => $shared,
                    'solo' => false,
                    'points' => $points,
                ];

                $roundPoints[$pid] = ($roundPoints[$pid] ?? 0) + $points;
            }

            $rows[] = [
                'category' => $catId,
                'label' => $catLabel,
                'emoji' => $catEmoji,
                'entries' => $entries,
            ];
        }

        // Sumar a totales (preview)
        foreach ($roundPoints as $pid => $pts) {
            $totals[$pid] = ($totals[$pid] ?? 0) + $pts;
        }

        return [
            'round' => $room['round']['number'],
            'total_rounds' => $room['settings']['total_rounds'],
            'letter' => $letter,
            'categories' => $rows,
            'round_points' => $roundPoints,
            'totals' => $totals,
            'is_last_round' => $room['round']['number'] >= $room['settings']['total_rounds'],
        ];
    }

    // ==================== ANULAR RESPUESTA ====================

    public function voidAnswer(string $code, string $hostToken, int $playerId, string $category, bool $void): array
    {
        return $this->withLock($code, function () use ($code, $hostToken, $playerId, $category, $void) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['host_token'] !== $hostToken) {
                throw new \Exception('Token de host inválido', 403);
            }
            if ($room['status'] !== 'review') {
                throw new \Exception('No se puede anular ahora', 409);
            }
            if (! isset($room['round']['review'])) {
                throw new \Exception('No hay review', 409);
            }

            $voidKey = "{$playerId}:{$category}";
            $voids = $room['round']['voids'] ?? [];

            if ($void) {
                if (! in_array($voidKey, $voids)) {
                    $voids[] = $voidKey;
                }
            } else {
                $voids = array_filter($voids, fn ($v) => $v !== $voidKey);
            }

            $room['round']['voids'] = array_values($voids);
            $review = $this->calculateRound($room);
            $room['round']['review'] = $review;

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== SIGUIENTE RONDA ====================

    public function nextRound(string $code, string $hostToken): array
    {
        return $this->withLock($code, function () use ($code, $hostToken) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['host_token'] !== $hostToken) {
                throw new \Exception('Token de host inválido', 403);
            }
            if ($room['status'] !== 'review') {
                throw new \Exception('No hay review para confirmar', 409);
            }
            if (! isset($room['round']['review'])) {
                throw new \Exception('Review faltante', 409);
            }

            // Confirmar puntos en history y totals
            $room = $this->confirmRound($room);

            if ($review['is_last_round']) {
                $room['status'] = 'finished';
                $room['round'] = null;
            } else {
                // Nueva ronda
                $letter = $this->pickLetter($room);
                $now = (int) (microtime(true) * 1000);
                $animationMs = $this->config['timing']['animation_ms'] ?? 3500;

                $room['round'] = $this->newRound($room, $letter, $room['round']['number'] + 1, $now + $animationMs);
                $room['status'] = 'playing';
                $room['letters_used'][] = $letter;
            }

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== NUEVA PARTIDA ====================

    public function newGame(string $code, string $hostToken): array
    {
        return $this->withLock($code, function () use ($code, $hostToken) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }
            if ($room['host_token'] !== $hostToken) {
                throw new \Exception('Token de host inválido', 403);
            }
            if ($room['status'] !== 'finished') {
                throw new \Exception('Solo se puede desde finished', 409);
            }

            $room['status'] = 'lobby';
            $room['round'] = null;
            $room['history'] = [];
            $room['letters_used'] = [];
            $room['totals'] = array_fill_keys(array_keys($room['players']), 0);

            $this->setRoom($code, $room);

            return $room;
        });
    }

    // ==================== CHAT DE SALA ====================

    /**
     * Envía un mensaje al chat de la sala.
     *
     * - Solo jugadores registrados (token de jugador válido).
     * - Límite de 1 mensaje por segundo por jugador (chat.min_interval_ms).
     * - Se eliminan etiquetas HTML en el servidor y el cliente las muestra escapadas.
     * - Solo se conservan los últimos chat.history_limit mensajes.
     *
     * @return array{player: array, text: string, time: string}
     */
    public function sendChatMessage(string $code, string $token, string $text): array
    {
        return $this->withLock($code, function () use ($code, $token, $text) {
            $room = $this->getRoom($code);
            if (! $room) {
                throw new \Exception('Sala inexistente', 404);
            }

            $player = $this->findPlayerByToken($room, $token);
            if (! $player) {
                throw new \Exception('Solo los jugadores registrados pueden escribir en el chat', 403);
            }

            // Límite de frecuencia: 1 mensaje por jugador por intervalo
            $minInterval = (int) ($this->config['chat']['min_interval_ms'] ?? 1000);
            $now = (int) (microtime(true) * 1000);
            $lastAt = $room['chat_rate'][$player['id']] ?? 0;
            if ($now - $lastAt < $minInterval) {
                throw new \Exception('Muy rápido: esperá un segundo entre mensajes', 429);
            }

            // Sanitizar: sin etiquetas HTML y sin exceder el máximo
            $clean = trim(strip_tags($text));
            $maxLength = (int) ($this->config['chat']['max_length'] ?? 200);
            if ($clean === '') {
                throw new \Exception('El mensaje no puede estar vacío', 422);
            }
            if (mb_strlen($clean) > $maxLength) {
                $clean = mb_substr($clean, 0, $maxLength);
            }

            $message = [
                'player' => [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'emoji' => $player['emoji'],
                    'color' => $player['color'],
                ],
                'text' => $clean,
                'time' => now()->format('H:i'),
                'ts' => $now,
            ];

            $room['chat'][] = $message;
            $historyLimit = (int) ($this->config['chat']['history_limit'] ?? 50);
            if (count($room['chat']) > $historyLimit) {
                $room['chat'] = array_slice($room['chat'], -$historyLimit);
            }
            $room['chat_rate'][$player['id']] = $now;

            $this->setRoom($code, $room);

            return $message;
        });
    }

    // ==================== HELPERS ====================

    private function findPlayerByToken(array $room, string $token): ?array
    {
        foreach ($room['players'] as $p) {
            if ($p['token'] === $token) {
                return $p;
            }
        }

        return null;
    }

    protected function normalize(string $text): string
    {
        $text = Str::ascii($text);           // quita tildes
        $text = mb_strtolower($text);        // minúsculas
        $text = preg_replace('/[^a-z\s]/', '', $text); // solo letras y espacios
        $text = preg_replace('/\s+/', ' ', $text);     // colapsar espacios

        return trim($text);
    }

    /**
     * Validar si una respuesta es válida según la letra de la ronda.
     * Réplica de la validación del frontend (JS).
     */
    public function isValid(string $text, string $letter): bool
    {
        $norm = $this->normalize($text);

        return mb_strlen($norm) >= 2 && $norm[0] === mb_strtolower($letter);
    }

    private function buildRanking(array $room): array
    {
        $ranking = [];
        foreach ($room['players'] as $pid => $p) {
            $ranking[] = [
                'player_id' => $pid,
                'name' => $p['name'],
                'emoji' => $p['emoji'],
                'color' => $p['color'],
                'total' => $room['totals'][$pid] ?? 0,
            ];
        }
        usort($ranking, fn ($a, $b) => $b['total'] <=> $a['total']);

        $rank = 1;
        $prevTotal = null;
        $skip = 0;
        foreach ($ranking as $i => $r) {
            if ($r['total'] !== $prevTotal) {
                $rank = $i + 1 - $skip;
                $prevTotal = $r['total'];
            } else {
                $skip++;
            }
            $ranking[$i]['rank'] = $rank;
        }

        $winnerIds = array_filter($ranking, fn ($r) => $r['rank'] === 1);

        return [
            'ranking' => $ranking,
            'winner_ids' => array_map(fn ($r) => $r['player_id'], $winnerIds),
        ];
    }

    public function getCategoryLabel(string $id): string
    {
        return $this->config['categories'][$id]['label'] ?? $id;
    }

    public function getCategoryEmoji(string $id): string
    {
        return $this->config['categories'][$id]['emoji'] ?? '📝';
    }
}
