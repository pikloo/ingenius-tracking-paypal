<?php

namespace IngeniusTrackingPaypal\Admin;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;

class BearerToken implements Bearer {

    /**
     * @var Token
     */
    private $token;

    /**
     * BearerToken constructor.
     *
     */
    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Returns the bearer token.
     *
     * @return Token
     */
    public function bearer(): Token {
        return new Token($this->token);
    }
    
}
