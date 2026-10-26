<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutti Frutti - Pizarra (Host)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.12.4/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Gochi+Hand&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-chalkboard font-nunito text-chalk flex flex-col" data-code="{{ $code }}">
    <div id="app" class="flex-1 flex flex-col p-4 md:p-6">
        <!-- Header con código y QR -->
        <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div class="text-center md:text-left">
                <h1 class="font-gochi text-3xl md:text-4xl text-chalk-yellow mb-1">PIZARRA</h1>
                <div class="flex items-center justify-center md:justify-start gap-3">
                    <span class="font-gochi text-4xl md:text-5xl font-bold text-chalk-yellow tracking-widest" id="roomCode">{{ $code }}</span>
                    <button id="btnCopyUrl" class="bg-green-700 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition" title="Copiar URL para celulares">
                        📋 Copiar URL
                    </button>
                    <button id="chatToggle" type="button" aria-expanded="false" aria-controls="chatPanel"
                            aria-label="Abrir chat de la sala"
                            class="relative w-9 h-9 rounded-full bg-green-900/60 border border-green-600 text-xl
                                   flex items-center justify-center hover:bg-green-900/80 transition
                                   focus:outline-none focus:ring-2 focus:ring-chalk-yellow">
                        💬
                        <span id="chatBadge" class="hidden absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1
                                       rounded-full bg-chalk-pink text-green-900 text-[10px] font-bold
                                       flex items-center justify-center leading-none">0</span>
                    </button>
                </div>
                <p class="text-chalk/60 text-sm mt-1">Compartí este código o el QR para que se unan</p>
            </div>
            <div id="qrContainer" class="w-32 h-32 md:w-40 md:h-40 bg-white rounded-lg p-2 flex-shrink-0"></div>
        </header>

        <!-- Estado / Ruleta -->
        <main class="flex-1 flex flex-col items-center justify-center gap-6">
            <!-- LOBBY -->
            <section id="lobbyView" class="hidden w-full max-w-4xl">
                <div class="text-center mb-6">
                    <h2 class="font-gochi text-2xl text-chalk-yellow">🎮 Esperando jugadores...</h2>
                    <p class="text-chalk/60">Mínimo {{ $minPlayers }} jugadores para empezar</p>
                </div>
                <div id="lobbyPlayers" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl mx-auto">
                    <!-- Players renderizados por JS -->
                </div>
            </section>

            <!-- RUEDA ALEATORIA (SVG) - visible en lobby, playing, stopping y review -->
            <section id="wheelView" class="hidden w-full max-w-2xl flex flex-col items-center gap-4">
                <div class="relative w-full max-w-lg" id="wheelContainer">
                    <!-- Rueda SVG (diseño propio, sin librerías) -->
                    <div class="relative aspect-square max-w-lg mx-auto" style="max-width: 460px;">
                        <svg id="wheelSvg" viewBox="0 0 400 400" class="w-full h-full select-none"
                            style="transform-origin: 200px 200px;" role="img" aria-label="Rueda de letras">
                            <!-- Aro exterior -->
                            <circle cx="200" cy="200" r="196" fill="#14261F" stroke="#2a6b4a" stroke-width="6"/>
                            <!-- Segmentos (generados por JS) -->
                            <g id="wheelSegments"></g>
                        </svg>

                        <!-- Centro fijo: NO rota con la rueda, para que la letra siempre se lea -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-[5]">
                            <div class="w-[24%] aspect-square rounded-full bg-[#1E3A32] border-4 border-chalk-yellow flex items-center justify-center">
                                <span id="wheelCenterLetter" class="font-gochi text-[2.6rem] leading-none text-chalk-yellow select-none">?</span>
                            </div>
                        </div>

                        <!-- Flecha indicadora fija (a un costado) -->
                        <div id="wheelArrow" class="absolute top-1/2 right-0 -translate-y-1/2 translate-x-1/2 pointer-events-none z-10"
                            style="width:0;height:0;border-left:26px solid #FF8FB8;border-top:17px solid transparent;border-bottom:17px solid transparent;filter:drop-shadow(0 0 5px #FF8FB8);"
                            aria-hidden="true"></div>
                    </div>

                    <!-- Botón Girar (debajo de la rueda para no tapar la letra) -->
                    <div class="flex justify-center mt-4">
                        <button id="btnSpin" type="button"
                            class="bg-gradient-to-r from-chalk-yellow to-chalk-pink text-green-900 font-gochi text-2xl font-bold py-4 px-10 rounded-full shadow-xl hover:from-chalk-pink hover:to-chalk-yellow transition disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus:ring-4 focus:ring-chalk-yellow/60"
                            disabled>
                            🎯 GIRAR
                        </button>
                    </div>

                    <!-- Temporizador grande (1:30) -->
                    <div id="bigTimer" class="mt-4 text-center" aria-live="off">
                        <span id="bigTimerText" class="font-gochi text-5xl md:text-7xl text-chalk-yellow" style="font-variant-numeric:tabular-nums;">--:--</span>
                        <div id="bigTimerLabel" class="text-chalk/60 text-sm mt-1">Tiempo restante</div>
                    </div>
                </div>
            </section>

            <!-- PLAYING / STOPPING -->
            <section id="playingView" class="hidden w-full max-w-2xl flex flex-col items-center gap-6">
                <!-- Ronda actual -->
                <div class="text-center">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <span class="font-gochi text-2xl text-chalk-blue">Ronda</span>
                        <span id="roundNumber" class="font-gochi text-3xl text-chalk-yellow bg-green-900/50 border border-green-600 px-4 py-1 rounded">1</span>
                        <span class="font-gochi text-2xl text-chalk-blue">de</span>
                        <span id="totalRounds" class="font-gochi text-3xl text-chalk-yellow bg-green-900/50 border border-green-600 px-4 py-1 rounded">5</span>
                    </div>
                    <div class="flex items-center justify-center gap-2 text-chalk/70 text-sm">
                        <span id="roundLetter" class="font-gochi text-4xl text-chalk-yellow">?</span>
                    </div>
                </div>

                <!-- Jugadores con progreso -->
                <div id="playingPlayers" class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Players renderizados por JS -->
                </div>

                <!-- Timer barra (legacy, se mantiene para compatibilidad) -->
                <div class="w-full max-w-md hidden" id="timerBarContainer">
                    <div class="flex justify-between text-sm mb-1">
                        <span id="timerLabel" class="text-chalk-pink">Tiempo restante</span>
                        <span id="timerText" class="font-gochi text-xl text-chalk-yellow">90s</span>
                    </div>
                    <div class="h-3 bg-green-900/50 border border-green-600 rounded-full overflow-hidden">
                        <div id="timerBar" class="h-full bg-gradient-to-r from-chalk-yellow to-chalk-pink rounded-full transition-all duration-1000" style="width: 100%"></div>
                    </div>
                </div>

                <!-- Banner BASTA -->
                <div id="bastaBanner" class="hidden fixed inset-0 bg-red-900/95 z-50 flex flex-col items-center justify-center p-4">
                    <div class="screen-shake text-center">
                        <div id="bastaTitle" class="font-gochi text-5xl md:text-7xl text-chalk-pink mb-4">¡BASTA!</div>
                        <div id="bastaWho" class="font-gochi text-2xl text-chalk-yellow mb-2">Gritó <span id="bastaName"></span> <span id="bastaEmoji"></span></div>
                        <div id="bastaCountdown" class="text-chalk/80">Quedan <span id="bastaTimer" class="font-gochi text-3xl text-chalk-yellow">10</span>s</div>
                    </div>
                </div>
            </section>

            <!-- REVIEW -->
            <section id="reviewView" class="hidden w-full max-w-4xl overflow-y-auto flex-1">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-gochi text-2xl text-chalk-yellow">Revisión - Ronda <span id="reviewRound">1</span></h2>
                    <span id="reviewLetter" class="font-gochi text-4xl text-chalk-yellow bg-green-900/50 border border-green-600 px-3 py-1 rounded">M</span>
                </div>
                <div id="reviewTable" class="space-y-4">
                    <!-- Categorías renderizadas por JS -->
                </div>
                <div class="mt-6">
                    <h3 class="font-gochi text-xl text-chalk-yellow mb-2">🏆 Ranking de la partida</h3>
                    <div id="reviewRankingList" class="space-y-2">
                        <!-- Ranking renderizado por JS -->
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button id="btnNextRound" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-3 rounded-xl hover:from-green-400 hover:to-green-500 transition">
                        ➡️ SIGUIENTE RONDA
                    </button>
                </div>
            </section>

            <!-- FINISHED -->
            <section id="finishedView" class="hidden w-full max-w-2xl text-center">
                <div class="font-gochi text-4xl text-chalk-yellow mb-4">🏆 PARTIDA TERMINADA</div>
                <div id="podium" class="space-y-4 mb-8">
                    <!-- Podio renderizado por JS -->
                </div>
                <button id="btnNewGame" class="bg-gradient-to-r from-chalk-yellow to-chalk-pink text-green-900 font-bold py-3 px-8 rounded-xl text-lg hover:from-chalk-pink hover:to-chalk-yellow transition">
                    🔄 NUEVA PARTIDA
                </button>
            </section>
        </main>

        <!-- Footer con estado de conexión -->
        <footer class="mt-4 p-3 bg-green-900/30 border border-green-700 rounded-xl">
            <div class="flex items-center justify-center gap-2 text-sm text-chalk/70">
                <div id="connDot" class="w-2 h-2 rounded-full bg-gray-500"></div>
                <span id="connText">Conectando...</span>
            </div>
        </footer>
    </div>

    <!-- Chat de la sala (solo lectura para el host): bottom-sheet en móvil, panel lateral en desktop -->
    <div id="chatBackdrop" class="hidden fixed inset-0 z-40 bg-black/50 md:bg-transparent md:pointer-events-none" aria-hidden="true"></div>
    <aside id="chatPanel"
           class="hidden fixed z-40 inset-x-0 bottom-0 max-h-[70dvh] rounded-t-3xl border-t-2 border-green-600
                  bg-[#163025] shadow-2xl flex flex-col
                  md:inset-x-auto md:right-4 md:top-24 md:bottom-4 md:w-80 md:max-h-none md:rounded-3xl md:border-2"
           role="dialog" aria-label="Chat de la sala" aria-modal="false">
        <header class="flex items-center justify-between px-4 pt-3 pb-2 flex-shrink-0">
            <h2 class="font-gochi text-xl text-chalk-yellow">💬 Chat</h2>
            <button id="chatClose" type="button" aria-label="Cerrar chat"
                    class="w-8 h-8 rounded-full bg-green-900/60 border border-green-600 text-chalk text-sm font-bold
                           hover:bg-green-900/80 focus:outline-none focus:ring-2 focus:ring-chalk-yellow">
                ✕
            </button>
        </header>
        <div id="chatList" role="log" aria-live="polite" aria-relevant="additions"
             class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 pb-2 space-y-2">
            <!-- Mensajes generados por JS -->
        </div>
        <p id="chatEmpty" class="px-4 pb-3 text-xs text-chalk/60">Nadie escribió todavía. 👋</p>
        <p class="px-4 pb-3 text-xs text-chalk/50 border-t border-green-700 pt-2 flex-shrink-0">
            ✏️ Solo los jugadores pueden escribir acá.
        </p>
    </aside>

    <script>
        // ===== CONFIGURACIÓN =====
        const CODE = '{{ $code }}';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        const HOST_TOKEN = sessionStorage.getItem(`tutti:${CODE}:host`);
        const API_BASE = `/tutti/salas/${CODE}`;
        const WS_HOST = '127.0.0.1';
        const WS_PORT = 8080;

        // Letras del juego (mismo origen que el backend: config/tutti.php)
        const LETTER_POOL = @json(config('tutti.room.letter_pool'));
        const EXCLUDED_LETTERS = @json(config('tutti.room.excluded_letters'));
        const CATEGORIES_CONFIG = @json(config('tutti.categories'));
        // Misma ventana que el backend (config/tutti.php → timing.close_tolerance_ms)
        const CLOSE_TOLERANCE_MS = {{ (int) config('tutti.timing.close_tolerance_ms') }};
        const minPlayers = {{ $minPlayers }};

        // Letras que pueden salir en la rueda (pool menos excluidas)
        const WHEEL_LETTERS = LETTER_POOL.split('').filter(l => !EXCLUDED_LETTERS.includes(l));

        // Paleta de la rueda: verde, amarillo, naranja, azul (del proyecto)
        const WHEEL_COLORS = [
            { fill: '#2a6b4a', text: '#F1EFE4' }, // verde pizarra
            { fill: '#FFD84D', text: '#1E3A32' }, // amarillo tiza
            { fill: '#F97316', text: '#1E3A32' }, // naranja
            { fill: '#8EC9FF', text: '#1E3A32' }, // azul tiza
        ];

        // Geometría de la rueda (viewBox 0 0 400 400)
        const WHEEL = { cx: 200, cy: 200, rOuter: 190, rLabel: 133, arrowAngle: 90 };
        const SPIN_FULL_TURNS = 5;             // vueltas completas antes de frenar
        const SPIN_MIN_MS = 4000;
        const SPIN_MAX_MS = 6000;
        const SPIN_DEFAULT_MS = 5000;
        const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // ===== ESTADO =====
        let roomState = null;
        let pusher = null;
        let channel = null;
        let currentStatus = 'lobby';
        let isLastRound = false;
        let serverNowOffset = 0;               // server_now - Date.now()
        let wheelRotation = 0;                 // rotación absoluta actual (deg)
        let spinning = false;
        let lastSpinId = null;
        let pendingSpinFallback = null;
        let spinEndTimer = null;
        let closeTimeout = null;                // cierre programado de la ronda (round.review)
        let closeRetryTimer = null;
        let closeRetries = 0;
        let closeScheduled = false;
        let reviewShown = false;                // ya se recibió round.review en esta ronda
        let timerStartsAt = 0;
        let timerEndsAt = 0;
        let bigTimerInterval = null;
        const completedLetters = new Set();    // letras de rondas ya terminadas

        // ===== ELEMENTOS DOM =====
        const lobbyView = document.getElementById('lobbyView');
        const wheelView = document.getElementById('wheelView');
        const playingView = document.getElementById('playingView');
        const reviewView = document.getElementById('reviewView');
        const finishedView = document.getElementById('finishedView');
        const lobbyPlayers = document.getElementById('lobbyPlayers');
        const playingPlayers = document.getElementById('playingPlayers');
        const roundNumber = document.getElementById('roundNumber');
        const totalRoundsEl = document.getElementById('totalRounds');
        const roundLetter = document.getElementById('roundLetter');
        const timerText = document.getElementById('timerText');
        const timerBar = document.getElementById('timerBar');
        const timerLabel = document.getElementById('timerLabel');
        const bastaBanner = document.getElementById('bastaBanner');
        const bastaTitle = document.getElementById('bastaTitle');
        const bastaWho = document.getElementById('bastaWho');
        const bastaCountdown = document.getElementById('bastaCountdown');
        const bastaTimer = document.getElementById('bastaTimer');
        const btnNextRound = document.getElementById('btnNextRound');
        const btnNewGame = document.getElementById('btnNewGame');
        const reviewTable = document.getElementById('reviewTable');
        const reviewRound = document.getElementById('reviewRound');
        const reviewLetter = document.getElementById('reviewLetter');
        const reviewRankingList = document.getElementById('reviewRankingList');
        const podium = document.getElementById('podium');
        const connDot = document.getElementById('connDot');
        const connText = document.getElementById('connText');
        const qrContainer = document.getElementById('qrContainer');

        // Chat (solo lectura para el host)
        const chatToggle = document.getElementById('chatToggle');
        const chatBadge = document.getElementById('chatBadge');
        const chatPanel = document.getElementById('chatPanel');
        const chatBackdrop = document.getElementById('chatBackdrop');
        const chatClose = document.getElementById('chatClose');
        const chatList = document.getElementById('chatList');
        const chatEmpty = document.getElementById('chatEmpty');
        let chatUnread = 0;

        // Rueda
        const wheelSvg = document.getElementById('wheelSvg');
        const wheelSegments = document.getElementById('wheelSegments');
        const wheelCenterLetter = document.getElementById('wheelCenterLetter');
        const btnSpin = document.getElementById('btnSpin');
        const bigTimerText = document.getElementById('bigTimerText');
        const bigTimerLabel = document.getElementById('bigTimerLabel');

        // ===== INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', async () => {
            // QR Code
            const joinUrl = `${window.location.origin}/tutti/jugar/${CODE}`;
            new QRCode(qrContainer, { text: joinUrl, width: 128, height: 128, colorDark: '#1E3A32', colorLight: '#ffffff' });

            // Copiar URL
            document.getElementById('btnCopyUrl').addEventListener('click', () => {
                navigator.clipboard.writeText(joinUrl);
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'URL copiada', timer: 1500, showConfirmButton: false, background: '#1E3A32', color: '#F1EFE4' });
            });

            // Dibujar la rueda y arrancar el temporizador grande
            renderWheel();
            startBigTimerLoop();

            // Conectar WebSocket
            connectWebSocket();

            // Cargar estado inicial (permite recuperar tras recargar)
            await loadState();

            // Event listeners
            btnSpin.addEventListener('click', spin);
            btnNextRound.addEventListener('click', nextRound);
            btnNewGame.addEventListener('click', newGame);
        });

        // ===== WEBSOCKET =====
        function connectWebSocket() {
            pusher = new Pusher('uoj2o6k8snqvfgox3ewd', {
                wsHost: WS_HOST, wsPort: WS_PORT, wssPort: WS_PORT,
                forceTLS: false, enabledTransports: ['ws'], cluster: 'mt1', disableStats: true,
            });
            channel = pusher.subscribe(`tutti-${CODE}`);

            channel.bind('lobby.updated', data => { updateLobbyPlayers(data.players); updateView(data.status); });
            channel.bind('wheel.spun', data => { applySpin(data); });
            channel.bind('round.started', data => { onRoundStarted(data); });
            channel.bind('player.progress', data => { updatePlayerProgress(data.player_id, data.filled, data.total); });
            channel.bind('round.ended', data => {
                showBastaBanner(data.by, data.ends_at, data.reason);
                // El cliente sólo pide el cierre: el servidor valida y emite round.review
                scheduleRoundClose(data.ends_at);
            });
            channel.bind('round.review', data => { showReview(data); });
            channel.bind('game.finished', data => { showFinished(data); });
            channel.bind('chat.message', data => { appendChatMessage(data); });

            pusher.connection.bind('connected', () => { connDot.className = 'w-2 h-2 rounded-full bg-green-400 animate-pulse-ring'; connText.textContent = 'Conectado en tiempo real'; });
            pusher.connection.bind('disconnected', () => { connDot.className = 'w-2 h-2 rounded-full bg-red-400'; connText.textContent = 'Desconectado - Reintentando...'; });
            pusher.connection.bind('error', () => { connDot.className = 'w-2 h-2 rounded-full bg-red-400'; connText.textContent = 'Error de conexión'; });
        }

        // ===== API =====
        async function api(method, url, body = null) {
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' };
            if (HOST_TOKEN) headers['X-Tutti-Token'] = HOST_TOKEN;
            const res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : null });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error');
            return data;
        }

        async function loadState() {
            try {
                const res = await fetch(`${API_BASE}/estado`, { headers: { 'X-Tutti-Token': HOST_TOKEN, 'X-Tutti-Role': 'host' } });
                const data = await res.json();
                if (data.ok) { roomState = data; renderState(data); }
            } catch (e) { console.error('Error cargando estado:', e); }
        }

        // ===== RENDER ESTADO (incluye recuperación tras recargar) =====
        function renderState(data) {
            roomState = data;
            updateView(data.status);

            // Historial del chat (últimos 50 mensajes) — también tras recargar
            renderChatHistory(data.chat);

            if (data.status === 'lobby') {
                updateLobbyPlayers(data.players);
            }

            // Recuperación: si hay ronda en curso, se repone la rueda y el temporizador
            if (data.round && (data.status === 'playing' || data.status === 'stopping')) {
                serverNowOffset = (data.server_now || Date.now()) - Date.now();
                roundNumber.textContent = data.round.number;
                roundLetter.textContent = data.round.letter;
                wheelCenterLetter.textContent = data.round.letter;
                timerStartsAt = data.round.starts_at;
                timerEndsAt = data.round.ends_at;
                parkWheel(data.round.letter, true);
                renderPlayingPlayers(data.players || []);
            }

            // Recuperación en revisión
            if (data.status === 'review' && data.review) {
                isLastRound = !!data.review.is_last_round;
                showReview(data.review);
            }

            // Recuperación al finalizar (podio desde /estado)
            if (data.status === 'finished' && data.ranking) {
                showFinished(Array.isArray(data.ranking) ? { ranking: data.ranking } : data.ranking);
            }

            updateSpinButton();
        }

        function updateView(status) {
            currentStatus = status;
            [lobbyView, wheelView, playingView, reviewView, finishedView].forEach(v => v.classList.add('hidden'));
            if (status === 'lobby') {
                lobbyView.classList.remove('hidden');
                wheelView.classList.remove('hidden');
            } else if (status === 'playing' || status === 'stopping') {
                wheelView.classList.remove('hidden');
                playingView.classList.remove('hidden');
            } else if (status === 'review') {
                wheelView.classList.remove('hidden');
                reviewView.classList.remove('hidden');
            } else if (status === 'finished') {
                finishedView.classList.remove('hidden');
            }
        }

        function updateLobbyPlayers(players) {
            lobbyPlayers.innerHTML = '';
            Object.values(players || {}).forEach(p => {
                const el = document.createElement('div');
                el.className = 'bg-green-900/30 border border-green-600 rounded-xl p-4 flex items-center gap-3';
                el.innerHTML = `<span class="text-3xl">${p.emoji}</span><div><div class="font-semibold">${p.name}</div><div class="text-chalk/60 text-sm">ID: ${p.id}</div></div>`;
                lobbyPlayers.appendChild(el);
            });
        }

        // ===== RUEDA (SVG) =====
        function polar(r, deg) {
            const rad = (deg * Math.PI) / 180;
            return { x: WHEEL.cx + r * Math.sin(rad), y: WHEEL.cy - r * Math.cos(rad) };
        }

        function segmentPath(a0, a1) {
            const p0 = polar(WHEEL.rOuter, a0);
            const p1 = polar(WHEEL.rOuter, a1);
            const large = ((a1 - a0) % 360 + 360) % 360 > 180 ? 1 : 0;
            return `M ${WHEEL.cx} ${WHEEL.cy} L ${p0.x.toFixed(2)} ${p0.y.toFixed(2)} A ${WHEEL.rOuter} ${WHEEL.rOuter} 0 ${large} 1 ${p1.x.toFixed(2)} ${p1.y.toFixed(2)} Z`;
        }

        /** Dibuja un segmento por letra disponible con colores alternados de la paleta. */
        function renderWheel() {
            const n = WHEEL_LETTERS.length;
            if (n === 0) return;
            const seg = 360 / n;
            const fontSize = n > 16 ? 24 : n > 12 ? 28 : 32;

            let svg = '';
            WHEEL_LETTERS.forEach((letter, i) => {
                const center = i * seg;
                const a0 = center - seg / 2;
                const a1 = center + seg / 2;
                const color = WHEEL_COLORS[i % WHEEL_COLORS.length];
                const used = completedLetters.has(letter);

                svg += `<path d="${segmentPath(a0, a1)}" fill="${color.fill}" stroke="#14261F" stroke-width="2" opacity="${used ? 0.28 : 1}"/>`;

                // Etiqueta: siempre horizontal para que se lea de un vistazo
                const pos = polar(WHEEL.rLabel, center);
                svg += `<text x="${pos.x.toFixed(2)}" y="${pos.y.toFixed(2)}" text-anchor="middle" dominant-baseline="central" `
                    + `font-family="Gochi Hand, cursive" font-size="${fontSize}" font-weight="bold" fill="${color.text}" `
                    + `opacity="${used ? 0.35 : 1}">${letter}</text>`;
            });

            wheelSegments.innerHTML = svg;
        }

        /** Ángulo (mod 360) al que hay que rotar para que la letra quede bajo la flecha. */
        function targetRotationFor(letter) {
            const idx = WHEEL_LETTERS.indexOf(letter);
            if (idx < 0) return null;
            const seg = 360 / WHEEL_LETTERS.length;
            return ((WHEEL.arrowAngle - idx * seg) % 360 + 360) % 360;
        }

        /** Deja la rueda frenada exactamente en la letra, sin animación. */
        function parkWheel(letter, instant = true) {
            const target = targetRotationFor(letter);
            if (target === null) return;
            const currentMod = ((wheelRotation % 360) + 360) % 360;
            let delta = target - currentMod;
            if (delta < 0) delta += 360;
            wheelRotation += delta;
            if (instant) {
                const prev = wheelSvg.style.transition;
                wheelSvg.style.transition = 'none';
                wheelSvg.style.transform = `rotate(${wheelRotation}deg)`;
                void wheelSvg.getBoundingClientRect();
                wheelSvg.style.transition = prev;
            } else {
                wheelSvg.style.transform = `rotate(${wheelRotation}deg)`;
            }
        }

        /**
         * Gira la rueda hasta frenar EXACTAMENTE en el segmento de la letra,
         * con easing ease-out y duración entre 4 y 6 segundos.
         */
        function spinToLetter(letter, durationMs) {
            const target = targetRotationFor(letter);
            if (target === null) return;

            const currentMod = ((wheelRotation % 360) + 360) % 360;
            let delta = target - currentMod;
            if (delta < 0) delta += 360;

            const duration = Math.min(SPIN_MAX_MS, Math.max(SPIN_MIN_MS, durationMs || SPIN_DEFAULT_MS));
            const finalRotation = wheelRotation + (360 * SPIN_FULL_TURNS) + delta;

            spinning = true;
            updateSpinButton();

            if (REDUCED_MOTION) {
                // Sin movimiento: se muestra la letra directamente
                wheelSvg.style.transition = 'none';
                wheelRotation = finalRotation;
                wheelSvg.style.transform = `rotate(${wheelRotation}deg)`;
                void wheelSvg.getBoundingClientRect();
                wheelSvg.style.transition = '';
                spinning = false;
                showLetter(letter);
                updateSpinButton();
                return;
            }

            // ease-out (curva suave que frena progresivamente)
            wheelSvg.style.transition = `transform ${duration}ms cubic-bezier(0.22, 1, 0.36, 1)`;
            void wheelSvg.getBoundingClientRect();   // forzar reflow para que arranque la transición
            wheelRotation = finalRotation;
            wheelSvg.style.transform = `rotate(${wheelRotation}deg)`;

            clearTimeout(spinEndTimer);
            spinEndTimer = setTimeout(() => {
                spinning = false;
                showLetter(letter);
                updateSpinButton();
            }, duration + 60);
        }

        function showLetter(letter) {
            wheelCenterLetter.textContent = letter;
            roundLetter.textContent = letter;
        }

        /** Aplica un sorteo (deduplicado por spin_id). Origen: evento o respuesta HTTP. */
        function applySpin(spin) {
            if (!spin || !spin.letter) return;
            if (spin.spin_id && spin.spin_id === lastSpinId) return;
            lastSpinId = spin.spin_id || null;
            if (pendingSpinFallback) { clearTimeout(pendingSpinFallback); pendingSpinFallback = null; }
            if (spin.server_now) serverNowOffset = spin.server_now - Date.now();
            spinToLetter(spin.letter, spin.duration_ms);
        }

        /** Botón Girar: habilitado solo cuando se puede sortear una ronda. */
        function updateSpinButton() {
            let canSpin = false;
            if (currentStatus === 'lobby') canSpin = true;
            else if (currentStatus === 'review') canSpin = !isLastRound;
            btnSpin.classList.toggle('hidden', !canSpin);
            btnSpin.disabled = !canSpin || spinning;
        }

        async function spin() {
            if (spinning) return;
            btnSpin.disabled = true;
            try {
                const data = await api('POST', `${API_BASE}/girar`);
                if (!data.replayed) {
                    // Respaldo: si el evento no llega (WS caído), se anima igual
                    pendingSpinFallback = setTimeout(() => { applySpin(data); }, 900);
                }
            } catch (e) {
                btnSpin.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1E3A32', color: '#F1EFE4', confirmButtonColor: '#FFD84D' });
            }
        }

        // ===== TEMPORIZADOR GRANDE (basado en ends_at + server_now) =====
        function startBigTimerLoop() {
            if (bigTimerInterval) clearInterval(bigTimerInterval);
            bigTimerInterval = setInterval(updateBigTimer, 200);
            updateBigTimer();
        }

        function updateBigTimer() {
            if (!timerEndsAt) {
                bigTimerText.textContent = '--:--';
                return;
            }
            const now = Date.now() + serverNowOffset;
            const remaining = Math.max(0, timerEndsAt - now);
            const totalSec = Math.ceil(remaining / 1000);
            const m = Math.floor(totalSec / 60);
            const s = totalSec % 60;
            bigTimerText.textContent = `${m}:${String(s).padStart(2, '0')}`;
            bigTimerText.classList.toggle('text-red-400', totalSec <= 10);
            bigTimerText.classList.toggle('text-chalk-yellow', totalSec > 10);

            // Se acabó el tiempo (nadie gritó BASTA): se pide el cierre de la ronda
            if (remaining <= 0 && currentStatus === 'playing') scheduleRoundClose(timerEndsAt);
        }

        // ===== RONDA =====
        function onRoundStarted(data) {
            if (data.server_now) serverNowOffset = data.server_now - Date.now();
            roundNumber.textContent = data.round;
            totalRoundsEl.textContent = roomState?.settings?.total_rounds ?? totalRoundsEl.textContent;
            roundLetter.textContent = data.letter;
            wheelCenterLetter.textContent = data.letter;

            timerStartsAt = data.starts_at;
            timerEndsAt = data.ends_at;

            // Ronda nueva: se limpia el cierre pendiente y la revisión anterior
            resetRoundClose();
            reviewShown = false;

            bastaBanner.classList.add('hidden');
            updateView('playing');
            updateBigTimer();
            updateSpinButton();
            refreshPlayers(); // jugadores al día (el evento no los incluye)
        }

        /** Refresca jugadores/configuración sin tocar la rueda ni el temporizador. */
        async function refreshPlayers() {
            try {
                const res = await fetch(`${API_BASE}/estado`, { headers: { 'X-Tutti-Token': HOST_TOKEN, 'X-Tutti-Role': 'host', 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) return;
                roomState = { ...(roomState || {}), players: data.players, settings: data.settings };
                if (currentStatus === 'playing' || currentStatus === 'stopping') renderPlayingPlayers(data.players || []);
            } catch (e) { console.warn('No se pudo refrescar la lista de jugadores:', e.message); }
        }

        function renderPlayingPlayers(players) {
            playingPlayers.innerHTML = '';
            const catsTotal = roomState?.settings?.categories?.length || 6;
            Object.values(players || {}).forEach(p => {
                const filled = p.filled || 0;
                const pct = catsTotal > 0 ? (filled / catsTotal) * 100 : 0;
                const el = document.createElement('div');
                el.className = 'bg-green-900/30 border border-green-600 rounded-xl p-3 flex items-center gap-3';
                el.innerHTML = `
                    <span class="text-2xl">${p.emoji}</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold truncate">${p.name}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <svg class="w-10 h-10" viewBox="0 0 36 36"><circle class="progress-ring-circle text-green-600" cx="18" cy="18" r="15.9" fill="none" stroke-width="3" stroke-dasharray="100" stroke-dashoffset="${100 - pct}"></circle></svg>
                            <span class="text-xs text-chalk/70">${filled}/${catsTotal}</span>
                        </div>
                    </div>
                `;
                playingPlayers.appendChild(el);
            });
        }

        function updatePlayerProgress(playerId, filled, total) {
            const pct = total > 0 ? (filled / total) * 100 : 0;
            const circles = playingPlayers.querySelectorAll('.progress-ring-circle');
            Object.values(roomState?.players || {}).forEach((p, i) => {
                if (p.id === playerId && circles[i]) {
                    circles[i].style.strokeDashoffset = 100 - pct;
                    circles[i].parentElement.nextElementSibling.textContent = `${filled}/${total}`;
                }
            });
        }

        function showBastaBanner(by, endsAt, reason = 'basta') {
            const isBasta = reason === 'basta';
            bastaTitle.textContent = isBasta ? '¡BASTA!' : '⏰ ¡Se acabó!';
            bastaWho.textContent = (isBasta && by) ? ('Gritó ' + by.name + ' ' + by.emoji) : 'Revisemos las respuestas';
            bastaCountdown.classList.toggle('hidden', !isBasta);
            bastaBanner.classList.remove('hidden');
            bastaBanner.querySelector('.screen-shake').classList.add('screen-shake');
            bigTimerLabel.textContent = isBasta ? 'Tiempo extra por BASTA' : 'Cerrando ronda...';

            if (!isBasta) {
                setTimeout(() => bastaBanner.classList.add('hidden'), 4000);
                return;
            }

            const updateBasta = () => {
                const now = Date.now() + serverNowOffset;
                const remaining = Math.max(0, endsAt - now);
                bastaTimer.textContent = Math.ceil(remaining / 1000);
                if (remaining <= 0) clearInterval(bastaInterval);
            };
            const bastaInterval = setInterval(updateBasta, 500);
            setTimeout(() => bastaBanner.classList.add('hidden'), 10000);
        }

        // ===== CIERRE DE RONDA (pide el cierre; el servidor emite round.review) =====
        function resetRoundClose() {
            if (closeTimeout) { clearTimeout(closeTimeout); closeTimeout = null; }
            if (closeRetryTimer) { clearTimeout(closeRetryTimer); closeRetryTimer = null; }
            closeScheduled = false;
            closeRetries = 0;
        }

        /**
         * Programa el cierre de la ronda pasada la tolerancia del servidor
         * (ends_at + close_tolerance_ms). El servidor valida el momento,
         * emite round.ended si nadie gritó BASTA y luego round.review.
         */
        function scheduleRoundClose(endsAt) {
            if (closeScheduled) return;
            if (currentStatus === 'review' || currentStatus === 'finished') return;
            resetRoundClose();
            closeScheduled = true;
            const waitMs = Math.max(0, (endsAt || 0) - (Date.now() + serverNowOffset)) + CLOSE_TOLERANCE_MS + 300;
            closeTimeout = setTimeout(closeRound, waitMs);
        }

        async function closeRound() {
            if (currentStatus === 'review' || currentStatus === 'finished') return;
            try {
                await api('POST', `${API_BASE}/cerrar-ronda`);
                // Respaldo: si el evento no llega (WS caído) se recarga el estado
                setTimeout(() => { if (!reviewShown) loadState(); }, 1500);
            } catch (e) {
                // 409 = todavía no pasó la tolerancia: se reintenta
                closeRetries++;
                if (closeRetries <= 12) {
                    closeRetryTimer = setTimeout(closeRound, 500);
                } else {
                    closeScheduled = false;
                    console.warn('No se pudo cerrar la ronda:', e.message);
                }
            }
        }

        // ===== REVIEW =====
        function showReview(data) {
            reviewShown = true;
            updateView('review');
            isLastRound = !!data.is_last_round;
            reviewRound.textContent = data.round;
            reviewLetter.textContent = data.letter;
            reviewTable.innerHTML = '';

            // La ronda ya terminó: su letra deja de estar "disponible"
            completedLetters.add(data.letter);
            renderWheel();
            parkWheel(data.letter, true);

            // "SIGUIENTE RONDA" solo tiene sentido en la última ronda
            btnNextRound.classList.toggle('hidden', !isLastRound);

            // Por WS viene "rows" (completo); desde /estado viene en "categories"
            const rows = data.rows || data.categories || [];
            rows.forEach(row => {
                const catEl = document.createElement('div');
                catEl.className = 'bg-green-900/30 border border-green-600 rounded-xl p-4';
                catEl.innerHTML = `
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">${row.emoji}</span>
                        <span class="font-gochi text-xl text-chalk-yellow">${row.label}</span>
                    </div>
                    <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));">
                        ${row.entries.map(e => {
                            const player = Object.values(roomState?.players || {}).find(p => p.id === e.player_id);
                            if (!player) return '';
                            let cls = 'p-2 rounded-lg text-xs text-center ';
                            if (!e.valid && !e.voided) cls += 'bg-gray-700 border border-gray-600';
                            else if (e.voided) cls += 'bg-red-900/30 border border-red-600 line-through';
                            else if (e.solo) cls += 'bg-yellow-600/30 border-2 border-chalk-yellow';
                            else if (e.shared) cls += 'bg-blue-600/30 border border-blue-500';
                            else cls += 'bg-green-600/30 border border-green-500';
                            return `<div class="${cls}" title="${e.text} (${e.points} pts)"><div class="font-semibold">${e.text || '—'}</div><div class="text-chalk/70">${player.emoji} ${player.name}: ${e.points} pts${e.solo ? ' 👑' : e.shared ? ' 🔁' : ''}</div></div>`;
                        }).join('')}
                    </div>
                `;
                catEl.querySelectorAll('[title]').forEach((el, idx) => {
                    const entry = row.entries[idx];
                    if (entry.valid && !entry.voided) {
                        el.style.cursor = 'pointer';
                        el.addEventListener('click', () => voidAnswer(entry.player_id, row.category, true));
                    } else if (entry.voided) {
                        el.style.cursor = 'pointer';
                        el.addEventListener('click', () => voidAnswer(entry.player_id, row.category, false));
                    }
                });
                reviewTable.appendChild(catEl);
            });

            renderReviewRanking(data.totals || {});

            updateSpinButton();
        }

        /** Ranking acumulado en la pizarra (totales tras la ronda recién cerrada). */
        function renderReviewRanking(totals) {
            const players = Object.values(roomState?.players || {})
                .map(p => ({ ...p, total: totals[p.id] ?? 0 }))
                .sort((a, b) => b.total - a.total);

            reviewRankingList.innerHTML = players.map((p, i) => `
                <div class="flex items-center gap-3 bg-green-900/30 border border-green-600 rounded-xl px-4 py-2">
                    <span class="font-gochi text-xl text-chalk-yellow w-8">${i + 1}º</span>
                    <span class="text-2xl">${p.emoji}</span>
                    <span class="font-semibold flex-1 truncate">${p.name}</span>
                    <span class="font-gochi text-xl text-chalk-yellow">${p.total} pts</span>
                </div>`).join('');
        }

        async function voidAnswer(playerId, category, voidFlag) {
            try {
                await api('POST', `${API_BASE}/anular`, { player_id: playerId, category, void: voidFlag });
            } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1E3A32', color: '#F1EFE4', confirmButtonColor: '#FFD84D' }); }
        }

        async function nextRound() {
            try { await api('POST', `${API_BASE}/siguiente`); } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1E3A32', color: '#F1EFE4', confirmButtonColor: '#FFD84D' }); }
        }

        async function newGame() {
            try {
                await api('POST', `${API_BASE}/nueva`);
                completedLetters.clear();
                isLastRound = false;
                lastSpinId = null;
                timerEndsAt = 0;
                timerStartsAt = 0;
                wheelRotation = 0;
                wheelSvg.style.transition = 'none';
                wheelSvg.style.transform = 'rotate(0deg)';
                void wheelSvg.getBoundingClientRect();
                wheelSvg.style.transition = '';
                wheelCenterLetter.textContent = '?';
                roundLetter.textContent = '?';
                renderWheel();
                updateBigTimer();
            } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1E3A32', color: '#F1EFE4', confirmButtonColor: '#FFD84D' }); }
        }

        // ===== CHAT (solo lectura para el host) =====
        function setChatOpen(open) {
            chatPanel.classList.toggle('hidden', !open);
            chatBackdrop.classList.toggle('hidden', !open);
            chatToggle.setAttribute('aria-expanded', String(open));
            if (open) {
                chatUnread = 0;
                updateChatBadge();
                chatList.scrollTop = chatList.scrollHeight;
            }
        }

        function updateChatBadge() {
            const show = chatUnread > 0 && chatPanel.classList.contains('hidden');
            chatBadge.classList.toggle('hidden', !show);
            chatBadge.textContent = chatUnread > 9 ? '9+' : String(chatUnread);
        }

        /** Agrega un mensaje al chat (sólo texto plano: nunca se inserta HTML). */
        function appendChatMessage(msg) {
            if (!msg || !msg.text) return;
            const p = msg.player || {};
            const row = document.createElement('div');
            row.className = 'flex items-start gap-2';

            const avatar = document.createElement('span');
            avatar.className = 'text-lg leading-none mt-0.5 select-none';
            avatar.textContent = p.emoji || '🙂';
            avatar.setAttribute('aria-hidden', 'true');

            const body = document.createElement('div');
            body.className = 'min-w-0 flex-1';

            const head = document.createElement('div');
            head.className = 'flex items-baseline gap-1.5';
            const name = document.createElement('span');
            name.className = 'text-xs font-bold text-chalk truncate';
            name.textContent = p.name || 'Jugador';
            const time = document.createElement('span');
            time.className = 'text-[10px] text-chalk/50';
            time.textContent = msg.time || '';
            head.append(name, time);

            const text = document.createElement('p');
            text.className = 'text-sm text-chalk/90 break-words whitespace-pre-wrap';
            text.textContent = msg.text;

            body.append(head, text);
            row.append(avatar, body);
            chatList.appendChild(row);

            while (chatList.children.length > 50) chatList.removeChild(chatList.firstChild);

            chatEmpty.classList.add('hidden');
            const nearBottom = chatList.scrollHeight - chatList.scrollTop - chatList.clientHeight < 80;
            if (nearBottom || !chatPanel.classList.contains('hidden')) chatList.scrollTop = chatList.scrollHeight;

            if (chatPanel.classList.contains('hidden')) {
                chatUnread++;
                updateChatBadge();
            }
        }

        function renderChatHistory(list) {
            chatList.innerHTML = '';
            (list || []).forEach(appendChatMessage);
            if (!chatList.children.length) chatEmpty.classList.remove('hidden');
            chatUnread = 0;
            updateChatBadge();
            chatList.scrollTop = chatList.scrollHeight;
        }

        chatToggle.addEventListener('click', () => setChatOpen(chatPanel.classList.contains('hidden')));
        chatClose.addEventListener('click', () => setChatOpen(false));
        chatBackdrop.addEventListener('click', () => setChatOpen(false));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !chatPanel.classList.contains('hidden')) setChatOpen(false);
        });

        // ===== FINISHED =====
        function showFinished(data) {
            updateView('finished');
            podium.innerHTML = '';
            const medals = ['🥇', '🥈', '🥉'];
            const winners = data.winner_ids || [];
            (data.ranking || []).forEach((r, i) => {
                const isWinner = winners.includes(r.player_id);
                const el = document.createElement('div');
                el.className = `bg-green-900/30 border ${isWinner ? 'border-chalk-yellow' : 'border-green-600'} rounded-xl p-4 ${isWinner ? 'animate-pulse-ring' : ''}`;
                el.innerHTML = `
                    <div class="flex items-center justify-center gap-3 mb-2">
                        <span class="text-3xl">${medals[i] || `${r.rank}º`}</span>
                        <span class="text-2xl">${r.emoji}</span>
                        <span class="font-gochi text-2xl">${r.name}</span>
                    </div>
                    <div class="font-gochi text-3xl text-chalk-yellow">${r.total} pts</div>
                `;
                podium.appendChild(el);
            });
            if (winners.length > 0) launchConfetti();
        }

        function launchConfetti() {
            const duration = 4000; const end = Date.now() + duration;
            (function frame() {
                confetti({ particleCount: 7, angle: 60, spread: 60, origin: { x: 0 }, colors: ['#FFD84D', '#FF8FB8', '#8EC9FF', '#FF6FA5', '#4FD1A5'] });
                confetti({ particleCount: 7, angle: 120, spread: 60, origin: { x: 1 }, colors: ['#FFD84D', '#FF8FB8', '#8EC9FF', '#FF6FA5', '#4FD1A5'] });
                if (Date.now() < end) requestAnimationFrame(frame);
            })();
        }
    </script>

</body>
</html>