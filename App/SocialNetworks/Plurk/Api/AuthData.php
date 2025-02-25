<?php

namespace FSPoster\App\SocialNetworks\Plurk\Api;


class AuthData
{

    public string $appKey;
    public string $appSecret = '';
    public string $accessToken = '';
    public string $accessTokenSecret = '';

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}