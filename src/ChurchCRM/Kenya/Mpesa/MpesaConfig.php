<?php

namespace ChurchCRM\Kenya\Mpesa;

class MpesaConfig
{
    // Environment: 'sandbox' or 'production'
    const ENVIRONMENT = 'sandbox';

    // Daraja API credentials
    const CONSUMER_KEY    = 'dyfGGFvtjCNumz9NlfOgTHZ2VG8UPAN5bTnpB8gCx4Ptfn2O';
    const CONSUMER_SECRET = '6BEVMGUygzDGqJ1hyDshBPtdxTtqQKj9AVoAbm40AokirSchwtAPKu2zHl6hghJO';

    // STK Push (Lipa Na M-Pesa) credentials
    const SHORTCODE   = '174379';
    const PASSKEY     = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';

    // Callback URL — update this when deployed to a live server
    const CALLBACK_URL = 'https://scroll-negation-cyclist.ngrok-free.app/churchcrm-kenya/src/ChurchCRM/Kenya/Mpesa/MpesaCallback.php';

    // API base URLs
    const SANDBOX_URL    = 'https://sandbox.safaricom.co.ke';
    const PRODUCTION_URL = 'https://api.safaricom.co.ke';

    public static function getBaseURL(): string
    {
        return self::ENVIRONMENT === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
    }
}