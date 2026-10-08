# Humareda Prime Bot — Go-Live Checklist

Run top to bottom. Items marked **(client)** need the restaurant's input.

## 1. Capacity & settings (do this FIRST — replaces placeholders)
- [ ] **(client)** Confirm per-weekday numbers: open / last seating / close,
      slot minutes, turn minutes, **covers per slot**, **auto-confirm max**.
- [ ] Enter them in **Ajustes → Horario y cupos** (these overwrite the T1
      placeholder seed).
- [ ] **(client)** Reminder timing: fixed time (e.g. 11:00) or N hours before.
      Set in **Ajustes → Recordatorio**.
- [ ] Set restaurant name, staff notification email, default language.
- [ ] Run `php artisan booking:simulate <date> <peak-time> --party=4 --count=30 --cleanup`
      → "oversold? no".

## 2. Infrastructure
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`,
      `APP_TIMEZONE=America/Mexico_City`, `APP_LOCALE=es`.
- [ ] DB tables use **InnoDB** (required for the booking lock).
- [ ] `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
- [ ] Mailer configured (`MAIL_*`, `MAIL_FROM_ADDRESS`) for handoff emails.
- [ ] Cron (Hostinger): `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Confirm `reminders:dispatch` is registered in `bootstrap/app.php` schedule,
      and `ReservationObserver` in `AppServiceProvider::boot()`.
- [ ] `php artisan migrate --force` run; `php artisan config:cache route:cache` (optional).
- [ ] `npm run build` deployed (compiled assets present).

## 3. Firebase (panel auth)
- [ ] Service-account JSON uploaded, `FIREBASE_CREDENTIALS` path correct, out of git.
- [ ] Web config vars set; staff emails seeded/invited; test a real login.

## 4. WhatsApp — moving off the test number
- [ ] **(client)** Decide: take over the live **229-550-7070** line, or use
      **coexistence** (keep the Business app on the phone + Cloud API). Verify
      coexistence availability for the account before promising it.
- [ ] Complete **Meta Business verification**.
- [ ] Register the live number on the WhatsApp Business Platform (migrate the
      number into the WABA; this detaches it from the consumer app unless using
      coexistence).
- [ ] Point `.env`: `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`,
      `WHATSAPP_TOKEN` (permanent system-user token), `WHATSAPP_APP_SECRET`,
      `WHATSAPP_VERIFY_TOKEN`.
- [ ] Set the **production webhook**: `https://<APP_URL>/api/webhook/whatsapp`,
      verify token matches, subscribe to `messages`.
- [ ] Remove test-number recipient allow-list assumptions.

## 5. Templates (reminders)
- [ ] **(client)** Approve the same-day reminder **utility template** in Meta.
      Body with 3 vars in order: `{{1}}` name, `{{2}}` time, `{{3}}` party.
- [ ] Set `WHATSAPP_REMINDER_TEMPLATE` to the approved name.
- [ ] Send yourself a reminder outside the 24h window to confirm template delivery.

## 6. Smoke test on the live number
- [ ] Book end-to-end (text + a voice note), get the confirmation.
- [ ] FAQ (menu / hours) answers from the KB; an uncovered question hands off.
- [ ] "hablar con alguien" → staff email arrives, chat shows under **Requieren
      atención**, staff reply reaches the guest, "Regresar al bot" works.
- [ ] Cancel + modify flows; covers free/adjust (check **Reservaciones**).
- [ ] Trigger a reminder; reply CONFIRMO and CANCELO.

## 7. True concurrency check (oversell guard)
Open two `php artisan tinker` sessions; in each, nearly simultaneously:
```php
app(\App\Services\Availability\BookingService::class)
    ->book($contactId, '<date>', '<tight-slot>', <party>, 'Race A');
```
with a party that fits once but not twice → exactly one gets a reservation, the
other returns `full`. (Requires InnoDB.)

## 8. Client training (hand-off)
- [ ] Walk staff through: Reservaciones (create/edit/cancel), Conversaciones
      (reply, take over, return to bot), Conocimiento (edit FAQ), Ajustes.
- [ ] Explain the 24h window: free-form replies only work if the guest messaged
      in the last 24h; otherwise it's a template.
- [ ] Explain "Bot activo" toggle (kill switch) in Ajustes.
- [ ] Leave this checklist + the per-tier READMEs with them.
