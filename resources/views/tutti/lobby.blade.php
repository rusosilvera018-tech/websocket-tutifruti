<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutti Frutti - Lobby</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.12.4/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Gochi+Hand&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(255, 216, 77, 0.6); } 70% { box-shadow: 0 0 0 12px rgba(255, 216, 77, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 216, 77, 0); } }
        .animate-pulse-ring { animation: pulse-ring 2s infinite; }
        @media (prefers-reduced-motion: reduce) { .animate-pulse-ring { animation: none; } }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-green-900 via-green-800 to-black font-nunito text-gray-100 flex flex-col items-center px-4 py-8">
    <div class="w-full max-w-2xl space-y-6">
        <!-- Header -->
        <header class="text-center">
            <h1 class="font-gochi text-4xl md:text-5xl text-yellow-300 mb-2">TUTTI FRUTTI</h1>
            <p class="text-green-200/80">Juego en vivo por sala</p>
        </header>

        <!-- Crear Sala -->
        <section id="createSection" class="bg-green-900/30 border border-green-700 rounded-2xl p-6 space-y-4">
            <h2 class="font-gochi text-2xl text-yellow-300 text-center mb-4">🎲 Crear nueva sala</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2 text-green-200">Categorías (4-8)</label>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2" id="categoryCheckboxes">
                        @php
                            $allCats = config('tutti.categories');
                            $defaultCats = config('tutti.defaults.categories');
                        @endphp
                        @foreach($allCats as $id => $cat)
                            <label class="flex items-center gap-2 p-2 bg-green-800/50 border border-green-600 rounded-lg cursor-pointer hover:border-green-400 transition">
                                <input type="checkbox" name="categories[]" value="{{ $id }}" 
                                    class="w-4 h-4 text-green-500 border-green-600 rounded focus:ring-green-500"
                                    @if(in_array($id, $defaultCats)) checked @endif>
                                <span class="text-sm flex items-center gap-1">{{ $cat['emoji'] }} {{ $cat['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p id="catError" class="hidden text-red-400 text-sm">Seleccioná entre 4 y 8 categorías</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-green-200">Rondas</label>
                        <select id="totalRounds" class="w-full bg-green-800 border border-green-600 rounded-lg px-4 py-2 text-white focus:border-green-400 focus:outline-none focus:ring-1 focus:ring-green-500">
                            <option value="3">3 rondas</option>
                            <option value="5" selected>5 rondas</option>
                            <option value="7">7 rondas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-green-200">Tiempo por ronda</label>
                        <select id="roundSeconds" class="w-full bg-green-800 border border-green-600 rounded-lg px-4 py-2 text-white focus:border-green-400 focus:outline-none focus:ring-1 focus:ring-green-500">
                            <option value="60">60 segundos</option>
                            <option value="90" selected>90 segundos</option>
                            <option value="120">120 segundos</option>
                        </select>
                    </div>
                </div>

                <button id="btnCreate" class="w-full bg-gradient-to-r from-yellow-400 to-orange-500 text-green-900 font-bold py-3 rounded-xl hover:from-yellow-300 hover:to-orange-400 transition disabled:opacity-50">
                    🎮 Crear sala y ser Host
                </button>
            </div>
        </section>

        <!-- Unirse a Sala -->
        <section id="joinSection" class="bg-green-900/30 border border-green-700 rounded-2xl p-6 space-y-4">
            <h2 class="font-gochi text-2xl text-yellow-300 text-center mb-4">🚪 Unirse a una sala</h2>
            
            <div class="space-y-4">
                <div class="flex gap-2">
                    <input type="text" id="joinCode" maxlength="4" placeholder="CÓDIGO" class="flex-1 bg-green-800 border border-green-600 rounded-lg px-4 py-3 text-center text-2xl font-bold tracking-widest text-white placeholder-green-500 focus:border-green-400 focus:outline-none focus:ring-1 focus:ring-green-500 uppercase" style="font-family: 'Gochi Hand', cursive;">
                    <input type="text" id="joinName" placeholder="Tu nombre" class="flex-1 bg-green-800 border border-green-600 rounded-lg px-4 py-3 text-white placeholder-green-500 focus:border-green-400 focus:outline-none focus:ring-1 focus:ring-green-500">
                </div>
                <button id="btnJoin" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-3 rounded-xl hover:from-green-400 hover:to-green-500 transition disabled:opacity-50">
                    🎯 Unirse
                </button>
            </div>
        </section>

        <p class="text-center text-green-300/60 text-sm">El host ve la <strong>pizarra</strong>. Los jugadores usan el <strong>cuaderno</strong> en sus celulares.</p>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        // ----- Crear Sala -----
        document.getElementById('btnCreate').addEventListener('click', async () => {
            const btn = document.getElementById('btnCreate');
            const checkboxes = document.querySelectorAll('#categoryCheckboxes input:checked');
            const categories = Array.from(checkboxes).map(c => c.value);
            const totalRounds = parseInt(document.getElementById('totalRounds').value);
            const roundSeconds = parseInt(document.getElementById('roundSeconds').value);

            if (categories.length < 4 || categories.length > 8) {
                document.getElementById('catError').classList.remove('hidden');
                return;
            }
            document.getElementById('catError').classList.add('hidden');

            btn.disabled = true;
            btn.textContent = 'Creando...';

            try {
                const res = await fetch('/tutti/salas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ categories, total_rounds: totalRounds, round_seconds: roundSeconds }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message);
                
                // Guardar token de host y redirigir
                sessionStorage.setItem(`tutti:${data.code}:host`, data.host_token);
                window.location.href = `/tutti/host/${data.code}`;
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1a2e1a', color: '#d1f5d1', confirmButtonColor: '#ffd84d' });
                btn.disabled = false;
                btn.textContent = '🎮 Crear sala y ser Host';
            }
        });

        // ----- Unirse -----
        document.getElementById('btnJoin').addEventListener('click', async () => {
            const btn = document.getElementById('btnJoin');
            const code = document.getElementById('joinCode').value.trim().toUpperCase();
            const name = document.getElementById('joinName').value.trim();

            if (!code || code.length !== 4) return Swal.fire({ icon: 'warning', title: 'Código inválido', text: 'Debe tener 4 letras', background: '#1a2e1a', color: '#d1f5d1', confirmButtonColor: '#ffd84d' });
            if (!name) return Swal.fire({ icon: 'warning', title: 'Nombre requerido', text: 'Escribí tu nombre', background: '#1a2e1a', color: '#d1f5d1', confirmButtonColor: '#ffd84d' });

            btn.disabled = true;
            btn.textContent = 'Uniendo...';

            try {
                const res = await fetch(`/tutti/salas/${code}/unirse`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ name }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message);

                sessionStorage.setItem(`tutti:${code}:player`, data.token);
                window.location.href = `/tutti/jugar/${code}`;
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1a2e1a', color: '#d1f5d1', confirmButtonColor: '#ffd84d' });
                btn.disabled = false;
                btn.textContent = '🎯 Unirse';
            }
        });

        // Enter en inputs
        ['joinCode', 'joinName'].forEach(id => {
            document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btnJoin').click(); });
        });

        // Auto-uppercase código
        document.getElementById('joinCode').addEventListener('input', e => { e.target.value = e.target.value.toUpperCase(); });
    </script>
</body>
</html>