<?php

namespace FSPoster\App\Providers\DB;

use FSPoster\App\Providers\Helpers\Helper;

trait BlogScope
{

	public static function booted()
	{
		self::addGlobalScope( 'blog', function ( QueryBuilder $builder, $queryType )
		{
			if( $queryType == 'insert' )
			{
				$builder->blog_id = Helper::getBlogId();
			}
			else
			{
				/**
				 * wrap wheres in brackets: example: "WHERE type=5 OR type=6 AND blog_id=10" => "WHERE (type=5 OR type=6) AND blog_id=10"
				 */
				if( ! empty( $builder->getWhereArr() ) )
				{
					$currentWhereArr = $builder->getWhereArr();

					$newWrappedWhere = new QueryBuilder( $builder->getModel() );

					$newWrappedWhere->where(function( $query ) use ( $currentWhereArr )
					{
						$query->setWhereArr( $currentWhereArr );
					});

					$builder->setWhereArr( $newWrappedWhere->getWhereArr() );
				}

				$builder->where( self::getField('blog_id'), Helper::getBlogId() );
			}
		});
	}

}
