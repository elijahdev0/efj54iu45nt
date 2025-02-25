<?php

namespace FSPoster\App\Providers\DB;

use FSPoster\App\Providers\Helpers\Helper;

class QueryBuilder extends \stdClass
{

    private $_properties_qb = [
        'model'         => null,
        'whereArr'      => [],
        'orderByArr'    => [],
        'groupByArr'    => [],
        'columnsArr'    => [],
        'joins'         => [],
        'offset'        => null,
        'limit'         => null,
        'excludeScopes' => [],
        'addedScopes'   => [],
        'where_id'      => null,
    ];

    public function __construct ( $model )
    {
        $this->_properties_qb[ 'model' ] = $model;

        $model::boot( $this );
    }

    public function __call ( $name, $arguments )
    {
        $scopeMethodName = 'scope' . Helper::snakeCaseToCamel( $name );

        if ( method_exists( $this->_properties_qb[ 'model' ], $scopeMethodName ) && is_callable( [ $this->_properties_qb[ 'model' ], $scopeMethodName ] ) )
        {
            array_unshift( $arguments, $this );
            call_user_func_array( [ $this->_properties_qb[ 'model' ], $scopeMethodName ], $arguments );
        }

        return $this;
    }

    /**
     * @param $id
     *
     * @return Collection|static
     */
    public function get ( $id = null )
    {
        $model = $this->_properties_qb[ 'model' ];

        return $this->where( $model::getField( 'id' ), $id )->fetch();
    }

    public function where ( $field, $valueOrSymbol = false, $value2 = false, $combinator = 'AND' )
    {
        if ( is_array( $field ) && $valueOrSymbol === false )
        {
            foreach ( $field as $fieldOfArr => $valueOfArr )
            {
                $this->where( $fieldOfArr, $valueOfArr );
            }

            return $this;
        }

        $symbol = $value2 === false ? '=' : $valueOrSymbol;
        $value  = $value2 === false ? $valueOrSymbol : $value2;

        if ( $field == 'id' && is_numeric( $value ) )
        {
            $this->_properties_qb[ 'where_id' ] = $value;
        }

        $this->_properties_qb[ 'whereArr' ][] = [ $field, $symbol, $value, $combinator ];

        return $this;
    }

    public function orWhere ( $field, $valueOrSymbol = false, $value2 = false )
    {
        return $this->where( $field, $valueOrSymbol, $value2, 'OR' );
    }

    public function whereFindInSet ( $field, $value, $combinator = 'AND' )
    {
        return $this->where( $field, 'find_in_set', $value, $combinator );
    }

    public function orWhereFindInSet ( $field, $value )
    {
        return $this->whereFindInSet( $field, $value, 'OR' );
    }

    public function orderBy ( $arr )
    {
        $this->_properties_qb[ 'orderByArr' ] = array_merge( $this->_properties_qb[ 'orderByArr' ], (array)$arr );

        return $this;
    }

    public function groupBy ( $arr )
    {
        $this->_properties_qb[ 'groupByArr' ] = array_merge( $this->_properties_qb[ 'groupByArr' ], (array)$arr );

        return $this;
    }

    public function select ( $arr, $unselect_old_fields = false )
    {
        if ( $unselect_old_fields )
        {
            $this->_properties_qb[ 'columnsArr' ] = [];
        }

        $this->_properties_qb[ 'columnsArr' ] = array_merge( $this->_properties_qb[ 'columnsArr' ], (array)$arr );

        return $this;
    }

    public function selectSubQuery ( QueryBuilder $subQuery, $alias )
    {
        $this->_properties_qb[ 'columnsArr' ][] = '( ' . $subQuery->toSql() . ' ) AS ' . $alias;

        return $this;
    }

    public function limit ( $limit )
    {
        $this->_properties_qb[ 'limit' ] = $limit;

        return $this;
    }

    public function offset ( $offset )
    {
        $this->_properties_qb[ 'offset' ] = $offset;

        return $this;
    }

