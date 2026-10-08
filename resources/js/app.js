/* =========================================================================
   Humareda Prime — centralized front-end helpers (vanilla JS).
   Import this once via Vite (resources/js/app.js). Everything is exposed on
   window.HP so inline Blade scripts can call helpers without duplication.
   ========================================================================= */

import 'bootstrap';
import './theme-toggle.js';

/* ---- CSRF + fetch core -------------------------------------------------- */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function request(method, url, body = null, options = {}) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        ...(options.headers || {}),
    };

    let payload = body;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const res = await fetch(url, { method, headers, body: payload, ...options });

    let data = null;
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) {
        data = await res.json();
    }

    if (!res.ok) {
        const message = data?.message || `Error ${res.status}`;
        throw Object.assign(new Error(message), { status: res.status, data });
    }
    return data;
}

const http = {
    get:    (url, options)        => request('GET', url, null, options),
    post:   (url, body, options)  => request('POST', url, body, options),
    put:    (url, body, options)  => request('PUT', url, body, options),
    patch:  (url, body, options)  => request('PATCH', url, body, options),
    delete: (url, body, options)  => request('DELETE', url, body, options),
};

/* ---- Toast notifications ------------------------------------------------ */
function ensureToastWrap() {
    let wrap = document.querySelector('.hp-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'hp-toast-wrap';
        document.body.appendChild(wrap);
    }
    return wrap;
}

const ICONS = {
    success: 'fa-circle-check',
    error:   'fa-circle-exclamation',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info',
};

function toast(message, type = 'info', timeout = 4000) {
    const wrap = ensureToastWrap();
    const el = document.createElement('div');
    el.className = `hp-toast is-${type}`;
    el.setAttribute('role', 'status');
    el.innerHTML = `<i class="fa-solid ${ICONS[type] || ICONS.info}"></i><div>${message}</div>`;
    wrap.appendChild(el);
    if (timeout) setTimeout(() => el.remove(), timeout);
    return el;
}

/* ---- Loading state on any element -------------------------------------- */
function setLoading(el, loading = true) {
    if (typeof el === 'string') el = document.querySelector(el);
    if (!el) return;
    if (loading) {
        el.classList.add('is-loading');
        el.dataset.originalHtml = el.innerHTML;
        el.innerHTML = `<span class="hp-spinner"></span>`;
        el.setAttribute('aria-busy', 'true');
    } else {
        el.classList.remove('is-loading');
        if (el.dataset.originalHtml !== undefined) el.innerHTML = el.dataset.originalHtml;
        el.removeAttribute('aria-busy');
    }
}

/* ---- Modal helper (Bootstrap 5) ---------------------------------------- */
function modal(selector) {
    const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (!el) return null;
    // eslint-disable-next-line no-undef
    const instance = bootstrap.Modal.getOrCreateInstance(el);
    return { el, show: () => instance.show(), hide: () => instance.hide(), instance };
}

/* ---- Form serialization ------------------------------------------------ */
function serializeForm(form) {
    if (typeof form === 'string') form = document.querySelector(form);
    const data = {};
    new FormData(form).forEach((value, key) => {
        if (key in data) {
            data[key] = [].concat(data[key], value);
        } else {
            data[key] = value;
        }
    });
    return data;
}

/* ---- Public surface ---------------------------------------------------- */
window.HP = { http, toast, setLoading, modal, serializeForm, csrfToken };

export { http, toast, setLoading, modal, serializeForm };
