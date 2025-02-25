<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Providers\Helpers\AssetHelper;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\Providers\License\UpdaterService;

class BackEnd
{

    private ?array $active_custom_post_types = null;

    public function __construct ()
    {
        add_action( 'current_screen', [ AssetHelper::class, 'enqueueAssets' ] );

        $this->makeYoastDuplicatePostsCompatible();
		$this->initMenu();

        if ( PluginHelper::isPluginActivated() && ! PluginHelper::canUserAccessToPlugin() )
        {
            $this->registerMetaBox();
            $this->registerNewsWidget();
            $this->registerActions();
            $this->registerBulkAction();
            $this->registerNotifications();
        }

		new UpdaterService();
    }

    public function initMenu ()
    {
        if ( PluginHelper::canUserAccessToPlugin() && ! PluginHelper::isDemoVersion() )
            return;

        add_action( 'admin_menu', function ()
        {
            add_menu_page( 'FS Poster', 'FS Poster', 'read', FSP_PLUGIN_MENU_SLUG, function ()
            {
                wp_enqueue_media();

                if ( ! PluginHelper::isDevelopmentMode() )
                {
                    wp_enqueue_script( 'fs-poster-dashboard', Helper::getFrontendAssetUrl('src/dashboard.tsx'), [ 'fs-poster-portal' ] );
                }

                echo '<div id="fs-poster-dashboard" class="fs-poster-dashboard"></div>';
            }, FSP_ROOT_DIR_URL . '/icon.svg', 90 );
        } );
    }

    private function registerMetaBox ()
    {
        add_action( 'add_meta_boxes', function ()
        {
            add_meta_box( 'fs-poster-metabox-container', 'FS Poster', [
                $this,
                'publish_meta_box',
            ], $this->getActiveCustomPostTypes(), 'side' );
        } );
    }

    public function publish_meta_box ( $post )
    {
        if ( ! PluginHelper::isDevelopmentMode() )
        {
            wp_enqueue_script( 'fs-poster-metabox', Helper::getFrontendAssetUrl('src/metabox.tsx'), [ 'fs-poster-portal' ] );
        }

        $dataFspRunned = 'data-fsp-runned="' . (metadata_exists( 'post', $post->ID, 'fsp_runned_for_this_post' ) ? 'true' : 'false') . '"';

        echo '<div id="fs-poster-metabox" data-post-id="' . $post->ID . '" data-post-status="' . $post->post_status . '" data-post-type="' . $post->post_type . '" ' . $dataFspRunned . '></div>';
    }

    private function registerNewsWidget ()
    {
        add_action( 'wp_dashboard_setup', function ()
        {
            wp_add_dashboard_widget( 'fsp-news', 'FS Poster', function ()
            {
	            $cachedData = json_decode( Settings::get( 'news_cache', false, true ) );
	            echo $cachedData->data ?? '';
            } );
        } );
    }

    private function getActiveCustomPostTypes ()
    {
        if ( is_null( $this->active_custom_post_types ) )
        {
            $this->active_custom_post_types = Settings::get( 'allowed_post_types', [ 'post', 'page', 'attachment', 'product' ] );
        }

        return $this->active_custom_post_types;
    }

    private function registerActions ()
    {
        $usedColumnsSave = [];

        foreach ( $this->getActiveCustomPostTypes() as $postType )
        {
            $postType = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $postType );

            switch ( $postType )
            {
                case 'post':
                    $typeName = 'posts';
                    break;
                case 'page':
                    $typeName = 'pages';
                    break;
                case 'attachment':
                    $typeName = 'media';
                    break;
                default:
                    $typeName = $postType . '_posts';
            }

            add_action( 'manage_' . $typeName . '_custom_column', function ( $column_name, $postId ) use ( &$usedColumnsSave )
            {
                if ( $column_name === 'fsp-share-column' && !isset( $usedColumnsSave[ $postId ] ) )
                {
                    if ( get_post_status( $postId ) === 'publish')
                    {
                        $postType = get_post_type($postId);
                        echo '<button type="button" class="button-link" onclick="window.FS_POSTER.schedule(' . $postId . ', \'' . $postType . '\')">Schedule</button>';
                    } else
                    {
                        echo '—';
                    }

                    $usedColumnsSave[ $postId ] = true;
                }
            }, 10, 2 );

            add_filter( 'manage_' . $typeName . '_columns', function ( $columns )
            {
                if ( is_array( $columns ) && !isset( $columns[ 'fsp-share-column' ] ) )
                {
                    $columns[ 'fsp-share-column' ] = fsp__('FS Poster');
                }

                return $columns;
            } );

        }

        $taxonomy = Request::get( 'taxonomy', '', 'string' );

        if ( !empty( $taxonomy ) )
        {
            $taxonomy = $_REQUEST[ 'taxonomy' ];

            add_filter( "manage_edit-{$taxonomy}_columns", function ( $columns )
            {
                return array_merge( $columns, [ 'fsp-share-column' => fsp__('FS Poster') ] );
            } );

            add_action( "manage_{$taxonomy}_custom_column", function ( $content, $columnName, $termId )
            {

                if ( $columnName === 'fsp-share-column' )
                {
                    $content = '<button type="button" class="button-link" onclick="window.FS_POSTER.schedule(' . $termId . ')">Schedule</button>';
                }

                echo $content;
            }, 10, 3 );
        }
    }

    private function registerBulkAction ()
    {
        foreach ( $this->getActiveCustomPostTypes() as $postType )
        {
            if ( $postType === 'attachment' )
            {
                $postType = 'upload';
            } else
            {
                $postType = 'edit-' . $postType;
            }

            add_filter( 'bulk_actions-' . $postType, function ( $bulk_actions )
            {
                $bulk_actions[ 'fs-poster-bulk-schedule' ] = fsp__( 'Bulk Schedule [FS Poster]' );

                return $bulk_actions;
            } );

            add_filter( 'handle_bulk_actions-' . $postType, function ( $redirect_to, $doaction, $post_ids )
            {
                if ( $doaction !== 'fs-poster-bulk-schedule' )
                {
                    return $redirect_to;
                }

                return add_query_arg( 'fs-poster-planner', implode( ',', $post_ids ), $redirect_to );
            }, 20, 3 );
        }
    }

    private function registerNotifications ()
    {
        $alert    = Settings::get( 'plugin_alert', '', true );
        $disabled = Settings::get( 'plugin_disabled', '0', true );

        if ( !empty( $alert ) && $disabled === '1' )
        {
            add_action( 'admin_notices', function () use ( $alert )
            {
                echo '<div class="notice notice-error"><p>' . fsp__( $alert ) . '</p></div>';
            } );
        }
    }

    private function makeYoastDuplicatePostsCompatible ()
    {
        add_filter( 'duplicate_post_excludelist_filter', function ( $meta_excludelist )
        {
            return array_merge( $meta_excludelist, [ 'fsp_*' ] );
        } );
    }

}
