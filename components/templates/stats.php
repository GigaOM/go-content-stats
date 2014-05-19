<div class="wrap" id="go-content-stats">

	<?php screen_icon( 'index' ); ?>
	<h2>Gigaom Content Stats</h2>
	<section id="content-stats">
		<?php
		// create a sub-list of content match table headers
		$content_match_th = '';
		$content_summary = '';
		if ( is_array( $this->config['content_matches'] ) )
		{
			foreach ( $this->config['content_matches'] as $match )
			{
				$content_match_th .= '<th>' . esc_attr( $match['label'] ) . '</th>';
				$content_summary .= '<td class="' . sanitize_title_with_dashes( $match['label'] ) . '"></td>';
			}
		}// end if
		?>

		<h3>Post performance by date published</h3>
		<table>
			<thead>
				<tr>
					<th>Day</th>
					<th>Posts</th>
					<th>PVs</th>
					<th>PVs/post</th>
					<th>Comments</th>
					<th>Comments/post</th>
					<?php echo $content_match_th; ?>
				</tr>
			</thead>
			<tbody>
			</tbody>
			<tfoot>
			</tfoot>
		</table>

		<script type="text/mustache" id="stat-row-template">
			<tr>
				<td class="day"></td>
				<td class="posts"></td>
				<td class="pvs"></td>
				<td class="pvs-per-post"></td>
				<td class="comments"></td>
				<td class="comments-per-posts"></td>
				<?php echo $content_summary; ?>
			</tr>
		</script>
	</section>

	<section id="criteria">
		<header>Select a knife to slice through the stats</header>

		<label for="<?php echo $this->get_field_id( 'period' ); ?>">Time period</label>
		<?php
		$months = array();
		$months[] = '<option value="' . date( 'Y-m', strtotime( '-31 days' ) ) . '">Last 30 days</option>';
		$starting_month = (int) date( 'n' );
		for ( $year = (int) date( 'Y' ); $year >= 2001; $year-- )
		{
			for ( $month = $starting_month; $month >= 1; $month-- )
			{
				$temp_time = strtotime( $year . '-' . $month . '-1' );
				$year_month = date( 'Y-m', $temp_time );
				$months[] = '<option value="' . $year_month . '" ' . selected( date( 'Y-m', $this->date_lesser_stamp ), $year_month, FALSE ) . '>' . date( 'M Y', $temp_time ) . '</option>';
			}// end for

			$starting_month = 12;
		}// end for
		?>
		<select name="<?php echo $this->get_field_name( 'period' ); ?>" id="<?php echo $this->get_field_id( 'period' ); ?>">
			<?php echo implode( $months ); ?>
		</select>

		<div id="taxonomy-data"></div>
	</section>

	<?php
	if ( empty( $this->wpcom_api_key ) )
	{
		echo '<p>WPCom stats using API Key '. $this->get_wpcom_api_key() .'</p>';
	}// end if
	?>
</div>