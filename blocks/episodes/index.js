/**
 * Podcast episodes block (editor side). Written without JSX so it needs no
 * build step; the front end is rendered by PHP (Render::block).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;
	var useSelect = wp.data.useSelect;

	wp.blocks.registerBlockType( 'sepo-podcast/episodes', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = be.useBlockProps();

			var terms = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'taxonomy', 'sepo_series', { per_page: 100 } );
			}, [] );

			var seriesOptions = [ { label: __( 'All series', 'sepo-podcast' ), value: '' } ];
			( terms || [] ).forEach( function ( t ) {
				seriesOptions.push( { label: t.name, value: t.slug } );
			} );

			return el(
				'div',
				blockProps,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Episodes', 'sepo-podcast' ) },
						el( c.SelectControl, {
							label: __( 'Series', 'sepo-podcast' ),
							value: a.series,
							options: seriesOptions,
							onChange: function ( v ) {
								set( { series: v } );
							},
						} ),
						el( c.RangeControl, {
							label: __( 'Episodes per page', 'sepo-podcast' ),
							value: a.perPage,
							min: 1,
							max: 50,
							onChange: function ( v ) {
								set( { perPage: v || 10 } );
							},
						} ),
						el( c.SelectControl, {
							label: __( 'Order', 'sepo-podcast' ),
							value: a.order,
							options: [
								{ label: __( 'Newest first', 'sepo-podcast' ), value: 'newest' },
								{ label: __( 'Oldest first', 'sepo-podcast' ), value: 'oldest' },
								{ label: __( 'Custom order (Order field)', 'sepo-podcast' ), value: 'custom' },
							],
							onChange: function ( v ) {
								set( { order: v } );
							},
						} ),
						el( c.ToggleControl, {
							label: __( 'Show search', 'sepo-podcast' ),
							checked: a.showSearch,
							onChange: function ( v ) {
								set( { showSearch: v } );
							},
						} ),
						el( c.ToggleControl, {
							label: __( 'Show series filter', 'sepo-podcast' ),
							checked: a.showSeries,
							onChange: function ( v ) {
								set( { showSeries: v } );
							},
						} )
					)
				),
				el( c.Disabled, null, el( ServerSideRender, { block: 'sepo-podcast/episodes', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
