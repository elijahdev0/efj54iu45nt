<?php

namespace FSPoster\App\SocialNetworks\Vk\Api;

class AuthData
{

    public string $accessToken;

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}