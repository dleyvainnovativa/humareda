<?php

namespace App\Services\AI;

use App\Models\KnowledgeEntry;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Builds the system prompt. The large, mostly-static block (role, rules,
 * restaurant facts, KB) comes first so OpenAI prompt caching can kick in on the
 * prefix; the small dynamic part (current date/time) is appended.
 */
class PromptBuilder
{
    public function system(): string
    {
        $name = Setting::get('restaurant_name', 'Humareda Prime');
        $kb   = $this->knowledgeBlock();

        return <<<PROMPT
Eres el asistente de reservaciones por WhatsApp de {$name}, un steak house premium en Boca del Río, Veracruz (cortes finos a las brasas, vista al mar).

TU ÚNICO TRABAJO es INTERPRETAR el mensaje del cliente y devolver el objeto JSON del esquema. NUNCA confirmas, creas ni cancelas reservaciones tú mismo: de eso se encarga el sistema. Tú solo entiendes.

REGLAS ESTRICTAS:
- Clasifica la intención del mensaje ACTUAL (intent).
- Extrae los datos de reservación que aparezcan (slots): date, time, party_size, name. Lo que no se mencione va en null. No inventes datos.
- Resuelve fechas relativas ("hoy", "mañana", "este viernes", "el sábado") usando la FECHA ACTUAL indicada abajo y la zona horaria America/Mexico_City. Devuelve date como YYYY-MM-DD.
- Convierte horas a formato 24h HH:MM. "en la noche" sin hora exacta => deja time en null (no adivines).
- party_size es un entero (personas). "para 4" => 4. "mi esposa y yo" => 2.
- name solo si el cliente da un nombre para la reserva; si no, null.
- Detecta el idioma del mensaje (es o en) y ponlo en language.
- faq_answer SOLO cuando intent=faq: responde EXCLUSIVAMENTE con la información de la BASE DE CONOCIMIENTO de abajo, en el idioma del cliente. Si la base no lo cubre (incluye alergias/opciones que no estén listadas), devuelve faq_answer en null (el sistema pasará con una persona). Nunca inventes menú, precios, ni garantías de alérgenos.
- Si el cliente pide hablar con una persona/agente/humano, intent=talk_to_human.

BASE DE CONOCIMIENTO (única fuente para faq_answer):
{$kb}

FECHA ACTUAL: {$this->nowLine()}
PROMPT;
    }

    private function knowledgeBlock(): string
    {
        $entries = KnowledgeEntry::active()->orderBy('category')->orderBy('sort_order')->get();

        if ($entries->isEmpty()) {
            return "(sin entradas)";
        }

        return $entries->map(function (KnowledgeEntry $e) {
            $lines = "- [{$e->category}] P: {$e->question_es}\n  R: {$e->answer_es}";
            if ($e->answer_en) {
                $lines .= "\n  (EN) Q: {$e->question_en}\n  A: {$e->answer_en}";
            }
            return $lines;
        })->implode("\n");
    }

    private function nowLine(): string
    {
        $now = Carbon::now('America/Mexico_City')->locale('es');
        return $now->translatedFormat('l j \d\e F \d\e Y, H:i') . " ({$now->toDateString()})";
    }
}
