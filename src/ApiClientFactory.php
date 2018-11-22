<?php

namespace WiziShop\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use WiziShop\SDK\Exception\AuthenticationException;
use WiziShop\SDK\Model\JWT;

class ApiClientFactory
{
	/**
	 * @param string $username Username
	 * @param string $password Password
	 * @param array $config Guzzle client configuration settings
	 *
	 * @return AuthenticatedApiClient
	 */
    public static function authenticate($username, $password, array $config = [])
    {
        $client = new Client([
            'base_uri' => AuthenticatedApiClient::API_URL,
        ]);

        try {
            $authResponse = $client->post('v3/auth/login', [
                'json' => [
                    'username' => $username,
                    'password' => $password
                ]
            ]);

            $jsonResponse = json_decode($authResponse->getBody(), true);

            $jwt = JWT::fromString($jsonResponse['token']);

            $config['account_id'] = $jsonResponse['account_id'];
			if( ! array_key_exists( 'default_shop_id' , $config ) ) {
				$config['default_shop_id'] = $jsonResponse['default_shop_id'];
			}

            $client = new AuthenticatedApiClient($jwt, $config);

            return $client;
        } catch (RequestException $e) {
            throw new AuthenticationException('Authentication problem', $e->getRequest(), $e->getResponse());
        }
    }

    public static function token($token, $account_id, $default_shop_id, array $config = [])
    {
        $config = [];
        try {
            $jwt = JWT::fromString($token);

            $config['account_id'] = $account_id;
            $config['default_shop_id'] = $default_shop_id;

            $client = new AuthenticatedApiClient($jwt, $config);

            return $client;
        } catch (RequestException $e) {
            throw new AuthenticationException('Authentication problem', $e->getRequest(), $e->getResponse());
        }
    }
}
