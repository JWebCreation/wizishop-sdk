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

        //var_dump( $client );
        //die;

        try {
            $authResponse = $client->post('v3/auth/login', [
                'json' => [
                    'username' => $username,
                    'password' => $password
                ]
            ]);

            //var_dump( $authResponse ); die;

            $jsonResponse = json_decode($authResponse->getBody(), true);

            //var_dump($jsonResponse);die;

            $jwt = JWT::fromString($jsonResponse['token']);

            $client = new AuthenticatedApiClient($jwt, $config);

            return $client;
        } catch (RequestException $e) {
            throw new AuthenticationException('Authentication problem', $e->getRequest(), $e->getResponse());
        }
    }
}
