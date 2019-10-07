<?php

namespace WiziShop\SDK;

use GuzzleHttp\Exception\RequestException;
use WiziShop\SDK\Exception\ApiException;
use WiziShop\SDK\Model\JWT;

class AuthenticatedApiClient extends \GuzzleHttp\Client
{
	/**
	 * @const string SDK version
	 */

	const VERSION = '1.0.9';
	/**
	 * @const string API URL (ending with /)
	 */

	const API_URL = 'https://api.wizishop.com/';

	/**
	 * @var JWT Json Web Token
	 */
	private $jwt;
	private $limit = 0;

    private $callRemain;

    public function __construct(JWT $jwt, array $config = [])
	{
	    $this->callRemain = NULL ;
		$this->jwt = $jwt;
		$shopId = $jwt->get('default_shop_id');
		$shopId = $config['default_shop_id'];
		$apiUrl = isset($config['base_uri']) ? $config['base_uri'] : self::API_URL;
		$baseUri = $apiUrl . 'v3/' . ($shopId ? sprintf('shops/%s/', $shopId) : '');
		$defaultConfig = [
			'base_uri' => $baseUri,
            'http_errors' => true,
            'debug' => false,
			'headers' => [
				'User-Agent' => sprintf('%s wizishop-php-sdk/%s', \GuzzleHttp\default_user_agent(), self::VERSION),
				'Authorization' => 'Bearer ' . $this->jwt->getToken()
			]
		];
		parent::__construct($defaultConfig + $config);
	}

    /**
     * @param $error (Message, Request and Response of the route)
     *
     * @return array
     */
    public function error( $error )
    {
        $error->getMessage();
        $error->getRequest();
        $error->getResponse();
    }

    private function waitLimit( $response )
    {
        $this->limit = intval( $response->getHeader('X-RateLimit-Remaining')[0] ) ;
        if ( $this->limit < 50 ) sleep(60);
    }

	/**
	 * @param callable $call Closure to get a json formatted result page for a page number
	 * @param callable $parse Closure to get an array of results from the result page
	 *
	 * @return array The collection of the total pages
	 */
	private function assembleResults(callable $call, callable $parse)
	{
		$currentPage = 1;
		$results = [];
		do {
			$resultPage = $call($currentPage);
			if (empty($resultPage)) {
				return [];
			}
			$results = array_merge($results, $parse($resultPage));
			$currentPage++;
		} while ($currentPage <= $resultPage['pages']);
		return $results;
	}

	/**
	 * @param string $route Route
	 * @param array $params
	 *
	 * @return array Results
	 */
	private function getAllResultsForRoute($route, array $params = [])
	{
		try {
			if (array_key_exists('page', $params) || array_key_exists('limit', $params)) {
				return $this->getSingleResultForRoute($route, [
					'query' => $params
				]);
			}
			return $this->assembleResults(
				function ($page) use ($route, $params) {
					$response = $this->get($route, [
						'query' => [
								'limit' => 100,
								'page' => $page
							] + $params
					]);

                    $this->waitLimit( $response );

					return json_decode($response->getBody(), true);
				},
				function ($resultPage) {
					return $resultPage['results'];
				}
			);
		} catch (RequestException $e) {
            if (404 == $e->getResponse()->getStatusCode()) { // If no result, the API returns 404
                return [];
            }
			throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
		}
	}

    /**
     * @param string $route
     * @param array $params
     *
     * @param string $method
     * @return array Result
     * @throws ApiException
     */
	private function getSingleResultForRoute($route, array $params = [] , string $method = 'get' )
	{
		try {
			$response = $this->$method($route, $params);

            $this->waitLimit( $response );

			return json_decode($response->getBody(), true);
		} catch (RequestException $e) {
			if (404 == $e->getResponse()->getStatusCode()) { // If no result, the API returns 404
				return [];
			}
			throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
		}
	}

	/**
	 * @return JWT Json Web Token
	 */
	public function getJWT()
	{
		return $this->jwt;
	}

	/**
	 * @param int $brandId
	 * @param array $params
	 *
	 * @return array Brand
	 */
	public function getBrand($brandId, array $params = [])
	{
		return $this->getSingleResultForRoute(sprintf('brands/%s', $brandId), $params);
	}

	/**
	 * @param array $params
	 *
	 * @return array Brands
	 */
	public function getBrands(array $params = [])
	{
		return $this->getAllResultsForRoute('brands', $params);
	}

    /**
     * @param array $params
     *
     * @return array Products
     */
    public function getProducts(array $params = [])
    {
        return $this->getAllResultsForRoute('products', $params);
    }

    /**
     * @param array $params
     *
     * @return array Brands
     */
    public function getCategories(array $params = [])
    {
//        return $this->getAllResultsForRoute('categories');
		return $this->getSingleResultForRoute("categories", []);
    }

	/**
	 * @param int $customerId
	 * @param array $params
	 *
	 * @return array Customer
	 */
	public function getCustomer($customerId, array $params = [])
	{
		return $this->getSingleResultForRoute(sprintf('customers/%s', $customerId), $params);
	}

