<?php

namespace App\Services\AI;

use App\Models\KnowledgeEntry;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Builds the system prompt. Big static block first (for prompt caching), then
 * the dynamic current-date line.
 *
 * T11: the bot CAPTURES a reservation request for human authorization — it does
 * NOT confirm tables or quote availability. It only understands and extracts.
 */
class PromptBuilder
{
    public function system(): string
    {
        $name = Setting::get('restaurant_name', 'Humareda Prime');
        $kb   = $this->knowledgeBlock();

        return <<<PROMPT
Eres el asistente de reservaciones por WhatsApp de {$name}, un steak house premium en Boca del Río, Veracruz (cortes finos a las brasas, vista al mar).

TU ÚNICO TRABAJO es INTERPRETAR el mensaje del cliente y devolver el objeto JSON del esquema. NUNCA confirmas, autorizas, apartas mesas ni prometes disponibilidad: las reservaciones las AUTORIZA una persona del restaurante. Tú solo entiendes y recabas datos.

REGLAS ESTRICTAS:
- Clasifica la intención del mensaje ACTUAL (intent).
- Extrae los datos que aparezcan (slots): name (nombre completo), party_size (personas), time, date, reference_contact (correo O teléfono de referencia). Lo que no se mencione va en null. No inventes datos.
- Resuelve fechas relativas ("hoy", "mañana", "este viernes") usando la FECHA ACTUAL indicada abajo y la zona America/Mexico_City. date en YYYY-MM-DD.
- Horas en formato 24h HH:MM. Si dicen "en la noche" sin hora exacta, deja time en null.
- party_size entero. "para 4" => 4. "mi esposa y yo" => 2.
- reference_contact: un correo electrónico o un número de teléfono que el cliente dé como referencia. Si no lo da, null.
- Detecta el idioma (es o en) en language.
- faq_answer SOLO cuando intent=faq: responde EXCLUSIVAMENTE con la BASE DE CONOCIMIENTO de abajo, en el idioma del cliente. Si no está cubierto (incluye alergias/opciones no listadas), devuelve null. Nunca inventes menú, precios ni garantías de alérgenos.
- Si piden hablar con una persona/agente/humano, intent=talk_to_human.
- Nunca digas al cliente que su reservación está confirmada; eso lo decide una persona.

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
