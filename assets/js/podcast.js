/**
 * Sepo Podcast front end: player, bottom dock, likes.
 * No build step and no libraries; wp-i18n is used for translations.
 */
( function () {
	'use strict';

	var cfg = window.SepoPodcast || {};
	var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
	var __ = function ( s, domain ) {
		return i18n ? i18n.__( s, domain ) : s;
	};
	var RATES = [ 1, 1.25, 1.5, 1.75, 2, 0.75 ];
	var players = [];

	/* ---------------------------------------------------------- helpers */

	function fmt( sec ) {
		if ( ! isFinite( sec ) || sec < 0 ) {
			sec = 0;
		}
		sec = Math.floor( sec );
		var h = Math.floor( sec / 3600 );
		var m = Math.floor( ( sec % 3600 ) / 60 );
		var s = sec % 60;
		var mm = h ? String( m ).padStart( 2, '0' ) : String( m );
		return ( h ? h + ':' : '' ) + mm + ':' + String( s ).padStart( 2, '0' );
	}

	function store( key, value ) {
		try {
			if ( value === undefined ) {
				return window.localStorage.getItem( key );
			}
			if ( value === null ) {
				window.localStorage.removeItem( key );
			} else {
				window.localStorage.setItem( key, String( value ) );
			}
		} catch ( e ) {}
		return null;
	}

	function api( path, method ) {
		var headers = { Accept: 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}
		return window
			.fetch( cfg.root + path, {
				method: method || 'GET',
				credentials: 'same-origin',
				headers: headers,
			} )
			.then( function ( r ) {
				return r.json().then( function ( body ) {
					return { ok: r.ok, status: r.status, body: body };
				} );
			} );
	}

	function setCount( root, selector, n ) {
		root.querySelectorAll( selector ).forEach( function ( el ) {
			el.textContent = Number( n ).toLocaleString();
		} );
	}

	/* ----------------------------------------------------------- player */

	function Player( el ) {
		this.el = el;
		this.audio = el.querySelector( 'audio' );
		this.controls = el.querySelector( '.sp-controls' );
		this.seek = el.querySelector( '[data-sp-seek]' );
		this.cur = el.querySelector( '[data-sp-current]' );
		this.dur = el.querySelector( '[data-sp-duration]' );
		this.toggleBtn = el.querySelector( '[data-sp-toggle]' );
		this.rateBtn = el.querySelector( '[data-sp-rate]' );
		this.id = el.getAttribute( 'data-id' ) || '';
		this.dragging = false;
		this.onState = null;
		this.meta = {};
		this.bind();
		players.push( this );
	}

	Player.prototype.bind = function () {
		var self = this;
		var a = this.audio;

		a.removeAttribute( 'controls' );
		this.controls.hidden = false;
		this.el.classList.add( 'is-ready' );

		this.toggleBtn.addEventListener( 'click', function () {
			self.toggle();
		} );
		this.el.querySelectorAll( '[data-sp-skip]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var by = parseFloat( b.getAttribute( 'data-sp-skip' ) ) || 0;
				a.currentTime = Math.max( 0, Math.min( ( a.duration || 0 ) - 0.5, a.currentTime + by ) );
			} );
		} );
		this.rateBtn.addEventListener( 'click', function () {
			var i = RATES.indexOf( a.playbackRate );
			a.playbackRate = RATES[ ( i + 1 ) % RATES.length ];
			self.rateBtn.textContent = a.playbackRate + '×';
			store( 'sp-rate', a.playbackRate );
		} );

		var saved = parseFloat( store( 'sp-rate' ) );
		if ( RATES.indexOf( saved ) > -1 ) {
			a.playbackRate = saved;
			a.defaultPlaybackRate = saved;
			this.rateBtn.textContent = saved + '×';
		}

		this.seek.addEventListener( 'input', function () {
			self.dragging = true;
			var t = ( self.seek.value / 1000 ) * ( a.duration || 0 );
			self.cur.textContent = fmt( t );
			self.paintSeek( self.seek.value / 10 );
		} );
		this.seek.addEventListener( 'change', function () {
			if ( a.duration ) {
				a.currentTime = ( self.seek.value / 1000 ) * a.duration;
			}
			self.dragging = false;
		} );

		a.addEventListener( 'loadedmetadata', function () {
			if ( isFinite( a.duration ) ) {
				self.dur.textContent = fmt( a.duration );
			}
			var pos = parseFloat( store( 'sp-pos-' + self.currentId() ) );
			if ( pos > 5 && a.duration && pos < a.duration - 5 ) {
				a.currentTime = pos;
			}
			a.playbackRate = a.defaultPlaybackRate;
		} );
		a.addEventListener( 'timeupdate', function () {
			if ( self.dragging ) {
				return;
			}
			var pct = a.duration ? ( a.currentTime / a.duration ) * 100 : 0;
			self.seek.value = Math.round( pct * 10 );
			self.paintSeek( pct );
			self.cur.textContent = fmt( a.currentTime );
			if ( Math.floor( a.currentTime ) % 5 === 0 ) {
				store( 'sp-pos-' + self.currentId(), a.currentTime.toFixed( 1 ) );
			}
		} );
		a.addEventListener( 'play', function () {
			players.forEach( function ( p ) {
				if ( p !== self && ! p.audio.paused ) {
					p.audio.pause();
				}
			} );
			self.state( true );
			self.mediaSession();
		} );
		a.addEventListener( 'pause', function () {
			self.state( false );
		} );
		a.addEventListener( 'ended', function () {
			store( 'sp-pos-' + self.currentId(), null );
			self.state( false );
		} );
		a.addEventListener( 'error', function () {
			self.state( false );
		} );
	};

	Player.prototype.currentId = function () {
		return this.id;
	};

	Player.prototype.paintSeek = function ( pct ) {
		this.seek.style.setProperty( '--sp-fill', pct + '%' );
	};

	Player.prototype.toggle = function () {
		if ( this.audio.paused ) {
			var p = this.audio.play();
			if ( p && p.catch ) {
				p.catch( function () {} );
			}
		} else {
			this.audio.pause();
		}
	};

	Player.prototype.state = function ( playing ) {
		this.el.classList.toggle( 'is-playing', playing );
		this.toggleBtn.setAttribute( 'aria-label', playing ? __( 'Pause', 'sepo-podcast' ) : __( 'Play', 'sepo-podcast' ) );
		if ( this.onState ) {
			this.onState( playing );
		}
	};

	Player.prototype.mediaSession = function () {
		if ( ! ( 'mediaSession' in navigator ) || ! window.MediaMetadata ) {
			return;
		}
		var a = this.audio;
		var m = this.meta;
		if ( ! m.title ) {
			var page = this.el.closest( '.sp-episode' );
			var art = page ? page.querySelector( '.sp-art' ) : null;
			m = {
				title: document.title,
				image: art && art.src ? art.src : '',
			};
		}
		try {
			navigator.mediaSession.metadata = new window.MediaMetadata( {
				title: m.title,
				artwork: m.image ? [ { src: m.image, sizes: '512x512' } ] : [],
			} );
			navigator.mediaSession.setActionHandler( 'seekbackward', function () {
				a.currentTime = Math.max( 0, a.currentTime - 15 );
			} );
			navigator.mediaSession.setActionHandler( 'seekforward', function () {
				a.currentTime = a.currentTime + 30;
			} );
		} catch ( e ) {}
	};

	/* ------------------------------------------------------------- dock */

	var dock = null;

	function buildDock() {
		var tpl = document.querySelector( '.sp-player' );
		var el = document.createElement( 'div' );
		el.className = 'sp-dock';
		el.hidden = true;
		el.setAttribute( 'role', 'region' );
		el.setAttribute( 'aria-label', __( 'Podcast player', 'sepo-podcast' ) );
		el.innerHTML =
			'<img class="sp-dock-art" alt="" width="52" height="52">' +
			'<div class="sp-dock-main"><a class="sp-dock-title" href="#"></a>' +
			'<div class="sp-player" data-sp-player><audio class="sp-audio" preload="none"></audio>' +
			'<div class="sp-controls">' +
			'<button type="button" class="sp-btn sp-play" data-sp-toggle></button>' +
			'<button type="button" class="sp-btn sp-skip" data-sp-skip="-15"><span>15</span></button>' +
			'<div class="sp-track"><input type="range" class="sp-seek" data-sp-seek min="0" max="1000" step="1" value="0">' +
			'<div class="sp-times"><span data-sp-current>0:00</span><span data-sp-duration>0:00</span></div></div>' +
			'<button type="button" class="sp-btn sp-skip sp-skip-fwd" data-sp-skip="30"><span>30</span></button>' +
			'<button type="button" class="sp-btn sp-rate" data-sp-rate>1×</button>' +
			'</div></div></div>' +
			'<button type="button" class="sp-btn sp-dock-close">&times;</button>';

		// Reuse the server-rendered icons so the dock matches the page.
		var icons = document.querySelector( '.sp-card-play' );
		var playBtn = el.querySelector( '[data-sp-toggle]' );
		playBtn.innerHTML = icons ? icons.innerHTML : '';
		playBtn.setAttribute( 'aria-label', __( 'Play', 'sepo-podcast' ) );
		el.querySelector( '[data-sp-seek]' ).setAttribute( 'aria-label', __( 'Seek', 'sepo-podcast' ) );
		el.querySelectorAll( '[data-sp-skip]' )[ 0 ].setAttribute( 'aria-label', __( 'Back 15 seconds', 'sepo-podcast' ) );
		el.querySelectorAll( '[data-sp-skip]' )[ 1 ].setAttribute( 'aria-label', __( 'Forward 30 seconds', 'sepo-podcast' ) );
		el.querySelector( '[data-sp-rate]' ).setAttribute( 'aria-label', __( 'Playback speed', 'sepo-podcast' ) );
		el.querySelector( '.sp-dock-close' ).setAttribute( 'aria-label', __( 'Close player', 'sepo-podcast' ) );

		var accentHost = tpl ? tpl.closest( '.sepo-podcast' ) : null;
		if ( accentHost ) {
			el.style.setProperty( '--sp-accent', getComputedStyle( accentHost ).getPropertyValue( '--sp-accent' ) );
		}
		document.body.appendChild( el );

		var player = new Player( el.querySelector( '[data-sp-player]' ) );
		player.currentId = function () {
			return player.id;
		};
		player.onState = function ( playing ) {
			document.querySelectorAll( '[data-sp-play]' ).forEach( function ( b ) {
				var mine = b.getAttribute( 'data-id' ) === player.id;
				b.classList.toggle( 'is-playing', mine && playing );
				b.setAttribute( 'aria-pressed', mine && playing ? 'true' : 'false' );
				var card = b.closest( '.sp-card' );
				if ( card ) {
					card.classList.toggle( 'is-current', mine );
				}
			} );
		};
		el.querySelector( '.sp-dock-close' ).addEventListener( 'click', function () {
			player.audio.pause();
			el.hidden = true;
			document.body.classList.remove( 'sp-has-dock' );
			player.id = '';
			player.onState( false );
		} );

		dock = { el: el, player: player };
		return dock;
	}

	function playInDock( btn ) {
		var d = dock || buildDock();
		var p = d.player;
		var id = btn.getAttribute( 'data-id' );

		if ( p.id === id ) {
			p.toggle();
			return;
		}

		p.id = id;
		p.meta = {
			title: btn.getAttribute( 'data-title' ) || '',
			image: btn.getAttribute( 'data-image' ) || '',
		};
		p.audio.src = btn.getAttribute( 'data-src' );
		p.dur.textContent = btn.getAttribute( 'data-duration' ) || '0:00';
		p.cur.textContent = '0:00';
		p.seek.value = 0;
		p.paintSeek( 0 );

		var title = d.el.querySelector( '.sp-dock-title' );
		title.textContent = p.meta.title;
		title.href = btn.getAttribute( 'data-url' ) || '#';
		var art = d.el.querySelector( '.sp-dock-art' );
		if ( p.meta.image ) {
			art.src = p.meta.image;
			art.style.display = '';
		} else {
			art.removeAttribute( 'src' );
			art.style.display = 'none';
		}

		d.el.hidden = false;
		document.body.classList.add( 'sp-has-dock' );
		p.toggle();
	}

	/* ------------------------------------------------------------ likes */

	function like( btn ) {
		var id = btn.getAttribute( 'data-sp-like' );
		if ( ! cfg.loggedIn ) {
			window.location.href = cfg.loginUrl;
			return;
		}
		btn.classList.add( 'is-busy' );
		api( 'episodes/' + id + '/like', 'POST' )
			.then( function ( res ) {
				if ( res.status === 401 || res.status === 403 ) {
					window.location.href = cfg.loginUrl;
					return;
				}
				if ( ! res.ok ) {
					return;
				}
				document.querySelectorAll( '[data-sp-like="' + id + '"]' ).forEach( function ( b ) {
					b.classList.toggle( 'is-liked', !! res.body.liked );
					b.setAttribute( 'aria-pressed', res.body.liked ? 'true' : 'false' );
					setCount( b, '[data-sp-likes]', res.body.likes );
					if ( res.body.liked ) {
						b.classList.remove( 'is-pop' );
						void b.offsetWidth;
						b.classList.add( 'is-pop' );
					}
				} );
			} )
			.catch( function () {} )
			.then( function () {
				btn.classList.remove( 'is-busy' );
			} );
	}

	/* Page caches can hold old numbers: refresh them on episode pages. */
	function refreshStats() {
		var page = document.querySelector( '.sp-episode [data-sp-player]' );
		if ( ! page || ! cfg.root ) {
			return;
		}
		var root = page.closest( '.sp-episode' );
		var id = page.getAttribute( 'data-id' );
		api( 'episodes/' + id + '/stats' )
			.then( function ( res ) {
				if ( ! res.ok ) {
					return;
				}
				setCount( root.querySelector( '.sp-actions' ), '[data-sp-likes]', res.body.likes );
				setCount( root.querySelector( '.sp-actions' ), '[data-sp-downloads]', res.body.downloads );
				root.querySelectorAll( '[data-sp-like]' ).forEach( function ( b ) {
					b.classList.toggle( 'is-liked', !! res.body.liked );
					b.setAttribute( 'aria-pressed', res.body.liked ? 'true' : 'false' );
				} );
			} )
			.catch( function () {} );
	}

	/* ------------------------------------------------------------- boot */

	function boot() {
		document.querySelectorAll( '.sepo-podcast [data-sp-player]' ).forEach( function ( el ) {
			if ( ! el.spPlayer ) {
				el.spPlayer = new Player( el );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			var play = e.target.closest( '[data-sp-play]' );
			if ( play ) {
				e.preventDefault();
				playInDock( play );
				return;
			}
			var likeBtn = e.target.closest( 'button[data-sp-like]' );
			if ( likeBtn ) {
				e.preventDefault();
				like( likeBtn );
				return;
			}
		} );

		refreshStats();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
