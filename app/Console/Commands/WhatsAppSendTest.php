<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;

/**
 * Manual outbound test:  php artisan whatsapp:test 5212295550000 "Hola desde Humareda"
 *
 * Note: on the WhatsApp TEST number you can only send to recipient numbers
 * you've added to the allowed list in the Meta dashboard, and free-form text
 * only works inside the 24h window (message the number first from that phone).
 */
class WhatsAppSendTest extends Command
{
    protected $signature = 'whatsapp:test {to : Recipient in wa format, e.g. 5212295550000} {message?}';
    protected $description = 'Send a test WhatsApp text message via the Cloud API.';

    public function handle(WhatsAppClient $wa): int
    {
        $to      = $this->argument('to');
        $message = $this->argument('message') ?? 'Prueba de Humareda Prime ✅';

        $this->info("Enviando a {$to}...");
        $wamid = $wa->sendText($to, $message);

        if ($wamid) {
            $this->info("Enviado. wamid: {$wamid}");
            return self::SUCCESS;
        }

        $this->error('Falló el envío. Revisa storage/logs/laravel.log y las credenciales de WhatsApp.');
        return self::FAILURE;
    }
}
