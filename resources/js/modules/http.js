/* http.js — thin fetch wrapper with CSRF, JSON handling, and GET/POST/PUT/DELETE
   helpers. Every network call in the app goes through here so error handling,
   headers, and the loading/toast integration stay consistent. */

function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
}

async function request(method, url, body = null, options = {}) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
        ...(options.headers || {}),
    };

    let payload;
    if (body instanceof FormData) {
        payload = body; // let the browser set the multipart boundary
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });

    // 204 or empty body
    const text = await res.text();
    const data = text ? safeParse(text) : null;

    if (!res.ok) {
        const message = (data && (data.message || data.error)) || `Error ${res.status}`;
        const err = new Error(message);
        err.status = res.status;
        err.data = data;
        throw err;
    }

    return data;
}

function safeParse(text) {
    try { return JSON.parse(text); } catch { return text; }
}

export const http = {
    get: (url, options) => request('GET', url, null, options),
    post: (url, body, options) => request('POST', url, body, options),
    put: (url, body, options) => request('PUT', url, body, options),
    del: (url, body, options) => request('DELETE', url, body, options),
};
