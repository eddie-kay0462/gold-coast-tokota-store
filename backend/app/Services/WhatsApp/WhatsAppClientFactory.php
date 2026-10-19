<?php

namespace App\Services\WhatsApp;

/** Cloud API when credentials exist, the logging stand-in when they do not. */
class WhatsAppClientFactory
{
    public function make(): WhatsAppClient
    {
        return CloudApiClient::isConfigured() ? CloudApiClient::fromConfig() : new LogWhatsAppClient;
    }
}
