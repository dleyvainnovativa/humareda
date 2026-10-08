<?php

namespace App\Services\Bot;

use Illuminate\Support\Carbon;

/**
 * Bilingual, deterministic reply strings. T11 reframes the booking endpoint
 * around "capture a request -> human authorizes": the bot never says a table is
 * confirmed; it sends an "in review" message and staff decide.
 */
class Replies
{
    public static function prettyDate(string $date, string $lang): string
    {
        $c = Carbon::parse($date)->locale($lang);
        return $lang === 'en' ? $c->translatedFormat('l, F j') : $c->translatedFormat('l j \d\e F');
    }

    public static function resLine(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        $party = $s['party'] ?? $s['party_size'] ?? '';
        return $lang === 'en'
            ? "{$d} at {$s['time']} · {$party} people ({$s['name']})"
            : "{$d} a las {$s['time']} · {$party} personas ({$s['name']})";
    }

    public static function greeting(string $lang): string
    {
        return $lang === 'en'
            ? "Hi! Welcome to Humareda Prime 🔥 I can help you request a table. What day, time and how many people?"
            : "¡Hola! Bienvenido a Humareda Prime 🔥 Con gusto te ayudo con tu solicitud de reservación. ¿Para qué día, hora y cuántas personas?";
    }

    /** First booking prompt: present the full set of fields the client listed. */
    public static function requestForm(string $lang): string
    {
        return $lang === 'en'
            ? "Great! To request your reservation, could you share:\n• Full name\n• Number of people\n• Time\n• Date\n• Reference email or phone"
            : "¡Con gusto! Para tu solicitud de reservación, ¿me compartes estos datos?\n• Nombre completo\n• Número de personas\n• Hora\n• Fecha\n• Correo o teléfono de referencia";
    }

    public static function askName(string $lang): string
    { return $lang === 'en' ? "What's the full name for the reservation?" : "¿A nombre de quién? (nombre completo)"; }

    public static function askParty(string $lang): string
    { return $lang === 'en' ? "How many people?" : "¿Para cuántas personas?"; }

    public static function askTime(string $lang): string
    { return $lang === 'en' ? "What time?" : "¿A qué hora?"; }

    public static function askDate(string $lang): string
    { return $lang === 'en' ? "What day?" : "¿Para qué día?"; }

    public static function askReference(string $lang): string
    { return $lang === 'en' ? "And an email or phone as a reference?" : "¿Un correo o teléfono de referencia?"; }

    public static function pastDate(string $lang): string
    { return $lang === 'en' ? "That date has already passed — what day would you like?" : "Esa fecha ya pasó — ¿para qué día te gustaría?"; }

    public static function invalidParty(string $lang): string
    { return $lang === 'en' ? "How many people? (a number, please)" : "¿Para cuántas personas? (un número, por favor)"; }

    /** Data-check summary before submitting the request. */
    public static function confirmSummary(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Please confirm your details to send the request:\n\n👤 {$s['name']}\n👥 {$s['party_size']} people\n🕐 {$s['time']}\n📅 {$d}\n📇 {$s['reference_contact']}\n\nAre these correct? (yes / no)"
            : "Confirma tus datos para enviar la solicitud:\n\n👤 {$s['name']}\n👥 {$s['party_size']} personas\n🕐 {$s['time']}\n📅 {$d}\n📇 {$s['reference_contact']}\n\n¿Son correctos? (sí / no)";
    }

    /** After the guest confirms: request captured, awaiting human authorization. */
    public static function inReview(string $lang): string
    {
        return $lang === 'en'
            ? "✅ Got it! Your reservation is under review — give us a few minutes to authorize it and we'll confirm here."
            : "✅ ¡Listo! Tu reservación está en proceso de revisión, danos unos minutos para autorizarla y te confirmamos por aquí.";
    }

    public static function changeWhat(string $lang): string
    {
        return $lang === 'en'
            ? "No problem — what should I change? (name, people, time, date or reference)"
            : "Sin problema — ¿qué cambio? (nombre, personas, hora, fecha o referencia)";
    }

    // ---- authorization outcomes (sent by staff action) --------------------

    public static function approved(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "🎉 Your reservation is confirmed: {$d} at {$s['time']} for {$s['party_size']}. See you at Humareda Prime!"
            : "🎉 ¡Tu reservación quedó confirmada! {$d} a las {$s['time']} para {$s['party_size']}. ¡Te esperamos en Humareda Prime!";
    }

