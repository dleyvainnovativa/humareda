<?php

namespace App\Services\Bot;

/**
 * Pure yes/no + handoff keyword detection. Cheap fast-paths that avoid or
 * complement the LLM. Deliberately conservative: unclear text is neither
 * yes nor no.
 */
class Affirmation
{
    public static function isYes(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/\b(si|sip|claro|confirmo|confirmar|dale|va|dalee|dele|dele pues|dele que si|correcto|dele si|de acuerdo|ok|okay|okey|perfecto|exacto|asi es|yes|yeah|yep|confirm|sounds good|correct)\b/u',
            $t
        );
    }

    public static function isNo(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/\b(no|nel|cambia|cambiar|mejor|otro|otra|espera|cancela|nop|nope|change|wait)\b/u',
            $t
        );
    }

    public static function wantsHuman(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/(hablar con (alguien|una persona|un humano|un agente|alguno|asesor)|con una persona|una persona real|un humano|un agente|asesor|talk to (someone|a (human|person|rep))|speak to (someone|a (human|person))|human being|real person|customer service)/u',
            $t
        );
    }

    private static function norm(string $text): string
    {
        $t = mb_strtolower(trim($text));
        // strip accents so "sí" == "si"
        $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return $t;
    }
}
