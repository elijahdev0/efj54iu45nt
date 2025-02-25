<?php

namespace FSPoster\App\SocialNetworks\Instagram\Api\LoginPassMethod;

class AuthData
{

	public string $username;
	public string $pass;
	public string $phone_id;
	public string $android_device_id;
	public string $device_id;
	public ?string $mid = null;
	public string $authorization = '';
	public string $user_id = '0';


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}