<?php

namespace IngeniusTrackingPaypal\Admin;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use RuntimeException;

class PayPalConnection
{
    private $client_id;
    private $client_secret;
    private $api_url;

    /**
     * PayPalConnection constructor.
     *
     * @param string $client_id     Le Client ID de l'application PayPal.
     * @param string $client_secret Le Secret de l'application PayPal.
     * @param string $api_url       L'URL de l'API PayPal (sandbox ou live).
     */
    public function __construct(string $client_id, string $client_secret, string $api_url = 'https://api.sandbox.paypal.com')
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        //TODO: détecter si sandbox ou live activé dans l'admin
        $this->api_url = $api_url;
    }

    /**
     * Méthode pour obtenir un Bearer Token.
     *
     * @throws RuntimeException Si la requête échoue ou si le token n'est pas valide.
     */
    public function it_get_paypal_bearer_token(): string
    {
        $ch = curl_init();

        $headers = [
            'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        curl_setopt($ch, CURLOPT_URL, $this->api_url . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code !== 200) {
            throw new RuntimeException('Échec de la récupération du Bearer Token: ' . $response);
        }

        $response_data = json_decode($response);

        $access_token = isset($response_data->access_token) ? $response_data->access_token : '';

        if (!$response_data || empty($access_token)) {
            throw new RuntimeException('Réponse invalide lors de la récupération du Token Paypal');
        }

        return $response;
    }

    // public function it_get_paypal_order_informations($access_token, $order_id)
    // {
    //     $ch = curl_init();
    //     curl_setopt_array($ch, [
    //         CURLOPT_URL => $this->api_url . '/v2/checkout/orders/' . $order_id,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_HTTPHEADER => [
    //             "Authorization: Bearer {$access_token}",
    //             "Content-Type: application/json",
    //         ],
    //     ]);

    //     $response = curl_exec($ch);
    //     curl_close($ch);

    //     if ($response === false) {
    //         throw new RuntimeException('Erreur lors de la récupération des détails de la commande PayPal');
    //     }

    //     // Décoder la réponse pour obtenir les détails de la commande
    //     return $response;
    // }
}
