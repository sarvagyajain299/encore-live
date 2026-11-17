<?php
/**
 * Elementor widget: "Encore Upcoming Shows".
 *
 * @package EncoreCore
 */

namespace EncoreCore\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Shows\ListFilters;
use EncoreCore\Shows\ShowListRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Marketing pages built in Elementor get a drag-and-drop show grid with
 * limit/city/genre controls. render() hands the settings to the same
 * ListFilters + ShowListRenderer the shortcode uses, so the widget, the
 * shortcode and the archive can never disagree about which shows to list.
 *
 * This file is only autoloaded inside the elementor/widgets/register hook,
 * so Widget_Base always exists when the class is defined.
 */
final class UpcomingShowsWidget extends Widget_Base {

	/**
	 * Machine name.
	 */
	public function get_name(): string {
		return 'encore-upcoming-shows';
	}

	/**
	 * Panel title.
	 */
	public function get_title(): string {
		return __( 'Encore Upcoming Shows', 'encore-core' );
	}

	/**
	 * Panel icon.
	 */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/**
	 * Panel category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Search keywords in the Elementor panel.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'encore', 'shows', 'events', 'tickets' );
	}

	/**
	 * Editor controls.
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content',
			array( 'label' => __( 'Shows', 'encore-core' ) )
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of shows', 'encore-core' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => ListFilters::MAX_LISTING,
				'default' => 6,
			)
		);

		$this->add_control(
			'city',
			array(
				'label'   => __( 'City', 'encore-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->term_options( Taxonomies::CITY, __( 'All cities', 'encore-core' ) ),
				'default' => '',
			)
		);

		$this->add_control(
			'genre',
			array(
				'label'   => __( 'Genre', 'encore-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->term_options( Taxonomies::GENRE, __( 'All genres', 'encore-core' ) ),
				'default' => '',
			)
		);

		// TODO(sarvagya): add a "Show past shows instead" switcher.
		// Hint: $this->add_control( 'past', array( 'label' => ..., 'type' => Controls_Manager::SWITCHER,
		// 'return_value' => 'yes', 'default' => '' ) ); then in render() pass 'when' => 'past' when
		// $settings['past'] === 'yes'. ListFilters::from_atts() will need to keep a valid 'when' too,
		// with a unit test in ListFiltersTest.

		$this->end_controls_section();
	}

	/**
	 * Front-end (and editor preview) output.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$filters  = ListFilters::from_atts(
			array(
				'limit' => $settings['limit'] ?? 6,
				'city'  => $settings['city'] ?? '',
				'genre' => $settings['genre'] ?? '',
			)
		);
		// The renderer escapes everything it prints.
		echo ( new ShowListRenderer() )->render( $filters, 'elementor_upcoming' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Term slug => name options for a select control.
	 *
	 * @param string $taxonomy  Taxonomy.
	 * @param string $all_label Label for "no filter".
	 * @return array<string, string>
	 */
	private function term_options( string $taxonomy, string $all_label ): array {
		$options = array( '' => $all_label );
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}
}
