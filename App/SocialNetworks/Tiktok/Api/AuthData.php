<?php

namespace FSPoster\App\SocialNetworks\Tiktok\Api;

class AuthData
{


	public string $appClientKey;
	public string $appClientSecret;
	public string $refreshToken;
	public string $accessToken;
	public string $accessTokenExpiresOn;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}