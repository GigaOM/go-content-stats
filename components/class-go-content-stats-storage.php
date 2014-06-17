<?php

class GO_Content_Stats_Storage
{
	private $table = NULL;
	private $core;
	private $fields = array(
		'id' => '%d',
		'date' => '%s',
		'property' => '%s',
		'url' => '%s',
		'post_id' => '%d',
		'views' => '%d',
		'added_timestamp' => '%s',
	);

	/**
	 * The constructor
	 */
	public function __construct( $core )
	{
		global $wpdb;

		$this->core = $core;
		$this->table = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'go_content_stats';
	}// end __construct

	/**
	 * inserts a record
	 *
	 * @param array $data Row to insert into table
	 */
	public function insert( $data )
	{
		global $wpdb;

		$defaults = array(
			'id' => NULL,
			'date' => NULL,
			'property' => NULL,
			'url' => NULL,
			'post_id' => 0,
			'views' => 0,
			'added_timestamp' => current_time( 'mysql', 1 ),
		);

		$args = array_merge( $defaults, $data );

		if ( ! $args['views'] )
		{
			return FALSE;
		}//end if

		$formats = array_values( $this->fields );

		$insert_id = $wpdb->insert( $this->table, $args, $formats );

		return $insert_id;
	}// end insert

	/**
	 * get stats based on provided args
	 *
	 * @param $args array of options to get based on, see the following list for what options are available:
	 *     'id' - row ID
	 *     'date'
	 *     'property'
	 *     'url'
	 *     'post_id'
	 *     'views'
	 *     'added_timestamp'
	 *     'orderby'  - Default is 'date'. How to order the queued alerts.
	 *     'order' - Default is 'ASC'. The order to retrieve the alerts.
	 *     'count' - if TRUE, returns a count rather than a result set
	 *     'sum' - if TRUE, returns a sum of the views rather than a result set
	 *     'groupby' - Sets grouping order
	 * @return array of results
	 */
	public function get( $args )
	{
		global $wpdb;

		$fields = array_keys( $this->fields );

		if ( isset( $args['sum'] ) && $args['sum'] )
		{
			$select = 'sum(views) as sum';
		}//end if
		elseif ( isset( $args['count'] ) && $args['count'] )
		{
			$select = 'count(1) as num';
		}//end elseif
		else
		{
			$select = '*';
		}//end else

		$query = "SELECT {$select} FROM {$this->table} WHERE 1=1 ";
		$curated_args = array();

		foreach ( $this->fields as $field_name => $format )
		{
			if ( isset( $args[ $field_name ] ) )
			{
				if ( NULL === $args[ $field_name ] )
				{
					$query .= " AND `$field_name` = '' ";
				}//end if
				elseif ( is_array( $args[ $field_name ] ) )
				{
					$query .= " AND `$field_name` IN ( '" . implode( "','", $args[ $field_name ] ) . "' )";
				}
				else
				{
					$query .= " AND $field_name = $format ";
				}//end else
				$curated_args[] = $args[ $field_name ];
			}//end if
		}//end foreach

		if ( ! empty( $args['groupby'] ) )
		{
			if ( ! is_array( $args['groupby'] ) )
			{
				$groupby = explode( ',', $args['groupby'] );
			}//end if

			foreach ( $groupby as $key => $value )
			{
				$value = trim( $value );

				if ( in_array( $value, $fields ) )
				{
					continue;
				}//end if

				unset( $groupby[ $key ] );
			}//end foreach

			$query .= "GROUP BY {$groupby}";
		}//end if

		if ( isset( $args['orderby'] ) && in_array( $args['orderby'], $fields ) )
		{
			$query .= " ORDER BY {$args['orderby']} ";
		}//end if
		else
		{
			$query .= ' ORDER BY `date` ';
		}//end else

		$args['order'] = ( isset( $args['order'] ) && $args['order'] == 'DESC' ) ? 'DESC' : 'ASC';
		$query .= " {$args['order']} ";

		if ( isset( $args['limit'] ) )
		{
			list( $start, $limit ) = explode( ',', $args['limit'] );

			if ( ! $limit && $start )
			{
				$limit = $start;
				$start = 0;
			}//end if

			$start = absint( $start );
			$limit = absint( $limit );

			$query .= " LIMIT {$start}, {$limit} ";
		}//end if

		$sql = $wpdb->prepare( $query, $curated_args );
		$results = $wpdb->get_results( $sql );

		if ( isset( $args['count'] ) && $args['count'] )
		{
			$record = array_shift( $results );

			return $record->num;
		}//end if

		if ( isset( $args['sum'] ) && $args['sum'] )
		{
			$record = array_shift( $results );

			return $record->sum;
		}//end if

		return $results;
	}// end get

