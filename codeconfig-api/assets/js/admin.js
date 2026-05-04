( function( $ ) {
	'use strict';

	$( document ).ready( function() {

		var versionForm = $( '#codeconfig-add-version-form' );

		if ( versionForm.length ) {

			var versionInput = $( '#version' );
			var slugInput = $( '#slug' );
			var fileInput = $( '#plugin_zip' );
			var statusDiv = $( '#codeconfig-detect-status' );
			var mediaBtn = $( '#codeconfig-select-media' );

			versionForm.on( 'submit', function( e ) {
				var version = versionInput.val();
				var slug = slugInput.val();

				if ( ! version || ! slug ) {
					return true;
				}

				e.preventDefault();

				$.post( codeconfigAdmin.ajaxUrl, {
					action:  'codeconfig_check_version',
					nonce:   codeconfigAdmin.nonce,
					version: version,
					slug:    slug,
				}, function( response ) {
					if ( response.success && response.data.exists ) {
						if ( confirm( codeconfigAdmin.confirmUpdate ) ) {
							submitForm();
						}
					} else {
						submitForm();
					}
				});
			});

			function submitForm() {
				versionForm[0].submit();
			}

			fileInput.on( 'change', function() {
				var file = this.files[0];

				if ( ! file ) {
					return;
				}

				// Check if it's a ZIP file
				if ( ! file.name.toLowerCase().endsWith('.zip') ) {
					alert( 'Only ZIP files are allowed.' );
					this.value = ''; // Clear the file input
					return;
				}

				if ( typeof JSZip === 'undefined' ) {
					serverParseZip( file );
					return;
				}

				statusDiv.html( '<span class="spinner is-active"></span> ' + codeconfigAdmin.detecting );

				var reader = new FileReader();

				reader.onload = function( e ) {
					JSZip.loadAsync( e.target.result ).then( function( zip ) {
						var slug = findZipSlug( zip );

						if ( ! slug ) {
							statusDiv.text( 'Could not detect plugin slug.' );
							return;
						}

						var mainFile = zip.file( slug + '/' + slug + '.php' );

						if ( mainFile ) {
							mainFile.async( 'string' ).then( function( content ) {
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

					statusDiv.html( '<span class="spinner is-active"></span> ' + codeconfigAdmin.detecting );

					$.post( codeconfigAdmin.ajaxUrl, {
						action: 'codeconfig_parse_media_zip',
						nonce:  codeconfigAdmin.mediaNonce,
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

			$( '.codeconfig-edit-toggle' ).on( 'click', function() {
				var wrapper = $( this ).closest( '.codeconfig-field-wrapper' );
				var input = wrapper.find( 'input' );

				input.prop( 'readonly', false ).removeClass( 'codeconfig-auto-filled' );
				$( this ).hide();
				wrapper.find( '.codeconfig-detected-badge' ).hide();
				input.focus();
			});

			$( document ).on( 'click', '.codeconfig-edit-btn', function() {
				var btn = $( this );
				var id = btn.data( 'id' );
				console.log('Edit button clicked, id:', id);
				console.log('codeconfigAdmin.versions:', codeconfigAdmin.versions);
				var data = codeconfigAdmin.versions && codeconfigAdmin.versions[ id ];

				if ( ! data ) {
					console.log('No data found for id:', id);
					alert('No version data found. ID: ' + id);
					return;
				}

				$( '#edit_id' ).val( id );
				$( '#edit_version' ).val( data.version );
				$( '#edit_slug' ).val( data.slug );
				$( '#edit_changelog' ).val( data.changelog );
				$( '#edit_zip' ).val( '' );

				tb_show( 'Edit Version', '#TB_inline?height=400&width=500&inlineId=codeconfig-edit-form' );
			});

			// Single action buttons (activate/deactivate/delete)
			$( document ).on( 'click', '.codeconfig-single-action-btn', function() {
				var btn = $( this );
				var action = btn.data( 'action' );
				var id = btn.data( 'id' );

				if ( action === 'delete' ) {
					if ( ! confirm( 'Delete this version?' ) ) {
						return;
					}
				}

				$( '#single-action-type' ).val( action );
				$( '#single-action-id' ).val( id );
				$( '#codeconfig-single-action-form' ).submit();
			});

			$( document ).on( 'click', '#codeconfig-cancel-edit', function() {
				tb_remove();
			});

			function findZipSlug( zip ) {
				var folders = {};
				var fileNames = Object.keys( zip.files );

				for ( var i = 0; i < fileNames.length; i++ ) {
					var name = fileNames[i];

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

				for ( var j = 0; j < fileNames.length; j++ ) {
					var parts = fileNames[j].split( '/' );

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
				var label = codeconfigAdmin.detected;

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
				var wrapper = input.closest( '.codeconfig-field-wrapper' );
				var badge = wrapper.find( '.codeconfig-detected-badge' );
				var toggle = wrapper.find( '.codeconfig-edit-toggle' );

				input.prop( 'readonly', true ).addClass( 'codeconfig-auto-filled' );
				badge.text( codeconfigAdmin.detected ).show();
				toggle.text( codeconfigAdmin.edit ).show();
			}

			function serverParseZip( file ) {
				statusDiv.html( '<span class="spinner is-active"></span> ' + codeconfigAdmin.detecting );

				var formData = new FormData();
				formData.append( 'action', 'codeconfig_parse_zip' );
				formData.append( 'nonce', codeconfigAdmin.nonce );
				formData.append( 'zip_file', file );

				$.ajax({
					url: codeconfigAdmin.ajaxUrl,
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

	// Make entire metabox header clickable to toggle open/close
	$( '.codeconfig-upload-metabox .postbox-header' ).css( 'cursor', 'pointer' );

	// Disable WordPress postbox.js for our custom metabox to avoid conflicts
	$( document ).ready( function() {
		$( '.codeconfig-upload-metabox' ).removeClass( 'postbox' );
	});

	$( document ).on( 'click', '.codeconfig-upload-metabox .postbox-header', function( e ) {
		// Don't toggle if clicking interactive elements
		if ( $( e.target ).is( 'button, input, select, textarea, a' ) ) {
			return;
		}

		var postbox = $( this ).closest( '.codeconfig-upload-metabox' );
		var button = postbox.find( '.handlediv' );
		var expanded = button.attr( 'aria-expanded' ) === 'true';

		button.attr( 'aria-expanded', ! expanded );
		postbox.toggleClass( 'closed' );
	});

		// Drag and drop functionality
		var dropZone = $( '#codeconfig-drop-zone' );

		dropZone.on( 'dragenter dragover', function( e ) {
			e.preventDefault();
			e.stopPropagation();
			$( this ).addClass( 'drag-over' );
		});

		dropZone.on( 'dragleave', function( e ) {
			e.preventDefault();
			e.stopPropagation();
			$( this ).removeClass( 'drag-over' );
		});

		dropZone.on( 'drop', function( e ) {
			e.preventDefault();
			e.stopPropagation();
			$( this ).removeClass( 'drag-over' );

			var files = e.originalEvent.dataTransfer.files;
			if ( files.length > 0 ) {
				var file = files[0];
				// Check if it's a ZIP file
				if ( file.name.toLowerCase().endsWith( '.zip' ) ) {
					// Set the file to the input
					var input = $( '#plugin_zip' )[0];
					var dataTransfer = new DataTransfer();
					dataTransfer.items.add( file );
					input.files = dataTransfer.files;

					// Trigger change event to parse the file
					$( input ).trigger( 'change' );
				} else {
					alert( 'Please drop a ZIP file only.' );
				}
			}
		});

		// Click on drop zone to open file browser
		dropZone.on( 'click', function( e ) {
			// Don't trigger if clicking on the "Select from Media Library" button
			if ( e.target.id === 'codeconfig-select-media' || $( e.target ).closest( '#codeconfig-select-media' ).length ) {
				return;
			}
			// Also don't trigger if clicking on the file input itself
			if ( e.target.type === 'file' ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			document.getElementById( 'plugin_zip' ).click();
		});

		// Also trigger on inner elements
		dropZone.children().on( 'click', function( e ) {
			if ( e.target.id === 'codeconfig-select-media' || $( e.target ).closest( '#codeconfig-select-media' ).length ) {
				return;
			}
			if ( e.target.type === 'file' ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			document.getElementById( 'plugin_zip' ).click();
		});

		// Confirm bulk action
		window.confirmBulkAction = function() {
			var action = $( '#bulk-action-selector' ).val();
			var checked = $( '#codeconfig-table-form input[name="bulk_ids[]"]:checked' ).length;

			if ( ! action ) {
				alert( 'Please select an action.' );
				return false;
			}

			if ( checked === 0 ) {
				alert( 'Please select at least one item.' );
				return false;
			}

			if ( action === 'delete' ) {
				if ( ! confirm( 'Are you sure you want to delete the selected items?' ) ) {
					return false;
				}
			}

			$( '#bulk-action-type' ).val( action );
			$( '#codeconfig-table-form' ).submit();
			return true;
		};

		// Select all checkbox
		$( '#cb-select-all' ).on( 'click', function() {
			$( '#codeconfig-table-form input[name="bulk_ids[]"]' ).prop( 'checked', this.checked );
		});
	});
})( jQuery );
