<?php

namespace FSPoster\App\SocialNetworks\Twitter\Api\AppMethod;

class AuthData
{

    public string $accessToken;
    public string $accessTokenSecret;
    public string $appKey;
    public string $appSecret;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}