	/**
	 * @param array $params
	 *
	 * @return array Customers
	 */
	public function getCustomers(array $params = [])
	{
		return $this->getAllResultsForRoute('customers', $params);
	}

	/**
	 * @param array $params
	 *
	 * @return array NewsletterSubscribers
	 */
	public function getNewsletterSubscribers(array $params = [])
	{
		return $this->getAllResultsForRoute('newsletter/subscribers', $params);
	}

	/**
	 * @param string $sku
	 * @param array $params
	 *
	 * @return array Sku
	 */
	public function getSku($sku, array $params = [])
	{
		return $this->getSingleResultForRoute(sprintf('skus/%s', rawurlencode($sku)), $params);
	}

	/**
	 * @param array $params
	 *
	 * @return array Skus
	 */
	public function getSkus(array $params = [])
	{
		return $this->getAllResultsForRoute('skus', $params);
	}

	/**
	 * @param array $params
	 *
	 * @return array Skus
	 */
	public function getDetailedSkus(array $params = [])
	{
		return $this->getAllResultsForRoute('skus', ['detailed' => 1] + $params);
	}

	/**
	 * @param string $sku SKU
	 * @param int $stock Stock value
	 * @param string $method How to update the stock value, can be either "replace" (default), "increase" or "decrease"
	 *
	 * @return array Sku
	 */
	public function updateSkuStock($sku, $stock, $method = 'replace')
	{
		if (!in_array($method, ['replace', 'increase', 'decrease'])) {
			throw new \InvalidArgumentException('Update stock method cannot be ' . $method);
		}
		try {
			$response = $this->put(sprintf('skus/%s', rawurlencode($sku)), [
				'json' => [
					'method' => $method,
					'stock' => $stock
				]
			]);

            $this->waitLimit( $response );

			return json_decode($response->getBody(), true);
		} catch (RequestException $e) {
			throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
		}
	}

    /**
     * @param array $fields
     * @return mixed
     * @throws ApiException
     */
    public function createCategory(array $fields )
	{
		try {
			$response = $this->post('categories', [
				'json' => json_decode( json_encode( $fields ) )
			]);

            $this->waitLimit( $response );

			return json_decode($response->getBody(), true);
		} catch (RequestException $e) {
		    dump($fields);
			throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
		}
	}

