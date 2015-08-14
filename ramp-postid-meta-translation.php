<?php
/*
Plugin Name: RAMP Post ID Meta Translation
Plugin URI: http://crowdfavorite.com
Description: Adds the ability to select which post meta fields represent a post mapping and adds them to the batch
Version: 1.0.3
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/*
 * Copyright (c) 2012-2013 Crowd Favorite, Ltd. All rights reserved.
 * http://crowdfavorite.com
 *
 * **********************************************************************
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * **********************************************************************
 */

// Deactivate if RAMP not present
if ( ! function_exists( 'cfd_init' ) ) {
	add_action( 'admin_init', 'deactivate_ramp_mm_keys' );

	function deactivate_ramp_mm_keys() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		echo  '<div class="error"><p><strong>RAMP Post ID Meta Translation</strong> requires RAMP to be active and has deactivated itself.</p></div>';
	}

	return;
}

load_plugin_textdomain('ramp-mm');

function ramp_mm_keys() {
	return (array) get_option('ramp_mm_keys');
}

function ramp_mm_init() {
	register_setting('cf-deploy-settings', 'ramp_mm_keys', 'ramp_mm_validate');
	foreach (ramp_mm_keys() as $key) {
		cfr_register_metadata($key);
	}
}
add_action('admin_init', 'ramp_mm_init');

function ramp_mm_validate($settings) {
	$excluded_keys = ramp_mm_excluded_keys();
	foreach ($settings as $key => $setting) {
		if (in_array($setting, $excluded_keys)) {
			unset($settings[$key]);
		}
	}
	return $settings;
}

function ramp_mm_drilldown_config() {
	/* Example of config
	$drilldown = array(
		'toplevel_meta_key' => array(
			'index1->index1child1->index1grandchild',
			'index1->index1child2',
		),
		'toplevel_meta_key2' => ...
  	*/
	return apply_filters('ramp_mm_drilldown_config', array() );
}

function ramp_mm_drilldown_keys() {
	return array_keys( ramp_mm_drilldown_config() );
}

// Don't show drilldown keys in the selectable list of keys to send, that would be confusing
function ramp_mm_drilldown_keys_exclude( $keys ) {
	return array_merge( ramp_mm_drilldown_keys(), $keys );
}
add_filter( 'ramp_mm_excluded_keys', 'ramp_mm_drilldown_keys_exclude' );

function ramp_mm_excluded_keys() {
	return apply_filters(
		'ramp_mm_excluded_keys',
		array(
			'_cfct_build_data',
			'_edit_last',
			'_edit_lock',
			'_format_audio_embed',
			'_format_gallery',
			'_format_image',
			'_format_link_url',
			'_format_quote_source_name',
			'_format_quote_source_url',
			'_format_url',
			'_format_video_embed',
			'_menu_item_classes',
			'_menu_item_menu_item_parent',
			'_menu_item_object',
			'_menu_item_object_id',
			'_menu_item_orphaned',
			'_menu_item_target',
			'_menu_item_type',
			'_menu_item_url',
			'_menu_item_xfn',
			'_post_restored_from',
			'_thumbnail_id',
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_wp_page_template',
			'_batch_deploy_messages',
			'_batch_destination',
			'_batch_export_complete',
			'_batch_export_failed',
			'_batch_import_complete',
			'_batch_id',
			'_batch_import_messages',
			'_batch_send_user',
			'_batch_session_token',
			'_batch_source',
			'_preflight_data',
			'_ramp_mm_comp_data',
		)
	);
}

