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

			item.xaxis = parse_date( item.xaxis );

			graph_data.push( item );
		}//end for

		var margin = 40;
		var height = 300 - margin;
		var width = parseInt( this.$top_graph.width(), 10 ) - margin * 2;

		var x_scale = d3.time.scale()
			.range( [ 0, width ] )
			.nice( d3.time.year )
			.domain(
				d3.extent( graph_data, function( d ) {
					return d.xaxis;
				} )
			);

		var y_scale_left = d3.scale.linear()
			.range( [ height, 0 ] )
			.domain( [
				0,
				d3.max( graph_data, function( d ) { return d.posts && d.pvs ? d.pvs / d.posts : 0; } )
			] );

		var y_scale_right = d3.scale.linear()
			.range( [ height, 0 ] )
			.domain( [
				0,
				d3.max( graph_data, function( d ) { return d.comments_per_post ? d.comments_per_post : 0; } )
			] );

		console.dir( graph_data );

		var pvs_line = d3.svg.line()
			.x( function( d ) {
				return x_scale( d.xaxis );
			} )
			.y( function( d ) {
				console.log( 'plotting y value for pvs data point: ' + d.xaxis + ' to be at our y_scale_left: ' + y_scale_left( d.posts && d.pvs ? d.pvs / d.posts : 0 ) );
				return y_scale_left( d.posts && d.pvs ? d.pvs / d.posts : 0 );
			} );

		var comments_line = d3.svg.line()
			.x( function( d ) {
				return x_scale( d.xaxis );
			} )
			.y( function( d ) {
				console.log( 'plotting y value for comments data point: ' + d.xaxis + ' to be at our y_scale_right: ' + y_scale_right( d.comments_per_post ? d.comments_per_post : 0 ) );
				return y_scale_right( d.comments_per_post ? d.comments_per_post : 0 );
			} );

		var graph = d3.select( '#top-graph' ).append( 'svg' )
			.attr( 'width', width + margin * 2 )
			.attr( 'height', height + margin * 2 )
			.append( 'g' )
				.attr( 'transform', 'translate(' + margin + ', ' + margin + ')' );

		var x_axis = d3.svg.axis().scale( x_scale );//.orient( 'bottom' );
		var y_axis_left = d3.svg.axis().scale( y_scale_left ).ticks( 10 ).orient( 'left' );
		var y_axis_right = d3.svg.axis().scale( y_scale_right ).ticks( 6 ).orient( 'right' );

		graph.append( 'g' )
			.attr( 'class', 'x axis' )
			.attr( 'transform', 'translate( 0, ' + height + ' )' )
			.call( x_axis );

		graph.append( 'svg:g' )
			.attr( 'class', 'y axis axis-left' )
			.call( y_axis_left )
			.append( 'text' )
				.attr( 'transform', 'rotate( -90 )' )
				.attr( 'y', 6 )
				.attr( 'dy', '.71em' )
				.style( 'text-anchor', 'end' )
				.text( 'page views per post' );

		graph.append( 'svg:g' )
			.attr( 'class', 'y axis axis-right' )
			.attr( 'transform', 'translate( ' + width + ', 0 )' )
			.call( y_axis_right )
			.append( 'text' )
				.attr( 'transform', 'rotate( -90 )' )
				.attr( 'y', 6 )
				.attr( 'dy', '-.71em' )
				.style( 'text-anchor', 'end' )
				.text( 'comments per post' );

		graph.append( 'svg:path' )
			.attr( 'class', 'line line-1 pvs-line' )
			.attr( 'd', pvs_line( graph_data ) );

		graph.append( 'svg:path' )
			.attr( 'class', 'line line-2 comments-line' )
			.attr( 'd', comments_line( graph_data ) );
	};
} )( jQuery );
