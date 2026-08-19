{{-- Topbar client/period switcher. The client LIST is cached in localStorage for
     instant rendering, but switching is authoritative — it POSTs to the server,
     which updates WorkContext. The browser never decides who's active on its own.

     Include in the topbar, replacing (or beside) the static context chips. --}}
<div class="topbar-switcher" data-reveal>
    <div class="switcher-field">
        <i class="fa-solid fa-building"></i>
        <select id="client-switch" data-current="{{ $workContext->hasClient() ? $workContext->client()->id : '' }}"></select>
    </div>
    <div class="switcher-field" id="period-field" style="{{ $workContext->hasClient() ? '' : 'display:none;' }}">
        <i class="fa-solid fa-calendar"></i>
        <select id="period-switch" data-current="{{ $workContext->hasPeriod() ? $workContext->period()->id : '' }}"></select>
    </div>
</div>

@push('scripts')
<script type="module">

(function () {
    const CACHE_KEY = 'contasat-clients-cache';
    const CACHE_TTL = 5 * 60 * 1000; // 5 min — new clients appear within this window

    const clientSel = document.getElementById('client-switch');
    const periodSel = document.getElementById('period-switch');
    const periodField = document.getElementById('period-field');
    const currentClient = clientSel.dataset.current;
    const currentPeriod = periodSel.dataset.current;

    let clientChoices, periodChoices;

    // --- Client list: cached in localStorage for instant paint, refreshed in bg ---
    async function loadClients() {
        let clients = readCache();
        if (clients) renderClients(clients);          // instant from cache

        // Always refresh in the background so new clients show up.
        try {
            const res = await App.http.get('{{ route('context.clients') }}');
            clients = res.clients;
            writeCache(clients);
            renderClients(clients);
        } catch (e) { /* keep cached render on failure */ }
    }

    function renderClients(clients) {
        if (clientChoices) clientChoices.destroy();
        clientSel.innerHTML = '<option value="">Selecciona cliente…</option>';
        clients.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.label;
            if (String(c.id) === String(currentClient)) opt.selected = true;
            clientSel.appendChild(opt);
        });
        clientChoices = new Choices(clientSel, {
            searchEnabled: true, itemSelectText: '', shouldSort: false,
            searchPlaceholderValue: 'Buscar cliente…',
        });
    }

    // --- Periods: loaded lazily when a client is active/selected ---
    async function loadPeriods(clientId, selectId) {
        if (!clientId) { periodField.style.display = 'none'; return; }
        try {
            const res = await App.http.get(`{{ url('context/periods') }}/${clientId}`);
            if (periodChoices) periodChoices.destroy();
            periodSel.innerHTML = '<option value="">Selecciona periodo…</option>';
            res.periods.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.label;
                if (String(p.id) === String(selectId)) opt.selected = true;
                periodSel.appendChild(opt);
            });
            periodChoices = new Choices(periodSel, {
                searchEnabled: true, itemSelectText: '', shouldSort: false,
                searchPlaceholderValue: 'Buscar periodo…',
            });
            periodField.style.display = '';
        } catch (e) { periodField.style.display = 'none'; }
    }

    // --- Switching is authoritative: POST to server, then redirect ---
    clientSel.addEventListener('change', async () => {
        const clientId = clientSel.value;
        if (!clientId) return;
        // Load that client's periods so the user can pick one; the switch itself
        // fires once a period is chosen, OR immediately with no period.
        await loadPeriods(clientId, null);
        try {
            const res = await App.http.post('{{ route('context.switch') }}', { client_id: clientId });
            window.location.href = res.redirect;
        } catch (e) { App.toast.error(e.message); }
    });

    periodSel.addEventListener('change', async () => {
        const periodId = periodSel.value;
        if (!periodId) return;
        try {
            const res = await App.http.post('{{ route('context.switch_period') }}', { period_id: periodId });
            window.location.href = res.redirect || window.location.href;
        } catch (e) { App.toast.error(e.message); }
    });

    function readCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            if (!raw) return null;
            const { at, clients } = JSON.parse(raw);
            return (Date.now() - at < CACHE_TTL) ? clients : null;
        } catch { return null; }
    }
    function writeCache(clients) {
        try { localStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), clients })); } catch {}
    }

    // Init
    loadClients();
    if (currentClient) loadPeriods(currentClient, currentPeriod);
})();
</script>
@endpush
