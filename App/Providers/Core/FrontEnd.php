<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Models\App;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\Providers\SocialNetwork\AuthWindowController;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;


class FrontEnd
{
    public function __construct ()
    {
        if ( ! PluginHelper::isPluginActivated() )
			return;

        $this->addSocialMetaTags();
        add_action( 'wp', [ $this, 'bootWp' ] );
    }

    public function bootWp ()
    {
        $this->checkVisits();
        $this->standardFSApp();
    }

    public function addSocialMetaTags ()
    {
        $the_metas = function ( $type )
        {
            if ( !is_singular() )
            {
                return;
            }

            $allowedPostTypes = Settings::get( 'add_meta_tags_to', [] );

            $currentPostType = get_post_type();

            if ( !in_array( $currentPostType, $allowedPostTypes ) )
            {
                return;
            }

            $thumb   = get_the_post_thumbnail_url();
            $excerpt = get_the_excerpt();
            $title   = get_the_title();

            $key = 'name';

            if ( $type === 'twitter' )
            {
                echo '<meta name="twitter:card" content="summary_large_image" />';
            } else if ( $type === 'og' )
            {
                echo '<meta property="og:type" content="article" />';
                $key = 'property';
            }

            if ( !empty( $title ) )
            {
                echo '<meta ' . $key . '="' . $type . ':title" content="' . htmlspecialchars( $title ) . '" />';
            }

            if ( !empty( $excerpt ) )
            {
                echo '<meta ' . $key . '="' . $type . ':description" content="' . htmlspecialchars( $excerpt ) . '" />';
            }

            if ( !empty( $thumb ) )
            {
                echo '<meta ' . $key . '="' . $type . ':image" content="' . $thumb . '" />';
            }
        };

        if ( Settings::get( 'enable_twitter_tags', false ) )
        {
            add_action( 'wp_head', function () use ( $the_metas )
            {
                $the_metas( 'twitter' );
            } );
        }

        if ( Settings::get( 'enable_og_tags', false ) )
        {
            add_action( 'wp_head', function () use ( $the_metas )
            {
                $the_metas( 'og' );
            } );
        }
    }

