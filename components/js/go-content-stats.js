if ( 'undefined' == typeof go_content_stats ) {
	var go_content_stats = {
		// endpoint is set from a wp_localize_script. If we get into here, we're in a bad place
		endpoint: ''
	};
}//end if

go_content_stats.event = {};

( function ( $ ) {
	go_content_stats.init = function() {
		this.$period = $( '#go-content-stats-period' );
		this.$stat_data = $( '#stat-data' );
		this.$taxonomy_data = $( '#taxonomy-data' );
		this.load_stats();
		$( document ).on( 'change', this.$period, this.event.select_period );

		Handlebars.registerHelper( 'number_format', this.number_format );
	};

	/**
	 * loads stats and dumps them onto the page
	 */
	go_content_stats.load_stats = function () {
		this.$stat_data.block();
		this.$taxonomy_data.block();

		var general_promise = this.fetch_stats( 'stats' );

		general_promise.done( $.proxy( function( response ) {
			this.render_general_stats( response.data );

			var pv_promise = this.fetch_stats( 'pv_stats' );

			pv_promise.done( $.proxy( function( response ) {
				this.render_pv_stats( response.data );
			}, this ) );
		}, this ) );

		var taxonomies_promise = this.fetch_stats( 'taxonomies' );

		taxonomies_promise.done( $.proxy( function( response ) {
			this.render_taxonomies( response.data );
		}, this ) );
	};

	/**
	 * gets the currently selected period and parses it into a start and end value
	 */
	go_content_stats.get_period = function () {
		var period = this.$period.val();
		var year = parseInt( period.substr( 0, 4 ), 10 );
		var month = parseInt( period.substr( 5, 2 ), 10 );

		var start = period + '-01';

		// providing 0 gives the last day of the month
		var end = period + '-' + ( new Date( year, month, 0 ) ).getDate();

		return {
			start: start,
			end: end
		};
	};

	/**
	 * fetches stats from the endpoint
	 *
	 * @param string which Type of stats to retrieve from the endpoint (stats|pv_stats|taxonomies)
	 * @return jqXHR
	 */
	go_content_stats.fetch_stats = function ( which ) {
		var period = this.get_period();

		var args = {
			date_lesser: period.start,
			date_greater: period.end,
			key: null,
			type: 'general',
			which: which
		};

		console.log( args );

		return $.getJSON( this.endpoint, args );
	};

	/**
	 * renders the general stats via a Handlebars template
	 */
	go_content_stats.render_general_stats = function ( data ) {
		// z: using handlebars: http://handlebarsjs.com/
		var source = $( '#stat-row-template' ).html();
		var template = Handlebars.compile( source );

		$( '#stat-data' ).html( template( data ) );
	};

	/**
	 * render the pv stats on the stats that have already been populated via
	 * the this.render_general_stats function
	 */
	go_content_stats.render_pv_stats = function ( data ) {
		var num_posts = 0;
		var pvs = 0;

		// populate the pcs columns in the stat rows
		for ( var i in data.stats ) {
			if ( ! data.stats[ i ].pvs ) {
				continue;
			}//end if

			var $row = $( '#row-' + i );

			num_posts = parseInt( $row.data( 'num-posts' ), 10 );
			pvs = parseInt( data.stats[ i ].pvs, 10 );

			$row.find( '.pvs' ).html( this.number_format( pvs ) );
			$row.find( '.pvs-per-post' ).html( this.number_format( pvs / num_posts ) );
		}//end for

		// populate the summary stat info
		var $summary = $( '.stat-summary' );
		num_posts = parseInt( $( '#stat-data thead .stat-summary' ).data( 'num-posts' ), 10 );
		pvs = parseInt( data.summary.pvs, 10 );

		$summary.find( '.pvs' ).html( this.number_format( pvs ) );
		$summary.find( '.pvs-per-post' ).html( this.number_format( pvs / num_posts ) );
	};

	go_content_stats.render_taxonomies = function ( data ) {
		var source = $( '#taxonomy-criteria-template' ).html();
		var template = Handlebars.compile( source );

		$( '#taxonomy-data' ).html( template( data ) );
	};

	/**
	 * output number with commas
	 */
	go_content_stats.number_format = function( num ) {
		if ( ! num || 'undefined' == typeof num ) {
			return 0;
		}//end if

		// round to 1 decimal
		num = parseFloat( parseInt( num, 10 ).toFixed( 2 ), 10 );

		return num.toString().replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
	};

	/**
	 * handle the selection of a new period
	 */
	go_content_stats.event.select_period = function ( e ) {
		go_content_stats.load_stats();
	};
} )( jQuery );

jQuery( function( $ ) {
	go_content_stats.init();
} );
