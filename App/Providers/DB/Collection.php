<?php

namespace FSPoster\App\Providers\DB;

use FSPoster\App\Providers\Helpers\Helper;

/**
 * Class Collection
 * @package BookneticApp\Providers
 */
class Collection implements \ArrayAccess, \JsonSerializable
{
    use CollectionCompat;
	/**
	 * @var Model
	 */
	private $model;

	/**
	 * @var array
	 */
	private $container = [];

	/**
	 * Collection constructor.
	 * @param array $array
	 */
	public function __construct( $array = false, $model = null )
	{
		$this->container    = $array;
		$this->model        = $model;

		if( ! empty( $this->model ) && method_exists( $this->model, 'casts' ) ) {
			$castsArr = call_user_func( [ new $this->model(), 'casts' ] );

			foreach ( $castsArr as $key => $type ) {
				if( array_key_exists( $key, $this->container ) ) {
					switch( $type )
					{
						case 'int':
						case 'integer':
							$this->container[$key] = (int)$this->container[$key];
							break;
						case 'string':
							$this->container[$key] = (string)$this->container[$key];
							break;
						case 'bool':
						case 'boolean':
							$this->container[$key] = (bool)$this->container[$key];
							break;
						case 'array':
							$this->container[$key] = json_decode( $this->container[$key] ?: '[]', true );
							break;
						case 'float':
							$this->container[$key] = (float)$this->container[$key];
							break;
					}
				}
			}
		}
	}

	/**
	 * @param $offset
	 * @param $value
	 */
	public function offsetSet( $offset, $value ) : void
	{
		if ( is_null( $offset ) )
		{
			$this->container[] = $value;
		}
		else
		{
			$this->container[ $offset ] = $value;
		}
	}

	/**
	 * @param $offset
	 * @return bool
	 */
	public function offsetExists( $offset ) : bool
	{
		if( isset( $this->container[$offset] ) )
			return true;

		if( isset($this->model) && method_exists( $this->model, 'get' . Helper::snakeCaseToCamel( $offset ) . 'Attribute' ) )
			return true;

		return false;
	}

	/**
	 * @param $offset
	 */
	public function offsetUnset( $offset ) : void
	{
		if( isset( $this->container[ $offset ] ) )
			unset( $this->container[ $offset ] );
	}

	public function __get( $name )
	{
        if( !empty($this->model) && isset( $this->model::$relations[ $name ] ) )
        {
            $model = $this->model;
            $relations = $model::$relations;
            /**
             * @var Model $rModel
             */
            $rModel = $relations[ $name ][0];

            if( isset( $relations[ $name ][1] ) )
            {
                $relationFieldName = $relations[ $name ][1];
            }
            else
            {
                $model = $this->model;

                $relationFieldName = rtrim( $model::getTableName(), 's' ) . '_id';
            }

            if( isset( $relations[ $name ][2] ) )
            {
                $idFieldName = $relations[ $name ][2];
            }
            else
            {
                $idFieldName = 'id';
            }

            return $rModel::where( $relationFieldName, $this->{$idFieldName} );
        }

		return $this->offsetGet( $name );
	}

	public function __isset( $name )
	{
		return $this->offsetExists( $name );
	}

	public function __call( $name, $arguments )
	{
		if( isset($this->model) && method_exists( $this->model, $name ) )
		{
			return call_user_func_array( [ $this->model, $name ], array_merge( $arguments, [ $this ] ) );
		}

		return null;
	}

	public function __set( $name, $value )
	{
		$this->offsetSet( $name, $value );
	}

	public function __unset( $name )
	{
		$this->offsetUnset( $name );
	}

	public function toArray()
	{
		return $this->container;
	}
}
