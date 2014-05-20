<div class="wrap" id="go-content-stats">

	<?php screen_icon( 'index' ); ?>
	<h2>Gigaom Content Stats</h2>
	<section id="content-stats">
		<?php
		// create a sub-list of content match table headers
		$content_match_th = '';
		$content_summary = '';
		$content_row = '';
		if ( is_array( $this->config['content_matches'] ) )
		{
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				$content_match_th .= '<th class="' . esc_attr( $key ) . '">' . esc_attr( $match['label'] ) . '</th>';
				$content_summary .= '<th class="' . esc_attr( $key ) . '"></th>';
				$content_row .= '<td class="' . esc_attr( $key ) . '">{{number_format ' . esc_html( $key ) . '}}</td>';
			}// end foreach
		}// end if
		?>

		<h3>Post performance by date published</h3>
		<button id="<?php echo $this->get_field_id( 'clear-cache' ); ?>">Clear local cache</button>
		<div id="stat-data">
			Loading data, please sit tight... [include a throbber or something]
		</div>

		<script type="text/x-handlebars-template" id="stat-row-template">
			<table>
				<thead>
					<tr>
						<th class="day">Day</th>
						<th class="posts">Posts</th>
						<th class="pvs">PVs</th>
						<th class="pvs-per-post">PVs/post</th>
						<th class="comments">Comments</th>
						<th class="comments-per-posts">Comments/post</th>
						<?php echo $content_match_th; ?>
					</tr>
					<tr class="stat-summary" data-num-posts="{{summary.posts}}">
						<th class="day">{{summary.days}} days</th>
						<th class="posts">{{summary.posts}}</th>
						<th class="pvs">{{summary.pvs}}</th>
						<th class="pvs-per-post">loading...</th>
						<th class="comments">{{summary.comments}}</th>
						<th class="comments-per-post"></th>
						<?php echo $content_summary; ?>
					</tr>
				</thead>
				<tbody>
					{{#each stats}}
					<tr id="row-{{@key}}" class="stat-row" data-num-posts="{{posts}}">
						<td class="day">{{day}}</td>
						<td class="posts"><a href="<?php echo admin_url( '/edit.php?m=' ); ?>{{day}}">{{number_format posts}}</a></td>
						<td class="pvs">{{pvs}}</td>
						<td class="pvs-per-post">{{pvs_per_post}}</td>
						<td class="comments">{{number_format comments}}</td>
						<td class="comments-per-posts">{{number_format comments_per_posts}}</td>
						<?php echo $content_row; ?>
					</tr>
					{{/each}}
				</tbody>
				<tfoot>
					<tr class="stat-summary" data-num-posts="{{summary.posts}}">
						<th class="day">{{summary.days}} days</th>
						<th class="posts">{{summary.posts}}</th>
						<th class="pvs">{{summary.pvs}}</th>
						<th class="pvs-per-post">loading...</th>
						<th class="comments">{{summary.comments}}</th>
						<th class="comments-per-post"></th>
						<?php echo $content_summary; ?>
					</tr>
					<tr>
						<th class="day">Day</th>
						<th class="posts">Posts</th>
						<th class="pvs">PVs</th>
						<th class="pvs-per-post">PVs/post</th>
						<th class="comments">Comments</th>
						<th class="comments-per-posts">Comments/post</th>
						<?php echo $content_match_th; ?>
					</tr>
				</tfoot>
			</table>
		</script>

		<script type="text/x-handlebars-template" id="taxonomy-criteria-template">
			<h3>Authors</h3>
			<ul>
			{{#each authors}}
				<li>
					<a href="<?php echo esc_url( $this->menu_url ); ?>&type={{type}}&key={{key}}">{{name}} ({{number_format hits}})</a>
				</li>
			{{/each}}
			</ul>

			{{#each taxonomy}}
				<ul>
					{{#each terms}}
						<li>
							<a href="<?php echo esc_url( $this->menu_url ); ?>&type={{type}}&key={{key}}">{{name}} ({{number_format hits}})</a>
						</li>
					{{/each}}
				</ul>
			{{/each}}
		</script>
	</section>

	<section id="criteria">
		<header>Select a knife to slice through the stats</header>

		<label for="<?php echo $this->get_field_id( 'period' ); ?>">Time period</label>
		<?php
		$period = isset( $_GET['period'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['period'] ) : '';

		$months = array();
		$months[] = '<option value="' . date( 'Y-m', strtotime( '-31 days' ) ) . '">Last 30 days</option>';
		$starting_month = (int) date( 'n' );
		for ( $year = (int) date( 'Y' ); $year >= 2001; $year-- )
		{
			for ( $month = $starting_month; $month >= 1; $month-- )
			{
				$temp_time = strtotime( $year . '-' . $month . '-1' );
				$year_month = date( 'Y-m', $temp_time );
				$months[] = '<option value="' . $year_month . '" ' . selected( $period, $year_month, FALSE ) . '>' . date( 'M Y', $temp_time ) . '</option>';
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
