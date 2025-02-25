<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Api;

class AuthData
{

    public string $accessToken;
    public string $accessTokenExpiresOn;
    public string $refreshToken;
    public string $appConsumerKey;
    public string $appConsumerSecret;

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}