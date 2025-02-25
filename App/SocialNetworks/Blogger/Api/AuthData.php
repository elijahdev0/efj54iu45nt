<?php

namespace FSPoster\App\SocialNetworks\Blogger\Api;

class AuthData
{

	public $appClientId;
	public $appClientSecret;

	public $accessToken;
	public $refreshToken;
	public $accessTokenExpiresOn;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}