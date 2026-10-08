/* Light/dark toggle. Persists to a cookie so the server could read it too,
   and falls back to the OS preference on first visit. Applies before paint
   via the inline snippet in the <head> (see layouts/app.blade.php). */

const KEY = 'hp-theme';

function currentTheme() {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith(`${KEY}=`));
    if (cookie) return cookie.split('=')[1];
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.cookie = `${KEY}=${theme}; path=/; max-age=31536000; SameSite=Lax`;
    document.querySelectorAll('[data-theme-icon]').forEach((el) => {
        el.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    });
}

function toggleTheme() {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(next);
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(currentTheme());
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', toggleTheme);
    });
});

window.HP = window.HP || {};
window.HP.toggleTheme = toggleTheme;
