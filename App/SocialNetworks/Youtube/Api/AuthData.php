<?php

namespace FSPoster\App\SocialNetworks\Youtube\Api;

class AuthData
{

	public string $channelId;
    public array $cookies;

    public int $cookieLastUpdatedAt = 0;

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}