    public function checkVisits ()
    {
        if ( is_single() || is_page() )
        {
	        $scheduleId = Request::get( 'fsp_sid', '0', 'int' );

			if( isset( $_COOKIE[ 'fsp_last_visited_' . $scheduleId ] ) )
				return;

	        if ( ! isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) || preg_match( '/abacho|accona|AddThis|AdsBot|ahoy|AhrefsBot|AISearchBot|alexa|altavista|anthill|appie|applebot|arale|araneo|AraybOt|ariadne|arks|aspseek|ATN_Worldwide|Atomz|baiduspider|baidu|bbot|bingbot|bing|Bjaaland|BlackWidow|BotLink|bot|boxseabot|bspider|calif|CCBot|ChinaClaw|christcrawler|CMC\/0\.01|combine|confuzzledbot|contaxe|CoolBot|cosmos|crawler|crawlpaper|crawl|curl|cusco|cyberspyder|cydralspider|dataprovider|digger|DIIbot|DotBot|downloadexpress|DragonBot|DuckDuckBot|dwcp|EasouSpider|ebiness|ecollector|elfinbot|esculapio|ESI|esther|eStyle|Ezooms|facebookexternalhit|facebook|facebot|fastcrawler|FatBot|FDSE|FELIX IDE|fetch|fido|find|Firefly|fouineur|Freecrawl|froogle|gammaSpider|gazz|gcreep|geona|Getterrobo-Plus|get|girafabot|golem|googlebot|\-google|grabber|GrabNet|griffon|Gromit|gulliver|gulper|hambot|havIndex|hotwired|htdig|HTTrack|ia_archiver|iajabot|IDBot|Informant|InfoSeek|InfoSpiders|INGRID\/0\.1|inktomi|inspectorwww|Internet Cruiser Robot|irobot|Iron33|JBot|jcrawler|Jeeves|jobo|KDD\-Explorer|KIT\-Fireball|ko_yappo_robot|label\-grabber|larbin|legs|libwww-perl|linkedin|Linkidator|linkwalker|Lockon|logo_gif_crawler|Lycos|m2e|majesticsEO|marvin|mattie|mediafox|mediapartners|MerzScope|MindCrawler|MJ12bot|mod_pagespeed|moget|Motor|msnbot|muncher|muninn|MuscatFerret|MwdSearch|NationalDirectory|naverbot|NEC\-MeshExplorer|NetcraftSurveyAgent|NetScoop|NetSeer|newscan\-online|nil|none|Nutch|ObjectsSearch|Occam|openstat.ru\/Bot|packrat|pageboy|ParaSite|patric|pegasus|perlcrawler|phpdig|piltdownman|Pimptrain|pingdom|pinterest|pjspider|PlumtreeWebAccessor|PortalBSpider|psbot|rambler|Raven|RHCS|RixBot|roadrunner|Robbie|robi|RoboCrawl|robofox|Scooter|Scrubby|Search\-AU|searchprocess|search|SemrushBot|Senrigan|seznambot|Shagseeker|sharp\-info\-agent|sift|SimBot|Site Valet|SiteSucker|skymob|SLCrawler\/2\.0|slurp|snooper|solbot|speedy|spider_monkey|SpiderBot\/1\.0|spiderline|spider|suke|tach_bw|TechBOT|TechnoratiSnoop|templeton|teoma|titin|topiclink|twitterbot|twitter|UdmSearch|Ukonline|UnwindFetchor|URL_Spider_SQL|urlck|urlresolver|Valkyrie libwww\-perl|verticrawl|Victoria|void\-bot|Voyager|VWbot_K|wapspider|WebBandit\/1\.0|webcatcher|WebCopier|WebFindBot|WebLeacher|WebMechanic|WebMoose|webquest|webreaper|webspider|webs|WebWalker|WebZip|wget|whowhere|winona|wlm|WOLP|woriobot|WWWC|XGET|xing|yahoo|YandexBot|YandexMobileBot|yandex|yeti|Zeus/i', $_SERVER[ 'HTTP_USER_AGENT' ] ) )
		        return;

	        $scheduleInf = Schedule::get( $scheduleId );

            if ( empty( $scheduleInf ) )
                return;

            Schedule::where( 'id', $scheduleId )->update( [
                'visit_count' => DB::field( 'visit_count+1' ),
            ] );

            setcookie( 'fsp_last_visited_' . $scheduleId, '1', Date::epoch( 'now', '+30 seconds' ), COOKIEPATH, COOKIE_DOMAIN );
        }
    }

    public function standardFSApp ()
    {
        $callback = Request::get( 'fsp_app_redirect', '0', 'num', [ '1' ] );
        $proxy    = Request::get( 'proxy', null, 'string' );
        $app      = Request::get( 'app', [], Request::TYPE_ARRAY );

        if (
            $callback != '1' ||
            empty( $app ) ||
            empty( $app[ 'social_network' ] ) || !in_array( $app[ 'social_network' ], array_keys( SocialNetworkAddon::getSocialNetworks() ) ) ||
            empty( $app[ 'slug' ] ) || !is_string( $app[ 'slug' ] ) ||
            empty( $app[ 'name' ] ) || !is_string( $app[ 'name' ] ) ||
            empty( $app[ 'data' ] ) || !is_array( $app[ 'data' ] )
        )
        {
            return;
        }

	    Helper::setCrossOriginOpenerPolicyHeaderIfNeed();

        $appInf = App::where( 'social_network', $app[ 'social_network' ] )->where( 'slug', $app[ 'slug' ] )->fetch();

        $app[ 'data' ] = json_encode( $app[ 'data' ] );

        if ( empty( $appInf ) )
        {
            $app[ 'created_by' ] = get_current_user_id();
            $app[ 'blog_id' ]    = Helper::getBlogId();
            App::insert( $app );
            $appLastId = App::lastId();
        } else
        {
            App::where( 'id', $appInf->id )->update( $app );
            $appLastId = $appInf->id;
        }

        $app = App::get( $appLastId );

		try
		{
			$res = apply_filters( 'fsp_standard_app_get_channels', [], $app->social_network, $app, $proxy );
		}
		catch ( SocialNetworkApiException $e )
		{
			AuthWindowController::error( $e->getMessage() );
		}
		catch( \Exception $e )
		{
			AuthWindowController::error( $e->getMessage() );
		}

        if ( isset( $res[ 'error_msg' ] ) )
        {
            AuthWindowController::error( $res[ 'error_msg' ] );
        } else if ( isset( $res[ 'channels' ] ) )
        {
            AuthWindowController::closeWindow( $res[ 'channels' ] );
        }
    }
}
