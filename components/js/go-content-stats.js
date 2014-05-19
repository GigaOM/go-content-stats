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
		this.load_stats();
		$( document ).on( 'change', this.$period, this.event.select_period );
	};

	/**
	 * loads stats and dumps them onto the page
	 */
	go_content_stats.load_stats = function () {
		var general_promise = this.fetch_stats( 'stats' );

		general_promise.done( $.proxy( function( response ) {
			this.render_general_stats( response.data );

			var pv_promise = this.fetch_stats( 'pv_stats' );

			pv_promise.done( $.proxy( function( response ) {
				console.log( response.data );
			}, this ) );
		}, this ) );

		var taxonomies_promise = this.fetch_stats( 'taxonomies' );

		taxonomies_promise.done( $.proxy( function( response ) {
			console.log( response.data );
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

	go_content_stats.render_general_stats = function ( data ) {
		// z: using handlebars: http://handlebarsjs.com/
		console.log( data );
		var source = $( '#stat-row-template' ).html();
		var template = Handlebars.compile( source );

		$( '#content-stats tbody' ).html( template( data ) );
	};

	go_content_stats.render_pv_stats = function () {
		// update this shits.
	};

	go_content_stats.render_taxonomies = function () {
		// update this shits.
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
