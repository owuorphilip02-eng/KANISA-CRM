<?php

namespace ChurchCRM\Kenya\Mpesa;

class MpesaService
{
    /**
     * Get OAuth access token from Daraja API
     */
    public static function getAccessToken(): string
    {
        $url = MpesaConfig::getBaseURL() . '/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode(MpesaConfig::CONSUMER_KEY . ':' . MpesaConfig::CONSUMER_SECRET);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['access_token'] ?? '';
    }

    /**
     * Initiate STK Push — sends M-Pesa payment prompt to member's phone
     *
     * @param string $phone   Member phone number e.g. 254712345678
     * @param int    $amount  Amount in KES
     * @param string $account Reference e.g. member name or ID
     * @param string $description e.g. "Tithe - June 2026"
     */
    public static function stkPush(string $phone, int $amount, string $account, string $description): array
    {
        $token     = self::getAccessToken();
        $timestamp = date('YmdHis');
        $password  = base64_encode(MpesaConfig::SHORTCODE . MpesaConfig::PASSKEY . $timestamp);
        $url       = MpesaConfig::getBaseURL() . '/mpesa/stkpush/v1/processrequest';

        $payload = [
            'BusinessShortCode' => MpesaConfig::SHORTCODE,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => MpesaConfig::SHORTCODE,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => MpesaConfig::CALLBACK_URL,
            'AccountReference'  => $account,
            'TransactionDesc'   => $description,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * Handle M-Pesa callback and return parsed payment data
     */
    public static function handleCallback(): ?array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (empty($data)) {
            return null;
        }

        $body = $data['Body']['stkCallback'] ?? null;
        if (!$body || $body['ResultCode'] !== 0) {
            return null; // Payment failed or cancelled
        }

        $items = $body['CallbackMetadata']['Item'] ?? [];
        $parsed = [];
        foreach ($items as $item) {
            $parsed[$item['Name']] = $item['Value'] ?? null;
        }

        return [
            'amount'        => $parsed['Amount'],
            'receipt'       => $parsed['MpesaReceiptNumber'],
            'phone'         => $parsed['PhoneNumber'],
            'transaction_date' => $parsed['TransactionDate'],
        ];
    }
}