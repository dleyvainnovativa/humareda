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

    /**
     * Invoice / CFDI intent. Deterministic safety net so a factura request is
     * never swallowed by the reservation fallback, even when the model
     * misclassifies it (it does — see real transcripts).
     */
    public static function wantsInvoice(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/(\bfactura(r|s|cion|me|ndo)?\b|\bcfdi\b|\brfc\b|comprobante fiscal|datos fiscales|dato fiscal)/u',
            $t
        );
    }

    /**
     * A thank-you / closing / acknowledgement. Used (only when idle with no
     * booking in progress) to reply warmly instead of re-dumping the greeting.
     * Deliberately excludes bare "ok/sí" and openers like "buen día".
     */
    public static function isClosing(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/\b(gracias|muchas gracias|mil gracias|perfecto|excelente|listo|de acuerdo|igualmente|muy amable|que esten bien|nos vemos|hasta luego|hasta pronto|thank you|thanks)\b/u',
            $t
        );
    }

    /**
     * A special seating / occasion request a person must arrange (sea view,
     * terrace, smoking area, private area, birthday, anniversary, decoration).
     * Recurs in real chats and the bot used to ignore it.
     */
    public static function wantsSpecialRequest(string $text): bool
    {
        $t = self::norm($text);
        return (bool) preg_match(
            '/(vista al mar|frente al mar|con vista|al mar\b|terraza|fumad|area de fumar|privad[oa]|cumpleanos|aniversario|festej|sorpresa|decora|mariachi|pastel)/u',
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
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return $t;
    }
}
