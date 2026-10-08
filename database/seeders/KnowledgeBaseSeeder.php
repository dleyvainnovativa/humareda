<?php

namespace Database\Seeders;

use App\Models\KnowledgeEntry;
use Illuminate\Database\Seeder;

/**
 * Initial FAQ knowledge base, drawn from the public site. Staff will edit
 * these in the panel (T8). The bot answers ONLY from active entries and never
 * invents — anything not covered here should escalate to a human.
 *
 * The dietary entry is deliberately conservative: it does NOT promise
 * allergen-free dishes. Update the veggie/dietary answer with the client's
 * real options before launch.
 */
class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'category'    => 'hours',
                'question_es' => '¿Cuál es el horario?',
                'answer_es'   => 'Domingo a jueves de 13:00 a 22:00 h, y viernes y sábado de 13:00 a 24:00 h.',
                'question_en' => 'What are your hours?',
                'answer_en'   => 'Sunday to Thursday 1:00 PM–10:00 PM, and Friday and Saturday 1:00 PM–12:00 AM.',
            ],
            [
                'category'    => 'location',
                'question_es' => '¿Dónde están ubicados?',
                'answer_es'   => 'Blvd. Vicente Fox Quesada 106, Costa Sol, 94290 Boca del Río, Veracruz. Frente al mar.',
                'question_en' => 'Where are you located?',
                'answer_en'   => 'Blvd. Vicente Fox Quesada 106, Costa Sol, 94290 Boca del Río, Veracruz. Right by the sea.',
            ],
            [
                'category'    => 'menu',
                'question_es' => '¿Qué cortes manejan?',
                'answer_es'   => 'Somos steak house de cortes finos a las brasas: Rib Eye, Rib Eye 2", Arrachera, T-Bone, Top Sirloin, New York, Cowboy, Porterhouse y Costillar de Rib Eye. También mixología de autor.',
                'question_en' => 'What cuts do you offer?',
                'answer_en'   => 'We are a premium grill steak house: Rib Eye, Rib Eye 2", Arrachera (skirt), T-Bone, Top Sirloin, New York, Cowboy, Porterhouse and Rib Eye short rib. Plus signature cocktails.',
            ],
            [
                'category'    => 'dietary',
                'question_es' => '¿Tienen opciones vegetarianas?',
                // Conservative placeholder — confirm real options with the client.
                'answer_es'   => 'Somos principalmente steak house. Contamos con algunas guarniciones y opciones ligeras; con gusto te confirmo el detalle con el equipo si buscas algo específico o tienes alguna alergia.',
                'question_en' => 'Do you have vegetarian options?',
                'answer_en'   => 'We are primarily a steak house. We do have some sides and lighter options; I can confirm specifics with the team if you have a particular need or any allergy.',
            ],
            [
                'category'    => 'policy',
                'question_es' => '¿Cómo hago una reservación?',
                'answer_es'   => 'Por aquí mismo por WhatsApp. Solo dime la fecha, la hora y cuántas personas.',
                'question_en' => 'How do I make a reservation?',
                'answer_en'   => 'Right here on WhatsApp. Just tell me the date, time and how many people.',
            ],
        ];

        foreach ($entries as $i => $entry) {
            KnowledgeEntry::updateOrCreate(
                ['question_es' => $entry['question_es']],
                array_merge($entry, ['is_active' => true, 'sort_order' => $i]),
            );
        }
    }
}