    public static function rejected(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "We're very sorry — we couldn't authorize your reservation for {$d} at {$s['time']}. Could we offer another time? Just reply here."
            : "Lo sentimos mucho — no pudimos autorizar tu reservación para el {$d} a las {$s['time']}. ¿Te proponemos otra opción? Respóndenos por aquí.";
    }

    // ---- status / cancel / modify ----------------------------------------

    public static function noReservation(string $lang): string
    {
        return $lang === 'en'
            ? "I don't see any upcoming reservation under your number. Would you like to request one?"
            : "No veo ninguna reservación próxima con tu número. ¿Te gustaría solicitar una?";
    }

    public static function readback(array $s, string $lang): string
    {
        $line = self::resLine($s, $lang);
        $tag  = ($s['status'] ?? 'confirmed') === 'pending'
            ? ($lang === 'en' ? ' (under review)' : ' (en revisión)') : '';
        return $lang === 'en' ? "Here's your reservation:\n📋 {$line}{$tag}" : "Aquí está tu reservación:\n📋 {$line}{$tag}";
    }

    public static function listReservations(array $list, string $lang, string $action): string
    {
        $intro = match ([$lang, $action]) {
            ['en', 'cancel'] => "Which would you like to cancel?",
            ['en', 'modify'] => "Which would you like to change?",
            ['en', 'check']  => "Here are your reservations:",
            ['es', 'cancel'] => "¿Cuál deseas cancelar?",
            ['es', 'modify'] => "¿Cuál deseas modificar?",
            default          => "Estas son tus reservaciones:",
        };
        $lines = [];
        foreach ($list as $i => $s) {
            $lines[] = ($i + 1) . ') ' . self::resLine($s, $lang);
        }
        return $intro . "\n\n" . implode("\n", $lines);
    }

    public static function confirmCancel(array $s, string $lang): string
    {
        return $lang === 'en'
            ? "Cancel this reservation?\n📋 " . self::resLine($s, $lang) . "\n\n(yes / no)"
            : "¿Cancelo esta reservación?\n📋 " . self::resLine($s, $lang) . "\n\n(sí / no)";
    }

    public static function cancelled(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Done — your reservation for {$d} at {$s['time']} is cancelled. Hope to see you another time!"
            : "Listo — tu reservación del {$d} a las {$s['time']} quedó cancelada. ¡Esperamos verte en otra ocasión!";
    }

    public static function cancelKept(string $lang): string
    { return $lang === 'en' ? "No problem — I've left it as it is." : "Sin problema — la dejo tal cual."; }

    public static function askWhatToChange(array $s, string $lang): string
    {
        return $lang === 'en'
            ? "Your current reservation:\n📋 " . self::resLine($s, $lang) . "\n\nWhat would you like to change — day, time or number of people?"
            : "Tu reservación actual:\n📋 " . self::resLine($s, $lang) . "\n\n¿Qué te gustaría cambiar — día, hora o personas?";
    }

    /** Modify re-enters review (T11, decision 4). */
    public static function modifyInReview(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Got it — your change to {$d} at {$s['time']} for {$s['party_size']} is under review. We'll confirm here shortly."
            : "Listo — tu cambio a {$d} a las {$s['time']} para {$s['party_size']} está en revisión. Te confirmamos por aquí en breve.";
    }

    public static function handoff(string $lang): string
    { return $lang === 'en' ? "Of course — I'll connect you with our team. Someone will reply here shortly." : "Claro, te conecto con nuestro equipo. En un momento te responden por aquí."; }

    public static function faqFallback(string $lang): string
    { return $lang === 'en' ? "Good question — let me check with the team and get back to you here." : "Buena pregunta — déjame confirmarlo con el equipo y te aviso por aquí."; }

    public static function pleaseType(string $lang, bool $audio = false): string
    {
        if ($audio) {
            return $lang === 'en' ? "I can't listen to voice notes yet — could you type your message?" : "Por ahora no puedo escuchar notas de voz — ¿me lo escribes, por favor?";
        }
        return $lang === 'en' ? "Could you send that as text so I can help?" : "¿Me lo mandas por texto para poder ayudarte?";
    }

    public static function selectionUnclear(string $lang): string
    { return $lang === 'en' ? "Which one? You can reply with the number." : "¿Cuál de ellas? Puedes responder con el número."; }

    public static function fallback(string $lang): string
    { return $lang === 'en' ? "I can help you request, change or cancel a table. What would you like to do?" : "Puedo ayudarte a solicitar, cambiar o cancelar una mesa. ¿Qué te gustaría hacer?"; }
}
