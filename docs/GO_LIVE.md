# Humareda Prime Bot — Go-Live Checklist (updated for T11: Request & Approve)

Run top to bottom. Items marked **(client)** need the restaurant's input.

> **Model reminder (T11):** the bot **captures** reservation requests; a **human
> authorizes** each one from the panel. The bot never confirms a table on its
> own and holds no covers — capacity shown in the panel is advisory. Plan staff
> time to work the "Por autorizar" queue.

## 1. Settings (do this FIRST)
- [ ] **(client)** Service hours per weekday (open / last seating / close) in
      **Ajustes → Horario y cupos**. The capacity fields (covers/slot, turn,
      auto-confirm max) are now only an **advisory load reference** for staff —
      set sensible values but they no longer block the bot.
- [ ] **(client)** Reminder timing in **Ajustes → Recordatorio**: fixed time
      (e.g. 11:00) or N hours before.
- [ ] Restaurant name, **staff notification email** (receives both handoff and
      reservation-authorization emails), default language.
- [ ] **(client)** Agree a **staff SLA**: how fast pending requests get
      authorized during service (e.g. within 10–15 min). Guests wait in "en
      revisión" until someone acts. The dashboard **"Por autorizar"** KPI + the
      email keep the queue visible.

## 2. Infrastructure
- [ ] `php artisan migrate --force` — includes the **T11 migration**
      (`reference_contact`, `reviewed_at`, `reviewed_by` on reservations).
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`,
      `APP_TIMEZONE=America/Mexico_City`, `APP_LOCALE=es`.
- [ ] `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
      (InnoDB is still fine to require, though the oversell lock is no longer on
      the bot's path.)
- [ ] Mailer configured (`MAIL_*`, `MAIL_FROM_ADDRESS`) — needed for BOTH the
      handoff email and the new **reservation-authorization** email.
- [ ] Cron (Hostinger): `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- [ ] `bootstrap/app.php` schedule has:
      `$schedule->command('reminders:dispatch')->everyMinute()->withoutOverlapping();`
- [ ] `AppServiceProvider::boot()` registers the observer **and both listeners**:
      ```php
      \App\Models\Reservation::observe(\App\Observers\ReservationObserver::class);
      \Illuminate\Support\Facades\Event::listen(
          \App\Events\ConversationHandedOff::class, \App\Listeners\NotifyStaffOfHandoff::class);
      \Illuminate\Support\Facades\Event::listen(
          \App\Events\ReservationRequested::class, \App\Listeners\NotifyStaffOfRequest::class);
      ```
      (Skip the `Event::listen` lines if your project has event auto-discovery on.)
- [ ] `npm run build` deployed.

## 3. Firebase (panel auth)
- [ ] Service-account JSON uploaded, `FIREBASE_CREDENTIALS` path correct, out of git.
- [ ] Web config vars set; staff emails seeded/invited; test a real login.

## 4. WhatsApp — moving off the test number
- [ ] **(client)** Take over the live **229-550-7070** line, or use
      **coexistence** (verify availability for the account first).
- [ ] Complete **Meta Business verification**.
- [ ] Register the live number on the WhatsApp Business Platform.
- [ ] `.env`: `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`, `WHATSAPP_TOKEN`
      (permanent system-user token), `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`.
- [ ] Production webhook: `https://<APP_URL>/api/webhook/whatsapp`, verify token
      matches, subscribe to `messages`.

## 5. Templates (reminders)
- [ ] **(client)** Approve the same-day reminder **utility template**. Body with
      3 vars in order: `{{1}}` name, `{{2}}` time, `{{3}}` party.
- [ ] Set `WHATSAPP_REMINDER_TEMPLATE` to the approved name.
- [ ] (Approval/decline and the "en revisión" messages are free-form service
      messages — no template needed, since the guest just messaged.)

## 6. Smoke test on the live number (request → approve)
- [ ] Say "quiero reservar" → bot asks for the 5 fields (nombre completo,
      personas, hora, fecha, correo/teléfono de referencia).
- [ ] Answer them (and try all-at-once + a voice note) → data summary → "sí" →
      **"tu reservación está en proceso de revisión…"**.
- [ ] Staff email arrives; request shows under **Reservaciones → Por autorizar**
      and on the dashboard KPI.
- [ ] **Autorizar** → guest gets "confirmada" + a same-day reminder is scheduled.
- [ ] **Rechazar** (a different request) → guest gets the polite decline.
- [ ] While a request is pending, ask a menu question → bot still answers.
- [ ] Modify a reservation → it returns to **Por autorizar** for re-approval.
      Cancel → instant.
- [ ] Reminder fires (`reminders:dispatch`); reply CONFIRMO and CANCELO.
- [ ] "hablar con alguien" → handoff email + **Conversaciones → Requieren
      atención**; staff reply reaches the guest; "Regresar al bot" works.

## 7. Accounting sanity (optional in the advisory model)
`php artisan booking:simulate <date> <time> --party=4 --count=30 --cleanup`
still runs the staff-create path through the locked BookingService. It's no
longer a launch blocker (the bot doesn't hold covers), but it's a quick check
that staff direct-create accounting behaves.

## 8. Client training (hand-off)
- [ ] **The queue is the job:** show staff **Reservaciones → Por autorizar** —
      each row shows the request + the day's confirmed load (advisory). Autorizar
      / Rechazar message the guest automatically.
- [ ] Conversaciones (reply, take over, return to bot), Conocimiento (edit FAQ),
      Ajustes (hours, reminders, bot kill-switch).
- [ ] Explain the 24h window for free-form replies; the bot's "en revisión"
      message buys time but the authorization should be prompt (the agreed SLA).
- [ ] Leave this checklist + the per-tier READMEs with them.
