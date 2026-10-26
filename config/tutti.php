<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tutti Frutti - Configuración del juego
    |--------------------------------------------------------------------------
    |
    | Configuración del store de caché para el estado de las salas.
    | Por defecto usa 'file' (archivo), pero se puede cambiar via .env
    | con TUTTI_STORE=database, redis, etc.
    |
    */
    'store' => env('TUTTI_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de sala
    |--------------------------------------------------------------------------
    */
    'room' => [
        'ttl_seconds' => 21600, // 6 horas
        'max_players' => 12,
        'min_players' => env('TUTTI_MIN_PLAYERS', 2),
        'code_length' => 4,
        'code_charset' => 'BCDFGHJKLMNPRSTVZ',
        'letter_pool' => 'ABCDEFGHIJLMNOPRSTUV',
        // Letras que nunca se sortean (se filtran siempre, aunque estén en el pool)
        'excluded_letters' => 'ÑKWXY',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categorías disponibles
    |--------------------------------------------------------------------------
    | El host elige entre 4 y 8. Default: nombre, animal, pais, fruta, color, objeto
    */
    'categories' => [
        'nombre' => ['label' => 'Nombre',       'emoji' => '🧑'],
        'animal' => ['label' => 'Animal',       'emoji' => '🐾'],
        'pais' => ['label' => 'País',         'emoji' => '🌎'],
        'fruta' => ['label' => 'Fruta o verdura', 'emoji' => '🍎'],
        'color' => ['label' => 'Color',        'emoji' => '🎨'],
        'objeto' => ['label' => 'Objeto',       'emoji' => '📦'],
        'profesion' => ['label' => 'Profesión',    'emoji' => '🛠️'],
        'comida' => ['label' => 'Comida',       'emoji' => '🍝'],
        'ciudad' => ['label' => 'Ciudad',       'emoji' => '🏙️'],
        'deporte' => ['label' => 'Deporte',      'emoji' => '⚽'],
    ],

    'defaults' => [
        'categories' => ['nombre', 'animal', 'pais', 'fruta', 'color', 'objeto'],
        'total_rounds' => 5,
        'round_seconds' => 90,
    ],

    'rounds_allowed' => [3, 5, 7],
    'seconds_allowed' => [60, 90, 120],

    /*
    |--------------------------------------------------------------------------
    | Emojis y colores de jugador (en orden, hasta 12)
    |--------------------------------------------------------------------------
    */
    'player_emojis' => [
        '🦊', '🐸', '🐙', '🦉', '🐯', '🐼',
        '🦄', '🐢', '🦖', '🐝', '🦋', '🐳',
    ],
    'player_colors' => [
        '#FF6FA5', '#4FD1A5', '#FFB020', '#7C8CFF',
        '#FF7A59', '#2DB7F5', '#C77DFF', '#8BD450',
        '#FF5C5C', '#F2C94C', '#36CFC9', '#5B8DEF',
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat de la sala
    |--------------------------------------------------------------------------
    | Solo jugadores registrados pueden escribir; se conservan los últimos
    | history_limit mensajes (el host también los ve, pero es de solo lectura).
    */
    'chat' => [
        'max_length' => 200,
        'history_limit' => 50,
        'min_interval_ms' => 1000, // 1 mensaje por jugador por segundo
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiempos (milisegundos)
    |--------------------------------------------------------------------------
    */
    'timing' => [
        'animation_ms' => 3500, // ruleta + 3-2-1
        'wheel_spin_ms' => env('TUTTI_WHEEL_SPIN_MS', 5000), // duración del giro de la rueda
        // Tras un BASTA la ronda se corta a los 2 s (antes de eso nadie puede cerrarla)
        'grace_after_basta' => 2000,
        // Ventana extra para aceptar respuestas después del cierre de la ronda
        'answers_grace_ms' => 2000,
        'close_tolerance_ms' => 2500,
        'random_delay_max' => 600,
    ],
];
