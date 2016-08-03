<?php
/**
 * Vox Widget Class
 */

class Vox_widget extends WP_Widget {

	/** constructor */
	function __construct() {
		parent::__construct( false, 'Vox Widget' );
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		global $wpdb;

		$title = apply_filters( 'widget_title', $instance['title'] );

		$before_widget = isset( $args['before_widget'] ) && '' !== $args['before_widget'] ? $args['before_widget'] : '';
		$after_widget = isset( $args['after_widget'] ) && '' !== $args['after_widget'] ? $args['after_widget'] : '';
		$before_title = isset( $args['before_title'] ) && '' !== $args['before_title'] ? $args['before_title'] : '';
		$after_title = isset( $args['after_title'] ) && '' !== $args['after_title'] ? $args['after_title'] : '';

		echo wp_kses( $before_widget, array( 'div' => array() ) );

		if ( $title ) :
			echo wp_kses( $before_title . $title . $after_title, array(
				'h1' => array( 'class' => array() ),
				'h2' => array( 'class' => array() ),
				'h3' => array( 'class' => array() ),
				'h4' => array( 'class' => array() ),
				'h5' => array( 'class' => array() ),
				'h6' => array( 'class' => array() ),
				'p'  => array( 'class' => array() ),
			));
		endif;
		?>
			<ul>
		<?php
		global $wpdb;

		$args = array(
			'post_type' => 'vox',
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'meta_query' => array(
				'relation' => 'AND',
				'year_clause' => array(
					'key' => 'vox_year',
					'compare' => 'NUMERIC',
				),
				'month_clause' => array(
					'key' => 'vox_month',
					'compare' => 'NUMERIC',
				),
			),
			'orderby' => array(
				'year_clause' => 'DESC',
				'month_clause' => 'DESC',
			),
		);

		$voxs = new WP_Query( $args );

		if ( ! $voxs->have_posts() ) {
		?>
				<p>Pas de vox trouvé.</p>
		<?php
		} else {
			$vox = $voxs->next_post();
			$post_id = $vox->ID;

			$vox_month = sanitize_text_field( get_post_meta( $post_id, 'vox_month', true ) );
			$vox_year = absint( get_post_meta( $post_id, 'vox_year', true ) );
			$pdf_url = get_permalink( $post_id );

			$title = ucfirst( date_i18n( 'F', mktime( 0, 0, 0, $vox_month, 1, 2013 ) ) ) . ' ' . $vox_year;
			?>
				<a class="vox__link" title="<?php esc_attr_e( $title ); ?>" href="<?php esc_attr_e( $pdf_url ); ?>"><img class="vox__icon" src="<?php esc_attr_e( plugins_url( 'widget/images/voxpdf_invert.png' , dirname( __FILE__ ) ) ); ?>" width="150" height="147" /></a>
			</ul>
			<?php
		}
		echo wp_kses( $after_widget, array( 'div' => array() ) );
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {

		$title = esc_attr( $instance['title'] );

		?>
		<p>
			<label for="<?php esc_attr_e( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( __( 'Title:' ) ); ?></label>
			<input class="widefat" id="<?php esc_attr_e( $this->get_field_id( 'title' ) ); ?>" name="<?php esc_attr_e( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php esc_attr_e( $title ); ?>" />
		</p>

		<?php
	}
} // class utopian_recent_posts
add_action( 'widgets_init', function() {
	return register_widget( 'Vox_widget' );
} );
