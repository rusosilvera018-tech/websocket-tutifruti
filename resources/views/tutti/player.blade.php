<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tutti Frutti - Cuaderno (Jugador)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.12.4/sweetalert2.all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Gochi+Hand&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Fondo de cuaderno (mobile first) */
        @media (max-width: 640px) {
            .cuaderno-fondo {
                background-color: #F6FAFF;
                background-image: repeating-linear-gradient(
                    90deg, transparent, transparent 27px,
                    rgba(213, 227, 245, 0.9) 28px, rgba(213, 227, 245, 0.9) 29px,
                    rgba(213, 227, 245, 0.9) 29px, rgba(213, 227, 245, 0.9) 30px, transparent 30px, transparent 31px
                );
            }
        }
        .font-gochi { font-family: 'Gochi Hand', cursive; }
        .font-nunito { font-family: 'Nunito', sans-serif; }

        /* Letra grande resaltada (amarillo del proyecto) */
        .letra-resaltado { background: #FFE45E; color: #1F3FBF; box-shadow: 0 3px 0 rgba(31, 63, 191, 0.18); }

        /* Estados de los inputs */
        .campo-input { transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease; }
        .input-valido { border-color: #22C55E !important; background: #F0FDF4; }
        /* Aviso suave (no bloquea): verde si arranca con la letra, rojo claro si no */
        .input-invalido { border-color: #FCA5A5 !important; background: #FEF2F2; animation: none; }
        .campo-input:focus-visible { outline: 3px solid #1F3FBF; outline-offset: 2px; }

        /* Foco visible en todos los controles */
        #btnBasta:focus-visible { outline: 3px solid #B91C1C; outline-offset: 3px; }

        /* Rueda girando */
        @keyframes girar-suave { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .girar-suave { animation: girar-suave 3.2s linear infinite; }

        /* Timer circular */
        .timer-ring-circle { transition: stroke-dashoffset 1s linear, stroke 0.3s ease; }

        /* Banner BASTA */
        @keyframes fade-in { to { opacity: 1; } }
        .banner-basta > * { opacity: 0; animation: fade-in 0.3s ease forwards; }
        .banner-basta > *:nth-child(2) { animation-delay: 0.25s; }
        .banner-basta > *:nth-child(3) { animation-delay: 0.5s; }
        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); } 70% { box-shadow: 0 0 0 12px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        .animate-pulse-ring { animation: pulse-ring 2s infinite; }

        /* Sin animaciones para quien las prefiera apagadas */
        @media (prefers-reduced-motion: reduce) {
            .girar-suave { animation: none; }
            .timer-ring-circle { transition: none; }
            .banner-basta > * { animation: none; opacity: 1; }
            .animate-pulse-ring { animation: none; }
        }

        /* Scroll suave dentro de la lista de campos y de resultados */
        #fieldsList, #resultsList { scrollbar-width: thin; }
        #fieldsList::-webkit-scrollbar, #resultsList::-webkit-scrollbar { width: 6px; }
        #fieldsList::-webkit-scrollbar-thumb, #resultsList::-webkit-scrollbar-thumb { background: rgba(31, 63, 191, 0.25); border-radius: 999px; }
    </style>
</head>
<body class="cuaderno-fondo font-nunito text-blue-900 h-dvh overflow-hidden flex flex-col">
    <div class="mx-auto w-full max-w-md flex-1 min-h-0 flex flex-col p-4">

        <!-- Encabezado en una fila: Ronda a la izquierda, Letra a la derecha -->
        <header class="flex items-end justify-between gap-3 mb-2 flex-shrink-0">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-widest text-red-700">Ronda</div>
                <div class="font-gochi text-3xl leading-none text-blue-900">
                    <span id="roundNumber">–</span><span class="text-blue-400">/</span><span id="totalRounds">–</span>
                </div>
            </div>
            <div class="flex items-end gap-2">
                <button id="chatToggle" type="button" aria-expanded="false" aria-controls="chatPanel"
                        aria-label="Abrir chat de la sala"
                        class="relative w-10 h-10 mb-1 rounded-full bg-white/90 border-2 border-blue-300 text-xl
                               flex items-center justify-center shadow hover:bg-white transition
                               focus:outline-none focus-visible:outline focus-visible:outline-3 focus-visible:outline-blue-700">
                    💬
                    <span id="chatBadge" class="hidden absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1
                                   rounded-full bg-red-600 text-white text-[10px] font-bold
                                   flex items-center justify-center leading-none">0</span>
                </button>
                <div class="text-[11px] font-bold uppercase tracking-widest text-red-700 pb-2">Letra</div>
                <div id="roundLetter" class="font-gochi text-5xl md:text-6xl leading-none letra-resaltado px-3 py-0.5 rounded-xl select-none"
                     aria-live="polite" aria-label="Letra de la ronda">?</div>
            </div>
        </header>

        <!-- Progreso personal -->
        <div class="flex items-center gap-2 mb-3 flex-shrink-0" aria-label="Tu progreso">
            <div class="h-2 flex-1 bg-white/70 border border-blue-300 rounded-full overflow-hidden">
                <div id="myBar" class="h-full bg-green-500 transition-all duration-300" style="width: 0%"></div>
            </div>
            <span class="text-xs font-extrabold text-blue-800 whitespace-nowrap">
                <span id="myFilled">0</span>/<span id="totalCats">0</span>
            </span>
        </div>

        <!-- Estado de espera: rueda girando / lobby / review -->
        <section id="waitingSection" class="flex-1 min-h-0 flex flex-col items-center justify-center gap-4 text-center px-6"
                 aria-live="polite">
            <div id="waitingEmoji" class="text-7xl girar-suave select-none" aria-hidden="true">🎡</div>
            <p id="waitingTitle" class="font-gochi text-2xl text-blue-900">🎡 Girando la rueda...</p>
            <p id="waitingSub" class="text-sm text-blue-700/70">La letra y los campos aparecen apenas se decida.</p>
        </section>

        <!-- Una sola pantalla: lista vertical de campos -->
        <main id="fieldsSection" class="hidden flex-1 min-h-0 flex flex-col">
            <h2 class="sr-only">Campos de la ronda</h2>
            <div id="fieldsList" role="group" aria-label="Campos de la ronda"
                 class="flex-1 min-h-0 overflow-y-auto overscroll-contain space-y-4 pr-1 pb-2">
                <!-- Campos generados por JS -->
            </div>
        </main>

        <!-- Resultados de la ronda (puntos por categoría + total) -->
        <section id="resultsSection" class="hidden flex-1 min-h-0 flex flex-col" aria-live="polite">
            <div class="flex items-center justify-between mb-2 flex-shrink-0">
                <h2 class="font-gochi text-xl text-blue-900">📝 Resultados</h2>
                <span id="resultsRound" class="text-xs font-bold text-blue-700"></span>
            </div>
            <div id="resultsList" class="flex-1 min-h-0 overflow-y-auto overscroll-contain space-y-3 pr-1 pb-2">
                <!-- Categorías generadas por JS -->
            </div>
            <div class="mt-2 flex-shrink-0 rounded-2xl border-2 border-yellow-300 bg-yellow-50 p-3 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-blue-700">Esta ronda</div>
                    <div id="myRoundPoints" class="font-gochi text-3xl text-blue-900 leading-none">0</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-blue-700">Total</div>
                    <div id="myTotalPoints" class="font-gochi text-3xl text-blue-900 leading-none">0</div>
                </div>
            </div>
        </section>

        <!-- Temporizador + botón BASTA fijo abajo -->
        <footer class="sticky bottom-0 -mx-4 px-4 pt-3 pb-[calc(1rem+env(safe-area-inset-bottom))]
                       bg-gradient-to-t from-[#F6FAFF] via-[#F6FAFF]/95 to-transparent flex-shrink-0">
            <div id="playRow" class="flex items-center gap-3">
                <div class="relative w-14 h-14 flex-shrink-0" role="timer" aria-label="Tiempo restante">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#E5E7EB" stroke-width="9"/>
                        <circle id="timerRing" class="timer-ring-circle" cx="50" cy="50" r="45" fill="none" stroke-width="9"
                                stroke-linecap="round" stroke="#22C55E" stroke-dasharray="283" stroke-dashoffset="0" aria-hidden="true"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span id="timerText" class="font-gochi text-xl text-blue-900" aria-hidden="true">–</span>
                    </div>
                </div>

                <button id="btnBasta" type="button" disabled
                        class="flex-1 py-4 text-2xl font-gochi rounded-2xl shadow-lg transition
                               bg-gradient-to-r from-red-600 to-red-700 text-white
                               hover:from-red-500 hover:to-red-600
                               disabled:opacity-40 disabled:cursor-not-allowed"
                        aria-label="Gritar BASTA: envía tus respuestas y termina la ronda">
                    🗣️ ¡BASTA!
                </button>
            </div>
            <div class="flex items-center justify-center gap-3 mt-2">
                <p class="text-xs text-blue-700/70">Enter pasa al siguiente campo</p>
                <span class="inline-flex items-center gap-1.5 text-blue-700/70">
                    <span id="connDot" class="w-2 h-2 rounded-full bg-gray-400"></span>
                    <span id="connText" class="text-xs">Conectando...</span>
                </span>
            </div>
        </footer>
    </div>

    <!-- Banner BASTA / tiempo agotado -->
    <div id="bastaBanner" class="hidden fixed inset-0 z-50 bg-blue-900/92 flex items-center justify-center p-6" role="alert" aria-live="assertive">
        <div class="banner-basta text-center">
            <div id="bannerTitle" class="font-gochi text-5xl text-white">🗣️ ¡BASTA!</div>
            <div id="bannerBody" class="font-nunito text-lg mt-2 text-white/90"></div>
            <div id="bannerSub" class="text-xl mt-2 text-yellow-300"></div>
        </div>
    </div>

    <!-- Chat de la sala: bottom-sheet en móvil, panel lateral en desktop -->
    <div id="chatBackdrop" class="hidden fixed inset-0 z-40 bg-blue-900/40 md:bg-transparent md:pointer-events-none" aria-hidden="true"></div>
    <aside id="chatPanel"
           class="hidden fixed z-40 inset-x-0 bottom-0 max-h-[70dvh] rounded-t-3xl border-t-2 border-blue-300
                  bg-[#F6FAFF] shadow-2xl flex flex-col
                  md:inset-x-auto md:right-4 md:top-24 md:bottom-4 md:w-80 md:max-h-none md:rounded-3xl md:border-2"
           role="dialog" aria-label="Chat de la sala" aria-modal="false">
        <header class="flex items-center justify-between px-4 pt-3 pb-2 flex-shrink-0">
            <h2 class="font-gochi text-xl text-blue-900">💬 Chat</h2>
            <button id="chatClose" type="button" aria-label="Cerrar chat"
                    class="w-8 h-8 rounded-full bg-white border-2 border-blue-200 text-blue-900 text-sm font-bold
                           hover:bg-blue-50 focus:outline-none focus-visible:outline focus-visible:outline-3 focus-visible:outline-blue-700">
                ✕
            </button>
        </header>
        <div id="chatList" role="log" aria-live="polite" aria-relevant="additions"
             class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 pb-2 space-y-2">
            <!-- Mensajes generados por JS -->
        </div>
        <p id="chatEmpty" class="px-4 pb-2 text-xs text-blue-700/60">Nadie escribió todavía. ¡Decí hola! 👋</p>
        <form id="chatForm" class="flex items-center gap-2 p-3 border-t-2 border-blue-200 bg-white/70 flex-shrink-0"
              autocomplete="off">
            <label for="chatInput" class="sr-only">Mensaje para el chat</label>
            <input id="chatInput" type="text" maxlength="200" placeholder="Mensaje..."
                   class="flex-1 min-w-0 rounded-xl border-2 border-blue-200 bg-white px-3 py-2 text-sm text-blue-900
                          placeholder-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-300 focus:border-blue-400"
                   enterkeyhint="send">
            <button id="chatSend" type="submit"
                    class="rounded-xl bg-blue-900 text-white px-4 py-2 text-sm font-bold hover:bg-blue-800
                           disabled:opacity-40 disabled:cursor-not-allowed
                           focus:outline-none focus-visible:outline focus-visible:outline-3 focus-visible:outline-blue-700">
                ➤
            </button>
        </form>
        <p id="chatHint" class="px-4 pb-2 text-xs text-red-600 hidden" role="status"></p>
    </aside>

    <script>
        // ===== CONFIGURACIÓN =====
        const CODE = '{{ $code }}';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        const PLAYER_TOKEN = sessionStorage.getItem('tutti:' + CODE + ':player');
        const API_BASE = '/tutti/salas/' + CODE;
        const WS_HOST = '127.0.0.1';
        const WS_PORT = 8080;
        // Ventana que el servidor acepta tras el cierre (config/tutti.php → timing.answers_grace_ms)
        const ANSWERS_GRACE_MS = {{ (int) config('tutti.timing.answers_grace_ms') }};

        // Fallback local para etiquetas (la lista de categorías viene del evento o de /estado)
        const CATEGORIES = @json(config('tutti.categories'));

        // ===== ESTADO =====
        let roomState = null;
        let currentRound = null;
        let currentLetter = '?';
        let serverNowOffset = 0;
        let myAnswers = {};
        let myPlayerId = null;
        let currentTimerInterval = null;
        let categoriesList = [];      // [{id, label, emoji}]
        let roundEnded = false;       // true cuando la ronda se cerró (BASTA o tiempo)
        let revealTimer = null;       // temporizador que espera a que la rueda frene
        let spinEndsAt = 0;           // instante (reloj servidor) en que la rueda deja de girar
        let roundLocked = false;      // ronda cerrada: campos bloqueados

        // ===== ELEMENTOS DOM =====
        const roundNumberEl = document.getElementById('roundNumber');
        const totalRoundsEl = document.getElementById('totalRounds');
        const roundLetterEl = document.getElementById('roundLetter');
        const myFilledEl = document.getElementById('myFilled');
        const totalCatsEl = document.getElementById('totalCats');
        const myBarEl = document.getElementById('myBar');
        const waitingSection = document.getElementById('waitingSection');
        const waitingEmoji = document.getElementById('waitingEmoji');
        const waitingTitle = document.getElementById('waitingTitle');
        const waitingSub = document.getElementById('waitingSub');
        const fieldsSection = document.getElementById('fieldsSection');
        const fieldsList = document.getElementById('fieldsList');
        const timerRing = document.getElementById('timerRing');
        const timerText = document.getElementById('timerText');
        const btnBasta = document.getElementById('btnBasta');
        const bastaBanner = document.getElementById('bastaBanner');
        const bannerTitle = document.getElementById('bannerTitle');
        const bannerBody = document.getElementById('bannerBody');
        const bannerSub = document.getElementById('bannerSub');
        const connDot = document.getElementById('connDot');
        const connText = document.getElementById('connText');
        const resultsSection = document.getElementById('resultsSection');
        const resultsList = document.getElementById('resultsList');
        const resultsRound = document.getElementById('resultsRound');
        const myRoundPointsEl = document.getElementById('myRoundPoints');
        const myTotalPointsEl = document.getElementById('myTotalPoints');
        const playRow = document.getElementById('playRow');

        // Chat
        const chatToggle = document.getElementById('chatToggle');
        const chatBadge = document.getElementById('chatBadge');
        const chatPanel = document.getElementById('chatPanel');
        const chatBackdrop = document.getElementById('chatBackdrop');
        const chatClose = document.getElementById('chatClose');
        const chatList = document.getElementById('chatList');
        const chatEmpty = document.getElementById('chatEmpty');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const chatSend = document.getElementById('chatSend');
        const chatHint = document.getElementById('chatHint');
        let chatUnread = 0;
        let lastChatSentAt = 0;   // espejo local del límite de 1 msg/s del servidor
        let chatHintTimer = null;

        // ===== UTILIDADES =====
        function getServerNow() { return Date.now() + serverNowOffset; }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
        }

        function normalize(text) {
            let t = String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            t = t.toLowerCase();
            t = t.replace(/[^a-z\s]/g, '');
            t = t.replace(/\s+/g, ' ').trim();
            return t;
        }

        function isValid(text, letter) {
            const norm = normalize(text);
            return norm.length >= 2 && norm[0] === String(letter).toLowerCase();
        }

        function setCategories(list) {
            if (Array.isArray(list) && list.length) {
                categoriesList = list.map(c => ({
                    id: c.id,
                    label: c.label || (CATEGORIES[c.id]?.label ?? c.id),
                    emoji: c.emoji || (CATEGORIES[c.id]?.emoji ?? '📝'),
                }));
            } else {
                categoriesList = [];
            }
            totalCatsEl.textContent = categoriesList.length;
        }

        // ===== API =====
        async function api(method, url, body = null) {
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' };
            if (PLAYER_TOKEN) headers['X-Tutti-Token'] = PLAYER_TOKEN;
            const res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : null });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error');
            return data;
        }

        // ===== CARGA INICIAL / RECUPERACIÓN TRAS RECARGAR =====
        async function loadInitialState() {
            try {
                const res = await fetch(API_BASE + '/estado', {
                    headers: { 'X-Tutti-Token': PLAYER_TOKEN, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.message || 'Error');

                roomState = data;
                if (data.server_now) serverNowOffset = data.server_now - Date.now();
                myPlayerId = data.me?.id ?? null;
                setCategories(data.settings?.categories);
                totalRoundsEl.textContent = data.settings?.total_rounds ?? '–';

                // Restaurar historial del chat (últimos 50 mensajes)
                renderChatHistory(data.chat);

                // Restaurar respuestas ya guardadas en el servidor
                myAnswers = {};
                if (data.me?.answers) {
                    Object.entries(data.me.answers).forEach(([k, v]) => { if (v) myAnswers[k] = v; });
                }

                const activa = data.status === 'playing' || data.status === 'stopping';
                if (activa && data.round) {
                    // Si la rueda todavía está girando, se espera a que frene para mostrar la letra
                    const delay = Math.max(0, (data.round.starts_at || 0) - getServerNow());
                    roundLocked = !!data.round.stopped_by;
                    revealRound({
                        number: data.round.number,
                        letter: data.round.letter,
                        starts_at: data.round.starts_at,
                        ends_at: data.round.ends_at,
                    }, delay > 300 ? delay : 0, false);
                } else if (data.status === 'finished') {
                    showWaiting('finished');
                    // Restaurar el podio también tras recargar (en /estado viene anidado)
                    const rk = Array.isArray(data.ranking) ? data.ranking : (data.ranking?.ranking || []);
                    if (rk.length) showFinished({ ranking: rk });
                } else if (data.status === 'review' && data.review) {
                    // Restaurar la pantalla de resultados tras recargar
                    showResults(data.review);
                } else if (data.status === 'lobby') {
                    showWaiting('lobby');
                } else {
                    showWaiting('review');
                }
            } catch (e) {
                console.error('Error cargando estado inicial:', e);
                showWaiting('lobby');
            }
        }

        // ===== ESTADOS DE ESPERA =====
        function showWaiting(mode) {
            if (revealTimer) { clearTimeout(revealTimer); revealTimer = null; }
            currentRound = null;
            currentLetter = '?';
            roundLetterEl.textContent = '?';
            roundNumberEl.textContent = '–';
            fieldsSection.classList.add('hidden');
            resultsSection.classList.add('hidden');
            playRow.classList.remove('hidden');
            waitingSection.classList.remove('hidden');
            stopTimer();

            if (mode === 'lobby') {
                waitingEmoji.textContent = '⏳';
                waitingEmoji.classList.remove('girar-suave');
                waitingTitle.textContent = 'Esperando a que arranque la partida...';
                waitingSub.textContent = 'El host va a girar la ruleta para sortear la letra.';
            } else if (mode === 'finished') {
                waitingEmoji.textContent = '🏆';
                waitingEmoji.classList.remove('girar-suave');
                waitingTitle.textContent = '¡Partida terminada!';
                waitingSub.textContent = 'Mirá el podio en la pizarra del host.';
            } else if (mode === 'review') {
                waitingEmoji.textContent = '✅';
                waitingEmoji.classList.remove('girar-suave');
                waitingTitle.textContent = 'Ronda terminada';
                waitingSub.textContent = 'Esperando la próxima letra...';
            } else {
                waitingEmoji.textContent = '🎡';
                waitingEmoji.classList.add('girar-suave');
                waitingTitle.textContent = '🎡 Girando la rueda...';
                waitingSub.textContent = 'La letra y los campos aparecen apenas se decida.';
            }

            myAnswers = {};
            myFilledEl.textContent = '0';
            myBarEl.style.width = '0%';
            btnBasta.disabled = true;
        }

        // ===== RONDA ACTIVA =====
        // La rueda tarda unos segundos en frenar: hasta entonces se ve el mensaje de giro.
        function revealRound(round, delayMs, focusFirst) {
            if (delayMs > 0) {
                showWaiting('spin');
                if (revealTimer) clearTimeout(revealTimer);
                revealTimer = setTimeout(() => {
                    revealTimer = null;
                    renderRound(round, { focus: focusFirst !== false });
                }, delayMs);
                return;
            }
            if (revealTimer) { clearTimeout(revealTimer); revealTimer = null; }
            renderRound(round, { focus: focusFirst !== false });
        }

        function renderRound(round, opts = {}) {
            currentRound = round;
            currentLetter = round.letter;
            roundEnded = false;

            roundNumberEl.textContent = round.number;
            roundLetterEl.textContent = round.letter;
            totalRoundsEl.textContent = roomState?.settings?.total_rounds ?? totalRoundsEl.textContent;

            waitingSection.classList.add('hidden');
            resultsSection.classList.add('hidden');
            playRow.classList.remove('hidden');
            fieldsSection.classList.remove('hidden');

            renderFields();
            updateMyProgress();
            startTimer(round.starts_at, round.ends_at);

            // Si la ronda ya se había cerrado, los campos quedan bloqueados
            if (roundLocked) lockFields();

            // Primer campo enfocado al empezar la ronda
            if (opts.focus !== false) {
                const first = fieldsList.querySelector('input');
                if (first) { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }
            }
        }

        function renderFields() {
            fieldsList.innerHTML = '';
            const letter = currentLetter;

            categoriesList.forEach((cat) => {
                const wrap = document.createElement('div');
                const inputId = 'campo-' + cat.id;
                wrap.innerHTML = `
                    <label for="${escapeHtml(inputId)}" class="flex items-center gap-1.5 font-gochi text-lg text-blue-900 mb-1">
                        <span aria-hidden="true" class="text-xl leading-none">${escapeHtml(cat.emoji)}</span>${escapeHtml(cat.label)}
                    </label>
                    <input id="${escapeHtml(inputId)}" type="text"
                        class="campo-input w-full rounded-xl border-2 border-blue-200 bg-white/95 px-4 py-3
                               text-lg font-bold uppercase tracking-wide text-blue-900 placeholder-blue-300
                               focus:outline-none focus:ring-4 focus:ring-blue-300 focus:border-blue-400"
                        autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next"
                        placeholder="${escapeHtml(letter)}..."
                        data-category-id="${escapeHtml(cat.id)}"
                        aria-label="${escapeHtml(cat.label)}: respuesta que empiece con ${escapeHtml(letter)}">
                `;
                const input = wrap.querySelector('input');

                // Restaurar respuesta si ya había una
                if (myAnswers[cat.id]) input.value = myAnswers[cat.id];

                input.addEventListener('input', () => {
                    myAnswers[cat.id] = input.value.trim().toUpperCase();
                    validateInput(input);
                    updateMyProgress();
                    debouncedSaveAnswers();
                });

                // Autoguardado al salir del campo (además del debounce al escribir)
                input.addEventListener('blur', () => {
                    myAnswers[cat.id] = input.value.trim().toUpperCase();
                    updateMyProgress();
                    saveAnswersNow();
                });

                // Enter: siguiente campo; en el último, foco en BASTA (nunca envía solo)
                input.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const inputs = allInputs();
                    const i = inputs.indexOf(input);
                    const next = inputs[i + 1];
                    if (next) {
                        next.focus();
                        next.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        return;
                    }
                    // Último campo: no se envía con Enter
                    if (!btnBasta.disabled) {
                        btnBasta.focus();
                        return;
                    }
                    // BASTA no está listo: se lleva el foco al primer campo pendiente
                    const pendiente = inputs.find(inp => {
                        const v = inp.value.trim();
                        return v === '' || !isValid(v, currentLetter);
                    });
                    if (pendiente) {
                        pendiente.focus();
                        pendiente.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                });

                fieldsList.appendChild(wrap);
                validateInput(input);
            });
        }

        function validateInput(input) {
            const val = input.value.trim();
            const valid = val !== '' && isValid(val, currentLetter);
            input.classList.toggle('input-valido', valid);
            input.classList.toggle('input-invalido', !valid && val !== '');
        }

        function allInputs() { return Array.from(fieldsList.querySelectorAll('input')); }

        function updateMyProgress() {
            const inputs = allInputs();
            const valid = inputs.filter(i => { const v = i.value.trim(); return v !== '' && isValid(v, currentLetter); }).length;
            myFilledEl.textContent = valid;
            const total = categoriesList.length;
            myBarEl.style.width = total > 0 ? ((valid / total) * 100) + '%' : '0%';
            checkBastaReady();
        }

        function checkBastaReady() {
            const inputs = allInputs();
            const ready = !roundEnded && currentLetter !== '?' && inputs.length > 0 &&
                inputs.every(i => { const v = i.value.trim(); return v !== '' && isValid(v, currentLetter); });
            btnBasta.disabled = !ready;
        }

        function validateAll() {
            return allInputs().every(i => { const v = i.value.trim(); return v !== '' && isValid(v, currentLetter); });
        }

        function lockFields() {
            roundEnded = true;
            allInputs().forEach(i => { i.disabled = true; });
            btnBasta.disabled = true;
        }

        // ===== TEMPORIZADOR (basado en ends_at y server_now) =====
        function startTimer(startsAt, endsAt) {
            stopTimer();
            if (!endsAt) return;
            const tick = () => {
                const now = getServerNow();
                const total = Math.max(1, endsAt - startsAt);
                let remaining = Math.max(0, endsAt - now);
                // Mientras gira la rueda la ronda todavía no arrancó: se muestra el tiempo completo
                if (startsAt && now < startsAt) remaining = Math.min(remaining, total);
                const pct = Math.max(0, Math.min(1, remaining / total));

                const circumference = 283; // 2 * PI * 45
                timerRing.style.strokeDashoffset = String(circumference * (1 - pct));
                timerRing.style.stroke = pct < 0.15 ? '#EF4444' : (pct < 0.35 ? '#F59E0B' : '#22C55E');
                timerText.textContent = String(Math.ceil(remaining / 1000));

                if (remaining <= 0) {
                    clearInterval(currentTimerInterval);
                    currentTimerInterval = null;
                    // Se acabó el tiempo: se bloquean los campos y se envía el guardado final
                    if (!roundEnded) { lockFields(); saveAnswersNow(true); }
                }
            };
            tick();
            currentTimerInterval = setInterval(tick, 200);
        }

        function stopTimer() {
            if (currentTimerInterval) { clearInterval(currentTimerInterval); currentTimerInterval = null; }
            timerRing.style.strokeDashoffset = '283';
            timerRing.style.stroke = '#22C55E';
            timerText.textContent = '–';
        }

        // ===== PUSHER / WEBSOCKETS =====
        function connectWebSocket() {
            const pusher = new Pusher('uoj2o6k8snqvfgox3ewd', {
                wsHost: WS_HOST, wsPort: WS_PORT, wssPort: WS_PORT,
                forceTLS: false, enabledTransports: ['ws'], cluster: 'mt1', disableStats: true,
            });
            const channel = pusher.subscribe('tutti-' + CODE);

            channel.bind('lobby.updated', () => {});
            channel.bind('player.progress', () => {});

            // La rueda está girando: esperar la letra
            channel.bind('wheel.spun', (data) => {
                if (data?.server_now) serverNowOffset = data.server_now - Date.now();
                const dur = Math.min(6000, Math.max(4000, data.duration_ms || 5000));
                spinEndsAt = getServerNow() + dur;
                showWaiting('spin');
            });

            // Arranca la ronda: letra + campos (se muestran cuando la rueda frena)
            channel.bind('round.started', (data) => {
                console.log('[DEBUG] round.started:', data);
                if (data?.server_now) serverNowOffset = data.server_now - Date.now();
                myAnswers = {};
                roundLocked = false;
                setCategories(data.categories);
                const delay = Math.max(0, spinEndsAt - getServerNow());
                revealRound({
                    number: data.round,
                    letter: data.letter,
                    starts_at: data.starts_at,
                    ends_at: data.ends_at,
                }, delay, true);
            });

            // Ronda cerrada (BASTA de alguien o tiempo agotado)
            channel.bind('round.ended', (data) => {
                if (data?.server_now) serverNowOffset = data.server_now - Date.now();
                roundLocked = true;
                lockFields();
                saveAnswersNow(true); // envío final de respuestas (ventana de ~2 s tras el cierre)
                if (data.reason === 'basta' && data.by) {
                    showBanner('🗣️ ¡BASTA!', 'Gritó <strong>' + escapeHtml(data.by.name) + '</strong> ' + escapeHtml(data.by.emoji), data.ends_at);
                } else {
                    showBanner('⏰ ¡Se acabó el tiempo!', 'Revisemos respuestas...', data.ends_at);
                }
            });

            channel.bind('round.review', (data) => {
                showResults(data);
            });

            // Mensaje del chat
            channel.bind('chat.message', (data) => {
                appendChatMessage(data);
            });

            channel.bind('game.finished', (data) => {
                showWaiting('finished');
                showFinished(data);
            });

            pusher.connection.bind('connected', () => {
                console.log('[DEBUG] WebSocket conectado');
                connDot.className = 'w-2 h-2 rounded-full bg-green-400 animate-pulse-ring';
                connText.textContent = 'Conectado';
                connText.className = 'text-xs text-green-600';
                if (!currentRound) loadInitialState();
            });
            pusher.connection.bind('disconnected', () => {
                connDot.className = 'w-2 h-2 rounded-full bg-red-400';
                connText.textContent = 'Desconectado';
                connText.className = 'text-xs text-red-600';
            });
            pusher.connection.bind('error', () => {
                connDot.className = 'w-2 h-2 rounded-full bg-red-400';
                connText.textContent = 'Error de conexión';
                connText.className = 'text-xs text-red-600';
            });
        }

        function showBanner(title, bodyHtml, endsAt) {
            bannerTitle.textContent = title;
            bannerBody.innerHTML = bodyHtml;
            bastaBanner.classList.remove('hidden');

            let interval = null;
            const update = () => {
                const secs = Math.max(0, Math.ceil(((endsAt || 0) - getServerNow()) / 1000));
                bannerSub.textContent = secs > 0 ? ('Quedan ' + secs + 's') : '';
                if (secs <= 0 && interval) { clearInterval(interval); interval = null; }
            };
            interval = setInterval(update, 500);
            update();

            setTimeout(() => bastaBanner.classList.add('hidden'), 4000);
        }

        function showFinished(data) {
            Swal.fire({
                title: '🏆 Partida terminada',
                html: '<div class="text-xl">El podio final es:</div><div class="mt-4 space-y-1">' +
                    (data.ranking || []).map(r => '<div>' + escapeHtml(r.emoji) + ' ' + escapeHtml(r.name) + ' — ' + r.total + ' pts (Lugar ' + r.rank + ')</div>').join('') + '</div>',
                icon: 'success',
                background: '#F6FAFF',
                color: '#1F3FBF',
                confirmButtonColor: '#FF6FA5',
                confirmButtonText: 'Entendido',
            });
        }

        // ===== PANTALLA DE RESULTADOS (puntos por categoría + total) =====
        function showResults(review) {
            if (revealTimer) { clearTimeout(revealTimer); revealTimer = null; }
            currentRound = null;
            roundLocked = true;
            roundEnded = true;
            stopTimer();
            lockFields();
            bastaBanner.classList.add('hidden');

            waitingSection.classList.add('hidden');
            fieldsSection.classList.add('hidden');
            resultsSection.classList.remove('hidden');
            playRow.classList.add('hidden');

            // Encabezado: conserva ronda y letra (también tras recargar)
            roundNumberEl.textContent = review.round ?? roundNumberEl.textContent;
            roundLetterEl.textContent = review.letter ?? roundLetterEl.textContent;

            const rows = review.rows || review.categories || [];
            const meId = String(myPlayerId);
            const playersById = {};
            // /estado devuelve players como objeto {"1": {...}} o como array
            Object.values(roomState?.players || {}).forEach(p => { playersById[String(p.id)] = p; });

            resultsRound.textContent = 'RONDA ' + (review.round ?? '–') + '/' +
                (review.total_rounds ?? roomState?.settings?.total_rounds ?? '–');

            resultsList.innerHTML = rows.map(row => {
                const entries = (row.entries || []).map(e => {
                    const isMe = String(e.player_id) === meId;
                    const p = playersById[String(e.player_id)];
                    const who = isMe ? 'Vos' : (p ? (p.emoji + ' ' + p.name) : ('#' + e.player_id));
                    let style = 'border-blue-100 bg-white/70 text-blue-900/60';
                    if (e.voided) style = 'border-red-200 bg-red-50 text-red-500';
                    else if (!e.valid) style = 'border-red-100 bg-red-50 text-red-500';
                    else if (e.shared) style = 'border-amber-200 bg-amber-50 text-amber-700';
                    else style = 'border-green-200 bg-green-50 text-green-700';
                    if (isMe) style += ' ring-2 ring-yellow-300';
                    const mark = e.voided ? '✗' : String(e.points ?? 0);
                    return '<li class="flex items-center justify-between gap-2 rounded-lg border-2 px-2.5 py-1.5 ' + style + '">' +
                        '<span class="min-w-0 flex-1 text-sm">' +
                            '<span class="font-bold">' + escapeHtml(who) + '</span> ' +
                            '<span class="opacity-80">' + escapeHtml(e.text || (e.voided ? '(anulada)' : '—')) + '</span>' +
                        '</span>' +
                        '<span class="font-gochi text-lg leading-none">' + mark + '</span>' +
                    '</li>';
                }).join('');

                return '<section class="rounded-xl border-2 border-blue-200 bg-white/95 p-3">' +
                    '<div class="mb-2 flex items-center gap-1.5">' +
                        '<span class="text-xl leading-none" aria-hidden="true">' + escapeHtml(row.emoji) + '</span>' +
                        '<span class="font-gochi text-lg text-blue-900">' + escapeHtml(row.label) + '</span>' +
                    '</div>' +
                    '<ul class="space-y-1">' + entries + '</ul>' +
                '</section>';
            }).join('');

            resultsList.insertAdjacentHTML('beforeend',
                '<p class="pb-1 text-center text-xs text-blue-700/70">Esperando la próxima letra en la pizarra...</p>');

            myRoundPointsEl.textContent = '+' + ((review.round_points || {})[meId] ?? 0);
            myTotalPointsEl.textContent = String((review.totals || {})[meId] ?? 0);
        }

        // ===== ENVÍO DE RESPUESTAS =====
        function collectAnswers() {
            const inputs = allInputs();
            // Sin inputs en pantalla (ronda aún no revelada): se usa lo que ya estaba guardado
            if (!inputs.length) return { ...myAnswers };
            const answers = {};
            inputs.forEach(input => {
                answers[input.dataset.categoryId] = input.value.trim();
            });
            return answers;
        }

        let saveDebounceTimer = null;
        function debouncedSaveAnswers() {
            if (saveDebounceTimer) clearTimeout(saveDebounceTimer);
            saveDebounceTimer = setTimeout(() => saveAnswersNow(), 1000);
        }

        // Guardado inmediato: al salir de un campo y al cerrar la ronda (force)
        async function saveAnswersNow(force = false) {
            if (saveDebounceTimer) { clearTimeout(saveDebounceTimer); saveDebounceTimer = null; }
            if (!force && (roundEnded || !currentRound)) return;
            // Fuera de la ventana que acepta el servidor (ends_at + gracia): no se insiste
            if (force && currentRound?.ends_at && getServerNow() > currentRound.ends_at + ANSWERS_GRACE_MS) return;
            const answers = collectAnswers();
            if (!Object.values(answers).some(v => v !== '')) return;
            try {
                await api('POST', API_BASE + '/respuestas', { answers });
            } catch (e) {
                // Ronda cerrada o fuera de la ventana del servidor: no se reintenta
                console.warn('No se pudieron guardar las respuestas:', e.message);
            }
        }

        btnBasta.addEventListener('click', async () => {
            if (btnBasta.disabled) return;

            if (!validateAll()) {
                Swal.fire({
                    icon: 'warning',
                    title: '¡No podés gritar BASTA!',
                    text: 'Completá todos los campos y que empiecen con la letra.',
                    background: '#F6FAFF', color: '#1F3FBF', confirmButtonColor: '#FF6FA5',
                });
                return;
            }

            try {
                const answers = collectAnswers();
                await api('POST', API_BASE + '/respuestas', { answers });
                await api('POST', API_BASE + '/basta', { answers });
                btnBasta.disabled = true;
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'info',
                    title: '¡BASTA! Esperando cierre...', timer: 3000,
                    background: '#F6FAFF', color: '#1F3FBF',
                });
            } catch (e) {
                Swal.fire({
                    icon: 'error', title: 'Error', text: e.message,
                    background: '#F6FAFF', color: '#1F3FBF', confirmButtonColor: '#EF4444',
                });
            }
        });

        // ===== CHAT DE LA SALA =====
        function setChatOpen(open) {
            chatPanel.classList.toggle('hidden', !open);
            chatBackdrop.classList.toggle('hidden', !open);
            chatToggle.setAttribute('aria-expanded', String(open));
            if (open) {
                chatUnread = 0;
                updateChatBadge();
                chatList.scrollTop = chatList.scrollHeight;
                chatInput.focus({ preventScroll: true });
            }
        }

        function updateChatBadge() {
            const show = chatUnread > 0 && chatPanel.classList.contains('hidden');
            chatBadge.classList.toggle('hidden', !show);
            chatBadge.textContent = chatUnread > 9 ? '9+' : String(chatUnread);
        }

        function showChatHint(text) {
            chatHint.textContent = text;
            chatHint.classList.remove('hidden');
            if (chatHintTimer) clearTimeout(chatHintTimer);
            chatHintTimer = setTimeout(() => chatHint.classList.add('hidden'), 4000);
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
            name.className = 'text-xs font-bold text-blue-900 truncate';
            name.textContent = p.name || 'Jugador';
            const time = document.createElement('span');
            time.className = 'text-[10px] text-blue-700/50';
            time.textContent = msg.time || '';
            head.append(name, time);

            const text = document.createElement('p');
            text.className = 'text-sm text-blue-900 break-words whitespace-pre-wrap';
            text.textContent = msg.text;

            body.append(head, text);
            row.append(avatar, body);
            chatList.appendChild(row);

            // Historial en pantalla acotado (igual que en el servidor: 50)
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
            // La historia cargada no debe contar como mensajes no leídos
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

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = chatInput.value.trim();
            if (!text) return;

            // Espejo local del límite del servidor (1 msg por segundo)
            const now = Date.now();
            if (now - lastChatSentAt < 1000) {
                showChatHint('Esperá un segundo entre mensajes ⏳');
                return;
            }
            lastChatSentAt = now;
            chatSend.disabled = true;
            try {
                await api('POST', API_BASE + '/chat', { text });
                chatInput.value = '';
                chatHint.classList.add('hidden');
            } catch (err) {
                showChatHint(err.message || 'No se pudo enviar el mensaje');
            } finally {
                chatSend.disabled = false;
            }
        });

        // ===== INICIAR =====
        connectWebSocket();
        loadInitialState();
    </script>
</body>
</html>
