<?php

namespace App\Http\Controllers;

use App\Events\TuttiRoomEvent;
use App\Services\TuttiGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TuttiController extends Controller
{
    public function __construct(
        private TuttiGame $game
    ) {}

    public function lobby(): View
    {
        return view('tutti.lobby');
    }

    public function host(string $code): View
    {
        return view('tutti.host', [
            'code' => strtoupper($code),
            'minPlayers' => config('tutti.room.min_players', 2),
        ]);
    }

    public function player(string $code): View
    {
        return view('tutti.player', ['code' => strtoupper($code)]);
    }

    public function crear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:4', 'max:8'],
            'categories.*' => ['string', 'in:nombre,animal,pais,fruta,color,objeto,profesion,comida,ciudad,deporte'],
            'total_rounds' => ['required', 'integer', 'in:3,5,7'],
            'round_seconds' => ['required', 'integer', 'in:60,90,120'],
        ]);

        try {
            $room = $this->game->createRoom($data['categories'], $data['total_rounds'], $data['round_seconds']);

            return response()->json([
                'ok' => true,
                'code' => $room['code'],
                'host_token' => $room['host_token'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function unirse(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:16'],
        ]);

        try {
            $room = $this->game->joinRoom($code, $data['name']);
            $player = null;
            foreach ($room['players'] as $p) {
                if ($p['name'] === trim($data['name'])) {
                    $player = $p;
                    break;
                }
            }

            return response()->json([
                'ok' => true,
                'token' => $player['token'] ?? null,
                'player' => $player ? [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'emoji' => $player['emoji'],
                    'color' => $player['color'],
                ] : null,
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;

            return response()->json(['ok' => false, 'message' => $e->getMessage()], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 400);
        }
    }

    public function estado(string $code, Request $request): JsonResponse
    {
        $token = $request->header('X-Tutti-Token');
        $isHost = $request->header('X-Tutti-Role') === 'host';

        try {
            $state = $this->game->getState($code, $token, $isHost);

            return response()->json($state);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;

            return response()->json(['ok' => false, 'message' => $e->getMessage()], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 400);
        }
    }
    // ==================== HOST: iniciar ====================

    public function iniciar(Request $request, string $code): JsonResponse
    {
        $hostToken = $request->header('X-Tutti-Token');
        if (! $hostToken) {
            return response()->json(['ok' => false, 'message' => 'Token de host requerido'], 403);
        }

        try {
            $room = $this->game->startGame($code, $hostToken);

            // Broadcast del evento round.started para que los jugadores vean la ronda
            $round = $room['round'];
            $categories = array_map(fn ($id) => [
                'id' => $id,
                'label' => $this->game->getCategoryLabel($id),
                'emoji' => $this->game->getCategoryEmoji($id),
            ], $room['settings']['categories']);

            $this->broadcast($code, 'round.started', [
                'round' => $round['number'],
                'letter' => $round['letter'],
                'starts_at' => $round['starts_at'],
                'ends_at' => $round['ends_at'],
                'server_now' => (int) (microtime(true) * 1000),
                'categories' => $categories,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ==================== HOST: girar la rueda ====================

    public function girar(Request $request, string $code): JsonResponse
    {
        $hostToken = $request->header('X-Tutti-Token');
        if (! $hostToken) {
            return response()->json(['ok' => false, 'message' => 'Token de host requerido'], 403);
        }

        try {
            $result = $this->game->spinWheel($code, $hostToken);
            $room = $result['room'];
            $spin = $result['spin'];

            // Solo el sorteo real emite eventos (el reenvío es idempotente y silencioso)
            if (! $spin['replayed']) {
                // 1) La rueda giró: el cliente anima hasta esta letra
                $this->broadcast($code, 'wheel.spun', [
                    'letter' => $spin['letter'],
                    'spin_id' => $spin['spin_id'],
                    'duration_ms' => $spin['duration_ms'],
                    'server_now' => $spin['server_now'],
                ]);

                // 2) La ronda arranca cuando termina la animación
                $round = $room['round'];
                $categories = array_map(fn ($id) => [
                    'id' => $id,
                    'label' => $this->game->getCategoryLabel($id),
                    'emoji' => $this->game->getCategoryEmoji($id),
                ], $room['settings']['categories']);

                $this->broadcast($code, 'round.started', [
                    'round' => $round['number'],
                    'letter' => $round['letter'],
                    'starts_at' => $round['starts_at'],
                    'ends_at' => $round['ends_at'],
                    'server_now' => $spin['server_now'],
                    'categories' => $categories,
                ]);
            }

            return response()->json(['ok' => true] + $spin);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ==================== JUGADOR ====================

    public function respuestas(Request $request, string $code): JsonResponse
    {
        $token = $request->header('X-Tutti-Token');
        if (! $token) {
            return response()->json(['ok' => false, 'message' => 'Token requerido'], 403);
        }

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        try {
            $this->game->saveAnswers($code, $token, $data['answers']);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function basta(Request $request, string $code): JsonResponse
    {
        $token = $request->header('X-Tutti-Token');
        if (! $token) {
            return response()->json(['ok' => false, 'message' => 'Token requerido'], 403);
        }

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        try {
            $room = $this->game->basta($code, $token, $data['answers']);
            $player = $this->findPlayerByToken($room, $token);

            // Evento round.ended con razón "basta" y quién lo gritó
            $this->broadcast($code, 'round.ended', [
                'reason' => 'basta',
                'by' => [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'emoji' => $player['emoji'],
                ],
                'ends_at' => $room['round']['ends_at'],
                'server_now' => (int) (microtime(true) * 1000),
            ]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ==================== CHAT DE SALA ====================

    public function chat(Request $request, string $code): JsonResponse
    {
        $token = $request->header('X-Tutti-Token');
        if (! $token) {
            return response()->json(['ok' => false, 'message' => 'Token requerido'], 403);
        }

        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
        ]);

        try {
            $message = $this->game->sendChatMessage($code, $token, $data['text']);

            $this->broadcast($code, 'chat.message', [
                'player' => $message['player'],
                'text' => $message['text'],
                'time' => $message['time'],
            ]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function anular(Request $request, string $code): JsonResponse
    {
        $hostToken = $request->header('X-Tutti-Token');
        if (! $hostToken) {
            return response()->json(['ok' => false, 'message' => 'Token de host requerido'], 403);
        }

        $data = $request->validate([
            'player_id' => ['required', 'integer'],
            'category' => ['required', 'string'],
            'void' => ['required', 'boolean'],
        ]);

        try {
            $this->game->voidAnswer($code, $hostToken, $data['player_id'], $data['category'], $data['void']);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function siguiente(Request $request, string $code): JsonResponse
    {
        $hostToken = $request->header('X-Tutti-Token');
        if (! $hostToken) {
            return response()->json(['ok' => false, 'message' => 'Token de host requerido'], 403);
        }

        try {
            $room = $this->game->nextRound($code, $hostToken);

            if ($room['status'] === 'finished') {
                $ranking = $this->buildRanking($room);
                $this->broadcast($code, 'game.finished', [
                    'ranking' => $ranking['ranking'],
                    'winner_ids' => $ranking['winner_ids'],
                ]);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function nueva(Request $request, string $code): JsonResponse
    {
        $hostToken = $request->header('X-Tutti-Token');
        if (! $hostToken) {
            return response()->json(['ok' => false, 'message' => 'Token de host requerido'], 403);
        }

        try {
            $this->game->newGame($code, $hostToken);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function cerrar(Request $request, string $code): JsonResponse
    {
        try {
            $room = $this->game->closeRound($code);

            // Solo se emite round.ended si nadie lo gritó antes (el BASTA ya lo emitió)
            if (empty($room['round']['stopped_by'])) {
                $this->broadcast($code, 'round.ended', [
                    'reason' => 'time',
                    'by' => null,
                    'ends_at' => $room['round']['ends_at'],
                    'server_now' => (int) (microtime(true) * 1000),
                ]);
            }

            if ($room['round']['review']) {
                $review = $room['round']['review'];
                $this->broadcast($code, 'round.review', [
                    'round' => $review['round'],
                    'total_rounds' => $review['total_rounds'],
                    'letter' => $review['letter'],
                    'categories' => $this->mapCategoriesForBroadcast($review['categories']),
                    'rows' => $review['categories'],
                    'round_points' => $review['round_points'],
                    'totals' => $review['totals'],
                    'is_last_round' => $review['is_last_round'],
                ]);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
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

    private function mapCategoriesForBroadcast(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'category' => $row['category'],
                'label' => $row['label'],
                'emoji' => $row['emoji'],
                'entries' => $row['entries'],
            ];
        }, $rows);
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

    private function broadcast(string $code, string $name, array $payload): void
    {
        broadcast(new TuttiRoomEvent($code, $name, $payload));
    }

    private function errorResponse(\Exception $e): JsonResponse
    {
        $statusCode = $e->getCode() ?: 400;

        return response()->json(['ok' => false, 'message' => $e->getMessage()], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 400);
    }
}
