<?php

class Nocks_NocksCheckout_Util {

	/**
	 * @param $merchants
	 *
	 * @return array
	 */
	public static function getMerchantsOptions($merchants) {
		$options = [];
		foreach ($merchants as $merchant) {
			$merchantName = $merchant['name'];
			foreach ($merchant['merchant_profiles']['data'] as $profile) {
				$label = ($merchantName === $profile['name'] ? $merchantName : $merchantName . ' (' . $profile['name'] . ')')
				         . ' (' . $merchant['coc'] . ')';

				$options[] = [
					'value' => $profile['uuid'],
					'label' => htmlentities($label, ENT_COMPAT, 'UTF-8'),
				];
			}
		}

		return $options;
	}

	/**
	 * @param $scopes
	 *
	 * @return bool
	 */
	public static function hasAllRequiredScopes($scopes) {
		$requiredScopes = ['merchant.read', 'transaction.create', 'transaction.read'];

		$requiredAccessTokenScopes = array_filter($scopes, function($scope) use ($requiredScopes) {
			return in_array($scope, $requiredScopes);
		});

		return sizeof($requiredAccessTokenScopes) === sizeof($requiredScopes);
	}
}