<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · ContaSAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;550;600;650;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/theme.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" data-reveal>
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="logo-mark" style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--brand-500),var(--brand-700));display:grid;place-items:center;color:#fff;">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </span>
            <div>
                <div style="font-weight:650; letter-spacing:-0.02em; font-size:1.05rem;">ContaSAT</div>
                <div class="text-muted" style="font-size:12px;">Conciliación fiscal</div>
            </div>
        </div>

        <h1 style="font-size:1.25rem; font-weight:650; margin-bottom:.25rem;">Iniciar sesión</h1>
        <p class="text-muted mb-4" style="font-size:13.5px;">Accede con tu cuenta para continuar</p>

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
    </div>
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
