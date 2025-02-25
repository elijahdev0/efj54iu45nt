<?php

namespace FSPoster\App\Providers\Core;

use Exception;
use WP_REST_Request;

class RestRequest
{
    private WP_REST_Request $request;
    private array           $params;
    private array           $body;
    const TYPE_INTEGER = 'integer';
    const TYPE_STRING  = 'string';
    const TYPE_ARRAY   = 'array';
    const TYPE_FLOAT   = 'array';
    const TYPE_BOOL    = 'bool';

    public function __construct ( WP_REST_Request $request )
    {
        $this->request = $request;
        $this->body    = $request->get_json_params() ?? [];
        $this->params  = $request->get_query_params() ?? [];
    }

    public function param ( $key, $default = null, $dataType = null, $whitelist = [] )
    {
        return $this->checkTypeAndGet( $key, $default, $dataType, $whitelist );
    }

    /**
     * @throws Exception
     */
    public function require ( $key, $checkType, $errorMessage = [], $whitelist = [] )
    {
        $value = $this->param( $key, '', $checkType, $whitelist );

        if ( empty( $value ) )
        {
            throw new Exception( $errorMessage );
        }

        return $value;
    }

    public function getRequest (): WP_REST_Request
    {
        return $this->request;
    }

    private function checkTypeAndGet ( $key, $default = null, $dataType = null, $whitelist = [] )
    {
        $res = $this->request->get_param( $key ) ?? $default;

        if ( !empty( $dataType ) )
        {
            if ( $dataType === self::TYPE_BOOL )
            {
                $res = is_bool( $res ) ? $res : $default;
            } else if ( $dataType === self::TYPE_INTEGER )
            {
                $res = is_numeric( $res ) ? (int)$res : $default;
            } else if ( $dataType === self::TYPE_STRING )
            {
                $res = is_string( $res ) ? $res : $default;
            } else if ( $dataType === self::TYPE_ARRAY )
            {
                $res = is_array( $res ) ? $res : $default;
            } else if ( $dataType === self::TYPE_FLOAT )
            {
                $res = is_numeric( $res ) ? (float)$res : $default;
            }
        }

        if ( !empty( $whitelist ) && !in_array( $res, $whitelist ) && $dataType !== self::TYPE_ARRAY )
        {
            $res = $default;
        } else if ( !empty( $whitelist ) && $dataType === self::TYPE_ARRAY )
        {
            $res = array_intersect( $whitelist, $res );
            $res = array_values( $res );
        }

        return $res;
    }
}