<?php

class GO_Content_Stats_WP_CLI extends WP_CLI_Command
{
	/**
	 * Loads stats from a given date range
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : WordPress gets executed in the context of this URL. Note: use a search URL (format: search.gigaom.com)
	 * [--start=<start>]
	 * : Date to begin fetching data from (format: something strtotime-able)
	 * [--end=<end>]
	 * : Date to end fetching data from (format: something strtotime-able)
	 * [--json-dir=<json-dir>]
	 * : Location to store and check for Google Analytics json data
	 * [--fill-post-ids]
	 * : If specified, will attempt to fill post ids upon completion of the fetch
	 *
	 * ## EXAMPLES
	 *
	 *     wp go_content_stats fetch --start='-3 weeks' --end='now' --json-dir='/tmp/google-analytics-json/'
	 *
	 * @synopsis [--start=<start>] [--end=<end>] [--json-dir=<json-dir>] [--fill-post-ids]
	 */
	public function fetch( $unused_args, $assoc_args )
	{
		$start = strtotime( 'yesterday' );
		$end = $start;

		if ( ! isset( $assoc_args['url'] ) || FALSE === strpos( $assoc_args['url'], 'search' ) )
		{
			WP_CLI::error( '--url must be specified and it must be a search URL.' );
		}// end if

		if ( isset( $assoc_args['start'] ) )
		{
			$start = strtotime( $assoc_args['start'] );
		}//end if

		if ( isset( $assoc_args['end'] ) )
		{
			$end = strtotime( $assoc_args['end'] );
		}//end if

		if ( $start > $end )
		{
			WP_CLI::error( '--start must be less than --end' );
		}// end if

		$start = date( 'Y-m-d', $start );
		$end = date( 'Y-m-d', $end );

		WP_CLI::line( "Processing $start => $end" );

		if ( isset( $assoc_args['json-dir'] ) )
		{
			go_content_stats()->load()->output_directory = $assoc_args['json-dir'];

			if ( ! file_exists( go_content_stats()->load()->output_directory ) )
			{
				mkdir( go_content_stats()->load()->output_directory );
			}//end if
		}//end if

		go_content_stats()->load()->load_range( $start, $end );

		WP_CLI::line( 'Finished fetching analytics data.' );

		if ( isset( $assoc_args['fill-post-ids'] ) )
		{
			$this->fill_post_ids();
		}//end if
	}//end fetch

	/**
	 * fills post ids on records that are missing them
	 *
	 * ## OPTIONS
	 * [--url=<url>]
	 * : WordPress gets executed in the context of this URL. Note: use a search URL (format: search.gigaom.com)
	 *
	 * ## EXAMPLES
	 *
	 *     wp go_content_stats fill_post_ids
	 */
	public function fill_post_ids()
	{
		if ( ! isset( $assoc_args['url'] ) || FALSE === strpos( $assoc_args['url'], 'search' ) )
		{
			WP_CLI::error( '--url must be specified and it must be a search URL.' );
		}// end if

		WP_CLI::line( 'Filling GUIDs.' );
		$count = 1;
		$time = time();
		while ( $count > 0 )
		{
			$count = go_content_stats()->storage()->fill_post_id();

			$new_time = time();
			$seconds = $new_time - $time;
			WP_CLI::line( " $count: $seconds seconds" );
			$time = $new_time;
		}// end while

		WP_CLI::success( 'Post IDs filled.' );
	}//end fill_post_ids
}//end class