function ramp_mm_admin_form($obj) {
	$options = '';
	$keys = array_diff(ramp_mm_available_keys(), ramp_mm_excluded_keys());
	$count = count($keys);
	$selected = ramp_mm_keys();
	$drilldown_keys = ramp_mm_drilldown_keys();
	if ($count) {
		$i = 0;
		foreach ($keys as $key) {
			if ($count > 10) {
				if ($i > 0 && $i % ceil($count / 3) == 0) {
					$options .= '</ul><ul>';
				}
			}
			$i++;
			$id = 'ramp_mm_keys-'.$key;
			$checked = (in_array($key, $selected) ? ' checked="checked"' : '');
			$options .= '
		<li>
			<input type="checkbox" name="ramp_mm_keys[]" value="'.esc_attr($key).'" id="'.esc_attr($id).'"'.$checked.' />
			<label for="'.esc_attr($id).'">'.esc_html($key).'</label>
		</li>
			';
		}
?>
<style>
#ramp-mm-keys ul {
	float: left;
	width: 33%;
}
</style>
<?php
	}
	else {
		$options = '<li>'.__('No custom fields found.', 'ramp-mm').'</li>';
	}
?>
<div class="form-section" id="ramp-mm-keys">
	<fieldset>
		<legend><?php _e('Custom Fields', 'ramp-mm'); ?></legend>
		<p class="cf-elm-help"><?php _e('Select any custom fields that represent a post id to be translated when a batch is sent.', 'ramp-mm'); ?></p>
		<div class="cf-elm-block cf-elm-width-full">
<?php
	echo '<ul>'.$options.'</ul>';
?>

			<div class="cf-clearfix"></div>
		<?php if ( ! empty ( $drilldown_keys ) ): ?>
			<p class="cf-elm-help"> <?php _e( 'The following keys are set by the drilldown configuration and will be processed automatically. Please see the README for more information', 'ramp-mm' ); ?></p>
			<?php // @TODO Show the config for each of these keys? ?>
			<code><?php echo implode( ',', $drilldown_keys ); ?></code>
		<?php endif; ?>
		</div>
	</fieldset>
</div>
<?php
}
add_action('cf_deploy_admin_form', 'ramp_mm_admin_form');

