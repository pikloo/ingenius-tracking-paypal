<?php //phpcs:ignore

use RuntimeException;

if ( ! class_exists( 'PayPalConnection' ) ) {
	/**
	 * Handles the connection to PayPal for retrieving and sending data.
	 *
	 * @package Ingenius_Tracking_Paypal
	 */
	class PayPalConnection {

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

		protected const PAYPAL_LIVE_API_URL = 'https://api.paypal.com';

		/**
		 * Initializes the PayPal connection by setting up the client ID, client secret, and the API URL to be used for requests.
		 *
		 * @param string $client_id The PayPal client ID used for authentication.
		 * @param string $client_secret The PayPal client secret used for authentication.
		 */
		public function __construct( string $client_id, string $client_secret ) {
			$this->client_id     = $client_id;
			$this->client_secret = $client_secret;
			$this->api_url       = self::PAYPAL_LIVE_API_URL;
		}

		/**
		 * Retrieve the bearer token from PayPal API authentification endpoint
		 *
		 * @link https://developer.paypal.com/api/rest/authentication/
		 * @throws RuntimeException If the API failed to retrieve a token.
		 */
		public function get_paypal_bearer_token(): string {
			$ch = curl_init();

			$headers = array(
				'Authorization: Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
				'Content-Type: application/x-www-form-urlencoded',
			);

			curl_setopt( $ch, CURLOPT_URL, $this->api_url . '/v1/oauth2/token' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials' );

			$response  = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			curl_close( $ch );

			if ( 200 !== $http_code ) {
				throw new RuntimeException( sprintf( esc_html__( 'Failed to retrieve token: %$', 'ingenius-tracking-paypal' ), esc_html( $response ) ) );
			}

			$response_data = json_decode( $response );

			$access_token = isset( $response_data->access_token ) ? $response_data->access_token : '';

			if ( ! $response_data || empty( $access_token ) ) {
				throw new RuntimeException( esc_html__( 'Réponse invalide lors de la récupération du Token Paypal', 'ingenius-tracking-paypal' ) );
			}

			return $response;
		}


		/**
		 * Get order details
		 *
		 * @param string $paypal_order_id The ID of Paypal order.
		 * @param string $access_token The bearer access token.
		 *
		 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_get
		 */
		public function get_order_details( string $paypal_order_id, string $access_token ): array {
			return $this->handle_paypal_request( '/v2/checkout/orders', $access_token, $paypal_order_id );
		}

		/**
		 * Add a new order tracking
		 *
		 * @param string $paypal_order_id The ID of Paypal order.
		 * @param array  $tracking_data An array of tracking datas to send to Paypal API.
		 * @param string $access_token The bearer access token.
		 *
		 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_track_create
		 */
		public function add_order_tracking( string $paypal_order_id, array $tracking_data, string $access_token ): array {
			return $this->handle_paypal_request( "/v2/checkout/orders/{$paypal_order_id}/track", $access_token, null, $tracking_data );
		}

		/**
		 * Update an order tracking
		 *
		 * @param string $transaction_id The ID of Paypal transaction.
		 * @param array  $tracking_data An array of tracking datas to send to Paypal API.
		 * @param string $access_token The bearer access token.
		 *
		 * @link https://developer.paypal.com/docs/api/tracking/v1/#trackers_put
		 */
		public function update_order_tracking( string $transaction_id, array $tracking_data, string $access_token ): array {
			return $this->handle_paypal_request( '/v1/shipping/trackers', $access_token, "{$transaction_id}-{$tracking_data["tracking_number"]}", $tracking_data, 'PUT' );
		}

		/**
		 * Handle a request to paypal API
		 *
		 * @param string      $endpoint The Paypal API endpoint to call.
		 * @param string      $access_token The bearer access token.
		 * @param string|null $params  The parameter of the URL request.
		 * @param string|null $body The body of the request.
		 * @param string|null $method The HTTP method of the request.
		 */
		protected function handle_paypal_request( string $endpoint, string $access_token, string|null $params = null, array|null $body = null, string|null $method = null ): array {
			$ch  = curl_init();
			$url = $this->api_url . $endpoint;
			if ( null !== $params ) {
				$url .= '/' . $params;
			}
			$options = array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => array(
					"Authorization: Bearer {$access_token}",
					'Content-Type: application/json',
				),
			);

			if ( null !== $body ) {
				$options[ CURLOPT_POSTFIELDS ] = json_encode( $body );
			}

			if ( null !== $method ) {
				$options[ CURLOPT_CUSTOMREQUEST ] = $method;
			}

			curl_setopt_array( $ch, $options );

			$response         = curl_exec( $ch );
			$http_status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$request          = curl_getinfo( $ch );

			curl_close( $ch );
			$request = json_encode( $request );

			return array(
				'code'     => $http_status_code,
				'response' => $response,
			);
		}
	}
}
