<?php

/**
 * Delete property value from database.
 *
 * If it's on a option page it will fetch the value from the
 * option table instead of the metadata table.
 *
 * @param  int    $id
 * @param  string $slug
 * @param  string $type
 *
 * @return mixed
 */
function papi_delete_property_meta_value( $id, $slug, $type = 'post' ) {
	$type = papi_get_meta_type( $type );

	// Delete cached value.
	papi_cache_delete( $slug, $id, $type );

	// Set the right delete value function for the type.
	$delete_value_fn = $type === 'option' ? 'delete_option' : 'delete_metadata';

	/**
	 * Change delete value function.
	 *
	 * @param string $delete_function
	 */
	$delete_value_fn = apply_filters( 'papi/core/delete_value_fn', $delete_value_fn );

	// Check so the function is callable before using it.
	if ( ! is_callable( $delete_value_fn ) ) {
		return;
	}

	if ( $type === 'option' ) {
		return call_user_func_array( $delete_value_fn, [unpapify( $slug )] );
	}

	return call_user_func_array( $delete_value_fn, [papi_get_meta_type( $type ), $id, unpapify( $slug )] );
}

/**
 * Convert array of slugs to array with arrays in.
 *
 * @param  array  $values
 * @param  string $slug
 *
 * @return array
 */
function papi_from_property_array_slugs( array $values, $slug ) {
	$results = [];

	if ( empty( $values ) ) {
		return $results;
	}

	for ( $i = 0; $i < $values[$slug]; $i++ ) {
		$item      = [];
		$item_slug = $slug . '_' . $i . '_';
		$keys      = preg_grep( '/' . preg_quote( $item_slug ) . '/' , array_keys( $values ) );

		foreach ( $keys as $key ) {
			$arr_key = str_replace( $item_slug, '', $key );
			$item[$arr_key] = $values[$key];
		}

		$results[] = $item;
	}

	return $results;
}

/**
 * Check if the given value is a instance of a property or not.
 *
 * @param  mixed $value
 *
 * @return bool
 */
function papi_is_property( $value ) {
	return $value instanceof Papi_Core_Property;
}

/**
 * Get options and properties.
 *
 * @param  mixed $file_or_options
 * @param  array $properties
 * @param  bool  $is_box
 *
 * @return array
 */
function papi_get_options_and_properties( $file_or_options = [], $properties = [], $is_box = true ) {
	$options = [];

	if ( is_array( $file_or_options ) ) {
		if ( empty( $properties ) && $is_box ) {
			// Check if we have a title or not.
			if ( isset( $file_or_options['title'] ) ) {
				$options['title'] = $file_or_options['title'];
			} else if ( isset( $file_or_options[0]->title ) ) {
				$options['title'] = $file_or_options[0]->title;
			} else if ( isset( $file_or_options[0]->options, $file_or_options[0]->options['title'] ) ) {
				$options['title'] = $file_or_options[0]->options['title'];
			} else {
				$options['title'] = '';
			}

			$properties  = $file_or_options;

			if ( ! empty( $properties['title'] ) ) {
				$options = $properties;
			}
		} else {
			$options = array_merge( $options, $file_or_options );

			if ( ! $is_box ) {
				// Add all non string keys to the properties array
				foreach ( $options as $key => $value ) {
					if ( ! is_string( $key ) ) {
						$properties[] = $value;
						unset( $options[$key] );
					}
				}
			}
		}
	} else if ( is_string( $file_or_options ) ) {
		// If it's a template we need to load it the right way
		// and add all properties the right way.
		if ( preg_match( '/\.php$/', $file_or_options ) === 1 ) {
			$values   = $properties;
			$template = papi_template( $file_or_options, $values );

			// Create the property array from existing property array or a new.
			$properties = [];
			$options    = $template;

			// Add all non string keys to the properties array
			foreach ( $options as $key => $value ) {
				if ( ! is_string( $key ) ) {
					$properties[] = $value;
					unset( $options[$key] );
				}
			}
		} else {
			// The first parameter is used as the title.
			$options['title'] = $file_or_options;
		}
	}

	return [$options, $properties];
}

/**
 * Get property class name.
 *
 * Will replace 'Property' if it exists in the string
 * since it's a old way to write which type to use.
 *
 * @param  string $type
 *
 * @return string
 */
function papi_get_property_class_name( $type ) {
	if ( ! is_string( $type ) || empty( $type ) ) {
		return;
	}

	$type = str_replace( '-', '_', $type );
	$type = explode( '_', $type );
	$type = array_map( 'ucfirst', $type );
	$type = implode( '_', $type );

	return 'Papi_Property_' . ucfirst( preg_replace( '/^Property/', '', $type ) );
}

/**
 * Get property value from database.
 *
 * If it's on a option page it will fetch the value from the
 * option table instead of the metadata table.
 *
 * @param  int    $id
 * @param  string $slug
 * @param  string $type
 *
 * @return mixed
 */