function ramp_mm_available_keys() {
	global $wpdb;
	return $wpdb->get_col("
		SELECT DISTINCT meta_key
		FROM $wpdb->postmeta
		ORDER BY meta_key
	");
}

function ramp_mm_cfd_init() {
	$ramp_meta = RAMP_Meta_Mappings::factory();
	$ramp_meta->add_actions();
}
add_action('cfd_admin_init', 'ramp_mm_cfd_init');

class RAMP_Meta_Mappings {
	var $existing_ids = array(); // Ids already processed or in the batch
	var $added_posts = array(); // New Posts added to the batch
	var $batch_posts = array(); // Posts that are a part of the batch
	var $data = array();
	var $client_server_post_mappings = array(); // Store the client id => server id mapping
	var $comparison_data = array();
	var $comparison_key = '_ramp_mm_comp_data';
	var $history_key = '_ramp_mm_history_data';
	var $name = 'RAMP Meta Mappings';

	static $instance;

	// Singleton
	public static function factory() {
		if (!isset(self::$instance)) {
			self::$instance = new RAMP_Meta_Mappings;
		}
		return self::$instance;
	}

	function __construct() {
		$this->meta_keys_to_map = $this->_combine_meta_keys();
		$this->drilldown_keys = ramp_mm_drilldown_keys();
		$this->drilldown_config = ramp_mm_drilldown_config();
		$this->extras_id = cfd_make_callback_id('ramp_mm_keys');
	}

	function _combine_meta_keys() {
		$mm_keys = ramp_mm_keys();
		$drilldown_keys = ramp_mm_drilldown_keys();
		return array_merge( $mm_keys, $drilldown_keys );
	}

	function add_actions() {
	// Client Actions
		// Need comparison data on preflight page
		add_action('ramp_preflight_batch', array($this, 'fetch_comparison_data'));

		// This runs after the two actions above, comparison data is stored in $this->post_types_compare
		// Adds additional posts to the batch based on meta data
		add_action('ramp_pre_get_deploy_data', array($this, 'pre_get_deploy_data'));

		// Modifies the object that the client gets locally to compare with the server post
		add_filter('ramp_get_comparison_data_post', array($this, 'get_comparison_data_post'));

		// Modified the meta just before sending it to the server
		add_filter('ramp_get_deploy_object', array($this, 'get_deploy_object'), 10, 2);

		// Extras handling
		add_action('ramp_extras_preflight_name', array($this, 'extras_preflight_name'), 10, 2);
		add_filter('ramp_get_comparison_extras', array($this, 'get_extras_filter'));
		add_filter('ramp_get_preflight_extras', array($this, 'get_extras_filter'));
		add_filter('ramp_get_deploy_extras', array($this, 'get_deploy_extras'), 10, 2);
		add_filter('ramp_add_extras_comparison_data', array($this, 'get_extras_filter'));
		add_action('ramp_do_batch_extra_send', array($this, 'do_batch_extra_send'), 10, 2);

		// Cleanup
		add_filter('ramp_close_batch_send', array($this, 'close_batch_send'));

		add_filter('ramp_history_data', array($this, 'history_data'), 10, 2);

	// Server actions
		// Preps the data with the meta keys to map
		add_filter('ramp_compare_extras', array($this, 'compare_extras'), 10, 2);

		// Modifies server return data. Updates post meta to be guids of posts on the server
		add_filter('ramp_compare', array($this, 'compare'));

		// Adds additional messages to the preflight display
		add_filter('ramp_preflight_post', array($this, 'preflight_post'), 10, 3);

		// Handles the remapping
		add_action('ramp_do_batch_extra_receive', array($this, 'do_batch_extra_receive'), 10, 3);

	}

// Client functions
	/**
	 * Runs on Client
	 * Fetch comparison data on preflight and save in the meta
	 * Allows a consistent set of comparison data to be accessed on the send page
	 **/
	function fetch_comparison_data($admin_deploy) {
		$admin_deploy->batch->init_comparison_data();
		$admin_deploy->batch->add_extras_comparison_data($admin_deploy->get_comparison_extras());
		$admin_deploy->do_server_comparison($admin_deploy->batch);

		// Now the batch has populated c_data
		$data = $admin_deploy->batch->get_comparison_data('post_types');

		$this->save_comparison_data($admin_deploy->batch->ID, $data);
	}

	/**
	 * Save comparison data in a compact way on the batch post
	 **/
	function save_comparison_data($batch_id, $data) {
		$post_guids = array();
		foreach ($data as $post_type => $posts) {
			$post_guids = array_merge($post_guids, array_keys($posts));
		}

		update_post_meta($batch_id, $this->comparison_key, $post_guids);
	}

	/**
	 * Loads comparison data that happened on preflight
	 **/
	function load_comparison_data($batch_id) {
		$this->comparison_data = (array) get_post_meta($batch_id, $this->comparison_key, true);
	}

	/**
	 * Deletes preflight comparison data
	 **/
	function delete_comparison_data($batch_id) {
		delete_post_meta($batch_id, $this->comparison_key);
		$this->comparison_data = array();
	}

	/**
	 * Runs on client
	 * Used for displaying extra row name on preflight
	 **/
	function extras_preflight_name($name, $extra_id) {
		if ($extra_id == $this->extras_id) {
			return $this->name;
		}
		return $name;
	}

	/**
	 * Runs on client
	 *
	 * Modifies the post the client grabs to compare with the server post
	 * Replaces the post's meta mapped keys with the guids
	 **/
	function get_comparison_data_post($post) {
		$post_meta_keys = $post->profile['meta'];
		if (is_array($post_meta_keys)) {
			foreach ($post_meta_keys as $meta_key => $meta_value) {
				if ( in_array( $meta_key, $this->meta_keys_to_map ) ) {
					if ( is_array( $meta_value ) ) {
						if ( in_array( $meta_key, $this->drilldown_keys ) ) {
							foreach ( $this->drilldown_config[ $meta_key ] as $drilldown_pattern ) {
								$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
								$guid = cfd_get_post_guid( $drilldown_id );
								if ( $guid ) {
									$meta_value = $this->_set_drilldown_value( $meta_value, $drilldown_pattern, $guid );
									$post->profile['meta'][ $meta_key ] = $meta_value;
								}
							}
						}
						else {
							foreach ( $meta_value as $index => $array_post_id ) {
								$guid = cfd_get_post_guid( $array_post_id );
								if ( $guid ) {
									$post->profile['meta'][ $meta_key ][ $index ] = $guid;
								}
							}
						}
					}
					else {
						$guid = cfd_get_post_guid( $meta_value );
						if ( $guid ) {
							$post->profile['meta'][ $meta_key ] = $guid;
						}
					}

				}
			}
		}
		return $post;
	}

	/**
	 * Runs on client
	 * Modifies a post object's meta with the guid where appropriate
	 * This occurs just before sending data to the server
	 **/
	function get_deploy_object($object, $object_type) {
		if ($object_type == 'post_types') {
			if (isset($object['meta']) && is_array($object['meta'])) {
				foreach ($object['meta'] as $meta_key => $meta_value) {
					if ( in_array( $meta_key, $this->meta_keys_to_map ) ) {
						$meta_value = maybe_unserialize( $meta_value );
						if ( is_array( $meta_value ) ) {
							// Drilldown
							if ( in_array( $meta_key, $this->drilldown_keys ) ) {
								foreach ( $this->drilldown_config[ $meta_key ] as $drilldown_pattern ) {
									$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
									if ( is_numeric( $drilldown_id ) ) {
										$guid = cfd_get_post_guid($drilldown_id);
										if ( $guid ) {
											$meta_value = $this->_set_drilldown_value( $meta_value, $drilldown_pattern, $guid );
											$object['meta'][ $meta_key ] = $meta_value;
										}
									}
								}
							}
							// Array keys of post ids
							else {
								foreach ( $meta_value as $index => $array_post_id ) {
									if ( is_numeric( $array_post_id ) ) {
										$guid = cfd_get_post_guid($array_post_id);
										if ( $guid ) {
											$object['meta'][ $meta_key ][ $index ] = $guid;
										}
									}
								}
							}
						}
						else if ( is_numeric( $meta_value ) ) {
							$guid = cfd_get_post_guid( $meta_value );
							if ( $guid ) {
								$object['meta'][ $meta_key ] = $guid;
							}
						}
					}
				}
			}
		}
		return $object;
	}

	/**
	 * Runs on both,
	 * helper function to get a drilldown value
	 *
	 **/
	function _get_drilldown_value( $meta_value, $drilldown ) {
		$drilldown_arr = explode( '->', $drilldown );
		$val = $meta_value;
		foreach ( $drilldown_arr as $drilldown_index ) {
			if ( isset( $val[ $drilldown_index ] ) ) {
				$val = $val[ $drilldown_index ];
			}
			// Drilldown isn't actually set, return false
			else {
				return false;
			}
		}

		return $val;
	}

	/**
	 * Runs on both,
	 * helper function to get a drilldown value
	 *
	 * @param $array array, Object to drilldown into and set a value
	 * @param $drilldown string, Drilldown config string
	 * @param $value mixed, value to set in the object
	 **/
	function _set_drilldown_value( $array, $drilldown, $value ) {
		$drilldown_arr = explode( '->', $drilldown );
		if ( ! is_array( $array ) ) {
			$array = array();
		}

		$drilldown_depth = count( $drilldown_arr );
		$temp_arr = &$array;
		foreach ( $drilldown_arr as $index => $drilldown_index ) {
			if ( $index == $drilldown_depth - 1 ) {
				$temp_arr[ $drilldown_index ] = $value;
			}
			else {
				if ( ! isset( $temp_arr[ $drilldown_index ] ) || ! is_array( $temp_arr[ $drilldown_index] ) ) {
					$temp_arr[ $drilldown_index ] = array();
				}
			}

			$temp_arr = &$temp_arr[ $drilldown_index ];
		}

		return $array;
	}
	/**
	 * Runs on client
	 * Process a post so any of the mapped meta keys also get processed in the batch
	 *
	 * @param int $post_id ID of a post to map
	 * @param bool $add_guid whether or not to add this guid to the set of batch posts. Prevents loading the same posts into memory
	 **/
	function process_post( $post_id, $add_guid = true ) {
		if ( $add_guid ) {
			$this->batch_posts[] = cfd_get_post_guid( $post_id );
		}
		$meta = get_metadata( 'post', $post_id );
		if (is_array( $meta ) ) {
			foreach ( $meta as $meta_key => $meta_values ) {
				// $meta_values should always be an array
				if ( is_array( $meta_values ) ) {
					foreach ( $meta_values as $meta_value ) {
						$meta_value = maybe_unserialize( $meta_value );
						if ( in_array( $meta_key, $this->meta_keys_to_map ) && (int) $meta_value > 0 ) {
							if ( is_array( $meta_value ) ) {
								// Drilldown
								if ( in_array( $meta_key, $this->drilldown_keys ) ) {
									foreach ( $this->drilldown_config[ $meta_key ] as $drilldown_pattern ) {
										$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
										if ( (int) $drilldown_id > 0 ) {
											$this->_process_post( $drilldown_id );
										}
									}
								}
								// Array of post ids
								else {
									foreach ( $meta_value as $array_post_id ) {
										if ( (int) $array_post_id > 0 ) {
											$this->_process_post( $array_post_id );
										}
									}
								}
							}
							else {
								$this->_process_post( $meta_value );
							}
						}
					}
				}
			}
		}
		do_action( 'ramp_after_process_post', $post_id, $this );
	}

	// Helper for processing posts
	function _process_post( $post_id ) {
		$new_post = get_post( $post_id );
		if (
			$new_post // Post exists check
			&& ! in_array( $new_post->ID, $this->existing_ids ) // Post isnt already in the batch
			&& in_array( $new_post->guid, $this->comparison_data )// Post is modified
		) {
			if ( ! is_array($this->data['post_types'][ $new_post->post_type ] ) ) {
				$this->data['post_types'][ $new_post->post_type ] = array();
			}
			$this->data['post_types'][ $new_post->post_type ][] = $new_post->ID;
			$this->existing_ids[] = $new_post->ID;
			// Use for processes and notices
			$this->added_posts[ $new_post->ID ] = array(
				'post_title' => $new_post->post_title,
				'post_type' => $new_post->post_type,
				'guid' => $new_post->guid
			);
			$this->batch_posts[] = $new_post->guid;
			$this->process_post( $new_post->ID, false );
		}
	}

	// Helper for displaying the meta keys
	function meta_to_markup($meta_keys) {
		return '<code>'.implode('</code>, <code>', $meta_keys).'</code>';
	}

	/**
	 * Runs on the client
	 * Add extra meta data to pass from client to server
	 **/
	function get_extras($extras, $type = 'default') {
		if ($type == 'history') {
			$batch_id = $_GET['batch'];
			$meta = get_post_meta($batch_id, $this->history_key, true);
			$meta_keys = isset($meta['meta_keys']) ? $meta['meta_keys'] : array();
			$drilldown_config = isset($meta['drilldown_config']) ? $meta['drilldown_config'] : array();
		}
		else {
			$meta_keys = $this->meta_keys_to_map;
			$drilldown_config = $this->drilldown_config;
		}
		$extras[$this->extras_id] = array(
			'meta_keys' => $meta_keys, // The keys we're mapping (or were mapped)
			'drilldown_config' => $drilldown_config, // Drilldown configuration
			'mapped_posts' => $this->added_posts, // All posts which have been added to the batch by this plugin
			'batch_posts' => $this->batch_posts, // All posts being sent in the batch
			'name' => __('Meta Mappings', 'ramp-mm'),
			'description' => sprintf(__('Key mappings: %s', 'ramp-mm'), $this->meta_to_markup($meta_keys)),
			'__message__' => sprintf(__('Keys to be remapped: %s', 'ramp-mm'), $this->meta_to_markup($meta_keys)),
		);
		return $extras;
	}

	function get_deploy_extras($extras, $type) {
		return $this->get_extras($extras, $type);
	}

	function get_extras_filter($extras) {
		return $this->get_extras($extras, 'default');
	}

	/**
	 * Runs on the client
	 * Add extra data to send data via filter instead of callback
	 **/
	function do_batch_extra_send($extra, $id) {
		if ($id == $this->extras_id) {
			$extras = $this->get_extras(array(), 'default');
			$extra = $extras[$this->extras_id];
		}
		return $extra;
	}

	/**
	 * Runs on the client
	 * Loads additional posts into the deploy data based on meta values
	 *
	 **/
	function pre_get_deploy_data($batch) {
		$this->load_comparison_data($batch->ID);
		// We get a reference to the object but not arrays
		$this->data = $batch->data;
		$existing_ids = array();
		if (isset($this->data['post_types']) && is_array($this->data['post_types'])) {
			foreach ($this->data['post_types'] as $post_type => $post_ids) {
				foreach ($post_ids as $post_id) {
					$this->existing_ids[] = $post_id;
				}
			}

			foreach ($this->data['post_types'] as $post_type => $post_ids) {
				foreach ($post_ids as $post_id) {
					$this->process_post($post_id);
				}
			}
		}
		// So the server knows which ones are added, also for display
		if (isset($this->data['extras'])) {
			$this->data['extras'] = $this->get_extras($this->data['extras']);
		}
		else {
			$this->data['extras'] = array();
		}

		$batch->data = $this->data;
	}


	/**
	 * Runs on Client after sending a batch
	 * Store history data
	 * Cleanup meta that was saved to the batch post
	 **/
	function close_batch_send($args) {
		$batch_id = $args['batch_id'];
		$batch = new cfd_batch(array('ID' => intval($batch_id)));

		// Save this data for the history view without modifying RAMP data
		$this->pre_get_deploy_data($batch);
		$history_data = array(
			'meta_keys' => $this->meta_keys_to_map,
			'posts' => $this->added_posts,
			'drilldown_config' => $this->drilldown_config,
		);
		update_post_meta($batch_id, $this->history_key, $history_data);
		// Cleanup
		$this->delete_comparison_data($batch_id);
	}

	/**
	 * Runs on client
	 *
	 * Displays history data when viewing a batch history
	 * Includes posts added to the batch by the plugin
	 **/
	function history_data($data, $batch_id) {
		$rm_data = get_post_meta($batch_id, $this->history_key, true);
		if (is_array($rm_data)) {
			foreach ($rm_data['posts'] as $post_id => $post_data) {
				$post_type = $post_data['post_type'];
				if (!in_array($post_data['guid'], (array)$data['post_types'][$post_type])) {
					$data['post_types'][$post_type][$post_data['guid']] = array(
						'post' => array(
							'ID' => $post_id,
							'post_title' => $post_data['post_title'],
							'post_type' => $post_data['post_type'],
						),
					);
				}
			}
		}

		return $data;
	}

// Server functions

	/**
	 * Runs on Server
	 *
	 * Updates any post meta ids with a guid of the post its mapped to
	 * Occurs before sending post difference back to the client
	 **/
	function compare($c_data) {
		$meta_keys_to_map = $c_data['extras'][ $this->extras_id ]['meta_keys']['status'];
		$drilldown_config = $c_data['extras'][ $this->extras_id ]['drilldown_config']['status'];
		$drilldown_keys = array_keys( $drilldown_config );
		foreach ( $c_data['post_types'] as $post_type => $posts ) {
			foreach ( $posts as $post_guid => $post_data ) {
				// Make sure that the return data is what we want and not something else like an error
				if ( isset( $post_data['profile']['meta'] ) ) {
					$post_meta = $post_data['profile']['meta'];
					if ( is_array( $post_meta ) ) {
						foreach ( $post_meta as $meta_key => $meta_value ) {
							if ( in_array( $meta_key, $meta_keys_to_map ) ) {
								if ( is_numeric( $meta_value ) ) {
									// Get guid and set it as that!
									$guid = cfd_get_post_guid( $meta_value );
									if ( $guid ) {
										$c_data['post_types'][ $post_type ][ $post_guid ]['profile']['meta'][ $meta_key ] = $guid;
									}
								}
								// @TODO Consider associative array of keys
								// Process arrays of keys
								else if ( is_array( $meta_value ) ) {
									$storage_array = array();
									// Drilldown
									if ( in_array( $meta_key, $drilldown_keys ) ) {
										$storage_array = $meta_value;
										foreach ( $drilldown_config[ $meta_key ] as $drilldown_pattern ) {
											$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
											$guid = cfd_get_post_guid( $drilldown_id );
											if ( $guid ) {
												$storage_array = $this->_set_drilldown_value( $storage_array, $drilldown_pattern, $guid );
											}
										}
									}
									// Array of ids
									else {
										foreach ($meta_value as $array_post_id ) {
											$guid = cfd_get_post_guid( $array_post_id );
											if ( $guid ) {
												$storage_array[] = $guid;
											}
											else {
												$storage_array[] = $array_post_id;
											}
										}
									}
									$c_data['post_types'][ $post_type ][ $post_guid ]['profile']['meta'][ $meta_key ] = $storage_array;
								}
							}
						}
					}
				}
			}
		}
		// This gets returned to the client
		return $c_data;
	}

	/**
	 * Runs on server
	 * So compare($c_data) knows the meta_keys to lookup
	 * Adds in extra data via a filter instead of callback
	 **/
	function compare_extras($ret, $extras) {
		if (!isset($ret[$this->extras_id])) {
			$ret[$this->extras_id] = $extras[$this->extras_id];
		}
		return $ret;
	}

	/**
	 * Runs on server
	 * Processes data throws notices for RAMP Meta added items
	 **/
	function preflight_post($ret, $post, $batch_items) {
		if (!empty($batch_items['extras'][$this->extras_id]['mapped_posts'])) {
			$meta_added = $batch_items['extras'][$this->extras_id]['mapped_posts'];
			$mapped_keys = $batch_items['extras'][$this->extras_id]['meta_keys'];

			// Show notice that this post was not originally in the batch, but added by RAMP Meta
			if (in_array($post['post']['ID'], array_keys($meta_added))) {
				$ret['__notice__'][] =  __('This post was added by the RAMP Meta Plugin.', 'ramp-mm');
			}

			// Show notice on post of what items the meta maps to
			if ( isset($post['meta']) && is_array($post['meta'] ) ) {
				foreach ($post['meta'] as $meta_key => $meta_value) {
					$meta_value = maybe_unserialize( $meta_value );
					if ( is_array( $meta_value ) ) {

						// Drilldown
						if ( in_array( $meta_key, $this->drilldown_keys ) ) {
							foreach ( $this->drilldown_config[ $meta_key ] as $drilldown_pattern ) {
								$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
								$return_data = $this->_preflight_post( $drilldown_id, $meta_added, $meta_key, $mapped_keys, $batch_items );
								if ( isset( $return_data['type'] ) ) {
									$ret[ $return_data['type'] ][] = $return_data['msg'];
								}
							}
						}
						// Array of IDs
						else {
							foreach ( $meta_value as $array_post_id ) {
								$return_data = $this->_preflight_post( $array_post_id, $meta_added, $meta_key, $mapped_keys, $batch_items );
								if ( isset( $return_data['type'] ) ) {
									$ret[ $return_data['type'] ][] = $return_data['msg'];
								}
							}
						}
					}
					// Single Value
					else {
						$return_data = $this->_preflight_post( $meta_value, $meta_added, $meta_key, $mapped_keys, $batch_items );
						if ( isset( $return_data['type'] ) ) {
							$ret[ $return_data['type'] ][] = $return_data['msg'];
						}
					}
				}
			}
		}

		// Prevent duplicate messages if the same post is mapped
		if ( isset( $ret['__notice__'] )) {
			$ret['__notice__'] = array_unique( $ret['__notice__'] );
		}
		if ( isset( $ret['__error__'] )) {
			$ret['__error__'] = array_unique( $ret['__error__'] );
		}
		return $ret;
	}

	function _preflight_post( $post_id, $meta_added, $meta_key, $mapped_keys, $batch_items) {
		$return = array();
		if ( in_array($post_id, array_keys( $meta_added ) ) && in_array( $meta_key, $mapped_keys ) ) {
			$guid = $meta_added[$post_id]['guid'];
			$post_type = $meta_added[$post_id]['post_type'];
			// Need to ensure that the post is still there, throw an error if its not
			if ( isset($batch_items['post_types'][$post_type][$guid] ) ) {
				$return['msg'] = sprintf(__('%s "%s" was found mapped in the post meta and has been added to the batch.', 'ramp-mm'), $meta_added[$post_id]['post_type'], $meta_added[$post_id]['post_title']);
				$return['type'] = '__notice__';
			}
			else {
				$return['msg'] = sprintf(__('%s "%s" was mapped by the RAMP Meta plugin but not found in the batch.', 'ramp-mm'), $meta_added[$post_id]['post_type'], $meta_added[$post_id]['post_title']);
				$return['type'] = '__error__';
			}
		}

		return $return;
	}

	/**
	 * Runs on server
	 * Recieves a list of guids that have been mapped locally
	 * Recieves a list of meta_keys that need to be remapped (they are currently set as guids)
	 *
	 * This is always run last in the batch send process
	 **/
	function do_batch_extra_receive($extra_data, $extra_id, $batch_args) {
		if ( $extra_id == $this->extras_id ) {

			$batch_guids = (array) array_unique($batch_args['batch_posts']);
			$mapped_keys = $batch_args['meta_keys'];
			$drilldown_keys = array_keys( $batch_args['drilldown_config'] );
			$drilldown_config = $batch_args['drilldown_config'];
			// Loop through list of guids sent in the batch
			foreach ( $batch_guids as $guid ) {
				$server_post = cfd_get_post_by_guid( $guid );
				if ( $server_post ) {
					$meta = get_metadata( 'post', $server_post->ID );
					if ( is_array( $meta ) ) {
						// Loop through server post meta checking meta keys that should be mapped
						foreach ( $meta as $meta_key => $meta_values ) {
							foreach ( $meta_values as $meta_value ) {
								$meta_value = maybe_unserialize( $meta_value );
								// ! is_numeric as this should come in via a guid
								if ( in_array( $meta_key, $mapped_keys ) && ! is_numeric( $meta_value ) ) {
									if ( is_array( $meta_value ) ) {
										$storage_array = array();
										if ( in_array( $meta_key, $drilldown_keys ) ) {
											$storage_array = $meta_value;
											foreach ( $drilldown_config[ $meta_key ] as $drilldown_pattern ) {
												$drilldown_id = $this->_get_drilldown_value( $meta_value, $drilldown_pattern );
												if ( ! is_numeric( $drilldown_id ) ) {
													$mapped_server_post = cfd_get_post_by_guid( $drilldown_id );
													if ( $mapped_server_post ) {
														$storage_array = $this->_set_drilldown_value( $storage_array, $drilldown_pattern, $mapped_server_post->ID );
													}
												}
											}
										}
										else {
											foreach ($meta_value as $array_guid_value ) {
												if ( ! is_numeric( $array_guid_value ) ) {
													$mapped_server_post = cfd_get_post_by_guid( $array_guid_value );
													if ( $mapped_server_post ) {
														$storage_array[] = $mapped_server_post->ID;
													}
													else {
														$storage_array[] = $array_guid_value;
													}
												}
												else {
													$storage_array[] = $array_guid_value;
												}
											}
										}
										update_post_meta( $server_post->ID, $meta_key, $storage_array, $meta_value );
									}
									else {
										$mapped_server_post = cfd_get_post_by_guid( $meta_value );
										if ( $mapped_server_post ) {
											update_post_meta( $server_post->ID, $meta_key, $mapped_server_post->ID, $meta_value );
										}
									}
								}
							}
						}
					}
				}
			}
			do_action( 'ramp_after_do_batch_extra_receive', $extra_data, $extra_id, $batch_args, $batch_guids, $mapped_keys );
		}
	}
}