    /**
     * @param array $fields
     * @return array Product
     * @throws ApiException
     */
    public function createProduct( array $fields )
    {
        try {
            $response = $this->post('products', [
                'json' => json_decode( json_encode( $fields ) )
            ]);

            $this->waitLimit( $response );

            return json_decode($response->getBody(), true) ;
        } catch (RequestException $e) {
            $path = dirname(__DIR__, 5) . "/cms/web/errors/";
            $this->log( json_encode( $fields , JSON_UNESCAPED_UNICODE + JSON_PRETTY_PRINT ) , $path . 'error-product-' . $fields['sku'] . ".json" ) ;

            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    private function log( $content , $nameFile )
    {
        $fichier = fopen($nameFile, 'w+');
        fwrite($fichier, $content);
        fclose($fichier);
    }


    /**
     * @param $id
     * @param $fields
     * @return mixed
     * @throws ApiException
     */
    public function updateProduct($id, $fields)
    {
        try {
            $response = $this->put('products/' . $id , [
                'json' => $fields
            ]);

            $this->waitLimit( $response );

            return json_decode($response->getBody(), true) ;
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @param array $fields
     * @throws ApiException
     */
    public function createOrderCustomState( array $fields )
    {
        try {
            $response = $this->post('ordercustomstate', [
                'json' => $fields
            ]);

            $this->waitLimit( $response );

            return json_decode($response->getBody(), true) ;
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @param string $brandId
     * @param string $newName
     * @param string $newUrl
     * @param string $newImageUrl
     *
     * @return array Brand
     */
    public function updateBrand($brandId, $newName, $newUrl = null, $newImageUrl = null)
    {
        try {
            $fields = [
                'name' => $newName
            ];
            if ($newUrl) {
                $fields['url'] = $newUrl;
            }
            if ($newImageUrl) {
                $fields['image_url'] = $newImageUrl;
            }
            $response = $this->patch(sprintf('brands/%s', $brandId), [
                'json' => $fields
            ]);

            $this->waitLimit( $response );

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @param string $name
     * @param string $newImageUrl
     *
     * @return array Brand
     */
    public function createBrand($name, $newImageUrl = null)
    {
        try {
            $fields = [
                'name' => $name
            ];
            if ($newImageUrl) {
                $fields['image_url'] = $newImageUrl;
            }
            $response = $this->post('brands', [
                'json' => $fields
            ]);

            $this->waitLimit( $response );

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

	/**
	 * @param int $brandId
	 *
	 * @return bool
	 */
	public function deleteBrand($brandId)
	{
        return $this->getSingleResultForRoute(sprintf('brands/%s', $brandId), [] , 'delete');
	}

	/**
	 * @param array $params Array of params
	 *                      status_code: Order status code ( abandoned: 0, pending payment: 5, payment awaiting verification: 10, awaiting replenishment: 11, awaiting preparation: 20, preparing: 25, partially sent: 29, sent: 30, delivered: 35, being returned: 40, returned: 45, refunded: 46, canceled: 50)
	 *                      start_date: Start date
	 *                      end_date: End date
	 *
	 * @return array Orders
	 */

	public function getOrders(array $params = [])
	{
		if (array_key_exists('status_code', $params) && ($params['status_code'] < 0 || $params['status_code'] > 50)) {
			throw new \InvalidArgumentException('Order status code should be between 0 and 50');
		}
		if (array_key_exists('start_date', $params) && $params['start_date'] instanceof \DateTime) {
			$params['start_date'] = $params['start_date']->format('Y-m-d H:i:s');
		}
		if (array_key_exists('end_date', $params) && $params['end_date'] instanceof \DateTime) {
			$params['end_date'] = $params['end_date']->format('Y-m-d H:i:s');
		}
		return $this->getAllResultsForRoute('orders', $params);
	}

    /**
     * @param $orderId
     * @param array $params
     * @return array
     * @throws ApiException
     */
    public function getOrder($orderId, array $params = [])
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s', $orderId), $params);
	}

	/**
	 * @param int $orderId Order id
	 * @param array $params
	 *
	 * @return mixed PDF data to write
	 */
	public function getInvoiceForOrder($orderId, array $params = [])
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/invoice', $orderId), $params);
	}

	/**
	 * @param int $orderId Order id
	 * @param array $params
	 *
	 * @return mixed PDF data to write
	 */
	public function getPickingSlipForOrder($orderId, array $params = [])
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/picking_slip', $orderId), $params);
	}

	/**
	 * @param int $orderId Order id
	 * @param array $params
	 *
	 * @return mixed PDF data to write
	 */
	public function getDeliverySlipForOrder($orderId, array $params = [])
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/delivery_slip', $orderId), $params);
	}

	/**
	 * Changes order status to "pending payment" (status_code: 5)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function pendingPaymentOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/pending_payment', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "pending payment verification" (status_code: 10)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function pendingPaymentVerificationOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/pending_payment_verification', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "pending replenishment" (status_code: 11)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function pendingReplenishmentOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/pending_replenishment', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "pending preparation" (status_code: 20)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function pendingPreparationOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/pending_preparation', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "preparing" (status_code: 25)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function preparingOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/pending_preparation', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "partially sent" (status_code: 29)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function partiallySentOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/partially_sent', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "sent" (status_code: 30)
	 *
	 * @param int $orderId Order id
	 * @param array $trackingNumbers Tracking numbers
	 *                               Example: [
	 *                                   'tracking_numbers' => [
	 *                                       [
	 *                                           'shipping_id' => 39,
	 *                                           'tracking_number' => 'XVBFD-2'
	 *                                       ]
	 *                                   ]
	 *                               ]
	 *
	 * @return array Order details with the new status
	 */
	public function shipOrder($orderId, array $trackingNumbers)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/ship', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "delivered" (status_code: 35)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function deliveredOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/delivered', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "being returned" (status_code: 40)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function returnOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/return', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "returned" (status_code: 45)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function returnedOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/returned', $orderId), [] , 'put');
	}

	/**
	 * Changes order status to "refunded" (status_code: 46)
	 *
	 * @param int $orderId Order id
	 *
	 * @return array Order details with the new status
	 */
	public function refundedOrder($orderId)
	{
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/refunded', $orderId), [] , 'put');
	}

/**
 * Changes order status to "canceled" (status_code: 50)
 *
 * @param int $orderId Order id
 *
 * @return array Order details with the new status
 */
    public function cancelOrder($orderId)
    {
        return $this->getSingleResultForRoute(sprintf('orders/%s/status/cancel', $orderId), [] , 'put');
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param array $fields
     * @return mixed
     * @throws ApiException
     */
    public function createCustomer( array $fields )
    {
        try {
            $response = $this->post('customers', [
                'json' => json_decode( json_encode( $fields ) )
            ]);

            $this->waitLimit( $response );

            $rst = json_decode($response->getBody(), true);

            return array_merge([
                'exist' => false
            ], $rst );
        } catch (RequestException $e) {
            if ( $e->getResponse()->getStatusCode() == 400 )
            {
                $this->waitLimit( $e->getResponse() );

                $result = json_decode($e->getResponse()->getBody(), true);

                if ( $result )
                {
                    dump($result['message']);
                    list( $null , $id ) = explode('#' , $result['message'] );

                    return [
                        'exist' => true,
                        'id' => $id
                    ];
                }
            }
            else
            {
                throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
            }
        }
    }

    /*

    public function createCustomer( array $fields )
    {
        $response = $this->post('customers', [
            'json' => json_decode( json_encode( $fields ) )
        ]);

        $result = json_decode($response->getBody(), true) ;

        $this->waitLimit( $response );

        if (400 == $response->getStatusCode() or 200 == $response->getStatusCode()) { // If no result, the API returns 404
            return $result ;
        }
        else
        {
            return false ;
        }
    }
     */

}