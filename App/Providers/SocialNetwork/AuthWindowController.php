<?php

namespace FSPoster\App\Providers\SocialNetwork;


abstract class AuthWindowController
{

	public static function error ( $message = '' )
	{
		if ( empty( $message ) )
			$message = fsp__( 'An error occurred while processing your request! Please close the window and try again' );

		echo '<div>' . esc_html( $message ) . '</div>';
		?>
		<script type="application/javascript">
			if ( typeof window.opener.FSPosterToast === 'object' )
			{
				window.opener.FSPosterToast.error( "<?php echo addslashes($message); ?>" );
				window.close();
			}
		</script>
		<?php

		exit();
	}

	public static function closeWindow ( $channels )
	{
		echo '<div>' . fsp__( 'Loading...' ) . '</div>';
		?>
		<script type="application/javascript">
			if ( typeof window.opener.FSPosterAddChannels === 'function' )
			{
				window.opener.FSPosterAddChannels( <?php print json_encode( $channels )?>  );
				window.close();
			}
		</script>
		<?php

		exit;
	}

}