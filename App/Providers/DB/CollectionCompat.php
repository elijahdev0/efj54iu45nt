<?php

namespace FSPoster\App\Providers\DB;

use FSPoster\App\Providers\Helpers\Helper;

if (version_compare(PHP_VERSION, '8.0.0', '<')){
    trait CollectionCompat
    {
        public function offsetGet( $offset )
        {
            if( isset( $this->container[ $offset ] ) )
                return $this->container[ $offset ];

            if( isset($this->model) && method_exists( $this->model, 'get' . Helper::snakeCaseToCamel( $offset ) . 'Attribute' ) )
                return call_user_func( [ new $this->model(), 'get' . Helper::snakeCaseToCamel( $offset ) . 'Attribute' ], $this );

            return null;
        }

        public function jsonSerialize()
        {
            return $this->toArray();
        }
    }
}
else
{
    trait CollectionCompat
    {
        public function offsetGet( $offset ) : mixed
        {
            if( isset( $this->container[ $offset ] ) )
                return $this->container[ $offset ];

            if( isset($this->model) && method_exists( $this->model, 'get' . Helper::snakeCaseToCamel( $offset ) . 'Attribute' ) )
                return call_user_func( [ new $this->model(), 'get' . Helper::snakeCaseToCamel( $offset ) . 'Attribute' ], $this );

            return null;
        }

        public function jsonSerialize() : mixed
        {
            return $this->toArray();
        }
    }
}