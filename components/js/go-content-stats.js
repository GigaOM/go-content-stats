var go_content_stats = {
	event: {}
};

( function ( $ ) {
	go_content_stats.init = function() {
		this.$period = $( '#go-content-stats-period' );
		this.load_default_stats();
		$( document ).on( 'change', this.$period, this.event.select_period );
	};

	go_content_stats.load_stats = function () {
		var period = this.$period.val();
		var start = period + Date
	};

	go_content_stats.select_period = function () {

	};

	go_content_stats.fetch_stats = function () {
		$.get( 'the shit' );
	};

	go_content_stats.render_stats = function () {
		// update this shits.
	};

	go_content_stats.fetch_pv_stats = function () {
		$.get( 'the shit' );
	};

	go_content_stats.render_pv_stats = function () {
		// update this shits.
	};

	go_content_stats.fetch_taxonomies = function () {
		$.get( 'the shit' );
	};

	go_content_stats.render_taxonomies = function () {
		// update this shits.
	};

	go_content_stats.event.select_period = function ( e ) {
		go_content_stats.select_period();
	};


} )( jQuery );

jQuery( function( $ ) {
	go_content_stats.init();
} );