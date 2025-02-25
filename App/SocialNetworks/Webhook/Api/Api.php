<?php

namespace FSPoster\App\SocialNetworks\Webhook\Api;

use Exception;
use FSPoster\GuzzleHttp\Client;

class Api
{

	public ?string  $proxy = null;

	public string $postException = \Exception::class;

	public function setProxy ( ?string $proxy ): self
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function setPostException ( string $exceptionClass ): self
	{
		$this->postException = $exceptionClass;

		return $this;
	}

	public function sendPost ( PostingData $postingData ) : array
    {
		$options = [];

		if ( ! empty( $postingData->headers ) )
			$options['headers'] = $postingData->headers;

		if ( ! empty( $this->proxy ) )
			$options['proxy'] = $this->proxy;

		if ( strtoupper( $postingData->method ) === 'POST' || strtoupper( $postingData->method ) === 'PUT' )
		{
			if ( ! empty( $postingData->formData ) && $postingData->contentType === 'form' )
			{
				$options['form_params'] = $postingData->formData;

				if( empty( $options['headers']['Content-Type'] ) )
					$options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			}
			else if ( ! empty( $postingData->jsonData ) && $postingData->contentType === 'json' )
			{
				$options['body'] = $postingData->jsonData;

				if( empty( $options['headers']['Content-Type'] ) )
					$options['headers']['Content-Type'] = 'application/json';
			}
		}

		try
		{
			$client   = new Client(['verify'=>false]);
			$response = $client->request( strtoupper( $postingData->method ), $postingData->url, $options );
		}
		catch ( Exception $e )
		{
			throw new $this->postException( $e->getMessage() );
		}

		$statusCode      = $response->getStatusCode();
		$responseContent = $response->getBody()->getContents();

        return [
			'response_status'   => $statusCode,
	        'response_body'     => $responseContent
        ];
	}

    public function fetchChannels ()
    {
        // TODO: Implement fetchChannels() method.
    }
}