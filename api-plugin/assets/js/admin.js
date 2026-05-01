(function( $ ) {
	'use strict';

	$( document ).ready( function() {

		var versionForm = $( 'form[action*="myplugin_versions_action"]' ).first();

		if ( versionForm.length ) {
			versionForm.on( 'submit', function( e ) {
				var form = $( this );
				var version = $( '#version' ).val();
				var slug = $( '#slug' ).val();

				if ( ! version || ! slug ) {
					return true;
				}

				e.preventDefault();

				$.post( mypluginAdmin.ajaxUrl, {
					action:  'myplugin_check_version',
					nonce:   mypluginAdmin.nonce,
					version: version,
					slug:    slug,
				}, function( response ) {
					if ( response.success && response.data.exists ) {
						if ( confirm( mypluginAdmin.confirmUpdate ) ) {
							form.append( '<input type="hidden" name="existing_id" value="' + response.data.id + '" />' );
							form[0].submit();
						}
					} else {
						form[0].submit();
					}
				});
			});
		}
	});
})( jQuery );
