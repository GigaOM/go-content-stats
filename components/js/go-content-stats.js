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

	// holds the current loaded stats
	go_content_stats.stats = {};
	go_content_stats.summary = {};

	go_content_stats.gaps = {
		general: [],
		pvs: []
	};

	go_content_stats.init = function() {
		this.$start = $( '#go-content-stats-start' );
		this.$end = $( '#go-content-stats-end' );
		this.$stat_data = $( '#stat-data' );
		this.$taxonomy_data = $( '#taxonomy-data' );

		this.$start.datepicker( {
			dateFormat: 'yy-mm-dd',
			defaultDate: '-30d',
			changeMonth: true,
			numberOfMonths: 3,
			onClose: function( selected ) {
				go_content_stats.$end.datepicker( 'option', 'minDate', selected );
			}
		} );

		this.$end.datepicker( {
			dateFormat: 'yy-mm-dd',
			defaultDate: '-1d',
			changeMonth: true,
			numberOfMonths: 3,
			onClose: function( selected ) {
				go_content_stats.$start.datepicker( 'option', 'maxDate', selected );
			}
		} );

		this.period = this.get_period();
		this.context = this.get_context();

		// this registers a handlebars helper so we can output formatted numbers
		// rounded to 1 decimal
		Handlebars.registerHelper( 'number_format', this.number_format );

		// load stats for the current page
		// @TODO: we need push state URLs on change of the select box. To test, I place &period=2013-01 in the URL
		this.prep_stats();

		$( document ).on( 'click', '#go-content-stats-clear-cache', this.event.clear_cache );
		$( document ).on( 'change', this.$period, this.event.select_period );
		$( document ).on( 'go-content-stats-insert', this.event.mind_the_gap );
		$( document ).on( 'go-content-stats-update', this.event.mind_the_gap );
		$( window ).on( 'popstate', this.event.change_state );
	};

	/**
	 * push a state change
	 */
	go_content_stats.push_state = function () {
		period = this.get_period();

		history.pushState( period, '', 'index.php?page=go-content-stats&start=' + period.start + '&end=' + period.end );
		this.change_state( period );
	};

	/**
	 *
	 */
	go_content_stats.change_state = function ( period ) {
		this.period = period || this.get_period();
		this.context = this.get_context();
		this.prep_stats();
	};

	go_content_stats.get_range = function() {
		var days = [];

		var current = this.period.start.split( '-' );
		var end = this.period.end.split( '-' );

		current = new Date( current );
		end = new Date( end );

		while ( current <= end ) {
			days.push( this.format_date( current ) );
			current = new Date( current.setDate( current.getDate() + 1 ) );
		}//end while

		return days;
	};

	/**
	 * format a date into YYYY-MM-DD
	 */
	go_content_stats.format_date = function( date ) {
		var dd = date.getDate();

		// January is 0
		var mm = date.getMonth() + 1;

		var yyyy = date.getFullYear();

		dd = dd < 10 ? '0' + dd : dd;
		mm = mm < 10 ? '0' + mm : mm;

		return yyyy + '-' + mm + '-' + dd;
	};

	go_content_stats.load_stats = function() {
		var days = this.get_range();
		var day;

		// clear the stats object so we start fresh
		this.stats = {};
		this.summary = {
			'days': 0,
			'posts': 0,
			'pvs': 0,
			'comments': 0,
		};

		for ( var i in days ) {
			day = this.store.get( days[ i ], this.get_context() );

			this.stats[ days[ i ] ] = day;

			if ( null === day ) {
				continue;
			}//end if

			this.summary.days++;
			this.summary.posts += day.posts;
			this.summary.pvs += day.pvs;
			this.summary.comments += day.comments;
		}//end for
	};

	/**
	 * loads stats and dumps them onto the page
	 *
	 * NOTE: we are using $.proxy when handling the promise objects so the callback's
	 *       context will be go_content_stats
	 */
	go_content_stats.prep_stats = function () {
		this.load_stats();
		this.fill_gaps();
		this.mind_the_gap( { stats: [], type: 'general' } );
		this.mind_the_gap( { stats: [], type: 'pvs' } );
	};

	go_content_stats.fill_gaps = function() {
		var days = this.get_range();
		var day;

		for ( var i in days ) {
			day = this.stats[ days[ i ] ];

			if ( null === day ) {
				this.gaps.general[ i ] = days[ i ];
			}//end if

			if (
				day
				&& (
					'undefined' == typeof day.pvs
					|| null === day.pvs
				)
			) {
				this.gaps.pvs[ i ] = days[ i ];
			}//end if
		}//end for

		console.log( this.gaps );

		this.$stat_data.block();
		this.$taxonomy_data.block();

		this.fetch_in_chunks( 'general', this.gaps.general.slice( 0 ) );
		this.fetch_in_chunks( 'pvs', this.gaps.pvs.slice( 0 ) );

		var taxonomies_promise = this.fetch_stats( 'taxonomies', {
			days: this.get_range()
		} );

		// when the taxonomy data has come back, render it
		taxonomies_promise.done( $.proxy( function( response ) {
			this.receive( 'taxonomy', response );
		}, this ) );
	};

	go_content_stats.fetch_in_chunks = function( type, gaps ) {
		while ( gaps.length > 0 ) {
			var args = {
				days: []
			};

			while ( args.days.length < 50 && gaps.length > 0 ) {
				args.days.push( gaps.shift() );
			}//end for

			var promise = this.fetch_stats( type, args );

			// when the general stats have come back, render them and then fire off
			// a request for page view (pv) stats
			promise.done( $.proxy( function( response ) {
				this.receive( type, response, args );
			}, this ) );
		}//end while
	};

	go_content_stats.receive = function( type, response, args ) {
		if ( ! response.success ) {
			console.warn( 'bad response: ' + response.data );
			return;
		}// end if

		console.info( type );
		console.dir( response.data );

		// @TODO: check context (needs to be added to response)
		if ( response.data.period.period !== this.period.period ) {
			return;
		}//end if

		go_content_stats[ 'receive_' + type ]( response, args );
	};


	go_content_stats.receive_general = function( response, args ) {
		this.store.insert( response.data, this.get_context() );

		// when the pv stats have come back, render them
		var pv_promise = this.fetch_stats( 'pv_stats', args );

		pv_promise.done( $.proxy( this.receive_pvs, this ) );
	};

	go_content_stats.receive_pvs = function( response ) {
		this.store.update( response.data, this.get_context() );
	};

	go_content_stats.receive_taxonomy = function( response ) {
		this.render_taxonomies( response.data );
	};

	/**
	 * gets the currently selected period and parses it into a start and end value
	 */
	go_content_stats.get_period = function () {
		return {
			start: this.$start.val(),
			end: this.$end.val()
		};
	};

	/**
	 * gets the current selected context
	 */
	go_content_stats.get_context = function () {
		// @TODO: handle authors and taxonomy contexts
		return 'general';
	};

	/**
	 * fetches stats from the endpoint
	 *
	 * @param string which Type of stats to retrieve from the endpoint (stats|pv_stats|taxonomies)
	 * @return jqXHR
	 */
	go_content_stats.fetch_stats = function ( which, args ) {
		var period = this.get_period();

		var defaults = {
			date_start: period.start,
			date_end: period.end,
			key: null,
			type: 'general',
			which: which,
			days: []
		};

		args = $.extend( defaults, args );

		console.info( which );
		console.dir( args );

		return $.getJSON( this.endpoint, args );
	};

	/**
	 * renders the general stats via a Handlebars template
	 */
	go_content_stats.render_general_stats = function () {
		this.load_stats();

		// z: using handlebars: http://handlebarsjs.com/
		var source = $( '#stat-row-template' ).html();
		var template = Handlebars.compile( source );

		var template_data = {
			stats: this.stats,
			summary: this.summary
		};

		$( '#stat-data' ).html( template( template_data ) );

		// populate the summary stat info
		var $summary = $( '.stat-summary' );
		num_posts = parseInt( $( '#stat-data thead .stat-summary' ).data( 'num-posts' ), 10 );

		if ( ! num_posts ) {
			return;
		}//end if

		$summary.find( '.comments-per-post' ).html( this.number_format( this.summary.comments / num_posts ) );
	};

	/**
	 * render the pv stats on the stats that have already been populated via
	 * the this.render_general_stats function
	 */
	go_content_stats.render_pvs_stats = function () {
		this.load_stats();

		var num_posts = 0;
		var pvs = 0;

		// populate the pvs columns in the stat rows
		for ( var i in this.stats ) {
			if ( null == this.stats[ i ].pvs ) {
				continue;
			}//end if

			var $row = $( '#row-' + i );

			num_posts = parseInt( $row.data( 'num-posts' ), 10 );
			pvs = parseInt( this.stats[ i ].pvs, 10 );

			this.summary.pvs += pvs;

			$row.find( '.pvs' ).html( this.number_format( pvs ) );
			$row.find( '.pvs-per-post' ).html( this.number_format( pvs / num_posts ) );
		}//end for

		// populate the summary stat info
		var $summary = $( '.stat-summary' );
		num_posts = parseInt( $( '#stat-data thead .stat-summary' ).data( 'num-posts' ), 10 );

		$summary.find( '.pvs' ).html( this.number_format( this.summary.pvs ) );

		if ( ! num_posts ) {
			return;
		}//end if

		$summary.find( '.pvs-per-post' ).html( this.number_format( this.summary.pvs / num_posts ) );
	};

	/**
	 * renders the taxonomy data via a Handlebars template
	 */
	go_content_stats.render_taxonomies = function ( data ) {
		var source = $( '#taxonomy-criteria-template' ).html();
		var template = Handlebars.compile( source );
console.log( data );
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

	go_content_stats.mind_the_gap = function( data ) {
		for ( var i in data.stats ) {
			var index = this.gaps[ data.type ].indexOf( i );

			if ( -1 !== index ) {
				this.gaps[ data.type ].splice( index, 1 );
			}//end if
		}//end for

		if ( this.gaps[ data.type ].length > 0 ) {
			return;
		}//end if

		// we only want to render the pvs data if the general data has all been loaded
		if ( 'pvs' == data.type && this.gaps.general.length > 0 ) {
			return;
		}//end if

		this[ 'render_' + data.type + '_stats' ]();
	};

	go_content_stats.event.mind_the_gap = function( e, response ) {
		go_content_stats.mind_the_gap( response.data );
	};

	/**
	 * handle the selection of a new period
	 */
	go_content_stats.event.select_period = function ( e ) {
		console.error( 'IN EVENT' );
		console.log( e );
		e.preventDefault();
		go_content_stats.push_state();
	};

	/**
	 * handle the state change
	 */
	go_content_stats.event.change_state = function ( e ) {
		e.preventDefault();

		if ( 'undefined' != typeof e.originalEvent.state.start ){
			go_content_stats.change_state( e.originalEvent.state );
		}
	};

	/**
	 * handles clearing local storage cache
	 */
	go_content_stats.event.clear_cache = function( e ) {
		go_content_stats.store.clear();
		go_content_stats.push_state();
	};

	go_content_stats.store.clear = function() {
		for ( var i in localStorage ) {
			if ( i.match( /^go-content-stats-/ ) ) {
				localStorage.removeItem( i );
			}//end if
		}//end for
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
			this.set( i, context, data.stats[ i ] );
		}

		$( document ).trigger( 'go-content-stats-insert', { data: data } );
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
		for ( var i in data.stats ) {
			record = this.get( i, context );
			$.extend( record, data.stats[ i ] );
			this.set( i, context, record );
		}

		$( document ).trigger( 'go-content-stats-update', { data: data } );
	};

	/**
	 * get stats for a key
	 *
	 * @param  string key the key to fetch, ex. 2014-12-23
	 * @return object the stats for the key
	 */
	go_content_stats.store.get = function ( key, context ) {
		var record = JSON.parse( localStorage.getItem( 'go-content-stats-' + context + '-' + key ) );
		var now = new Date().getTime();

		if ( ! record ) {
			return null;
		}//end if

		if ( record.inserted_timestamp + this.ttl < now ) {
			this.delete( key, context );
			return null;
		}//end if

		return record;
	};

	/**
	 * set stats for a key
	 * @param string key the key to set, ex. 2014-12-23
	 * @param object stats the stats for the key
	 * @return null
	 */
	go_content_stats.store.set = function ( key, context, stats ) {
		localStorage.setItem( 'go-content-stats-' + context + '-' + key, JSON.stringify( stats ) );
	};

	/**
	 * delete stats for a key
	 * @param string key the key to delete, ex. 2014-12-23
	 * @return null
	 */
	go_content_stats.store.delete = function ( key, context ) {
		localStorage.removeItem( 'go-content-stats-' + context + '-' + key );
	};
} )( jQuery );

jQuery( function( $ ) {
	go_content_stats.init();
} );