    private function join ( $joinTo, $joinType, $select_fields = 'id', $field1 = null, $field2 = null, $unselect_fields = false )
    {
        $joinTo = $this->normalizeTableName( $joinTo );

        $model = $this->_properties_qb[ 'model' ];

        $relations = $model::$relations;

        if ( isset( $relations[ $joinTo ] ) || ( !is_null( $field1 ) && !is_null( $field2 ) ) || is_array( $field1 ) )
        {
            $tableName = isset( $relations[ $joinTo ] ) ? $relations[ $joinTo ][ 0 ]::getTableName() : $joinTo;

            if ( !is_array( $field1 ) )
            {
                $field1 = !is_null( $field1 ) ? $field1 : DB::table( $tableName ) . '.' . $relations[ $joinTo ][ 1 ];
                $field2 = !is_null( $field2 ) ? $field2 : DB::table( $this->getTableName() ) . '.' . $relations[ $joinTo ][ 2 ];
            }

            $this->_properties_qb[ 'joins' ][] = [ $tableName, is_array( $field1 ) ? $field1 : [ [ $field1, '=', $field2 ] ], $joinType ];

            if ( !empty( $select_fields ) )
            {
                $select_fields = is_array( $select_fields ) ? $select_fields : (array)$select_fields;

                if ( empty( $this->_properties_qb[ 'columnsArr' ] ) && !$unselect_fields )
                    $this->_properties_qb[ 'columnsArr' ][] = DB::table( $this->getTableName() ) . '.*';

                foreach ( $select_fields as &$select_field )
                {
                    $select_field = DB::table( $tableName ) . '.' . $select_field . ' AS `' . $joinTo . '_' . $select_field . '`';
                }

                $this->_properties_qb[ 'columnsArr' ] = array_merge( $this->_properties_qb[ 'columnsArr' ], $select_fields );
            }
        }

        return $this;
    }

    public function leftJoin ( $joinTo, $select_fields = 'id', $field1 = null, $field2 = null, $unselect_fields = false )
    {
        return $this->join( $joinTo, 'LEFT', $select_fields, $field1, $field2, $unselect_fields );
    }

    public function rightJoin ( $joinTo, $select_fields = 'id', $field1 = null, $field2 = null, $unselect_fields = false )
    {
        return $this->join( $joinTo, 'RIGHT', $select_fields, $field1, $field2, $unselect_fields );
    }

    public function innerJoin ( $joinTo, $select_fields = 'id', $field1 = null, $field2 = null, $unselect_fields = false )
    {
        return $this->join( $joinTo, 'INNER', $select_fields, $field1, $field2, $unselect_fields );
    }

    public function withoutGlobalScope ( $scope )
    {
        if ( !in_array( $scope, $this->_properties_qb[ 'excludeScopes' ] ) )
        {
            $this->_properties_qb[ 'excludeScopes' ][] = $scope;
        }

        return $this;
    }

    private function bootGlobalScopes ( $queryType )
    {
        foreach ( $this->_properties_qb[ 'model' ]::getGlobalScopes() as $scope => $closure )
        {
            if ( !in_array( $scope, $this->_properties_qb[ 'excludeScopes' ] ) && !in_array( $scope, $this->_properties_qb[ 'addedScopes' ] ) )
            {
                $this->_properties_qb[ 'addedScopes' ][] = $scope;

                call_user_func( $closure, $this, $queryType );
            }
        }
    }

    public function fetch ()
    {
        $this->bootGlobalScopes( 'select' );

        $this->_properties_qb[ 'model' ]::trigger( 'retrieving', $this );

        $data = DB::fetch( $this->getTableName(), $this->getWhereArr(), $this->_properties_qb[ 'orderByArr' ], $this->_properties_qb[ 'columnsArr' ], $this->_properties_qb[ 'offset' ], $this->_properties_qb[ 'limit' ], $this->_properties_qb[ 'groupByArr' ], $this->_properties_qb[ 'joins' ] );

        if ( !$data )
        {
            return $data;
        }

        $result = new Collection( $data, $this->_properties_qb[ 'model' ] );

        $this->_properties_qb[ 'model' ]::trigger( 'retrieved', $result );

        return $result;
    }

    public function fetchAll ()
    {
        $this->bootGlobalScopes( 'select' );

        $this->_properties_qb[ 'model' ]::trigger( 'retrieving', $this );

        $data       = DB::fetchAll( $this->getTableName(), $this->getWhereArr(), $this->_properties_qb[ 'orderByArr' ], $this->_properties_qb[ 'columnsArr' ], $this->_properties_qb[ 'offset' ], $this->_properties_qb[ 'limit' ], $this->_properties_qb[ 'groupByArr' ], $this->_properties_qb[ 'joins' ] );
        $returnData = [];

        foreach ( $data as $row )
        {
            $result = new Collection( $row, $this->_properties_qb[ 'model' ] );
            $this->_properties_qb[ 'model' ]::trigger( 'retrieved', $result );

            $returnData[] = $result;
        }

        return $returnData;
    }

