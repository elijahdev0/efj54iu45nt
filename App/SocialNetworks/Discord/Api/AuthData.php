<?php

namespace FSPoster\App\SocialNetworks\Discord\Api;

class AuthData
{

    public string $botToken;
    public string $clientId;

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}