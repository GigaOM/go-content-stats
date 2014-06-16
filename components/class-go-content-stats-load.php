<?php

class GO_Content_Stats_Load
{
	private $analytics;
	private $config;

	public function load_range( $start, $end )
	{
		$profiles = $this->config( 'google_profiles' );
		$date = $start;
		$data = array();
		while ( strtotime( $date ) <= strtotime( $end ) )
		{
			foreach ( $profiles as $property => $profile_id )
			{
				$data = $this->generate_day( $date, $profile_id );

				$this->populate_stats( $date, $data, $property );
			}//end foreach

			unset( $data );
			$date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $date ) ) );
		}//end while
	}// end load_range

	/**
	 * get the configuration for this plugin
	 *
	 * @param $key (string) if not NULL, return the value of this configuration
	 *  key if it's set. else return FALSE. if $key is NULL then return
	 *  the whole config array.
	 */
	private function config( $key = NULL )
	{
		if ( ! $this->config )
		{
			$this->config = apply_filters( 'go_config', array(), 'go-content-stats' );
		}//end if

		if ( $key )
		{
			if ( isset( $this->config[ $key ] ) )
			{
				return $this->config[ $key ];
			}//end if
			else
			{
				return FALSE;
			}//end else
		}//end if

		return $this->config;
	}//end config

	private function analytics()
	{
		if ( ! $this->analytics )
		{
			if ( ! ( $this->config( 'key_file' ) && $this->config( 'google_auth_account' ) ) )
			{
				return FALSE;
			}// end if

			// required by the Google library
			set_include_path( get_include_path() . PATH_SEPARATOR . __DIR__ . '/external' );

			require_once 'external/Google/Client.php';
			require_once 'external/Google/Service/Analytics.php';

			$client = new Google_Client();
			$client->setApplicationName( 'Gigaom Content Stats' );

			$key = file_get_contents( $this->config( 'key_file' ) );
			$cred = new Google_Auth_AssertionCredentials(
				$this->config( 'google_auth_account' ),
				array( 'https://www.googleapis.com/auth/analytics.readonly' ),
				$key
			);
			$client->setAssertionCredentials( $cred );

			$this->analytics = new Google_Service_Analytics( $client );
		}//end if

		return $this->analytics;
	}//end analytics

	private function generate_day( $date, $profile_id )
	{
		$output_directory = $this->config( 'output_directory' );

		if ( $output_directory )
		{
			$filename = "$output_directory/$profile_id-$date.json";

			if ( file_exists( $filename ) )
			{
				$tmp = file_get_contents( $filename );
				$tmp = json_decode( $tmp );
				return $tmp;
			}// end if
		}// end if

		$data = $this->get_analytics( $date, $profile_id );
		fwrite( STDOUT, $date . ' (fetch): ' . count( $data ) . "\n" );

		if ( $output_directory )
		{
			file_put_contents( $filename, json_encode( $data ) );
		}// end if

		return $data;
	}// end generate_day

	private function get_analytics( $date, $profile_id, $index = FALSE )
	{
		$full_data = array();

		$args = array(
			'max-results' => 10000,
			'dimensions' => 'ga:pagePath,ga:hostname',
		);

		if ( $index )
		{
			$args[ 'start-index' ] = $index;
		}// end if

		$data = $this->analytics()->data_ga->get(
			'ga:' . $profile_id,
			$date,
			$date,
			'ga:pageviews',
			$args
		);

		$full_data = $data->rows;

		if ( ! empty( $data->nextLink ) )
		{
			preg_match( '/start-index=([0-9]+)/', $data->nextLink, $matches );
			$more_data = $this->get_analytics( $date, $profile_id, $matches[1] );
			$full_data = array_merge( $full_data, $more_data );
		}// end if

		return $full_data;
	}// end get_analytics

	private function populate_stats( $date, $data, $property )
	{
		if ( ! $data )
		{
			return;
		}//end if

		fwrite( STDOUT, $date . ' (insert): ' . count( $data ) . "\n" );
		go_content_stats()->storage()->delete( array( 'date' => $date ) );

		foreach ( $data as $row )
		{
			$stat_row = array(
				'date' => $date,
				'property' => $property,
				'url' => 'http://' .  $row[1] . $row[0],
				'views' => $row[2],
			);
			go_content_stats()->storage()->insert( $stat_row );
		}// end foreach
	}// end populate_stats
}// end GO_Content_Stats_Load