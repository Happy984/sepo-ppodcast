/**
 * Episode details box: source tabs, Media Library picker, protected upload.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.SepoPodcastAdmin || {};
	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) {
		return s;
	};

	$( function () {
		var $box = $( '.sepo-podcast-box' );
		if ( ! $box.length ) {
			return;
		}

		function showSource() {
			var v = $box.find( 'input[name="sepo_source"]:checked' ).val() || 'media';
			$box.attr( 'data-source', v );
		}
		$box.on( 'change', 'input[name="sepo_source"]', showSource );
		showSource();

		/* Media Library picker */
		var frame = null;
		$box.on( 'click', '[data-sp-pick]', function ( e ) {
			e.preventDefault();
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: __( 'Choose episode audio', 'sepo-podcast' ),
					button: { text: __( 'Use this audio', 'sepo-podcast' ) },
					library: { type: 'audio' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					$box.find( '[data-sp-audio-id]' ).val( a.id );
					$box.find( '[data-sp-audio-name]' ).text( a.filename || a.title );
					$box.find( '[data-sp-unpick]' ).prop( 'hidden', false );
					var $dur = $box.find( '[data-sp-duration]' );
					if ( ! $dur.val() && a.fileLength ) {
						$dur.val( a.fileLength );
					}
				} );
			}
			frame.open();
		} );
		$box.on( 'click', '[data-sp-unpick]', function ( e ) {
			e.preventDefault();
			$box.find( '[data-sp-audio-id]' ).val( '' );
			$box.find( '[data-sp-audio-name]' ).text( '' );
			$( this ).prop( 'hidden', true );
		} );

		/* Protected upload: sent straight away so it works in both editors. */
		$box.on( 'change', '[data-sp-upload]', function () {
			var input = this;
			var file = input.files && input.files[ 0 ];
			var $msg = $box.find( '[data-sp-progress]' );
			if ( ! file ) {
				return;
			}
			if ( cfg.maxBytes && file.size > cfg.maxBytes ) {
				$msg.text( __( 'This file is larger than the server allows.', 'sepo-podcast' ) ).addClass( 'is-error' );
				input.value = '';
				return;
			}

			var data = new window.FormData();
			data.append( 'action', 'sepo_podcast_upload' );
			data.append( 'nonce', cfg.nonce );
			data.append( 'post_id', $( '#post_ID' ).val() || '' );
			data.append( 'file', file );

			$msg.removeClass( 'is-error' ).text( __( 'Uploading…', 'sepo-podcast' ) );

			var xhr = new window.XMLHttpRequest();
			xhr.open( 'POST', cfg.ajaxUrl );
			xhr.upload.onprogress = function ( ev ) {
				if ( ev.lengthComputable ) {
					$msg.text( __( 'Uploading…', 'sepo-podcast' ) + ' ' + Math.round( ( ev.loaded / ev.total ) * 100 ) + '%' );
				}
			};
			xhr.onload = function () {
				var res = null;
				try {
					res = JSON.parse( xhr.responseText );
				} catch ( e ) {}
				if ( res && res.success ) {
					$box.find( '[data-sp-private]' ).val( res.data.name );
					$box.find( '[data-sp-private-name]' ).text( res.data.name + ' (' + res.data.size + ')' );
					$box.find( '.sp-panel[data-for="protected"] .sp-warn' ).remove();
					$msg.text( __( 'Uploaded. Save the episode to keep it.', 'sepo-podcast' ) );
				} else {
					$msg.text( res && res.data && res.data.message ? res.data.message : __( 'Upload failed.', 'sepo-podcast' ) ).addClass( 'is-error' );
				}
				input.value = '';
			};
			xhr.onerror = function () {
				$msg.text( __( 'Upload failed.', 'sepo-podcast' ) ).addClass( 'is-error' );
				input.value = '';
			};
			xhr.send( data );
		} );
	} );
} )( window.jQuery );
