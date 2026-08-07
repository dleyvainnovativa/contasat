<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · ContaSAT</title>

    {{-- Anti-FOUC: set the theme before first paint. Must stay inline and
         blocking — a deferred module runs too late and the light theme flashes.
         Mirrors the app layout so login and app agree on the stored theme. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('contasat-theme');
            var mode = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var el = document.documentElement;
            el.setAttribute('data-theme', mode);
            el.setAttribute('data-bs-theme', mode);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;550;600;650;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/theme.css','resources/css/login.css', 'resources/js/app.js'])
</head>
<body class="login-body">

    {{-- Theme toggle: floats top-right, above both panels. --}}
    <button class="login-theme-toggle" data-theme-toggle aria-label="Cambiar tema">
        <i class="fa-solid fa-moon"></i>
    </button>

    <div class="login-split">

        {{-- Left: the brand panel. Tells what ContaSAT does, in the product's own
             terms — the reconciliation story, not generic marketing. Hidden on
             mobile, where only the form remains. --}}
        <aside class="login-brand" aria-hidden="true">
            <div class="login-brand__top">
                <span class="logo-mark login-brand__logo">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </span>
                <span class="login-brand__name">ContaSAT</span>
            </div>

            <div class="login-brand__pitch">
                <h2>Del CFDI a la póliza,<br>sin la hoja de cálculo.</h2>
                <p>Descarga, concilia y timbra la contabilidad electrónica de todos tus clientes en un solo lugar.</p>
            </div>

            {{-- The pipeline, as the product actually models it. Structure that
                 encodes something true: this IS an ordered flow. --}}
            <ol class="login-flow">
                <li><i class="fa-solid fa-cloud-arrow-down"></i> Descarga masiva del SAT</li>
                <li><i class="fa-solid fa-code-compare"></i> Conciliación automática</li>
                <li><i class="fa-solid fa-file-code"></i> Contabilidad electrónica</li>
            </ol>

            <div class="login-brand__foot">
                <span class="data">CFDI 4.0</span>
                <span class="data">Anexo 24</span>
                <span class="data">e.firma</span>
            </div>
        </aside>

        {{-- Right: the form. --}}
        <main class="login-form-panel">
            <div class="login-form-inner" data-reveal>
                {{-- Compact brand, shown only on mobile where the aside is gone. --}}
                <div class="login-mobile-brand">
                    <span class="logo-mark"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span>ContaSAT</span>
                </div>

                <h1 class="login-title">Iniciar sesión</h1>
                <p class="login-sub">Accede con tu cuenta para continuar</p>

                <div id="login-form">
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" id="email" class="form-control" placeholder="tucorreo@ejemplo.com" autocomplete="email">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <input type="password" id="password" class="form-control" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <button id="login-btn" class="btn btn-brand w-100 btn-icon justify-content-center">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar
                    </button>
                </div>

                <p class="login-legal">
                    Al continuar aceptas el manejo seguro de tus datos fiscales.
                </p>
            </div>
        </main>
    </div>

{{-- Firebase Web SDK (modular, via CDN) --}}
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
    import { getAuth, signInWithEmailAndPassword } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js';

    const firebaseConfig = @json($firebaseConfig ?? []);
    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);

    const btn = document.getElementById('login-btn');

    async function doLogin() {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        if (!email || !password) { App.toast.warning('Ingresa correo y contraseña.'); return; }

        await App.loading.button(btn, async () => {
            try {
                const cred = await signInWithEmailAndPassword(auth, email, password);
                const idToken = await cred.user.getIdToken();
                const res = await App.http.post('{{ route('auth.session') }}', { id_token: idToken });
                window.location.href = res.redirect;
            } catch (err) {
                App.toast.error(mapError(err));
            }
        });
    }

    function mapError(err) {
        const code = err && err.code ? err.code : '';
        if (code.includes('invalid-credential') || code.includes('wrong-password') || code.includes('user-not-found'))
            return 'Correo o contraseña incorrectos.';
        if (code.includes('too-many-requests')) return 'Demasiados intentos. Intenta más tarde.';
        return err.message || 'No se pudo iniciar sesión.';
    }

    btn.addEventListener('click', doLogin);
    document.getElementById('password').addEventListener('keydown', (e) => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>