<?php

namespace FSPoster\App\Pages\Channels;

use Exception;
use FSPoster\App\Models\ChannelLabel;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Core\RestRequest;

class LabelsController
{

    /**
     * @throws Exception
     */
    public static function create ( RestRequest $request ): array
    {
        $name  = $request->require( 'name', RestRequest::TYPE_STRING, fsp__( 'Name can\'t be empty' ) );
        $color = $request->require( 'color', RestRequest::TYPE_STRING, fsp__( 'Color can\'t be empty' ) );

        $existingLabel = ChannelLabel::where( 'name', $name )->fetch();

        if ( !empty( $existingLabel ) )
        {
            throw new Exception( fsp__( 'A label with the name already exists.' ) );
        }

        if ( preg_match( '/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color ) !== 1 )
        {
            throw new Exception( fsp__( 'Color must be a valid hexadecimal color code' ) );
        }

        ChannelLabel::insert( [
            'name'       => $name,
            'color'      => $color,
            'created_by' => get_current_user_id(),
            'blog_id'    => Helper::getBlogId(),
        ] );

        return [
            'status' => 'ok',
            'value'  => ChannelLabel::lastId(),
            'label'  => $name,
            'color'  => $color,
        ];
    }

    /**
     * @throws Exception
     */
    public static function edit ( RestRequest $request ): array
    {
        $id    = $request->require( 'id', RestRequest::TYPE_INTEGER, fsp__( 'ID can\'t be empty' ) );
        $name  = $request->require( 'name', RestRequest::TYPE_STRING, fsp__( 'Name can\'t be empty' ) );
        $color = $request->require( 'color', RestRequest::TYPE_STRING, fsp__( 'Color can\'t be empty' ) );

        if ( preg_match( '/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color ) !== 1 )
            throw new Exception( fsp__( 'Color must be a valid hexadecimal color code' ) );

        $existingLabel = ChannelLabel::where( 'id', $id )->fetch();

        if ( empty( $existingLabel ) )
            throw new Exception( fsp__( 'Label doesn\'t exist.' ) );

		if( $existingLabel->created_by != get_current_user_id() )
			throw new Exception( fsp__( 'You don\'t have access to the label' ) );

        if ( $existingLabel->color === $color && $existingLabel->name === $name )
        {
            return [];
        }

        ChannelLabel::where( 'id', $id )->update( [
            'name'  => $name,
            'color' => $color,
        ] );

        return [
            'label' => $name,
            'value' => $id,
            'color' => $color,
        ];
    }

    /**
     * @throws Exception
     */
    public static function delete ( RestRequest $request ): array
    {
        $ids      = $request->require( 'ids', RestRequest::TYPE_ARRAY, fsp__( 'IDs cannot be empty' ) );
        $myLabels = ChannelLabel::where( 'id', 'in', $ids )->fetchAll();

        if ( count( $ids ) !== count( $myLabels ) )
            throw new Exception( fsp__( 'Wrong labels' ) );

		foreach ( $myLabels AS $label )
		{
			if( $label->created_by != get_current_user_id() )
				throw new Exception( fsp__( 'You don\'t have access to the label' ) );
		}

        ChannelLabel::where( 'id', $ids )->delete();
        return [];
    }

    public static function get ( RestRequest $request ): array
    {
        $labels = ChannelLabel::fetchAll();

        $labels = array_map( fn ( $label ) => [
            'value'         => (int)$label->id,
            'label'         => $label->name,
            'color'         => $label->color,
	        'created_by'    => $label->created_by
        ], $labels );

        return [ 'labels' => $labels ];
    }
}