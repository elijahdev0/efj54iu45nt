<?php

namespace FSPoster\App\Providers\SocialNetwork;

use FSPoster\App\Models\App;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\Session;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;

class CallbackUrlHandler
{

	public static function handleCallbackRequest ()
	{
		$isCallbackRequest = false;
		foreach ( SocialNetworkAddon::getSocialNetworks() AS $socialNetwork )
		{
			if( $socialNetwork->checkIsCallbackRequest() )
			{
				$isCallbackRequest = true;
				break;
			}
		}

		if( ! $isCallbackRequest )
			return;

		Helper::setCrossOriginOpenerPolicyHeaderIfNeed();

		$appId = (int) Session::get( 'app_id' );
		$proxy = Session::get( 'proxy' );

		Session::remove( 'app_id' );
		Session::remove( 'proxy' );

		if ( empty( $appId ) )
			return;

		$app = App::get( $appId );

		if( empty( $app ) )
			return;

		try
		{
			$data = apply_filters( 'fsp_auth_get_channels', [], $app->social_network, $app, $proxy );

			if (empty($data['channels']))
				throw new \Exception( 'No channels found for this account' );

			AuthWindowController::closeWindow( $data[ 'channels' ]);
		}
		catch ( SocialNetworkApiException $e )
		{
			AuthWindowController::error( $e->getMessage() );
		}
		catch( \Exception $e )
		{
			AuthWindowController::error( $e->getMessage() );
		}
	}

	public static function getCallbackUrlsByNetwors () : array
	{
		$callbackUrls = [];

		foreach ( SocialNetworkAddon::getSocialNetworks() AS $socialNetwork )
		{
			$callbackUrls[ $socialNetwork->getSlug() ] = $socialNetwork->getCallbackUrl();
		}

		return $callbackUrls;
	}

}