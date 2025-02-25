<?php

namespace FSPoster\App\AI\App;

use Exception;
use FSPoster\App\Models\AITemplate;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Helpers\Helper;

class Controller
{

    /**
     * @throws \Exception
     */
    public static function saveTemplate ( RestRequest $request ): array
    {
        $id           = $request->param( 'id', '', RestRequest::TYPE_STRING );
        $title        = $request->require( 'title', RestRequest::TYPE_STRING, fsp__( 'Please enter a title' ) );
        $provider     = $request->require( 'provider', RestRequest::TYPE_STRING, fsp__( 'Please specify a valid AI provider' ) );
        $prompt       = $request->require( 'prompt', RestRequest::TYPE_STRING, fsp__( 'Please set a prompt' ) );
        $fallbackText = $request->param( 'fallback_text', '', RestRequest::TYPE_STRING );
        $model        = $request->require( 'ai_model', RestRequest::TYPE_STRING, fsp__( 'Please specify a model' ) );
        $type         = $request->require( 'type', RestRequest::TYPE_STRING, fsp__( 'Please select type' ), [ 'image', 'text' ] );
        $config       = $request->param( 'config', [], RestRequest::TYPE_ARRAY );

        $template = apply_filters( 'fsp_ai_save_template', [
            'id'            => $id,
            'title'         => $title,
            'provider'      => $provider,
            'prompt'        => $prompt,
            'fallback_text' => $fallbackText,
            'ai_model'      => $model,
            'type'          => $type,
            'config'        => $config,
            'created_by'    => get_current_user_id(),
            'blog_id'       => Helper::getBlogId(),
        ], $provider );

        if ( empty( $template[ 'id' ] ) )
        {
            $template[ 'config' ] = json_encode( $template[ 'config' ] );

            AITemplate::insert( $template );
        } else
        {
            $template[ 'config' ] = json_encode( $template[ 'config' ] );
            AITemplate::where( 'id', $template[ 'id' ] )->update( $template );
        }

        return [];
    }

    /**
     * @throws Exception
     */
    public static function get ( RestRequest $request )
    {
        $id = $request->param( 'id', 0, RestRequest::TYPE_INTEGER );

        $template = AITemplate::get( $id );

        if ( !$template )
        {
            throw new Exception( fsp__( 'Template not found' ) );
        }

        $template[ 'config' ] = $template->config_obj->toArray();

        unset( $template[ 'created_by' ] );
        unset( $template[ 'blog_id' ] );

        return [
            'template' => $template,
        ];
    }

    /**
     * @throws \Exception
     *
     * pagination sonradan gerekli olmadi, silmeli olduq
     */
    public static function listTemplates ( RestRequest $request ): array
    {
        $type = $request->param( 'type', '', RestRequest::TYPE_STRING, [ 'image', 'text' ] );

        $templates = new AITemplate();

        if ( !empty( $type ) )
        {
            $templates->where( 'type', $type );
        }

        $templates = $templates->fetchAll();

        return [
            'templates' => array_map( function ( $t )
            {
                $t[ 'id' ]     = (int)$t[ 'id' ];
                $t[ 'config' ] = json_decode( $t[ 'config' ], true );
                unset( $t[ 'blog_id' ] );
                unset( $t[ 'created_by' ] );
                return $t;
            }, $templates ),
        ];
    }

    /**
     * @throws \Exception
     */
    public static function deleteTemplates ( RestRequest $request ): array
    {
        $ids = $request->require( 'ids', RestRequest::TYPE_ARRAY, fsp__( 'Please select templates to delete' ) );

        AITemplate::where( 'id', 'in', $ids )->delete();

        return [];
    }

}
