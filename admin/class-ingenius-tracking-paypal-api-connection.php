<?php

namespace IngeniusTrackingPaypal\Admin;

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
    public function __construct(string $client_id, string $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        // $this->api_url = 'https://api.sandbox.paypal.com'; //TEST
        $this->api_url = $this->it_is_paypal_sandbox_mode()
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com';
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


    public static function it_is_paypal_sandbox_mode()
    {
        // Récupère les paramètres de PayPal dans WooCommerce
        $paypal_settings = get_option('woocommerce-ppcp-settings');

        // Vérifie si le mode sandbox est activé
        if (isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on']) {
            return true; // Mode sandbox activé
        }

        return false; // Mode live activé
    }

    public function it_get_order_tracking($transaction_id, $tracking_number, $access_token)
    {
        return $this->it_handle_paypal_request('/v1/shipping/trackers/', $access_token, "{$transaction_id}-{$tracking_number}");
    }

    public function it_add_order_tracking($paypal_order_id, $tracking_data,  $access_token)
    {
        return $this->it_handle_paypal_request("/v2/checkout/orders/{$paypal_order_id}/track",  $access_token, null, $tracking_data);
    }

    public function it_update_order_tracking($transaction_id, $tracking_data,  $access_token){
        return $this->it_handle_paypal_request("/v1/shipping/trackers/", $access_token, "{$transaction_id}-{$tracking_data["tracking_number"]}", $tracking_data);
    }

    protected function it_handle_paypal_request($endpoint, $access_token, $params = null, $body = null)
    {
        $ch = curl_init();
        $url = $this->api_url . $endpoint;
        if ($params) {
            $url .= '/' . $params;
        }
        $options = [
            CURLOPT_URL => $this->api_url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$access_token}",
                "Content-Type: application/json",
            ]
        ];

        if ($body) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

    
        // Décoder la réponse pour obtenir les détails de la commande
        return [
            'code' => $http_status_code,
            'response'=> $response    
        ];
    }
}
