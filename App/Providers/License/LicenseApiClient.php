<?php

namespace FSPoster\App\Providers\License;

use FSPoster\GuzzleHttp\Client;

class LicenseApiClient
{

	private const API_URL = 'https://api.fs-code.com/store/fs-poster/product/';
	private $accessToken = null;
	private $productName = 'FS Poster';
	private $productVersion = '7.0.0';
	private $proxy = null;

	public function sendClientRequest ( $endpoint, $method = 'GET', $data = [], $withoutAuthroization = false )
	{
		$client = new Client([
			'verify' => false
		]);

		$url = static::API_URL . $endpoint;

		$options = [];

		if ( !empty( $proxy ) )
			$options[ 'proxy' ] = $this->proxy;

		if( $method == 'POST' && ! empty( $data ) )
			$options['form_params'] = $data;
		else if( $method == 'GET' && ! empty( $data ) )
			$options['query'] = $data;

		if( ! $withoutAuthroization )
		{
			$options['headers'] = [
				'Authorization' => 'Bearer ' . $this->accessToken,
				'Product'       => $this->getFullProductString()
			];
		}

		try
		{
			$response = $client->request( $method, $url, $options );
			$response = json_decode( $response->getBody(), true );
		}
		catch ( \Exception $e )
		{
			$response = [];
		}

		return $response;
	}

	public function setAccessToken ( $accessToken )
	{
		$this->accessToken = $accessToken;

		return $this;
	}

	public function setProxy ( $proxy )
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function getFullProductString ()
	{
		return sprintf('%s %s', $this->productName, $this->productVersion);
	}

}