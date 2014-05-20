if ( 'undefined' == typeof go_content_stats ) {
	var go_content_stats = {
		// endpoint is set from a wp_localize_script. If we get into here, we're in a bad place
		endpoint: ''
	};
}//end if

( function ( $ ) {
	// initialize the event object
	go_content_stats.event = {};
	// initialize the store object
	go_content_stats.store = {
		ttl: 24 * 60 * 60 * 100
	};

	go_content_stats.init = function() {
		this.$period = $( '#go-content-stats-period' );
		this.period = this.get_period();
		this.$stat_data = $( '#stat-data' );
		this.$taxonomy_data = $( '#taxonomy-data' );

		// load stats for the current page
		// @TODO: we need push state URLs on change of the select box. To test, I place &period=2013-01 in the URL
		this.load_stats();

		$( document ).on( 'change', this.$period, this.event.select_period );
		$( window ).on( 'popstate', this.event.change_state );

		// this registers a handlebars helper so we can output formatted numbers
		// rounded to 1 decimal
		Handlebars.registerHelper( 'number_format', this.number_format );
	};

	/**
	 * push a state change
	 */
	go_content_stats.push_state = function () {
		// @TODO: prevent the overriding of data if another ajax event is fired off before the results of any
		//        of the items below
		var period = this.get_period();

		history.pushState( period, '', 'index.php?page=go-content-stats&period=' + period.period );
		this.change_state( period );
	};

	/**
	 *
	 */
	go_content_stats.change_state = function ( period ) {
		this.period = period;
		this.load_stats( period );
	};

	/**
	 * loads stats and dumps them onto the page
	 *
	 * NOTE: we are using $.proxy when handling the promise objects so the callback's
	 *       context will be go_content_stats
	 */
	go_content_stats.load_stats = function ( period ) {
		if ( 'undefined' == typeof period || ! period ) {
			period = this.get_period();
		}
		console.groupCollapsed( 'stat load ' + period.start + ' to ' + period.end );

		this.$stat_data.block();
		this.$taxonomy_data.block();

		this.context = 'general'; // @TODO: handle the context somehow
		var general_promise = this.fetch_stats( 'stats' );

		// when the general stats have come back, render them and then fire off
		// a request for page view (pv) stats
		general_promise.done( $.proxy( function( response ) {
			console.info( 'general' );
			console.dir( response.data );
			// @TODO: check context (needs to be added to response)
			if ( response.data.period.period !== this.period.period ) {
				return;
			}
			this.render_general_stats( response.data );
			this.store.insert( response.data, this.context );

			var pv_promise = this.fetch_stats( 'pv_stats' );

			// when the pv stats have come back, render them
			pv_promise.done( $.proxy( function( response ) {
				console.info( 'pv' );
				console.dir( response.data );
				// @TODO: check context (needs to be added to response)
				if ( response.data.period.period !== this.period.period ) {
					return;
				}
				this.render_pv_stats( response.data );
				this.store.update( response.data, this.context );
				console.groupEnd();
			}, this ) );
		}, this ) );

		var taxonomies_promise = this.fetch_stats( 'taxonomies' );

		// when the taxonomy data has come back, render it
		taxonomies_promise.done( $.proxy( function( response ) {
			console.info( 'taxonomies' );
			console.dir( response.data );
			// @TODO: check context (needs to be added to response)
			if ( response.data.period.period !== this.period.period ) {
				return;
			}
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
			end: end,
			period: period
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

	/**
	 * renders the taxonomy data via a Handlebars template
	 */
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
		e.preventDefault();
		go_content_stats.push_state();
	};

	/**
	 * handle the state change
	 */
	go_content_stats.event.change_state = function ( e ) {
		e.preventDefault();

		if ( 'undefined' != typeof e.originalEvent.state.period ){
			go_content_stats.change_state( e.originalEvent.state );
		}
	};

	/**
	 * insert multiple dates into the store
	 *
	 * @param  array data data elements to insert, indexed by date
	 * @param  string context 'general', 'author', or 'taxonomy'
	 * @return null
	 */
	go_content_stats.store.insert = function ( data, context ) {
		for ( var i in data.stats ) {
			data.stats[ i ].inserted_timestamp = new Date().getTime();
			this.set( context + '-' + i, data.stats[ i ] );
		}
		// trigger
	};

	/**
	 * update multiple dates data in the store
	 *
	 * @param  array data the data elements to update, indexed by date
	 * @param  string context 'general', 'author', or 'taxonomy'
	 * @return null
	 */
	go_content_stats.store.update = function ( data, context ) {
		var record;
		var key;
		for ( var i in data.stats ) {
			key = context + '-' + i;
			record = this.get( key );
			$.extend( record, data.stats[ i ] );
			this.set( key, record );
		}
		// trigger
	};

	/**
	 * get stats for a key
	 *
	 * @param  string key the key to fetch, ex. 2014-12-23
	 * @return object the stats for the key
	 */
	go_content_stats.store.get = function ( key ) {
		var record = JSON.parse( localStorage.getItem( 'go-content-stats-' + key ) );
		var now = new Date().getTime();

		if ( record.inserted_timestamp + this.ttl < now ) {
			this.delete( key );
			return null;
		}
		return record;
	};

	/**
	 * set stats for a key
	 * @param string key the key to set, ex. 2014-12-23
	 * @param object stats the stats for the key
	 * @return null
	 */
	go_content_stats.store.set = function ( key, stats ) {
		localStorage.setItem( 'go-content-stats-' + key, JSON.stringify( stats ) );
	};

	/**
	 * delete stats for a key
	 * @param string key the key to delete, ex. 2014-12-23
	 * @return null
	 */
	go_content_stats.store.delete = function ( key ) {
		localStorage.removeItem( 'go-content-stats-' + key );
	};
} )( jQuery );

jQuery( function( $ ) {
	go_content_stats.init();
} );