	/**
	 * calculate page views using unions
	 * for some reason, MySQL does not perform well with >9 IDs in the "IN()"
	 * with unions, it is super speedy quick like
	 *
	 * @param  array $post_ids post IDs
	 * @return int count of page views for the posts
	 */
	public function calc_pvs( $post_ids )
	{
		global $wpdb;

		$sql = 'SELECT SUM(v) FROM ( ';
		$sub_sql = 'SELECT SUM(views) v FROM wp_go_content_stats where post_id=';
		$sql .= $sub_sql . implode( ' union ' . $sub_sql, $post_ids ) . ') t';
		return $wpdb->get_var( $sql );
	}//end calc_pvs

	/**
	 * delete from the queue
	 *
	 * @param array fields to use as conditional for delete
	 */
	public function delete( $args )
	{
		global $wpdb;

		// make sure args only has good keys
		$args = array_intersect_key( $args, $this->fields );

		// get only the formats for the elements that are in args
		$formats = array_values( array_intersect_key( $this->fields, $args ) );

		return $wpdb->delete( $this->table,
			$args,
			$formats
		);
	}// end delete

	/**
	 */
	public function update( $data, $where )
	{
		global $wpdb;

		// make sure data only has good keys
		$data = array_intersect_key( $data, $this->fields );

		// get only the formats for the elements that are in data
		$formats = array_values( array_intersect_key( $this->fields, $data ) );

		// make sure where only has good keys
		$where = array_intersect_key( $where, $this->fields );

		// get only the formats for the elements that are in data
		$where_formats = array_values( array_intersect_key( $this->fields, $where ) );

		return $wpdb->update( $this->table,
			$data,
			$where,
			$formats,
			$where_formats
		);
	}// end update

	/**
	 * fill post_id
	 */
	public function fill_post_id()
	{
		global $wpdb;
		$args = array(
			'post_id' => 0,
			'limit' => '0,50',
			'orderby' => 'day',
			'order' => 'DESC',
		);

		switch_to_blog( 3 );

		$records = $this->get( $args );

		$remote_args = array(
			'compress' => TRUE,
		);

		$count = 0;

		foreach ( $records as $row )
		{
			echo '.';
			$post_id = -1;
			$guid = '';

			$content = wp_remote_get( $row->url, $remote_args );

			echo '-';

			if ( is_wp_error( $content ) )
			{
				$post_id = -2;
			}//end if
			else
			{
				$pattern = '/var bstat = ({.+});/';
				if ( 1 === preg_match( $pattern, $content['body'], $matches ) )
				{
					// the json payload
					$bstat_var = json_decode( $matches[1] );
					if ( ! empty( $bstat_var ) )
					{
						$guid = $bstat_var->guid;
					}//end if

					if ( $guid )
					{
						$sql = "SELECT ID FROM {$wpdb->posts} WHERE guid = %s";
						$sql = $wpdb->prepare( $sql, $guid );
						$result = $wpdb->get_var( $sql );
						$post_id = $result ?: -3;
					}//end if
				}//end if
			}//end else
			echo '+';
			$count += $this->update( array( 'post_id' => $post_id ), array(
				'property' => $row->property,
				'url' => $row->url,
				'post_id' => 0,
			) );

			echo '=';
		}//end foreach

		restore_current_blog();

		return $count;
	}//end fill_post_id

	/**
	 * create table if it doesn't exist
	 */
	public function create_table()
	{
		global $wpdb;

		$charset_collate = '';
		if ( version_compare( mysql_get_server_info(), '4.1.0', '>=' ) )
		{
			if ( ! empty( $wpdb->charset ) )
			{
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}//end if

			if ( ! empty( $wpdb->collate ) )
			{
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}//end if
		}//end if

		require_once ABSPATH . 'wp-admin/upgrade-functions.php';
		$sql = "
			CREATE TABLE {$this->table} (
				`id` int unsigned NOT NULL auto_increment,
				`date` DATE NOT NULL,
				`property` varchar(20) NOT NULL,
				`url` varchar(255) NOT NULL,
				`post_id` int NOT NULL DEFAULT 0,
				`views` mediumint unsigned NOT NULL DEFAULT 0,
				`added_timestamp` timestamp DEFAULT 0,
				PRIMARY KEY (id),
				KEY `date` (`date`),
				KEY `post_id` (`post_id`)
			) ENGINE=InnoDB $charset_collate
		";

		dbDelta( $sql );
	}//end create_table

	/**
	 * access the core object
	 *
	 * @return GO_Alerts object
	 */
	private function core()
	{
		return $this->core;
	}// end core
}//end class
