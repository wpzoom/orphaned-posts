( function( $ ) {
	$( function() {
		var $wrap = $( '#wpzoom-orphaned-data' ),
		    $bulk_actions = $wrap.find( 'form .bulkactions' );

		$bulk_actions.find( 'input:submit' ).prop( 'disabled', true );

		$bulk_actions.find( 'select[name^="action"]' ).on( 'change', function() {
			var $this_bulk_actions = $( this ).closest( '.bulkactions' ),
			    $post_type_select = $this_bulk_actions.find( '.bulkactions-post-type' ),
			    is_change_type = $( this ).val() == 'change_type',
			    disabled = $( this ).val() == '-1' || ( is_change_type && !$post_type_select.val() );

			$this_bulk_actions.find( 'input:submit' ).prop( 'disabled', disabled );

			$post_type_select.toggleClass( 'hidden', !is_change_type );
		} );

		$bulk_actions.find( 'select[name="wpzod_post_type"]' ).on( 'change', function() {
			$( this ).closest( '.bulkactions' ).find( 'input:submit' ).prop( 'disabled', !$( this ).val() );
		} );

		$wrap.find( '#the-list td.post-type select' ).on( 'change', function() {
			var $tr = $( this ).closest( 'tr' ),
			    id = $tr.attr( 'id' ).replace( 'post-', '' );

			$tr.find( '.check-column input:checkbox' ).attr( 'checked', true ).prop( 'checked', true );

			$wrap.find( 'select[name="action"]' ).val( 'change_type' );

			$wrap.find( 'select[name="wpzod_post_type"]' ).val( $( this ).val() );

			$wrap.find( '> form' ).submit();
		} );
	} );
} )( jQuery );