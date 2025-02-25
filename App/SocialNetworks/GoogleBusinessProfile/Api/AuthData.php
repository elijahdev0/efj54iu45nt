<?php

namespace FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api;

class AuthData
{

    public string $accessToken;
    public string $refreshToken;
    public string $accessTokenExpiresOn;
    public string $appClientId;
    public string $appClientSecret;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}