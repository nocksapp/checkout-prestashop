<?php

class Nocks_NocksCheckout_Api
{
	protected $url;
	protected $oauthUrl;

	protected $accessToken;

	public function __construct($accessToken, $testMode = false) {
		$this->accessToken = $accessToken;

		$this->url = $testMode ? 'https://sandbox.nocks.com/api/v2/' : 'https://api.nocks.com/api/v2/';
		$this->oauthUrl = $testMode ? 'https://sandbox.nocks.com/oauth/' : 'https://www.nocks.com/oauth/';
	}

	/**
	 * Get the token scopes
	 *
	 * @return array
	 */
	public function getTokenScopes() {
		$response = $this->call('token-scopes', null, true);

		if ($response) {
			return array_map(function($scope) {
				return $scope['id'];
			}, $response);
		}

		return [];
	}

	/**
	 * Get the merchants
	 *
	 * @return array
	 */
	public function getMerchants() {
		$response = $this->call('merchant', null);

		if ($response) {
			return $response['data'];
		}

		return [];
	}

	/**
	 * Create a new transaction
	 *
	 * @param $data
	 *
	 * @return bool|mixed|null
	 */
	public function createTransaction($data) {
		$response = $this->call('transaction', $data);

		if ($response) {
			return $response;
		}

		return false;
	}

	/**
	 * Get a transaction by id
	 *
	 * @param $transactionId
	 *
	 * @return null
	 */
	public function getTransaction($transactionId) {
		$response = $this->call('transaction/' . $transactionId, null);

		if ($response) {
			return $response['data'];
		}

		return null;
	}

	public function call($action, $postData, $isOauth = false) {
		if ($this->accessToken) {
			$url = ($isOauth ? $this->oauthUrl : $this->url) . $action;
			$method = is_array($postData) ? 'POST' : 'GET';
			$headers = [
				'Accept: application/json',
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->accessToken
			];

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

			if (in_array($method, ['POST', 'PUT'])) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
			}

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$responseString = curl_exec($ch);
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);

			if ($httpStatusCode >= 200 && $httpStatusCode < 300 && $responseString) {
				return json_decode($responseString, true);
			}
		}

		return null;
	}
}