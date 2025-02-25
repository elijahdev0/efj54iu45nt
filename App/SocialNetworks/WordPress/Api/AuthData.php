<?php

namespace FSPoster\App\SocialNetworks\WordPress\Api;

class AuthData
{

    public string $username;
    public string $password;
    public string $siteUrl;

	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}