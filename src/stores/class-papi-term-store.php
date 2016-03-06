<?php

/**
 * Term store implementation of Papi meta store.
 */
class Papi_Term_Store extends Papi_Core_Meta_Store {

	/**
	 * The WordPress term.
	 *
	 * @var object
	 */
	private $term;

	/**
	 * The meta type.
	 *
	 * @var string
	 */
	protected $type = 'term';

	/**
	 * The constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = 0 ) {
		if ( $id === 0 ) {
			$this->id = papi_get_term_id();
		} else {
			$this->id = intval( $id );
		}

		$this->term       = get_term( $this->id, '' );
		$id               = papi_get_taxonomy_type_id( $this->id );
		$this->type_class = papi_get_entry_type_by_id( $id );
	}

	/**
	 * Get the permalink for the term.
	 *
	 * @return string
	 */
	public function get_permalink() {
		return get_term_link( $this->id );
	}

	/**
	 * Get the WordPress term object.
	 *
	 * @return WP_Term
	 */
	public function get_term() {
		return $this->term;
	}

	/**
	 * Check if the term is a valid term object.
	 *
	 * @return bool
	 */
	public function valid() {
		return $this->term instanceof WP_Term;
	}

	/**
	 * Load property from page type.
	 *
	 * @param  string $slug
	 * @param  string $child_slug
	 *
	 * @return object
	 */
	public function get_property( $slug, $child_slug = '' ) {
		$taxonomy_type_id = papi_get_taxonomy_type_id( $this->id, 'term' );
		$taxonomy_type    = papi_get_entry_type_by_id( $taxonomy_type_id );

		if ( $taxonomy_type instanceof Papi_Taxonomy_Type === false ) {
			return;
		}

		return $this->prepare_property( $taxonomy_type->get_property( $slug, $child_slug ) );
	}
}