function papi_get_property_meta_value( $id, $slug, $type = 'post' ) {
	$type = papi_get_meta_type( $type );

	// Set the right get value function for the type.
	$get_value_fn = $type === 'option' ? 'get_option' : 'get_metadata';

	/**
	 * Change get value function.
	 *
	 * @param string $delete_function
	 */
	$get_value_fn = apply_filters( 'papi/core/get_value_fn', $get_value_fn );

	// Check so the function is callable before using it.
	if ( ! is_callable( $get_value_fn ) ) {
		return;
	}

	if ( $type === 'option' ) {
		$value = call_user_func_array( $get_value_fn, [unpapify( $slug ), null] );
	} else {
		$value = call_user_func_array( $get_value_fn, [$type, $id, unpapify( $slug ), true] );

		// Backward compatibility, slugs can contain `papi` prefix.
		if ( papi_is_empty( $value ) ) {
			$value = call_user_func_array( $get_value_fn, [$type, $id, $slug, true] );
		}
	}

	if ( papi_is_empty( $value ) ) {
		return;
	}

	return $value;
}

/**
 * Get property type by the given type.
 *
 * @param  mixed $type
 *
 * @return Papi_Core_Property
 */
function papi_get_property_type( $type ) {
	return Papi_Core_Property::factory( $type );
}

/**
 * Get the right key for a property type.
 *
 * @param  string $str
 *
 * @return string
 */
function papi_get_property_type_key( $str = '' ) {
	$suffix = '_property';

	if ( ! is_string( $str ) || strlen( $str ) === 0 ) {
		return $suffix;
	}

	$len = strlen( $str );

	if ( isset( $str[$len - 1] ) && $str[$len - 1] === ']' ) {
		$str = substr( $str, 0, $len - 1 );
		return papi_get_property_type_key( $str ) . ']';
	}

	return unpapify( $str . $suffix );
}

/**
 * Get the right key for a property type with a underscore as the first character.
 *
 * @param  string $str
 *
 * @return string
 */
function papi_get_property_type_key_f( $str ) {
	return papi_f( papi_get_property_type_key( $str ) );
}

/**
 * Check if it's ends with '_property'.
 *
 * @param  string $str
 *
 * @return bool
 */
function papi_is_property_type_key( $str = '' ) {
	$pattern = '_property';
	$pattern = str_replace( '_', '\_', $pattern );
	$pattern = str_replace( '-', '\-', $pattern );
	$pattern = '/' . $pattern . '$/';
	return preg_match( $pattern, $str ) === 1;
}

/**
 * Create a new property array or rendering a template property file.
 *
 * @param  mixed $file_or_options
 * @param  array $values
 *
 * @return Papi_Core_Property
 */
function papi_property( $file_or_options, array $values = [] ) {
	if ( papi_is_empty( $file_or_options ) ) {
		return;
	}

	if ( is_array( $file_or_options ) ) {
		if ( $property = Papi_Core_Property::factory( $file_or_options ) ) {
			$file_or_options = $property->get_options();
		}
	}

	if ( is_string( $file_or_options ) && is_array( $values ) ) {
		$file_or_options = papi_template( $file_or_options, $values );

		if ( is_object( $file_or_options ) ) {
			$file_or_options = papi_property( (array) $file_or_options->get_options() );
		}
	}

	if ( is_object( $file_or_options ) ) {
		return papi_get_property_type( $file_or_options );
	}
}

/**
 * Render the given property.
 *
 * @param mixed $property
 */
function papi_render_property( $property ) {
	$property = Papi_Core_Property::factory( $property );

	if ( ! papi_is_property( $property ) ) {
		return;
	}

	$property->render();
}

/**
 * Render properties the right way.
 *
 * @param array $properties
 */
function papi_render_properties( array $properties ) {
	if ( empty( $properties ) ) {
		return;
	}

	// If it's a tab the tabs class will
	// handle the rendering of the properties.
	if ( $properties[0] instanceof Papi_Core_Tab ) {
		new Papi_Admin_Meta_Box_Tabs( $properties );
	} else {
		?>
		<table class="papi-table">
			<tbody>
			<?php
			foreach ( $properties as $property ) {
				papi_render_property( $property );
			}
			?>
			</tbody>
		</table>
	<?php
	}
}

/**
 * Get require text for property.
 *
 * @param  stdClass $property
 *
 * @return string
 */
function papi_require_text( $property ) {
	if ( ! papi_is_property( $property ) || ! $property->required ) {
		return '';
	}

	return esc_html__( '(required field)', 'papi' );
}

/**
 * Get require tag for property.
 *
 * @param  stdClass $property
 * @param  bool     $text
 *
 * @return string
 */
function papi_required_html( $property, $text = false ) {
	if ( ! papi_is_property( $property ) || ! $property->required ) {
		return '';
	}

	return ' <span class="papi-rq" data-property-name="' . $property->title . '" data-property-id="' . $property->slug . '">' . ( $text ? papi_require_text( $property ) : '*' ) . '</span>';
}

/**
 * Populate properties array.
 *
 * @param  array|object $properties
 *
 * @return array
 */
