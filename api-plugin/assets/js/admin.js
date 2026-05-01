(function( $ ) {
	'use strict';

	$( document ).ready( function() {

		var versionForm = $( '#myplugin-add-version-form' );

		if ( versionForm.length ) {

			var versionInput = $( '#version' );
			var slugInput = $( '#slug' );
			var fileInput = $( '#plugin_zip' );
			var statusDiv = $( '#myplugin-detect-status' );
			var mediaBtn = $( '#myplugin-select-media' );

			versionForm.on( 'submit', function( e ) {
				var version = versionInput.val();
				var slug = slugInput.val();

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
							versionForm[0].submit();
						}
					} else {
						versionForm[0].submit();
					}
				});
			});

			fileInput.on( 'change', function() {
				var file = this.files[0];

				if ( ! file ) {
					return;
				}

				if ( typeof JSZip === 'undefined' ) {
					serverParseZip( file );
					return;
				}

				statusDiv.html( '<span class="spinner is-active"></span> ' + mypluginAdmin.detecting );

				var reader = new FileReader();

				reader.onload = function( e ) {
					JSZip.loadAsync( e.target.result ).then( function( zip ) {
						var slug = findZipSlug( zip );

						if ( ! slug ) {
							statusDiv.text( 'Could not detect plugin slug.' );
							return;
						}

						var mainFile = zip.file( slug + '/' + slug + '.php' );

						if ( mainFile.length > 0 ) {
							mainFile[0].async( 'string' ).then( function( content ) {
								var version = extractHeader( content, 'Version' );
								var name = extractHeader( content, 'Plugin Name' );

								autoFillFields( slug, version, name );
							});
						} else {
							autoFillFields( slug, '', '' );
						}

					}).catch( function() {
						serverParseZip( file );
					});
				};

				reader.readAsArrayBuffer( file );
			});

			mediaBtn.on( 'click', function( e ) {
				e.preventDefault();

				if ( typeof wp === 'undefined' || typeof wp.media === 'undefined' ) {
					statusDiv.text( 'Media library not loaded. Please refresh the page.' );
					return;
				}

				var frame = wp.media({
					title: 'Select Plugin ZIP',
					button: { text: 'Use this ZIP' },
					library: { type: 'application/zip' },
					multiple: false
				});

				frame.on( 'select', function() {
					var attachment = frame.state().get( 'selection' ).first().toJSON();

					statusDiv.html( '<span class="spinner is-active"></span> ' + mypluginAdmin.detecting );

					$.post( mypluginAdmin.ajaxUrl, {
						action: 'myplugin_parse_media_zip',
						nonce:  mypluginAdmin.mediaNonce,
						attachment_id: attachment.id,
					}, function( response ) {
						if ( response.success ) {
							autoFillFields( response.data.slug, response.data.version, response.data.name );
							$( '#media_attachment_id' ).val( attachment.id );
						} else {
							statusDiv.text( response.data.message || 'Parse failed.' );
						}
					}).fail( function() {
						statusDiv.text( 'Request failed.' );
					});
				});

				frame.open();
			});

			$( '.myplugin-edit-toggle' ).on( 'click', function() {
				var wrapper = $( this ).closest( '.myplugin-field-wrapper' );
				var input = wrapper.find( 'input' );

				input.prop( 'readonly', false ).removeClass( 'myplugin-auto-filled' );
				$( this ).hide();
				wrapper.find( '.myplugin-detected-badge' ).hide();
				input.focus();
			});

			$( document ).on( 'click', '.myplugin-edit-btn', function() {
				var btn = $( this );
				var id = btn.data( 'id' );
				var data = mypluginAdmin.versions && mypluginAdmin.versions[ id ];

				if ( ! data ) {
					return;
				}

				var form = $( '#myplugin-edit-form' );

				$( '#edit_id' ).val( id );
				$( '#edit_version' ).val( data.version );
				$( '#edit_slug' ).val( data.slug );
				$( '#edit_changelog' ).val( data.changelog );
				$( '#edit_zip' ).val( '' );

				form.slideDown( 200 );
				$( 'html, body' ).animate({ scrollTop: form.offset().top - 50 }, 300 );
			});

			$( document ).on( 'click', '#myplugin-cancel-edit', function() {
				$( '#myplugin-edit-form' ).slideUp( 200 );
			});

			function findZipSlug( zip ) {
				var folders = {};

				for ( var i = 0; i < zip.files.length; i++ ) {
					var name = zip.files[i].name;

					if ( name.indexOf( '/' ) !== -1 && name.split( '/' ).length === 2 && name.split( '/' )[1] === '' ) {
						var folder = name.replace( '/', '' );

						if ( ! folders[ folder ] ) {
							folders[ folder ] = 0;
						}

						folders[ folder ]++;
					}
				}

				var topFolders = Object.keys( folders );

				if ( topFolders.length === 1 ) {
					return topFolders[0];
				}

				if ( topFolders.length > 1 ) {
					topFolders.sort( function( a, b ) {
						return a.length - b.length;
					});
					return topFolders[0];
				}

				for ( var j = 0; j < zip.files.length; j++ ) {
					var parts = zip.files[j].name.split( '/' );

					if ( parts.length >= 2 && parts[0] ) {
						return parts[0];
					}
				}

				return null;
			}

			function extractHeader( content, header ) {
				var pattern = new RegExp( '^\\s*\\*\\s*' + header + '\\s*:\\s*(.+)$', 'mi' );
				var match = pattern.exec( content );
				return match ? match[1].trim() : '';
			}

			function autoFillFields( slug, version, name ) {
				var label = mypluginAdmin.detected;

				if ( name ) {
					label = name + ' v' + ( version || '?' );
				}

				if ( slug ) {
					slugInput.val( slug );
					lockField( slugInput, label );
				}

				if ( version ) {
					versionInput.val( version );
					lockField( versionInput, label );
				}

				statusDiv.text( '✓ ' + label );
			}

			function lockField( input, badgeText ) {
				var wrapper = input.closest( '.myplugin-field-wrapper' );
				var badge = wrapper.find( '.myplugin-detected-badge' );
				var toggle = wrapper.find( '.myplugin-edit-toggle' );

				input.prop( 'readonly', true ).addClass( 'myplugin-auto-filled' );
				badge.text( mypluginAdmin.detected ).show();
				toggle.text( mypluginAdmin.edit ).show();
			}

			function serverParseZip( file ) {
				statusDiv.html( '<span class="spinner is-active"></span> ' + mypluginAdmin.detecting );

				var formData = new FormData();
				formData.append( 'action', 'myplugin_parse_zip' );
				formData.append( 'nonce', mypluginAdmin.nonce );
				formData.append( 'zip_file', file );

				$.ajax({
					url: mypluginAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function( response ) {
						if ( response.success ) {
							autoFillFields( response.data.slug, response.data.version, response.data.name );
						} else {
							statusDiv.text( response.data.message || 'Parse failed.' );
						}
					},
					error: function() {
						statusDiv.text( 'Request failed.' );
					}
				});
			}
		}
	});
})( jQuery );
