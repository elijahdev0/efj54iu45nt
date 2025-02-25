<?php

namespace FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod;

class AuthData
{

	public string $fbUserId;
	public string $fbSess;
	public string $fb_dtsg = '';
	public string $lsd = '';
	public ?string $newPageID;
	public string $userAgent;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}