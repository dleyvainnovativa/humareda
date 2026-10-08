<?php

namespace App\Services\Bot;

use Illuminate\Support\Carbon;

/**
 * Bilingual, deterministic reply strings for transactional turns. These are
 * templated (no LLM) so booking prompts and confirmations are predictable.
 * FAQ answers come from the model (grounded); everything here is fixed copy.
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

    public static function greeting(string $lang): string
    {
        return $lang === 'en'
            ? "Hi! Welcome to Humareda Prime 🔥 I can help you book a table. What day, time and how many people?"
            : "¡Hola! Bienvenido a Humareda Prime 🔥 Con gusto te ayudo con tu reservación. ¿Para qué día, hora y cuántas personas?";
    }

    public static function askDate(string $lang): string
    {
        return $lang === 'en' ? "What day would you like to come?" : "¿Para qué día te gustaría venir?";
    }

    public static function askTime(string $lang): string
    {
        return $lang === 'en' ? "What time works for you?" : "¿A qué hora te gustaría?";
    }

    public static function askParty(string $lang): string
    {
        return $lang === 'en' ? "How many people will be dining?" : "¿Para cuántas personas?";
    }

    public static function askName(string $lang): string
    {
        return $lang === 'en' ? "And under what name should I put the reservation?" : "¿A nombre de quién pongo la reservación?";
    }

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
        $base = $lang === 'en'
            ? "That time is outside our service hours."
            : "Esa hora está fuera de nuestro horario de servicio.";
        if ($alts) {
            $list = implode(', ', $alts);
            $base .= $lang === 'en' ? " Nearest options: {$list}." : " Opciones cercanas: {$list}.";
        }
        return $base;
    }

    public static function closed(string $lang): string
    {
        return $lang === 'en'
            ? "We're closed that day. Would another day work?"
            : "Ese día estamos cerrados. ¿Te funcionaría otro día?";
    }

    public static function invalidParty(string $lang): string
    {
        return $lang === 'en'
            ? "How many people will be dining? (please tell me a number)"
            : "¿Para cuántas personas? (indícame un número, por favor)";
    }

    public static function bookingRace(string $lang): string
    {
        return $lang === 'en'
            ? "Sorry — that slot just filled up. Shall I look for a nearby time?"
            : "Lo siento, ese horario se acaba de llenar. ¿Busco una hora cercana?";
    }

    public static function handoff(string $lang): string
    {
        return $lang === 'en'
            ? "Of course — I'll connect you with our team. Someone will reply here shortly."
            : "Claro, te conecto con nuestro equipo. En un momento te responden por aquí.";
    }

    public static function bigParty(string $lang): string
    {
        return $lang === 'en'
            ? "For larger groups I'll pass you to our team to arrange it personally. One moment."
            : "Para grupos grandes te paso con nuestro equipo para organizarlo a detalle. Un momento.";
    }

    public static function faqFallback(string $lang): string
    {
        return $lang === 'en'
            ? "Good question — let me check with the team and get back to you here."
            : "Buena pregunta — déjame confirmarlo con el equipo y te aviso por aquí.";
    }

    public static function pleaseType(string $lang, bool $audio = false): string
    {
        if ($audio) {
            return $lang === 'en'
                ? "I can't listen to voice notes yet — could you type your message?"
                : "Por ahora no puedo escuchar notas de voz — ¿me lo escribes, por favor?";
        }
        return $lang === 'en'
            ? "Could you send that as text so I can help?"
            : "¿Me lo mandas por texto para poder ayudarte?";
    }

    public static function comingSoon(string $lang): string
    {
        return $lang === 'en'
            ? "I'll connect you with our team to help with that."
            : "Te conecto con nuestro equipo para ayudarte con eso.";
    }

    public static function fallback(string $lang): string
    {
        return $lang === 'en'
            ? "I can help you book, change or cancel a table. What would you like to do?"
            : "Puedo ayudarte a reservar, cambiar o cancelar una mesa. ¿Qué te gustaría hacer?";
    }
}
