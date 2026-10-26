<?php

use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\TuttiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('tutti.lobby');
});

// Rutas del proyecto original (emisor/receptor)
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Rutas del juego "Tutti Frutti" (lobby, pizarra y cuaderno)
Route::prefix('tutti')->name('tutti.')->group(function () {
    // Lobby: crear sala / unirse
    Route::get('/', [TuttiController::class, 'lobby'])->name('lobby');
    Route::get('/host/{code}', [TuttiController::class, 'host'])->name('host');
    Route::get('/jugar/{code}', [TuttiController::class, 'player'])->name('jugar');
    Route::post('/salas', [TuttiController::class, 'crear'])->name('crear');
    Route::post('/salas/{code}/unirse', [TuttiController::class, 'unirse'])->name('unirse');
    Route::get('/salas/{code}/estado', [TuttiController::class, 'estado'])->name('estado');

    // Host
    Route::post('/salas/{code}/iniciar', [TuttiController::class, 'iniciar'])->name('iniciar');
    Route::post('/salas/{code}/girar', [TuttiController::class, 'girar'])->name('girar');
    Route::post('/salas/{code}/anular', [TuttiController::class, 'anular'])->name('anular');
    Route::post('/salas/{code}/siguiente', [TuttiController::class, 'siguiente'])->name('siguiente');
    Route::post('/salas/{code}/nueva', [TuttiController::class, 'nueva'])->name('nueva');

    // Jugador
    Route::post('/salas/{code}/respuestas', [TuttiController::class, 'respuestas'])->name('respuestas');
    Route::post('/salas/{code}/basta', [TuttiController::class, 'basta'])->name('basta');
    Route::post('/salas/{code}/cerrar-ronda', [TuttiController::class, 'cerrar'])->name('cerrar-ronda');
    Route::post('/salas/{code}/chat', [TuttiController::class, 'chat'])->name('chat');
});
