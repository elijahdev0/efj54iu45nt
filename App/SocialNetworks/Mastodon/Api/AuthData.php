<?php

namespace FSPoster\App\SocialNetworks\Mastodon\Api;

class AuthData
{

	public string $server;
    public string $accessToken;
    public string $appClientKey;
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