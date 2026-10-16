<?php

namespace App\Services\Instagram;

use App\Services\Messenger\MessengerClient;

/**
 * Instagram DM send client (T12 Phase C, Route A: IG linked to the Facebook
 * Page). Instagram messaging runs on the Messenger Platform, so the Send API,
 * mark_seen, media fetch and profile lookup are identical — only the access
 * token differs (its own config key, defaulting to the Page token). Recipients
 * are IGSIDs.
 */
class InstagramClient extends MessengerClient
{
    public function __construct()
    {
        parent::__construct();
        $this->token = config('services.instagram.page_token');
    }
}