    public function toSql ()
    {
        $this->bootGlobalScopes( 'select' );

        return DB::selectQuery( $this->getTableName(), $this->getWhereArr(), $this->_properties_qb[ 'orderByArr' ], $this->_properties_qb[ 'columnsArr' ], $this->_properties_qb[ 'offset' ], $this->_properties_qb[ 'limit' ], $this->_properties_qb[ 'groupByArr' ], $this->_properties_qb[ 'joins' ] );
    }

    public function count (): int
    {
        $prevCols = $this->_properties_qb[ 'columnsArr' ];

        $count = $this->select( [ 'count(0) as `row_count`' ], true )->fetch()->row_count;

        $this->_properties_qb[ 'columnsArr' ] = $prevCols;

        return (int)$count;
    }

    public function sum ( $column )
    {
        $prevCols = $this->_properties_qb[ 'columnsArr' ];

        $sum = $this->select( [ 'SUM(' . $column . ') as `sum_column`' ], true )->fetch()->sum_column;

        $this->_properties_qb[ 'columnsArr' ] = $prevCols;

        return $sum;
    }

    public function max ( $column )
    {
        $prevCols = $this->_properties_qb[ 'columnsArr' ];

        $max = $this->select( [ 'MAX(' . $column . ') as `max_value`' ], true )->fetch();

        $this->_properties_qb[ 'columnsArr' ] = $prevCols;

        return $max->max_value ?? null;
    }

    public function min ( $column )
    {
        $prevCols = $this->_properties_qb[ 'columnsArr' ];

        $min = $this->select( [ 'MIN(' . $column . ') as `min_value`' ], true )->fetch();

        $this->_properties_qb[ 'columnsArr' ] = $prevCols;

        return $min->min_value ?? null;
    }

    public function update ( $data = [] )
    {
        $this->arrayToPropertis( $data );
        $this->bootGlobalScopes( 'update' );

        if ( $this->_properties_qb[ 'model' ]::trigger( 'updating', $this ) === false )
        {
            return false;
        }

        $result = DB::update( $this->getTableName(), $this->getProperties(), $this->getWhereArr() );

        $this->_properties_qb[ 'model' ]::trigger( 'updated', $this );

        return $result;
    }

    public function delete ()
    {
        $this->bootGlobalScopes( 'delete' );

        $deletedId = $this->_properties_qb[ 'where_id' ];

        if ( !empty( $deletedId ) && $this->_properties_qb[ 'model' ]::trigger( 'deleting', $deletedId ) === false )
        {
            return false;
        }

        $result = DB::delete( $this->getTableName(), $this->_properties_qb[ 'whereArr' ] );

        if ( !empty( $deletedId ) )
        {
            $this->_properties_qb[ 'model' ]::trigger( 'deleted', $deletedId );
        }

        return $result;
    }

    public function insert ( $data = [] )
    {
        $this->arrayToPropertis( $data );
        $this->bootGlobalScopes( 'insert' );

        if ( $this->_properties_qb[ 'model' ]::trigger( 'creating', $this ) === false )
        {
            return false;
        }

        $result = DB::DB()->insert( DB::table( $this->getTableName() ), $this->getProperties() );

        $this->_properties_qb[ 'model' ]::trigger( 'created', $this );

        return $result;
    }

    public function getTableName ()
    {
        $model = $this->_properties_qb[ 'model' ];

        return $model::getTableName();
    }

    private function normalizeTableName ( $tableName )
    {
        if ( is_subclass_of( $tableName, Model::class ) )
        {
            $tableName = $tableName::getTableName();
        }

        return $tableName;
    }

    public function getWhereArr ()
    {
        return $this->_properties_qb[ 'whereArr' ];
    }

    public function setWhereArr ( $whereArr )
    {
        $this->_properties_qb[ 'whereArr' ] = $whereArr;

        return $this;
    }

    public function getModel ()
    {
        return $this->_properties_qb[ 'model' ];
    }

    private function arrayToPropertis ( $array )
    {
        foreach ( $array as $key => $value )
        {
            if ( !in_array( $key, $this->_properties_qb[ 'model' ]::$writeableColumns ) )
            {
                continue;
            }

            $this->$key = $value;
        }
    }

    public function getProperties ()
    {
        $properties = get_object_vars( $this );

        unset( $properties[ '_properties_qb' ] );

        return $properties;
    }
}