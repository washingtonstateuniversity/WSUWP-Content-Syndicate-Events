<?php

class WSU_Syndicate_Shortcode_Events extends WSU_Syndicate_Shortcode_Base {
	/**
	 * @var array Overriding attributes applied to the base defaults.
	 */
	public $local_default_atts = array(
		'output'      => 'headlines',
		'host'        => 'calendar.wsu.edu',
		'query'       => 'events',
		'date_format' => 'M j',
	);

	/**
	 * @var array A set of default attributes for this shortcode only.
	 */
	public $local_extended_atts = array(
		'category'  => '',
		'period'    => '',
		'schema'    => '1.2.0', // Adjusted when forceably changing cached data via code.
		'shortcode' => 'wsuwp_events', // To enable easier filtering by shortcode.
	);

	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'wsuwp_events';

	public function __construct() {
		parent::construct();
	}

	public function add_shortcode() {
		add_shortcode( 'wsuwp_events', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Display events information for the [wsuwp_events] shortcode.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function display_shortcode( $atts ) {
		$atts = $this->process_attributes( $atts );

		$site_url = $this->get_request_url( $atts );
		if ( ! $site_url ) {
			return '<!-- wsuwp_events ERROR - an empty host was supplied -->';
		}

		$request = $this->build_initial_request( $site_url, $atts );

		// Build taxonomies on the REST API request URL, except for `category`
		// as it's a different taxonomy in this case than the function expects.
		$taxonomy_filters_atts = $atts;
		unset( $taxonomy_filters_atts['category'] );
		$request_url = $this->build_taxonomy_filters( $taxonomy_filters_atts, $request['url'] );

		if ( 'past' === $atts['period'] ) {
			$request_url = add_query_arg( array(
				'tribe_event_display' => 'past',
			), $request_url );
		}

		if ( '' !== $atts['category'] ) {
			$request_url = add_query_arg( array(
				'filter[taxonomy]' => 'tribe_events_cat',
			), $request_url );

			$terms = explode( ',', $atts['category'] );
			foreach ( $terms as $term ) {
				$term = trim( $term );
				$request_url = add_query_arg( array(
					'filter[term]' => sanitize_key( $term ),
				), $request_url );
			}
		}

		if ( ! empty( $atts['offset'] ) ) {
			$atts['count'] = absint( $atts['count'] ) + absint( $atts['offset'] );
		}

		if ( $atts['count'] ) {
			$count = ( 100 < absint( $atts['count'] ) ) ? 100 : $atts['count'];
			$request_url = add_query_arg( array(
				'per_page' => absint( $count ),
			), $request_url );
		}

		$new_data = $this->get_content_cache( $atts, 'wsuwp_events' );

		if ( ! is_array( $new_data ) ) {
			$response = wp_remote_get( $request_url );

			if ( ! is_wp_error( $response ) && 404 !== wp_remote_retrieve_response_code( $response ) ) {
				$data = wp_remote_retrieve_body( $response );

				$new_data = array();

				if ( ! empty( $data ) ) {
					$data = json_decode( $data );

					if ( null === $data ) {
						$data = array();
					}

					if ( isset( $data->code ) && 'rest_no_route' === $data->code ) {
						$data = array();
					}

					foreach ( $data as $post ) {
						$subset = new StdClass();
						$subset->ID = $post->id;
						$subset->title = $post->title->rendered;
						$subset->link = $post->link;
						$subset->excerpt = $post->excerpt->rendered;
						$subset->content = $post->content->rendered;
						$subset->terms = array(); // @todo implement terms
						$subset->date = $post->date;

						// Custom data added to events by WSUWP Extended Events Calendar
						$subset->start_date = isset( $post->start_date ) ? $post->start_date : '';
						$subset->event_city = isset( $post->event_city ) ? $post->event_city : '';
						$subset->event_state = isset( $post->event_state ) ? $post->event_state : '';
						$subset->event_venue = isset( $post->event_venue ) ? $post->event_venue : '';

						$subset_key = strtotime( $post->date );
						while ( array_key_exists( $subset_key, $new_data ) ) {
							$subset_key++;
						}
						$new_data[ $subset_key ] = $subset;
					}
				}

				// Store the built content in cache for repeated use.
				$this->set_content_cache( $atts, 'wsuwp_events', $new_data );
			}
		}

		if ( ! is_array( $new_data ) ) {
			$new_data = array();
		}

		// Reverse sort the array of data by date.
		krsort( $new_data );

		// Only provide a count to match the total count, the array may be larger if local
		// items are also requested.
		if ( $atts['count'] ) {
			$new_data = array_slice( $new_data, 0, $atts['count'], false );
		}

		$content = apply_filters( 'wsuwp_content_syndicate_json_output', false, $new_data, $atts );

		if ( false === $content ) {
			$content = $this->generate_shortcode_output( $new_data, $atts );
		}

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

		return $content;
	}

	/**
	 * Generates the content to display for a shortcode.
	 *
	 * @since 1.2.0
	 *
	 * @param array $new_data Data containing the events to be displayed.
	 * @param array $atts     Array of options passed with the shortcode.
	 *
	 * @return string Content to display for the shortcode.
	 */
	public function generate_shortcode_output( $new_data, $atts ) {
		ob_start();
		if ( 'headlines' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<ul class="wsuwp-content-syndicate-list">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="wsuwp-content-syndicate-event">
						<span class="content-item-event-date"><?php echo esc_html( date( $atts['date_format'], strtotime( $content->start_date ) ) ); ?></span>
						<span class="content-item-event-title"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></span>
							<span class="content-item-event-meta">
								<span class="content-item-event-venue"><?php echo esc_html( $content->event_venue ); ?></span>
								<span class="content-item-event-city"><?php echo esc_html( $content->event_city ); ?></span>
								<span class="content-item-event-state"><?php echo esc_html( $content->event_state ); ?></span>
							</span>
						</li><?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'excerpts_legacy' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<div class="wsuwp-content-syndicate-excerpts">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<div class="wsuwp-content-syndicate-event">
							<span class="content-item-event-date"><?php echo esc_html( date( $atts['date_format'], strtotime( $content->start_date ) ) ); ?></span>
							<span class="content-item-event-title"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></span>
							<span class="content-item-event-meta">
								<span class="content-item-event-venue"><?php echo esc_html( $content->event_venue ); ?></span>
								<span class="content-item-event-city"><?php echo esc_html( $content->event_city ); ?></span>
								<span class="content-item-event-state"><?php echo esc_html( $content->event_state ); ?></span>
							</span>
							<div class="content-item-event-excerpt"><?php echo wp_kses_post( $content->excerpt ); ?></div>
						</div><?php
					}
					?>
				</div>
			</div>
			<?php
		} // End if().
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
}
