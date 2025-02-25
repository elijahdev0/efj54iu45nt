<?php

namespace FSPoster\App\Providers\Helpers;

class CatWalker extends \Walker
{
	private array $data = [];
	private array $taxonomies;
	private array $response = [];

	private function __construct(){
		$this->taxonomies = get_taxonomies( ['public' => true] );

		foreach ($this->taxonomies as $taxonomy){
			$this->response[$taxonomy] = [
				'label'     => get_taxonomy($taxonomy)->label,
				'taxonomy'  => $taxonomy,
				'options'   => []
			];
		}
	}

	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'term_id',
	);

	public function start_el ( &$output, $object, $depth = 0, $args = [], $current_object_id = 0 )
	{
		$info = [
			'text'         => str_repeat("-", $depth) . $object->name,
			'id'           => $object->term_id,
			'taxonomy'     => $object->taxonomy
		];

		$this->data[] = $info;
		//parent::start_el( $output, $object, $depth, $args, $current_object_id );
	}

	public static function getCats( ?string $query = null, ?string $postType = null) : array
    {
		$walker = new self();

		$args = [
			'orderby'       => 'count',
			'order'         => 'DESC',
			'hide_empty'    => 0,
			'depth'         => 3,
			'hide_if_empty' => false
		];

		if(!empty($query)){
			$args['name__like'] = $query;
		}else{
			$args['number'] = 100;
		}

        if ( version_compare( get_bloginfo( 'version' ) , '4.5.0' ,  '>=' ) )
        {
            $args['taxonomy'] = $walker->taxonomies;
            $terms = get_terms( $args );
        }
        else
        {
            $terms = get_terms( $walker->taxonomies,$args );
        }

        $term_ids = [ ];

        foreach ( $terms as $term )
        {
            $term_ids[] = $term->term_id;
        }

        $term_ids = array_unique( $term_ids );

        foreach ( $terms as &$term )
        {
            if( $term->parent != 0 && !in_array( $term->parent, $term_ids ) )
            {
                $term->parent = 0;
            }
        }

		//makes hierarchy
		$walker->walk($terms, 10);

		foreach ($walker->data as $item)
        {
			$walker->response[$item['taxonomy']]['options'][] = [
				'label' => $item['text'],
				'value' => $item['id']
			];
		}

        $response = array_values($walker->response);

        foreach ( $response as $i => $value ){
            if (empty($value['options'])){
                unset($response[$i]);
            }
        }

		return array_values($response);
	}
}