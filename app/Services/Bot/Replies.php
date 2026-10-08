<?php

namespace App\Services\Bot;

use Illuminate\Support\Carbon;

/**
 * Bilingual, deterministic reply strings. (T5 adds cancel/modify/status copy —
 * this file replaces the T4 version.)
 */
class Replies
{
    public static function prettyDate(string $date, string $lang): string
    {
        $c = Carbon::parse($date)->locale($lang);
        return $lang === 'en'
            ? $c->translatedFormat('l, F j')
            : $c->translatedFormat('l j \d\e F');
    }

    /** One-line reservation descriptor. $s = [date,time,party,name] */
    public static function resLine(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        $party = $s['party'] ?? $s['party_size'] ?? '';
        return $lang === 'en'
            ? "{$d} at {$s['time']} · {$party} people ({$s['name']})"
            : "{$d} a las {$s['time']} · {$party} personas ({$s['name']})";
    }

    // ---- booking (T4) ------------------------------------------------------

    public static function greeting(string $lang): string
    {
        return $lang === 'en'
            ? "Hi! Welcome to Humareda Prime 🔥 I can help you book a table. What day, time and how many people?"
            : "¡Hola! Bienvenido a Humareda Prime 🔥 Con gusto te ayudo con tu reservación. ¿Para qué día, hora y cuántas personas?";
    }

    public static function askDate(string $lang): string
    { return $lang === 'en' ? "What day would you like to come?" : "¿Para qué día te gustaría venir?"; }

    public static function askTime(string $lang): string
    { return $lang === 'en' ? "What time works for you?" : "¿A qué hora te gustaría?"; }

    public static function askParty(string $lang): string
    { return $lang === 'en' ? "How many people will be dining?" : "¿Para cuántas personas?"; }

    public static function askName(string $lang): string
    { return $lang === 'en' ? "And under what name should I put the reservation?" : "¿A nombre de quién pongo la reservación?"; }

    public static function confirmSummary(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Please confirm your reservation:\n\n📅 {$d}\n🕐 {$s['time']}\n👥 {$s['party_size']} people\n📝 {$s['name']}\n\nShall I confirm it? (yes / no)"
            : "Confirma tu reservación, por favor:\n\n📅 {$d}\n🕐 {$s['time']}\n👥 {$s['party_size']} personas\n📝 {$s['name']}\n\n¿La confirmo? (sí / no)";
    }

    public static function confirmed(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "✅ All set, {$s['name']}! Your table for {$s['party_size']} is booked for {$d} at {$s['time']}. See you at Humareda Prime!"
            : "✅ ¡Listo, {$s['name']}! Tu mesa para {$s['party_size']} quedó reservada el {$d} a las {$s['time']}. ¡Te esperamos en Humareda Prime!";
    }

    /** @param string[] $alts */
    public static function alternatives(array $alts, string $lang): string
    {
        $list = implode(', ', $alts);
        return $lang === 'en'
            ? "That time isn't available. The closest open times are: {$list}. Which one works?"
            : "Esa hora no está disponible. Los horarios más cercanos son: {$list}. ¿Cuál te acomoda?";
    }

    public static function noAlternatives(string $lang): string
    {
        return $lang === 'en'
            ? "I'm sorry, we're fully booked then. Would another day work?"
            : "Lo siento, estamos llenos en ese horario. ¿Te funcionaría otro día?";
    }

    public static function outsideHours(string $lang, array $alts = []): string
    {
        $base = $lang === 'en' ? "That time is outside our service hours." : "Esa hora está fuera de nuestro horario de servicio.";
        if ($alts) {
            $list = implode(', ', $alts);
            $base .= $lang === 'en' ? " Nearest options: {$list}." : " Opciones cercanas: {$list}.";
        }
        return $base;
    }

    public static function closed(string $lang): string
    { return $lang === 'en' ? "We're closed that day. Would another day work?" : "Ese día estamos cerrados. ¿Te funcionaría otro día?"; }

    public static function invalidParty(string $lang): string
    { return $lang === 'en' ? "How many people will be dining? (please tell me a number)" : "¿Para cuántas personas? (indícame un número, por favor)"; }

    public static function bookingRace(string $lang): string
    { return $lang === 'en' ? "Sorry — that slot just filled up. Shall I look for a nearby time?" : "Lo siento, ese horario se acaba de llenar. ¿Busco una hora cercana?"; }

    public static function handoff(string $lang): string
    { return $lang === 'en' ? "Of course — I'll connect you with our team. Someone will reply here shortly." : "Claro, te conecto con nuestro equipo. En un momento te responden por aquí."; }

