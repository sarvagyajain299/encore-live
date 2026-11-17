<?php
/**
 * Native meta box driven by field definitions.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Admin;

use EncoreCore\Contracts\Service;
use EncoreCore\Meta\Fields;
use EncoreCore\Support\Sanitizer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * One class renders and saves the meta box for any of our post types.
 *
 * The save path is the security-critical part:
 *   nonce (request came from our form) -> not autosave/revision ->
 *   capability (this user may edit this post) -> sanitize each field -> save.
 */
final class MetaBox implements Service {

	private const NONCE_FIELD = 'encore_meta_nonce';

	/**
	 * Create a meta box for a post type.
	 *
	 * @param string $post_type Post type the box belongs to.
	 * @param string $title     Box title.
	 */
	public function __construct(
		private readonly string $post_type,
		private readonly string $title
	) {}

	/**
	 * Hook the box in and listen for saves of this post type only.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'add' ) );
		add_action( 'save_post_' . $this->post_type, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Add the box to the edit screen.
	 */
	public function add(): void {
		add_meta_box(
			'encore-' . $this->post_type . '-details',
			$this->title,
			array( $this, 'render' ),
			$this->post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Print the form. Every value is escaped at the moment it is output.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( $this->nonce_action(), self::NONCE_FIELD );

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( Fields::for_post_type( $this->post_type ) as $field ) {
			$value = get_post_meta( $post->ID, $field['key'], true );
			$id    = $this->input_name( $field );

			echo '<tr><th scope="row">';
			printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $field['label'] ) );
			echo '</th><td>';
			$this->render_input( $field, $id, $value );
			if ( ! empty( $field['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $field['help'] ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Save submitted fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		unset( $post );

		// 1. Nonce: proves the request came from our form, in this user's session (CSRF protection).
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action() ) ) {
			return;
		}

		// 2. Autosaves and revisions don't carry our fields; saving would blank them.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// 3. Capability: a valid nonce says "who sent it", not "are they allowed".
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 4. Sanitize each field with its own sanitizer, then save.
		foreach ( Fields::for_post_type( $this->post_type ) as $field ) {
			$name = $this->input_name( $field );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the field's own callback on the next line.
			$raw   = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : $this->missing_value( $field );
			$value = call_user_func( $field['sanitize'], $raw );

			update_post_meta( $post_id, $field['key'], $value );
		}
	}

	/**
	 * Value used when a field is absent from $_POST. Unticked checkboxes are
	 * simply not sent by the browser, so "absent" must mean "empty list".
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	private function missing_value( array $field ): mixed {
		return in_array( $field['input'], array( 'posts', 'social' ), true ) ? array() : '';
	}

	/**
	 * Nonce action: unique per post type.
	 */
	private function nonce_action(): string {
		return 'encore_save_' . $this->post_type;
	}

	/**
	 * Form field name: the meta key without its leading underscore.
	 *
	 * @param array<string, mixed> $field Field definition.
	 */
	private function input_name( array $field ): string {
		return ltrim( $field['key'], '_' );
	}

	/**
	 * Print one input control.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $id    Input id and name.
	 * @param mixed                $value Stored value.
	 */
	private function render_input( array $field, string $id, mixed $value ): void {
		switch ( $field['input'] ) {
			case 'datetime':
				// Stored "Y-m-d H:i:s" -> HTML datetime-local "Y-m-d\TH:i".
				$local = $value ? str_replace( ' ', 'T', substr( (string) $value, 0, 16 ) ) : '';
				printf( '<input type="datetime-local" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $id ), esc_attr( $local ) );
				break;

			case 'time':
				printf( '<input type="time" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $id ), esc_attr( (string) $value ) );
				break;

			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>', esc_attr( $id ), esc_textarea( (string) $value ) );
				break;

			case 'url':
				printf( '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text">', esc_attr( $id ), esc_url( (string) $value ) );
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%1$s" value="%2$s" step="%3$s" class="small-text">',
					esc_attr( $id ),
					esc_attr( (string) $value ),
					esc_attr( $field['step'] ?? '1' )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%1$s">', esc_attr( $id ) );
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'post':
				printf( '<select id="%1$s" name="%1$s"><option value="0">%2$s</option>', esc_attr( $id ), esc_html__( '— Select —', 'encore-core' ) );
				foreach ( $this->choices( $field['post_type'] ) as $choice ) {
					printf(
						'<option value="%d"%s>%s</option>',
						(int) $choice->ID,
						selected( (int) $value, (int) $choice->ID, false ),
						esc_html( get_the_title( $choice ) )
					);
				}
				echo '</select>';
				break;

			case 'posts':
				$selected = Sanitizer::id_list( $value );
				echo '<fieldset style="max-height:12rem;overflow:auto">';
				foreach ( $this->choices( $field['post_type'] ) as $choice ) {
					printf(
						'<label style="display:block"><input type="checkbox" name="%s[]" value="%d"%s> %s</label>',
						esc_attr( $id ),
						(int) $choice->ID,
						checked( in_array( (int) $choice->ID, $selected, true ), true, false ),
						esc_html( get_the_title( $choice ) )
					);
				}
				echo '</fieldset>';
				break;

			case 'social':
				$links = is_array( $value ) ? $value : array();
				foreach ( Sanitizer::SOCIAL_NETWORKS as $network ) {
					printf(
						'<p><label>%1$s<br><input type="url" name="%2$s[%3$s]" value="%4$s" class="regular-text" placeholder="https://"></label></p>',
						esc_html( ucfirst( $network ) ),
						esc_attr( $id ),
						esc_attr( $network ),
						esc_url( $links[ $network ] ?? '' )
					);
				}
				break;

			default:
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">', esc_attr( $id ), esc_attr( (string) $value ) );
		}
	}

	/**
	 * Posts offered in a relation picker.
	 *
	 * @param string $post_type Post type to list.
	 * @return WP_Post[]
	 */
	private function choices( string $post_type ): array {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'future' ),
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- admin-only picker; a promoter has well under 200 venues/artists.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}
}
