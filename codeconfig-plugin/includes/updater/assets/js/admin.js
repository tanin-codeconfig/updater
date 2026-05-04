(function( $ ) {
	'use strict';

	$( document ).ready( function() {

		console.log('CodeConfig Admin JS loaded');
		console.log('codeconfigPluginAdmin:', codeconfigPluginAdmin);

		var checkBtn = $( '#codeconfig-plugin-check-btn' );
		var refreshBtn = $( '#codeconfig-plugin-refresh-btn' );
		var testBtn = $( '#codeconfig-plugin-test-btn' );
		var pluginRowBtn = $( '#codeconfig-plugin-row-check' );

		console.log('Buttons found - checkBtn:', !!checkBtn.length, 'refreshBtn:', !!refreshBtn.length, 'testBtn:', !!testBtn.length, 'pluginRowBtn:', !!pluginRowBtn.length);

		if ( checkBtn.length ) {
			checkBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#codeconfig-plugin-check-result' );

				btn.prop( 'disabled', true ).html( '<span class="spinner is-active"></span> ' + codeconfigPluginAdmin.checking );
				resultDiv.hide().removeClass( 'notice-success notice-error notice-warning' );

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'check',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					if ( response.update_available ) {
						window.location.reload();
					} else {
						btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="margin-top:3px;"></span> ' + codeconfigPluginAdmin.checkBtn );
						resultDiv.addClass( 'notice-success' ).html( '<p>' + response.message + '</p>' ).show();
					}
				} ).fail( function() {
					btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="margin-top:3px;"></span> ' + codeconfigPluginAdmin.checkBtn );
					resultDiv.addClass( 'notice-error' ).html( '<p>Request failed. Try again.</p>' ).show();
				} );
			});
		}

		if ( refreshBtn.length ) {
			refreshBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#codeconfig-plugin-check-result' );

				btn.prop( 'disabled', true ).text( 'Clearing...' );

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'force-refresh',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					btn.prop( 'disabled', false ).text( 'Force Refresh' );
					window.location.reload();
				} ).fail( function() {
					btn.prop( 'disabled', false ).text( 'Force Refresh' );
					resultDiv.removeClass( 'notice-success' ).addClass( 'notice-error' );
					resultDiv.html( '<p>Failed.</p>' ).show();
				} );
			});
		}

		if ( testBtn.length ) {
			testBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#codeconfig-plugin-test-result' );

				btn.prop( 'disabled', true ).text( 'Testing...' );

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'test-connection',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					btn.prop( 'disabled', false ).text( 'Test Connection' );

					if ( response.success ) {
						resultDiv.removeClass( 'notice-error' ).addClass( 'notice-success' );
						resultDiv.html( '<p>' + response.message + '</p>' ).show();
					} else {
						resultDiv.removeClass( 'notice-success' ).addClass( 'notice-error' );
						resultDiv.html( '<p>' + ( response.message || 'Connection failed.' ) + '</p>' ).show();
					}
				} ).fail( function() {
					btn.prop( 'disabled', false ).text( 'Test Connection' );
					resultDiv.removeClass( 'notice-success' ).addClass( 'notice-error' );
					resultDiv.html( '<p>Connection failed.</p>' ).show();
				} );
			});
		}

		var pluginRowBtn = $( '#codeconfig-plugin-row-check' );
		if ( pluginRowBtn.length ) {
			pluginRowBtn.on( 'click', function( e ) {
				e.preventDefault();
				console.log('Plugin row button clicked');
				console.log('REST URL:', codeconfigPluginAdmin.restUrl);

				var btn = $( this );
				var originalText = btn.text();

				btn.html( '<span class="spinner is-active"></span> Checking...' ).addClass( 'disabled' ).css( 'pointer-events', 'none' );

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'check',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					console.log('Response:', response);
					if ( response.update_available ) {
						window.location.reload();
					} else {
						btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );
						var notice = '<div class="notice notice-success is-dismissible" style="margin:10px 5px;"><p>' + response.message + '</p></div>';
						btn.closest( 'tr' ).after( notice );
						setTimeout( function() {
							$( '.notice.is-dismissible' ).fadeOut();
						}, 5000 );
					}
				} ).fail( function( jqXHR, textStatus, errorThrown ) {
					console.log('AJAX Error:', textStatus, errorThrown);
					btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );
					var notice = '<div class="notice notice-error is-dismissible" style="margin:10px 5px;"><p>Request failed. Try again.</p></div>';
					btn.closest( 'tr' ).after( notice );
					setTimeout( function() {
						$( '.notice.is-dismissible' ).fadeOut();
					}, 5000 );
				} );
			});
		}
	});
})( jQuery );