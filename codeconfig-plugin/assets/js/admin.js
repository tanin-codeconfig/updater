(function( $ ) {
	'use strict';

	$( document ).ready( function() {

		var checkBtn = $( '#my-plugin-check-btn' );
		var refreshBtn = $( '#my-plugin-refresh-btn' );
		var testBtn = $( '#my-plugin-test-btn' );

		if ( checkBtn.length ) {
			checkBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#my-plugin-check-result' );

				btn.prop( 'disabled', true ).html( '<span class="spinner is-active"></span> ' + myPluginAdmin.checking );
				resultDiv.hide().removeClass( 'notice-success notice-error notice-warning' );

				$.post( myPluginAdmin.ajaxUrl, {
					action: 'my_plugin_manual_check',
					nonce:  myPluginAdmin.nonce,
				}, function( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="margin-top:3px;"></span> ' + myPluginAdmin.checkBtn );
						resultDiv.addClass( 'notice-error' ).html( '<p>' + ( response.data.message || 'Check failed.' ) + '</p>' ).show();
					}
				}).fail( function() {
					btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="margin-top:3px;"></span> ' + myPluginAdmin.checkBtn );
					resultDiv.addClass( 'notice-error' ).html( '<p>Request failed. Try again.</p>' ).show();
				});
			});
		}

		if ( refreshBtn.length ) {
			refreshBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#my-plugin-check-result' );

				btn.prop( 'disabled', true ).text( 'Clearing...' );

				$.post( myPluginAdmin.ajaxUrl, {
					action: 'my_plugin_force_refresh',
					nonce:  myPluginAdmin.nonce,
				}, function( response ) {
					btn.prop( 'disabled', false ).text( 'Force Refresh' );

					if ( response.success ) {
						window.location.reload();
					} else {
						resultDiv.removeClass( 'notice-success' ).addClass( 'notice-error' );
						resultDiv.html( '<p>' + ( response.data.message || 'Failed.' ) + '</p>' ).show();
					}
				});
			});
		}

		if ( testBtn.length ) {
			testBtn.on( 'click', function() {
				var btn = $( this );
				var resultDiv = $( '#my-plugin-test-result' );

				btn.prop( 'disabled', true ).text( 'Testing...' );

				$.post( myPluginAdmin.ajaxUrl, {
					action: 'my_plugin_test_connection',
					nonce:  myPluginAdmin.nonce,
				}, function( response ) {
					btn.prop( 'disabled', false ).text( 'Test Connection' );

					if ( response.success ) {
						resultDiv.removeClass( 'notice-error' ).addClass( 'notice-success' );
						resultDiv.html( '<p>' + response.data.message + '</p>' ).show();
					} else {
						resultDiv.removeClass( 'notice-success' ).addClass( 'notice-error' );
						resultDiv.html( '<p>' + ( response.data.message || 'Connection failed.' ) + '</p>' ).show();
					}
				});
			});
		}

		var pluginRowBtn = $( '#my-plugin-row-check' );
		if ( pluginRowBtn.length ) {
			pluginRowBtn.on( 'click', function( e ) {
				e.preventDefault();

				var btn = $( this );
				var originalText = btn.text();

				btn.html( '<span class="spinner is-active"></span> Checking...' ).addClass( 'disabled' ).css( 'pointer-events', 'none' );

				$.post( myPluginAdmin.ajaxUrl, {
					action: 'my_plugin_manual_check',
					nonce:  myPluginAdmin.nonce,
				}, function( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );
						var notice = '<div class="notice notice-error is-dismissible" style="margin:10px 5px;"><p>' + ( response.data ? response.data.message : 'Check failed.' ) + '</p></div>';
						btn.closest( 'tr' ).after( notice );
						setTimeout( function() {
							$( '.notice.is-dismissible' ).fadeOut();
						}, 5000 );
					}
				}).fail( function() {
					btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );
					var notice = '<div class="notice notice-error is-dismissible" style="margin:10px 5px;"><p>Request failed. Try again.</p></div>';
					btn.closest( 'tr' ).after( notice );
					setTimeout( function() {
						$( '.notice.is-dismissible' ).fadeOut();
					}, 5000 );
				});
			});
		}
	});
})( jQuery );
