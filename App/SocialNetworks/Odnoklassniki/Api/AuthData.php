<?php

namespace FSPoster\App\SocialNetworks\Odnoklassniki\Api;

class AuthData
{

    public string $accessToken;
    public string $accessTokenExpiresOn;
    public string $refreshToken;
    public string $appId;
    public string $appSecret;
    public string $appPublicKey;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}