<?php

namespace IngeniusTrackingPaypal\Admin;

use RuntimeException;

class PayPalConnection
{
    /**
     * ID client for Paypal API
     *
     * @var string
     */
    private string $client_id;
    /**
     * Client Secret for Paypal API
     *
     * @var string
     */
    private string $client_secret;
    /**
     * API Url for Paypal API
     *
     * @var string
     */
    private string $api_url;

    /**
     * @param string $client_id
     * @param string $client_secret
     * @param string $api_url
     */
    public function __construct(string $client_id, string $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->api_url = $this->it_is_paypal_sandbox_mode()
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com';
    }

    /**
     * Retrieve the bearer token from PayPal API authentification endpoint
     * @link https://developer.paypal.com/api/rest/authentication/
     * @return string
     * @throws RuntimeException
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
            throw new RuntimeException(sprintf(__('Failed to retrieve token: %$', TEXT_DOMAIN), $response));
        }

        $response_data = json_decode($response);

        $access_token = isset($response_data->access_token) ? $response_data->access_token : '';

        if (!$response_data || empty($access_token)) {
            throw new RuntimeException(__('Réponse invalide lors de la récupération du Token Paypal', TEXT_DOMAIN));
        }

        return $response;
    }

    /**
     * Determines whether the sandbox mode is enabled
     *
     * @return bool
     */
    public static function it_is_paypal_sandbox_mode(): bool
    {
        $paypal_settings = get_option('woocommerce-ppcp-settings');

        if (isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on']) {
            return true;
        }

        return false;
    }

    /**
     * Get order details
     * @link https://developer.paypal.com/docs/api/orders/v2/#orders_get
     *
     * @param string $paypal_order_id
     * @param string $access_token
     * @return array
     */
    public function it_get_order_details($paypal_order_id, $access_token): array
    {
        return $this->it_handle_paypal_request('/v2/checkout/orders', $access_token, $paypal_order_id);
    }

    /**
     * Add a new order tracking
     * @link https://developer.paypal.com/docs/api/orders/v2/#orders_track_create
     *
     * @param string $paypal_order_id
     * @param array $tracking_data
     * @param string $access_token
     * @return array
     */
    public function it_add_order_tracking($paypal_order_id, $tracking_data,  $access_token): array
    {
        return $this->it_handle_paypal_request("/v2/checkout/orders/{$paypal_order_id}/track",  $access_token, null, $tracking_data);
    }

    /**
     * Update an order tracking
     * @link https://developer.paypal.com/docs/api/tracking/v1/#trackers_put
     *
     * @param string $transaction_id
     * @param array $tracking_data
     * @param string $access_token
     * @return array
     */
    public function it_update_order_tracking($transaction_id, $tracking_data,  $access_token): array
    {
        return $this->it_handle_paypal_request("/v1/shipping/trackers", $access_token, "{$transaction_id}-{$tracking_data["tracking_number"]}", $tracking_data, 'PUT');
    }

    /**
     * Handle a request to paypal API
     *
     * @param string $endpoint
     * @param string $access_token
     * @param string $params
     * @param array $body
     * @param string $method
     * @return array
     */
    protected function it_handle_paypal_request($endpoint, $access_token, $params = null, $body = null, $method = null)
    {
        $ch = curl_init();
        $url = $this->api_url . $endpoint;
        if ($params) {
            $url .= '/' . $params;
        }
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$access_token}",
                "Content-Type: application/json",
            ]
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        if ($method !== null) {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $request = curl_getinfo($ch);

        curl_close($ch);
        $request = json_encode($request);

        return [
            'code' => $http_status_code,
            'response' => $response
        ];
    }
}
