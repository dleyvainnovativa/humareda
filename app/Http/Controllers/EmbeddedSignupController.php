<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Embedded Signup (Tech Provider / Coexistence) — T12-TP Phase 1b.
 *
 * The onboarding page launches Meta's Embedded Signup in the browser. When the
 * guest finishes, the frontend hands us:
 *   - an OAuth `code` (from FB.login, response_type=code)
 *   - the `waba_id` + `phone_number_id` (from the ES session-info events)
 *
 * complete() exchanges the code for a business token, subscribes our app to the
 * WABA's webhooks, and stores the number in `whatsapp_numbers`. From then on
 * ChannelManager routes that number's traffic through its own credentials.
 *
 * Config (set once the Meta Tech Provider app + ES config exist):
 *   META_APP_ID, META_APP_SECRET, META_ES_CONFIG_ID, META_GRAPH_VERSION
 * Until those are set the page shows a "configure first" notice; the code path
 * is complete and needs no further changes to go live.
 */
class EmbeddedSignupController extends Controller
{
    public function index(): View
    {
        return view('whatsapp.connect', [
            'numbers'  => WhatsAppNumber::orderByDesc('is_active')->orderBy('label')->get(),
            'appId'    => (string) config('services.meta.app_id'),
            'configId' => (string) config('services.meta.es_config_id'),
            'graphVer' => (string) config('services.meta.graph_version', 'v21.0'),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'            => ['required', 'string'],
            'waba_id'         => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'label'           => ['nullable', 'string', 'max:120'],
        ]);

        $token = $this->exchangeCodeForToken($data['code']);
        if (! $token) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo intercambiar el código por un token. Revisa META_APP_ID y META_APP_SECRET.',
            ], 422);
        }

        $this->subscribeApp($data['waba_id'], $token);

        $number = WhatsAppNumber::updateOrCreate(
            ['phone_number_id' => $data['phone_number_id']],
            [
                'waba_id'        => $data['waba_id'],
                'token'          => $token,
                'label'          => $data['label'] ?: null,
                'is_coexistence' => true,
                'is_active'      => true,
            ],
        );

        Log::info('WhatsApp number onboarded via Embedded Signup', [
            'phone_number_id' => $number->phone_number_id,
            'waba_id'         => $number->waba_id,
        ]);

        return response()->json([
            'ok'     => true,
            'number' => [
                'id'              => $number->id,
                'phone_number_id' => $number->phone_number_id,
                'waba_id'         => $number->waba_id,
            ],
        ]);
    }

    public function toggle(WhatsAppNumber $number): JsonResponse
    {
        $number->update(['is_active' => ! $number->is_active]);

        return response()->json(['ok' => true, 'is_active' => $number->is_active]);
    }

    public function destroy(WhatsAppNumber $number): JsonResponse
    {
        $number->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Exchange the Embedded Signup authorization code for a business access token.
     */
    private function exchangeCodeForToken(string $code): ?string
    {
        $v = config('services.meta.graph_version', 'v21.0');

        try {
            $res = Http::get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'client_id'     => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'code'          => $code,
            ]);

            if ($res->failed()) {
                Log::error('ES token exchange failed', ['body' => $res->body()]);
                return null;
            }

            return $res->json('access_token');
        } catch (\Throwable $e) {
            Log::error('ES token exchange exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Subscribe our app to the WABA so inbound webhooks (including the
     * coexistence fields) start flowing for this number.
     */
    private function subscribeApp(string $wabaId, string $token): void
    {
        $v = config('services.meta.graph_version', 'v21.0');

        try {
            Http::withToken($token)->post("https://graph.facebook.com/{$v}/{$wabaId}/subscribed_apps");
        } catch (\Throwable $e) {
            Log::warning('ES subscribe app failed', ['waba' => $wabaId, 'error' => $e->getMessage()]);
        }
    }
}