    public static function bigParty(string $lang): string
    { return $lang === 'en' ? "For larger groups I'll pass you to our team to arrange it personally. One moment." : "Para grupos grandes te paso con nuestro equipo para organizarlo a detalle. Un momento."; }

    public static function faqFallback(string $lang): string
    { return $lang === 'en' ? "Good question — let me check with the team and get back to you here." : "Buena pregunta — déjame confirmarlo con el equipo y te aviso por aquí."; }

    public static function pleaseType(string $lang, bool $audio = false): string
    {
        if ($audio) {
            return $lang === 'en' ? "I can't listen to voice notes yet — could you type your message?" : "Por ahora no puedo escuchar notas de voz — ¿me lo escribes, por favor?";
        }
        return $lang === 'en' ? "Could you send that as text so I can help?" : "¿Me lo mandas por texto para poder ayudarte?";
    }

    public static function fallback(string $lang): string
    { return $lang === 'en' ? "I can help you book, change or cancel a table. What would you like to do?" : "Puedo ayudarte a reservar, cambiar o cancelar una mesa. ¿Qué te gustaría hacer?"; }

    // ---- status / cancel / modify (T5) ------------------------------------

    public static function noReservation(string $lang): string
    {
        return $lang === 'en'
            ? "I don't see any upcoming reservation under your number. Would you like to book one?"
            : "No veo ninguna reservación próxima con tu número. ¿Te gustaría hacer una?";
    }

    public static function readback(array $s, string $lang): string
    {
        $line = self::resLine($s, $lang);
        return $lang === 'en' ? "Here's your reservation:\n📋 {$line}" : "Aquí está tu reservación:\n📋 {$line}";
    }

    /** @param array<int,array> $list */
    public static function listReservations(array $list, string $lang, string $action): string
    {
        $intro = match ([$lang, $action]) {
            ['en', 'cancel'] => "You have a few reservations. Which would you like to cancel?",
            ['en', 'modify'] => "You have a few reservations. Which would you like to change?",
            ['en', 'check']  => "Here are your reservations:",
            ['es', 'cancel'] => "Tienes varias reservaciones. ¿Cuál deseas cancelar?",
            ['es', 'modify'] => "Tienes varias reservaciones. ¿Cuál deseas modificar?",
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
        $line = self::resLine($s, $lang);
        return $lang === 'en'
            ? "Cancel this reservation?\n📋 {$line}\n\n(yes / no)"
            : "¿Cancelo esta reservación?\n📋 {$line}\n\n(sí / no)";
    }

    public static function cancelled(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Done — your reservation for {$d} at {$s['time']} is cancelled. Hope to see you another time!"
            : "Listo — tu reservación del {$d} a las {$s['time']} quedó cancelada. ¡Esperamos verte en otra ocasión!";
    }

    public static function cancelKept(string $lang): string
    { return $lang === 'en' ? "No problem — I've left your reservation as it is." : "Sin problema — dejo tu reservación tal cual."; }

    public static function askWhatToChange(array $s, string $lang): string
    {
        $line = self::resLine($s, $lang);
        return $lang === 'en'
            ? "Your current reservation:\n📋 {$line}\n\nWhat would you like to change — the day, time or number of people?"
            : "Tu reservación actual:\n📋 {$line}\n\n¿Qué te gustaría cambiar — el día, la hora o el número de personas?";
    }

    public static function confirmModify(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "Here's the updated reservation:\n\n📅 {$d}\n🕐 {$s['time']}\n👥 {$s['party_size']} people\n📝 {$s['name']}\n\nApply this change? (yes / no)"
            : "Así quedaría la reservación:\n\n📅 {$d}\n🕐 {$s['time']}\n👥 {$s['party_size']} personas\n📝 {$s['name']}\n\n¿Aplico el cambio? (sí / no)";
    }

    public static function modified(array $s, string $lang): string
    {
        $d = self::prettyDate($s['date'], $lang);
        return $lang === 'en'
            ? "✅ Updated! Your reservation is now {$d} at {$s['time']} for {$s['party_size']}. See you soon!"
            : "✅ ¡Actualizada! Tu reservación quedó el {$d} a las {$s['time']} para {$s['party_size']}. ¡Te esperamos!";
    }

    public static function modifyKept(string $lang): string
    { return $lang === 'en' ? "No problem — I've kept your original reservation." : "Sin problema — dejo tu reservación original."; }

    public static function selectionUnclear(string $lang): string
    { return $lang === 'en' ? "Which one? You can reply with the number." : "¿Cuál de ellas? Puedes responder con el número."; }
}
