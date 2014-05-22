( function ( $ ) {
	go_content_stats.graph = {};

	/**
	 * initialize the graphs for stats
	 */
	go_content_stats.graph.init = function() {
		this.$top_graph = $( '#top-graph' );
		console.log( this );
	};

	go_content_stats.graph.render_top_graph = function() {
		this.$top_graph.html( '' );

		var graph_data = [];

		var parse_date = d3.time.format( '%Y-%m-%d' ).parse;

		for ( var i in go_content_stats.stats ) {
			var item = go_content_stats.stats[ i ];

			item.day = parse_date( item.day );

			graph_data.push( item );
		}//end for

		var margin = 40;
		var height = 300 - margin;
		var width = parseInt( this.$top_graph.width(), 10 ) - margin * 2;

		var x_scale = d3.time.scale().range( [ 0, width ] ).nice( d3.time.year );
		var y_scale = d3.scale.linear().range( [ height, 0 ] ).nice();

		var x_axis = d3.svg.axis().scale( x_scale ).orient( 'bottom' );
		var y_axis = d3.svg.axis().scale( y_scale ).orient( 'left' );

		var comments_line = d3.svg.line()
			.interpolate( 'basis' )
			.x( function( d ) { return x_scale( d.day ); } )
			.y( function( d ) { return y_scale( d.comments ); } );

		var pvs_line = d3.svg.line()
			.interpolate( 'basis' )
			.x( function( d ) { return x_scale( d.day ); } )
			.y( function( d ) { return y_scale( d.pvs ); } );

		var graph = d3.select( '#top-graph' ).append( 'svg' )
			.attr( 'width', width + margin * 2 )
			.attr( 'height', height + margin * 2 )
			.append( 'g' )
				.attr( 'transform', 'translate(' + margin + ', ' + margin + ')' );

		x_scale.domain(
			d3.extent( graph_data, function( d ) {
				return d.day;
			} )
		);

		y_scale.domain( [
			0,
			d3.max( graph_data, function( d ) { return d.pvs > d.comments ? d.pvs : d.comments; } )
		] );

		graph.append( 'g' )
			.attr( 'class', 'x axis' )
			.attr( 'transform', 'translate( 0, ' + height + ' )' )
			.call( x_axis );

		graph.append( 'g' )
			.attr( 'class', 'y axis' )
			.call( y_axis )
			.append( 'text' )
				.attr( 'transform', 'rotate( -90 )' )
				.attr( 'y', 6 )
				.attr( 'dy', '.71em' )
				.style( 'text-anchor', 'end' )
				.text( 'comments' );

		graph.append( 'path' )
			.datum( graph_data )
			.attr( 'class', 'line line-1 pvs-line' )
			.attr( 'd', pvs_line );

		graph.append( 'path' )
			.datum( graph_data )
			.attr( 'class', 'line line-2 comments-line' )
			.attr( 'd', comments_line );
	};
} )( jQuery );
