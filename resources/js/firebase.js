/* Firebase browser sign-in for the login page. Loaded only on /login.
   Config is injected from the server (config/firebase.php web block) via a
   <script> global on the page: window.__FIREBASE_CONFIG__ and __AUTH_URL__. */

import { initializeApp } from 'firebase/app';
import {
    getAuth,
    setPersistence,
    browserLocalPersistence,
    signInWithEmailAndPassword,
    GoogleAuthProvider,
    signInWithPopup,
} from 'firebase/auth';

const app  = initializeApp(window.__FIREBASE_CONFIG__);
const auth = getAuth(app);
setPersistence(auth, browserLocalPersistence);

async function postToken(user, statusEl, submitBtn) {
    const idToken = await user.getIdToken();
    const res = await window.HP.http.post(window.__AUTH_URL__, { id_token: idToken });
    if (res.ok) {
        window.location.href = res.redirect;
    }
}

function wire() {
    const form   = document.getElementById('hp-login-form');
    const gbtn   = document.getElementById('hp-google');
    const status = document.getElementById('hp-login-status');

    const fail = (msg) => {
        status.textContent = msg;
        status.className = 'text-danger small mt-2';
    };

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('[type=submit]');
            window.HP.setLoading(btn, true);
            try {
                const { email, password } = window.HP.serializeForm(form);
                const cred = await signInWithEmailAndPassword(auth, email, password);
                await postToken(cred.user, status, btn);
            } catch (err) {
                window.HP.setLoading(btn, false);
                fail(err?.data?.message || 'No se pudo iniciar sesión. Verifica tus datos.');
            }
        });
    }

    if (gbtn) {
        gbtn.addEventListener('click', async () => {
            window.HP.setLoading(gbtn, true);
            try {
                const cred = await signInWithPopup(auth, new GoogleAuthProvider());
                await postToken(cred.user, status, gbtn);
            } catch (err) {
                window.HP.setLoading(gbtn, false);
                fail(err?.data?.message || 'No se pudo iniciar sesión con Google.');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', wire);