function papi_populate_properties( $properties ) {
	if ( ! is_array( $properties ) && ! is_object( $properties ) || empty( $properties ) ) {
		return [];
	}

	if ( is_object( $properties )  ) {
		return [$properties];
	}

	// Convert all non property objects to property objects.
	$properties = array_map( function ( $property ) {
		if ( is_array( $property ) ) {
			return papi_property( $property );
		}

		return $property;
	}, $properties );

	if ( ! isset( $properties[0] ) ) {
		$properties = [papi_property( $properties )];
	}

	// If the first property is a core tab, just return
	// the properties array and skip the last check.
	if ( empty( $properties ) || $properties[0] instanceof Papi_Core_Tab ) {
		return papi_sort_order( $properties );
	}

	return papi_sort_order( array_filter( array_reverse( $properties ), 'papi_is_property' ) );
}

/**
 * Update property values on the post with the given post id
 * or update property values on the option page.
 *
 * @param  array $meta
 *
 * @return bool
 */
function papi_update_property_meta_value( array $meta = [] ) {
	$meta       = array_merge( [
		'id'    => 0,
		'slug'  => '',
		'type'  => 'post',
		'value' => ''
	], $meta );
	$meta       = (object) $meta;
	$meta->type = papi_get_meta_type( $meta->type );
	$save_value = true;

	// Set the right update value function for the type.
	$update_value_fn = $meta->type === 'option' ? 'update_option' : 'update_metadata';

	/**
	 * Change update function.
	 *
	 * @param string $update_value_fn
	 */
	$update_value_fn = apply_filters( 'papi/core/update_value_fn', $update_value_fn );

	// Check so the function is callable before using it.
	if ( ! is_callable( $update_value_fn ) ) {
		return;
	}

	// Check for string keys in the array if any.
	foreach ( papi_to_array( $meta->value ) as $key => $value ) {
		if ( is_string( $key ) ) {
			$save_value = false;
			break;
		}
	}

	// If main value shouldn't be saved it should be array.
	if ( ! $save_value && is_array( $meta->value ) ) {
		$meta->value = [$meta->value];
	}

	// Delete saved value if empty.
	if ( papi_is_empty( $meta->value ) ) {
		return papi_delete_property_meta_value( $meta->id, $meta->slug, $meta->type );
	}

	$result = true;

	foreach ( papi_to_array( $meta->value ) as $key => $value ) {
		// Delete saved value if value is empty.
		if ( papi_is_empty( $value ) || $value === '[]' || $value === '{}' ) {
			return papi_delete_property_meta_value( $meta->id, $meta->slug, $meta->type );
		}

		// Delete main value cache.
		papi_cache_delete( $meta->slug, $meta->id, $meta->type );

		// If not a array we can save the value.
		if ( ! is_array( $value ) ) {
			if ( $save_value ) {
				$value = $meta->value;
			}

			if ( papi_get_meta_type( $meta->type ) === 'option' ) {
				$out = call_user_func_array( $update_value_fn, [unpapify( $meta->slug ), $value] );
				$result = $out ? $result : $out;
			} else {
				$out = call_user_func_array( $update_value_fn, [$meta->type, $meta->id, unpapify( $meta->slug ), $value] );
				$result = $out ? $result : $out;
			}

			continue;
		}

		// Delete all child value caches.
		papi_update_property_meta_value_cache_delete( $meta, $value );

		// Update metadata or option value for all child values.
		foreach ( $value as $child_key => $child_value ) {
			if ( papi_is_empty( $child_value ) ) {
				papi_delete_property_meta_value( $meta->id, $child_key, $meta->type );
			} else {
				if ( papi_get_meta_type( $meta->type ) === 'option' ) {
					call_user_func_array( $update_value_fn, [unpapify( $child_key ), $child_value] );
				} else {
					call_user_func_array( $update_value_fn, [$meta->type, $meta->id, unpapify( $child_key ), $child_value] );
				}
			}
		}
	}

	return $result;
}

/**
 * Delete cache values on update property meta.
 *
 * @param  object $meta
 * @param  array  $value
 */
function papi_update_property_meta_value_cache_delete( $meta, $value ) {
	$value = is_array( $value ) ? $value : [];

	foreach ( $value as $child_key => $child_value ) {
		papi_cache_delete( $child_key, $meta->id, $meta->type );

		if ( is_array( $child_value ) ) {
			papi_update_property_meta_value_cache_delete( $meta, $child_value );
		}
	}
}

/**
 * Convert array of arrays to array of slugs.
 * The given slug will match a key with the number of properties.
 *
 * @param  array  $value
 * @param  string $slug
 *
 * @return array
 */
function papi_to_property_array_slugs( array $value, $slug ) {
	$results = [];
	$counter = [];

	foreach ( $value as $index => $arr ) {

		if ( ! is_array( $arr ) ) {
			continue;
		}

		$counter[] = $arr;

		foreach ( $arr as $key => $val ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				continue;
			}

			if ( $key[0] !== '_' ) {
				$key = '_' . $key;
			}

			$item_slug = $slug . '_' . $index . $key;

			if ( papi_is_property_type_key( $item_slug ) ) {
				$item_slug = papi_f( $item_slug );
			}

			$results[$item_slug] = $val;
		}
	}

	$results[$slug] = count( $counter );

	return $results;
}
