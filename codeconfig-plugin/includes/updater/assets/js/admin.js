(function( $ ) {
	'use strict';

	$( document ).ready( function() {

		console.log('CodeConfig Admin JS loaded');
		console.log('codeconfigPluginAdmin:', codeconfigPluginAdmin);

		var checkBtn = $( '#codeconfig-plugin-check-btn' );
		var refreshBtn = $( '#codeconfig-plugin-refresh-btn' );
		var testBtn = $( '#codeconfig-plugin-test-btn' );


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
					btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update" style="margin-top:3px;"></span> ' + codeconfigPluginAdmin.checkBtn );
					if ( response.update_available ) {
						resultDiv.addClass( 'notice-warning' ).html( '<p>' + response.message + '</p>' ).show();
						$( '.codeconfig-plugin-update-btn' ).prop( 'disabled', false ).show();
					} else {
						resultDiv.addClass( 'notice-success' ).html( '<p>' + response.message + '</p>' ).show();
						$( '.codeconfig-plugin-update-btn' ).hide();
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

		var updateBtn = $( '.codeconfig-plugin-update-btn' );
		if ( updateBtn.length ) {
			updateBtn.on( 'click', function( e ) {
				e.preventDefault();
				var btn = $( this );
				var resultDiv = $( '#codeconfig-plugin-check-result' );

				btn.prop( 'disabled', true ).html( '<span class="spinner is-active"></span> Updating...' );
				resultDiv.hide();

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'update',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					if ( response.success ) {
						resultDiv.removeClass( 'notice-error notice-warning' ).addClass( 'notice-success' );
						resultDiv.html( '<p>' + response.message + '</p>' ).show();
						btn.hide();
						setTimeout( function() {
							window.location.reload();
						}, 2000 );
					} else {
						btn.prop( 'disabled', false ).html( 'Update Now' );
						resultDiv.removeClass( 'notice-success notice-warning' ).addClass( 'notice-error' );
						resultDiv.html( '<p>' + response.message + '</p>' ).show();
					}
				} ).fail( function() {
					btn.prop( 'disabled', false ).html( 'Update Now' );
					resultDiv.removeClass( 'notice-success notice-warning' ).addClass( 'notice-error' );
					resultDiv.html( '<p>Update failed. Try again.</p>' ).show();
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

				var btn = $( this );
				var originalText = btn.text();
				var pluginName = codeconfigPluginAdmin.pluginName || 'This plugin';
				var currentVersion = codeconfigPluginAdmin.currentVersion || '';

				btn.html( '<span class="spinner is-active"></span> Checking...' ).addClass( 'disabled' ).css( 'pointer-events', 'none' );

				$.ajax( {
					url: codeconfigPluginAdmin.restUrl + 'check',
					method: 'POST',
					beforeSend: function( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', codeconfigPluginAdmin.nonce );
					},
				} ).done( function( response ) {
					btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );

					$( '.codeconfig-plugin-update-tr' ).remove();

					if ( response.update_available ) {
						var newVersion = response.new_version || '';
						var pluginSlug = codeconfigPluginAdmin.pluginSlug || '';
						var basename = codeconfigPluginAdmin.basename || '';
						var nonce = codeconfigPluginAdmin.updateNonce || '';
						var noticeHtml = '<tr class="codeconfig-plugin-update-tr plugin-update-tr">' +
							'<td colspan="4" class="plugin-update colspanchange">' +
							'<div class="update-message notice inline notice-warning notice-alt">' +
							'<p>There is a new version of ' + pluginName + ' available. ' +
							'<a href="plugin-install.php?tab=plugin-information&plugin=' + pluginSlug + '&section=changelog&TB_iframe=true&width=600&height=800" class="thickbox open-plugin-details-modal" aria-label="View ' + pluginName + ' version ' + newVersion + ' details">View version ' + newVersion + ' details</a> ' +
							'or <a href="update.php?action=upgrade-plugin&plugin=' + basename + '&_wpnonce=' + nonce + '" class="update-link" aria-label="Update ' + pluginName + ' now">update now</a>.' +
							'</p>' +
							'</div>' +
							'</td>' +
							'</tr>';
						btn.closest( 'tr' ).after( noticeHtml );
					} else {
						var noticeHtml = '<tr class="codeconfig-plugin-update-tr">' +
							'<td colspan="4" class="plugin-update colspanchange">' +
							'<div class="update-message notice inline notice-success notice-alt">' +
							'<p>This plugin is up to date.</p>' +
							'</div>' +
							'</td>' +
							'</tr>';
						btn.closest( 'tr' ).after( noticeHtml );
						setTimeout( function() {
							$( '.codeconfig-plugin-update-tr' ).fadeOut();
						}, 5000 );
					}
				} ).fail( function( jqXHR, textStatus, errorThrown ) {
					console.log('AJAX Error:', textStatus, errorThrown);
					btn.html( originalText ).removeClass( 'disabled' ).css( 'pointer-events', '' );
					$( '.codeconfig-plugin-update-tr' ).remove();
					var notice = '<tr class="codeconfig-plugin-update-tr">' +
						'<td colspan="4" class="plugin-update colspanchange">' +
						'<div class="update-message notice inline notice-error notice-alt">' +
						'<p>Request failed. Try again.</p>' +
						'</div>' +
						'</td>' +
						'</tr>';
					btn.closest( 'tr' ).after( notice );
					setTimeout( function() {
						$( '.codeconfig-plugin-update-tr' ).fadeOut();
					}, 5000 );
				} );
			});
		}
	});
})( jQuery );