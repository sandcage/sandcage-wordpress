<?php
/**
 * Plugin Name: SandCage
 * Plugin URI: http://wordpress.org/plugins/sandcage/
 * Description: SandCage Plugin to integrate with your SandCage account. Manage your Files and Speed up your Website. Process, Store and Deliver.
 * Version: 0.1.0.0
 * Author: SandCage
 * Author URI: https://www.sandcage.com/
 * License: GPLv2 or later
 * Text Domain: sandcage
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

The SandCage WordPress plugin is distributed under the GNU General Public License, 
Version 2, June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
St, Fifth Floor, Boston, MA 02110, USA

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

Copyright 2016 SandCage
All rights reserved.
*/

include(dirname(__FILE__) . '/sandcage-api-php/SandCage.php');

use SandCage\SandCage;

/**
 * wpSandCage is the class that handles ALL of the plugin functionality,
 * and helps us avoid name collisions
 */
class wpSandCage{
	static $instance = false;

	private $_settings;
	private $_optionsName = 'sandcage-wp';
	private $_optionsGroup = 'sandcage-wp-options';
	private $_sc_wp_id = 'sandcage';
	private $_sc_wp_version = '0.1.0.0';
	private $_sc_base = 'https://www.sandcage.com/';

	/**
	 * Our constructor. Set the _settings, register actions and filters
	 *
	 * @uses _getSettings()
	 * @uses add_action()
	 * @uses add_filter()
	 */
	private function __construct() {
		$this->_getSettings();
		add_action( 'admin_init', array( $this, 'registerOptions' ) );
		add_action( 'admin_menu', array( $this, 'adminMenu' ) );
		add_action( 'admin_notices', array( $this, 'media_lib_admin_notices' ) );
		add_action( 'wp_ajax_nopriv_sandcage_callback_listener', array( $this, 'callback_listener' ) );
		add_action( 'media_buttons', array( $this, 'get_sandcage_my_assets' ), 0 );
		add_action( 'admin_footer-upload.php', array( $this, 'media_lib_batch_options' ) );
		add_action( 'load-upload.php', array( $this, 'handle_media_library_upload' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_lib_upload_column_value' ), 0, 2 );
		add_action( 'wp_ajax_add_media_from_sandcage', array( $this, 'add_media_from_sandcage' ) );
		add_filter( 'manage_media_columns', array( $this, 'add_media_from_sandcage_btn' ) );
		add_filter( 'image_downsize', array( $this, 'sandcage_image_downsize' ), 1, 3 );
		add_filter( 'wp_get_attachment_url', array( $this, 'update_remote_sandcage_urls' ), 1, 2 );

		// TODO | add https://developer.wordpress.org/reference/hooks/media_row_actions/
	}

	/**
	 * If an instance exists, this returns it. If not, it creates one and
	 * returns it.
	 *
	 * @return wpSandCage
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Sets the _settings if they are not already set
	 *
	 * @uses   get_option()
	 * @uses   wp_parse_args()
	 */
	private function _getSettings() {
		if ( empty( $this->_settings ) ) {
			$this->_settings = get_option( $this->_optionsName );
		}
		if ( !is_array( $this->_settings ) ) {
			$this->_settings = array();
		}

		$defaults = array(
			'api_key'=>''
		);
		$this->_settings = wp_parse_args( $this->_settings, $defaults );
	}

	/**
	 * Register the sandcage-wp option settings group name
	 *
	 * @uses register_setting()
	 */
	public function registerOptions() {
		register_setting( $this->_optionsGroup, $this->_optionsName );
	}

	/**
	 * Add the admin menu and submenu options
	 *
	 * @uses add_menu_page()
	 * @uses add_submenu_page()
	 * @uses plugins_url()
	 * @uses plugin_dir_url()
	 * @uses wp_enqueue_style()
	 */
	public function adminMenu() {
		$my_assets_item = $this->_sc_wp_id . '/my_assets.php';
		if ( empty( $this->_settings['api_key'] ) ) {
			add_menu_page( __( 'SandCage Menu', 'sandcage-wp' ), __( 'SandCage', 'sandcage-wp' ), 'manage_options', 'SandCage', array( $this, 'options' ), plugins_url( 'images/favico_16.png', __FILE__ ) );
		} else {
			add_menu_page( __( 'SandCage Menu', 'sandcage-wp' ), __( 'SandCage', 'sandcage-wp' ), 'manage_options', $my_assets_item, null, plugins_url( 'images/favico_16.png', __FILE__ ) );
		}
		add_submenu_page( $my_assets_item, __( 'SandCage Media Library', 'sandcage-wp' ), __( 'Media Library', 'sandcage-wp' ), 'publish_pages', $my_assets_item );
		add_submenu_page( $my_assets_item, __( 'SandCage Settings', 'sandcage-wp' ), __( 'Settings', 'sandcage-wp' ), 'manage_options', 'SandCage', array( $this, 'options' ));
		wp_enqueue_style( 'sandcage-wp', plugin_dir_url( __FILE__ ) . 'css/styles.css', array(), $this->_sc_wp_version );
		wp_enqueue_script( 'sandcage-js', plugins_url( '/js/sandcage.js?v=' . $this->_sc_wp_version . '&t=media_lib', __FILE__ ) );
	}

	/**
	 * Print and handle the SandCage options
	 *
	 * @uses wp_enqueue_script()
	 * @uses _show_help()
	 * @uses update_option()
	 * @uses settings_fields()
	 * @uses add_query_arg()
	 * @uses admin_url()
	 */
	public function options() {
		wp_enqueue_script( 'jquery' );
		$success = $error = false;
		if ( isset( $_POST['sc_hidden'] ) && ( intval( $_POST['sc_hidden'] ) == 1 ) ) {
			if ( isset( $_POST[$this->_optionsName . '_api_key'] ) && !empty( $_POST[$this->_optionsName . '_api_key'] ) ) {
				$this->_settings['api_key'] = sanitize_text_field( $_POST[$this->_optionsName . '_api_key'] );
				update_option( $this->_optionsName, $this->_settings );
				$success = true;
			} else {
				$error = true;
			}
		}

		if ( $success ) {
			?>
			<div class="updated"><p><strong><?php _e( 'Options saved.' ); ?></strong></p></div>
			<?php
		}
		if ( $error ) {
			?>
			<div class="error"><p><strong><?php _e( 'The options were not saved.' ); ?></strong></p></div>
			<?php
		}
		?>
		<script type="text/javascript">
		jQuery(function($){
			try {
				$('#wp_sandcage span.help').off('click');
				$('#wp_sandcage span.help').on('click', function(){
					$(this).next().toggle();
				});
			}
			catch(e) {
				$('#wp_sandcage span.help').unbind('click');
				$('#wp_sandcage span.help').bind('click', function(){
					$(this).next().toggle();
				});
			}
		});
		</script>
		<div class="wrap">
			<p><img src="<?php echo plugins_url( 'images/logo.png', __FILE__ );?>"></p>
			<h2><?php _e( 'SandCage Options', 'sandcage-wp' ); ?></h2>
			<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] ); ?>" method="post" id="wp_sandcage">
				<?php settings_fields( $this->_optionsGroup ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_api_key">
								<?php _e( 'SandCage API Key:', 'sandcage-wp' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>_api_key" value="<?php echo esc_attr( $this->_settings['api_key'] ); ?>" id="<?php echo $this->_optionsName; ?>_api_key" class="regular-text code" required />
							<?php $this->_show_help(); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e( 'SandCage Callback Endpoint URL:', 'sandcage-wp' ); ?>
						</th>
						<td>
							<?php echo add_query_arg( array( 'action'=>'sandcage_callback_listener' ), admin_url( 'admin-ajax.php' ) ); ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="hidden" name="sc_hidden" value="1">
					<input type="submit" name="Submit" value="<?php _e( 'Update Options &raquo;', 'sandcage-wp' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Add the option to batch upload files to SandCage
	 * Return null on missing api_key
	 */ 
	public function media_lib_batch_options() {
		if ( empty( $this->_settings['api_key'] ) ) {
			return null;
		}
		/*
		/ TODO | Add the ability to upload multiple files to SandCage in a single batch operation
		?>
		<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery("select[name='action'],select[name='action2']").each(function() {
				jQuery('<option>').val('batch_upload_to_sandcage').text('<?php _e( 'Upload to SandCage', 'sandcage-wp' ); ?>').appendTo(this);
			});
		});
		</script>
		<?php
		*/
	}

	/**
	 * Remove all the width and height references of the img tag 
	 * will not add width and height attributes to the image sent to the editor.
	 * 
	 * @param  bool Optional, default is 'false'. No height and width references.
	 * @param  int $wp_attachment_id Attachment ID for image.
	 * @param  array|string $size Optional, default is 'thumbnail'. Size of image, either array or string.
	 * @uses   wp_attachment_is_image()
	 * @uses   wp_get_attachment_url()
	 * @uses   wp_basename()
	 * @uses   image_get_intermediate_size()
	 * @uses   wp_get_attachment_thumb_file()
	 * @return bool|array False on failure, array on success.
	 */
	public function sandcage_image_downsize( $value = false, $wp_attachment_id, $size='thumbnail' ) {
		if ( !wp_attachment_is_image( $wp_attachment_id ) ) {
			return false;
		}

		$img_url = wp_get_attachment_url( $wp_attachment_id );
		$is_intermediate = false;
		$img_url_basename = wp_basename( $img_url );

		// try for a new style intermediate size
		if ( $intermediate = image_get_intermediate_size( $wp_attachment_id, $size ) ) {
			$img_url = str_replace( $img_url_basename, $intermediate['file'], $img_url );
			$is_intermediate = true;
		} elseif ( $size == 'thumbnail' ) {
			// Fall back to the old thumbnail
			if ( ( $thumb_file = wp_get_attachment_thumb_file( $wp_attachment_id ) ) && $info = getimagesize( $thumb_file ) ) {
				$img_url = str_replace( $img_url_basename, wp_basename( $thumb_file ), $img_url );
				$is_intermediate = true;
			}
		}

		if ( preg_match( '#(https?://[a-zA-Z0-9\-\.\/_]+)$#', $img_url, $matches ) ) {
			$img_url = $matches[0];
		}

		// We have the actual image size, but might need to further constrain it if content_width is narrower
		if ( $img_url) {
			return array( $img_url, 0, 0, $is_intermediate );
		}
		return false;
	}

	/**
	 * Return a cleaned up attachment URL.
	 *
	 * @param  string $source_url string The intended attachment URL.
	 * @param  int $wp_attachment_id Attachment ID.
	 * @uses   wp_get_attachment_metadata()
	 * @uses   preg_match()
	 * @return string An attachment URL. If the attachment was from SandCage, then it will have been cleaned up.
	 */
	public function update_remote_sandcage_urls( $source_url, $wp_attachment_id ) {
		$attachment_metadata = wp_get_attachment_metadata( $wp_attachment_id );
		if ( 
			is_array( $attachment_metadata ) && 
			isset( $attachment_metadata["from_sandcage"] ) && 
			( !isset( $attachment_metadata["sandcage_pending"] ) || !$attachment_metadata["sandcage_pending"] ) && 
			preg_match( '#^.*?/(https?://.*)#', $source_url, $matches ) 
			) {
			return $matches[0];
		}
		return $source_url;
	}

	/**
	 * Add the attachment.
	 * Print a JSON encoded array with error message on failure, or attachment info on success.
	 * 
	 * @uses   wp_get_attachment_metadata()
	 * @uses   current_user_can()
	 * @uses   wp_insert_sandcage_attachment()
	 * @uses   get_image_sizes()
	 */
	public function add_media_from_sandcage() {
		$result = array();
		if ( 
			empty( $_POST )
			) {
			 $result = array(
				'status'=>'error', 
				'message'=>'Sorry, we could not verify the request.'
				);
		} else {
			if ( $_POST['ids'] ) {
				if ( empty( $_POST['ids'] ) ) {
					$result = array(
						'status'=>'error', 
						'message'=>'No ids referenced.'
						);
				} else {
					$ids = explode( ',', $_POST['ids'] );

					if ( count( $ids ) > 0 ) {
						$result = array(
							'status'=>'success', 
							'ids'=>array()
							);
						foreach( $ids as $wp_attachment_id ) {
							$wp_attachment_id = intval( $wp_attachment_id );
							if ( $wp_attachment_id != 0 ) {

								$this_attachment_metadata = wp_get_attachment_metadata( $wp_attachment_id );
								$this_attachment_result = array("id"=>$wp_attachment_id, "pending"=>true);
								if ( isset( $this_attachment_metadata['sandcage_pending'] )) {
									$this_attachment_result['pending'] = $this_attachment_metadata['sandcage_pending'];
								}
								array_push($result['ids'], $this_attachment_result);
							}
						}
					}
				}
			} else {
				$wp_post_id = $wp_attachment_id = $sandcage_source_url = $name = '';
				$sandcage_file_token = $sandcage_request_id = null;
				if ( $_POST['src'] ) {
					$sandcage_source_url = sanitize_text_field( $_POST['src'] );
				}
				if ( $_POST['sandcage_request_id'] ) {
					$sandcage_request_id = sanitize_text_field( $_POST['sandcage_request_id'] );
				}
				if ( $_POST['name'] ) {
					$name = sanitize_text_field( $_POST['name'] );
				}
				if ( $_POST['sandcage_file_token'] ) {
					$sandcage_file_token = array(
						array(
							'reference_id'=>'wp|' . $wp_attachment_id . '|' . $name,
							'file_token'=>sanitize_text_field( $_POST['sandcage_file_token'] ),
							'size'=>''
							)
						);
				}
				if ( $_POST['attachment_id'] ) {
					$wp_attachment_id = intval( $_POST['attachment_id'] );
				}
				if ( $_POST['post_id'] ) {
					$wp_post_id = intval( $_POST['post_id'] );
				}
				if ( empty( $sandcage_source_url ) ) {
					$result = array(
						'status'=>'error', 
						'message'=>'The source URL of the file was not retrieved. Please reload the page and try again.'
						);
				} else if ( empty( $wp_post_id ) ) {
					$result = array(
						'status'=>'error', 
						'message'=>'Sorry, we could not verify the file addition.'
						);
				} else if ( 
					!empty( $wp_attachment_id ) && 
					!current_user_can( 'edit_post', $wp_attachment_id ) 
					) {
					$result = array(
						'status'=>'error', 
						'message'=>'You do not have permission to perform this action.'
						);
				} else if ( 
					!empty( $wp_post_id ) && 
					!current_user_can( 'edit_post', $wp_post_id ) 
					) {
					$result = array(
						'status'=>'error', 
						'message'=>'You do not have permission to perform this action.'
						);
				} else {
					$width = intval( $_POST['w'] );
					$height = intval( $_POST['h'] );
					$asset_info = array(
						'src_url'=>$sandcage_source_url, 
						'width'=>$_POST['w'], 
						'height'=>$_POST['h'], 
						'mime'=>$_POST['mime'], 
						'name'=>$name
						);
					$result = array(
						'status'=>'success', 
						'attachment_id'=>$this->wp_insert_sandcage_attachment( $asset_info, $wp_post_id, $wp_attachment_id, null, $sandcage_file_token, $sandcage_request_id ),
						'post_id'=>$wp_post_id,
						'thumb_dimensions'=>$this->get_image_sizes()
						);
				}
			}
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $result );
		die();
	}

	/**
	 * Handle the attachment data.
	 * 
	 * @param  array $asset_info Array of attachment information.
	 * @param  int $wp_post_id Post ID.
	 * @param  int $wp_attachment_id Attachment ID.
	 * @param  WP_Post $original_attachment Attachment object.
	 * @param  string $sandcage_file_token Optional, default is 'null'. The file token of the file on SandCage, returned from the scheduled tasks response.
	 * @param  string $sandcage_request_id Optional, default is 'null'. The SandCage request id, returned from the scheduled tasks response.
	 * @param  bool $pending Optional, default is 'false'. A boolean to indicate if the file is pending the callback from SandCage.
	 * @uses   wp_get_attachment_metadata()
	 * @uses   wp_insert_attachment()
	 * @uses   is_wp_error()
	 * @uses   wp_update_attachment_metadata()
	 * @return int Returns the resulting post ID (int) on success or 0 (int) on failure.
	 */
	public function wp_insert_sandcage_attachment( $asset_info, $wp_post_id, $wp_attachment_id, $original_attachment, $sandcage_file_token=null, $sandcage_request_id=null, $pending=false ) {
		$title = '';
		if ( isset( $asset_info['name'] ) ) {
			$title = $asset_info['name'];
		}
		$meta = null;
		if ( isset( $wp_attachment_id ) ) {
			$this_metadata = wp_get_attachment_metadata( $wp_attachment_id );
			$meta = $this_metadata['image_meta'];
			if ( ( $title == '' ) && isset( $original_attachment ) && isset( $original_attachment->post_title ) ) {
				$title = $original_attachment->post_title;
			}
		}

		$attachment = array(
			'guid'=>$asset_info['src_url'],
			'post_mime_type'=>'',
			'post_title'=>$title,
			'post_content'=>'',
			'post_parent'=>$wp_post_id
			);
		if ( isset( $asset_info['mime'] ) ) {
			$attachment['post_mime_type'] = $asset_info['mime'];
		}
		if ( $wp_attachment_id && ( (int)$wp_attachment_id == $wp_attachment_id ) ) {
			$attachment['ID'] = (int)$wp_attachment_id;
		}

		$this_attachment_id = wp_insert_attachment( $attachment, $asset_info['src_url'], $wp_post_id );
		if ( !is_wp_error( $this_attachment_id ) ) {
			if ( !$meta ) {
				$meta = array(
					'aperture'=>'0',
					'credit'=>'',
					'camera'=>'',
					'caption'=>'',
					'created_timestamp'=>'0',
					'copyright'=>'',  
					'focal_length'=>'0',
					'iso'=>'0',
					'shutter_speed'=>'0',
					'title'=>$title
					);
			}
			$metadata = array();
			if ( isset( $this_metadata ) ) {
				$metadata = $this_metadata;
			}
			$metadata['image_meta'] = $meta;
			$metadata['width'] = $asset_info['width'];
			$metadata['height'] = $asset_info['height'];
			$metadata['from_sandcage'] = true;
			$metadata['sandcage_request_id'] = $sandcage_request_id;
			$metadata['sandcage_file_token'] = $sandcage_file_token;
			$metadata['sandcage_pending'] = $pending;
			wp_update_attachment_metadata( $this_attachment_id,  $metadata );
		}
		return $this_attachment_id;
	}

	/**
	 * Update and return the media library table columns.
	 * 
	 * @param  array $cols The media library table columns.
	 * @uses   wp_nonce_url()
	 * @uses   admin_url()
	 * @return array The media library table columns with a column for SandCage.
	 */
	public function add_media_from_sandcage_btn( $cols ) {
		$admin_ajax_url = wp_nonce_url( admin_url( 'admin-ajax.php' ), 'add_media_from_sandcage', '_wpnonce_sandcage' );
		$cols['media_url'] = 'SandCage<span id="sandcage-media-library-list" data-admin-ajax="' . $admin_ajax_url . '"></span>';
		return $cols;
	}

	/**
	 * Print the feedback regarding the status of each of the attachments
	 * 
	 * @param  string $table_column Name of the custom column.
	 * @param  int $wp_attachment_id Attachment ID.
	 * @uses   plugins_url()
	 * @uses   wp_get_attachment_metadata()
	 * @uses   wp_nonce_url()
	 */
	public function media_lib_upload_column_value( $table_column, $wp_attachment_id ) {
		if ( $table_column == 'media_url' ) {
			$attachment_metadata = wp_get_attachment_metadata( $wp_attachment_id );
			if ( is_array( $attachment_metadata ) && $attachment_metadata["from_sandcage"] ) {
				$payload = '<div id="attachment-' . $wp_attachment_id . '">' .
					'<img src="' . plugins_url( 'images/favico_16.png', __FILE__ ) . '" data-attachment-id="' . $wp_attachment_id . '" class="uploaded-to-sandcage';
				if ( isset( $attachment_metadata["sandcage_pending"] ) && !$attachment_metadata["sandcage_pending"] ) {
					$payload .= '" width="16" height="16"/><span>' . __( 'On SandCage', 'sandcage-wp' );
				} else {
					$payload .= ' pending-processing-on-sandcage" width="16" height="16"/><span>' . __( 'Pending processing on SandCage', 'sandcage-wp' );
				}
				$payload .= '</span></div>';
			} else {
				$payload = '<div>' . 
						'<a href="' . wp_nonce_url( '?', 'bulk-media' ) . '&upload_to_sandcage=' . $wp_attachment_id . '" class="button upload-single-asset-to-sandcage">' . 
							'<img src="' . plugins_url( 'images/favico_16.png', __FILE__ ) . '" width="16" height="16"/> ' . 
							__( 'Upload to SandCage', 'sandcage-wp' ) . 
						'</a>' .
					'</div>';     
			}
			echo $payload;
		}
	}
 
	/**
	 * Print the SandCage "Add Media" button
	 * 
	 * @uses   get_sandcage_my_assets_iframe()
	 * @uses   wp_enqueue_script()
	 * @uses   plugins_url()
	 * @uses   wp_nonce_url()
	 * @uses   admin_url()
	 */
	public function get_sandcage_my_assets() {
		$my_assets_iframe = $this->get_sandcage_my_assets_iframe( 'wp_post' );
		if ( !$my_assets_iframe ) {
			return '';
		}
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'sandcage-js', plugins_url( '/js/sandcage.js?v=' . $this->_sc_wp_version, __FILE__ ) );
		$admin_ajax_url = wp_nonce_url( admin_url( 'admin-ajax.php' ), 'add_media_from_sandcage', '_wpnonce_sandcage' );

		echo '<a href="javascript:void(0);" class="button add_media_from_sandcage" id="add_media_from_sandcage" ' .
			'title="' . __( 'Add Media from SandCage', 'sandcage-wp' ) . '">' .
				'<img src="' . plugins_url( 'images/logo60x40.png', __FILE__ ) . '"> ' . __( 'Add Media', 'sandcage-wp' ) .
			'</a>' .
			'<span class="sandcage_message"></span>' .
			'<span id="sandcage-conf" data-src="' . $my_assets_iframe . '" data-admin-ajax="' . $admin_ajax_url . '"></span>';
	}

	/**
	 * Add one or more files to the scheduled tasks queue on SandCage
	 * 
	 * @global $pagenow
	 * @uses   _get_list_table()
	 * @uses   get_pagenum()
	 * @uses   current_action()
	 * @uses   wp_get_referer()
	 * @uses   upload_assets_to_sandcage()
	 * @uses   sandcage_media_lib_feedback()
	 */
	public function handle_media_library_upload() {
		$wp_list_table = _get_list_table( 'WP_Media_List_Table' );
		// TODO | Add https://developer.wordpress.org/reference/classes/wp_media_list_table/column_title/

		$pagenum = $wp_list_table->get_pagenum();
		$current_action = $wp_list_table->current_action();
		global $pagenow;
		$referer = wp_get_referer();

		$errors = array();
		if ( $pagenow == 'upload.php' && isset( $_REQUEST['upload_to_sandcage'] ) && !empty( $_REQUEST['upload_to_sandcage'] ) ) {
			if ( empty( $this->_settings['api_key'] ) ) {
				echo "Update your SandCage settings to upload files to SandCage";
				exit();
			}

			$response = json_decode( $this->upload_assets_to_sandcage( $_REQUEST['upload_to_sandcage'] ), true );
			if ( $response['status'] != 'success') {
				$errors = $response['error_msg'];
				$successes = 0; 
			} else {
				$errors = array();
				$successes = 1; 
			}
			
			$this->sandcage_media_lib_feedback( $errors, $successes, $referer );
		}
		if ( $current_action == 'batch_upload_to_sandcage' ) {
			if ( empty( $this->_settings['api_key'] ) ) {
				echo "Update your SandCage settings to upload files to SandCage";
				exit();
			}
			// TODO | Pending extension of the plugin to handle batch file uploads to SandCage

			echo "This feature will be added with a future version on the SandCage plugin";
			exit();
		}
	}

	/**
	 * Used to direct the user to the media library page with the notifications appended via GET
	 * 
	 * @param  array $errors An array of errors
	 * @param  int $successes The amount of successfully handled files
	 * @param  boolean|string $referer False on failure. Referer URL on success.
	 * @uses   add_query_arg()
	 * @uses   wp_redirect()
	 */
	public function sandcage_media_lib_feedback( $errors, $successes, $referer ) {
		$image = $successes == 1 ? 'image was' : 'images were';        
		$message = "$successes $image added to the upload queue on SandCage.";

		$has_errors = false;

		if ( !empty( $errors ) ) {
			$has_errors = true;
			$errors_count = count( $errors );
			$message = "$message $errors_count failed because:";
			foreach( $errors as $error ) {
				$message .= '<br/>' . $error['short'];
			}        
		}
		wp_redirect( add_query_arg( array( "sc_msg"=>urlencode( $message ), "sc_error"=>(int)$has_errors ), $referer ) );
		exit();    
	}

	/**
	 * Add the file to the scheduled tasks queue on SandCage
	 * 
	 * @param  int $wp_attachment_id Attachment ID.
	 * @uses   wp_get_attachment_metadata()
	 * @uses   get_attached_file()
	 * @uses   get_post()
	 * @uses   wp_get_attachment_url()
	 * @uses   get_image_sizes()
	 * @uses   add_query_arg()
	 * @uses   admin_url()
	 * @uses   scheduleFiles()
	 * @uses   getHttpStatus()
	 * @uses   getResponse()
	 * @uses   wp_insert_sandcage_attachment()
	 * @return null|array Null or array with error message on failure, array on success.
	 */
	public function upload_assets_to_sandcage( $wp_attachment_id ) {
		$wp_attachment_id = intval( $wp_attachment_id );
		if ( !$wp_attachment_id ) {
			return array(
				'status'=>'error',
				'short'=>'Not sure which attachment you are trying to work with. Please reload the page and try again.'
				);
		}

		$this_metadata = wp_get_attachment_metadata( $wp_attachment_id );

		$src_filename_pieces = explode( '.', basename( get_attached_file( $wp_attachment_id ) ) );
		$src_file_name = $src_filename_pieces[0];

		if ( is_array( $this_metadata ) && isset( $this_metadata["from_sandcage"] ) ) {
			// TODO | Split the feedback depending on if the file was returned as part of the callback
			return array(
				'status'=>'error',
				'short'=>'Already uploaded to SandCage.'
				);
		}
		$file_name = $mime = "";
		$original_attachment = null;
		if ( empty( $this_metadata ) ) {
			$original_attachment = get_post( $wp_attachment_id );
			$file_url = $original_attachment->guid;
			// $file_name = $original_attachment->post_title;
			if ( empty( $file_url ) ) {
				return array(
					'status'=>'error',
					'short'=>'Unsupported file type.'
					);
			}
		} else {
			$file_url = wp_get_attachment_url( $wp_attachment_id );
		}

		if ( !preg_match( '/^https?:/i', $file_url ) ) {
			return array(
				'status'=>'error',
				'short'=>'The file URL is not accessible.'
				);
		} else {
			$size_count = 0;
			if ( isset( $this_metadata['sizes'] ) && ( count( $this_metadata['sizes'] ) > 0 ) ) {
				$size_count = count( $this_metadata['sizes'] );
				$default_crop = false;
				$size_options = $this->get_image_sizes();

				foreach ( $this_metadata['sizes'] as $this_size_key=>$this_size_value ) {
					if ( !isset( $this_size_value['crop'] ) ) {
						$target_size = $size_options[$this_size_key];
						$this_metadata['sizes'][$this_size_key]['crop'] = $target_size['crop'];
					}
					if ( ( $mime == "" ) && isset( $this_size_value['mime-type'] ) ) {
						$mime = $this_size_value['mime-type'];
						break;
					}
				}
			}

			// TODO | Extend the plugin to check that the amount of thumbnails to be generated are not more than what the clients plan allows.

			$sandcage = new SandCage( $this->_settings['api_key'] );

			$payload = array(
				"jobs"=>array(
					array(
						"url"=>$file_url,
						"tasks"=>array(
							array(
								"reference_id"=>'wp|' . $wp_attachment_id . '|' . $src_file_name,
								"actions"=>"save"
							)
						)
					)
				)
			);
			if ( $size_count > 0 ) {
				foreach ( $this_metadata['sizes'] as $this_size_key=>$this_size_value ) {
					if ( $this_size_value['crop'] ) {
						$this_task = array(
							"reference_id"=>'wp|' . $wp_attachment_id . '|' . $src_file_name . '|' . $this_size_key,
							"actions"=>"cover",
							"width"=>$this_size_value['width'],
							"height"=>$this_size_value['height']
							);
					} else {
						$this_task = array(
							"reference_id"=>'wp|' . $wp_attachment_id . '|' . $src_file_name . '|' . $this_size_key,
							"actions"=>"resize",
							"width"=>$this_size_value['width'],
							"height"=>$this_size_value['height']
							);
					}
					array_push( $payload['jobs'][0]['tasks'], $this_task );
				}
			}
			$sandcage->call( 'schedule-tasks', $payload, add_query_arg( array( 'action'=>'sandcage_callback_listener' ), admin_url( 'admin-ajax.php' ) ) );
			$get_info_status = $sandcage->status;
			$get_info_response = $sandcage->response;

			if ( $get_info_status['http_code'] == 200 ) {
				$response = json_decode( $get_info_response, true );
				if ( ( $response['status'] == 'success' ) && isset( $response['tasks'] ) ) {

					if ( count( $response['tasks'] ) > 0 ) {
						$task_count = count( $response['tasks'] );

						// TODO | Loop over the response payload, match the file version and assign the file_token to the respective attachment

						$file_tokens = array();
						foreach ( $response['tasks'] as $task_response ) {
							$is_source_image = true;
							$new_file_token_generated = false;
							if ( isset( $this_metadata['sizes'] ) && ( count( $this_metadata['sizes'] ) > 0) ) {
								foreach ( $this_metadata['sizes'] as $this_size_key=>$this_size_value ) {
									if ( $task_response['reference_id'] == 'wp|' . $wp_attachment_id . '|' . $src_file_name . '|' . $this_size_key ) {
										$new_file_token = array(
											'reference_id'=>'wp|' . $wp_attachment_id . '|' . $src_file_name . '|' . $this_size_key,
											'file_token'=>$task_response['file_token'],
											'size'=>$this_size_key
											);
										$is_source_image = false;
										$new_file_token_generated = true;
										break;
									}
								}
							}
							if ( $is_source_image ) {
								$new_file_token = array(
									'reference_id'=>'wp|' . $wp_attachment_id . '|' . $src_file_name,
									'file_token'=>$task_response['file_token'],
									'size'=>''
									);
								$new_file_token_generated = true;
							}
							if ( $new_file_token_generated ) {
								array_push( $file_tokens, $new_file_token );
							}
								
						}

						$asset_info = array(
							'src_url'=>$file_url, 
							'width'=>$this_metadata['width'], 
							'height'=>$this_metadata['height'],
							'name'=>$src_file_name,
							'mime'=>$mime
							);

						$this->wp_insert_sandcage_attachment( $asset_info, null, $wp_attachment_id, $original_attachment, $file_tokens, $response['request_id'], true );
					}

				}

				return $get_info_response;
			} else {
				return array(
					'status'=>'error',
					'short'=>'An error occurred.'
					);
			}

		}
		
		return NULL;
	}

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global $_wp_additional_image_sizes
	 * @uses   get_intermediate_image_sizes()
	 * @uses   get_option()
	 * @uses   apply_filters()
	 * @return array $sizes Data for all currently-registered image sizes.
	 */
	public function get_image_sizes() {
		if ( isset( $this->sizes ) ) {
			return $this->sizes;
		}
		global $_wp_additional_image_sizes;

		$sizes = array();
		$get_intermediate_image_sizes = get_intermediate_image_sizes();

		foreach ( $get_intermediate_image_sizes as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[$_size]['width']  = get_option( "{$_size}_size_w" );
				$sizes[$_size]['height'] = get_option( "{$_size}_size_h" );
				$sizes[$_size]['crop']   = (bool)get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[$_size] ) ) {
				$sizes[$_size] = array(
					'width'  => $_wp_additional_image_sizes[$_size]['width'],
					'height' => $_wp_additional_image_sizes[$_size]['height'],
					'crop'   => $_wp_additional_image_sizes[$_size]['crop'],
				);
			}
		}

		$this->sizes = apply_filters( 'intermediate_image_sizes_advanced', $sizes );
		return $this->sizes;
	}

	/**
	 * Print the notices from SandCage on the media library
	 *
	 * @global $post_type
	 * @global $pagenow
	 */
	public function media_lib_admin_notices() {
		global $post_type, $pagenow;
		if ( $post_type == 'attachment' && $pagenow == 'upload.php' && isset( $_REQUEST['sc_msg'] ) && !empty( $_REQUEST['sc_msg'] ) ) {
			echo '<div class="';
			if ( !isset( $_REQUEST['sc_error'] ) || ( $_REQUEST['sc_error'] == '0' ) ) {
				echo 'updated';
			} else {
				echo 'error';
			}
			echo ' notice is-dismissible"><p>' . esc_html( $_REQUEST['sc_msg'] ) . '</p></div>';
		}
		if ( $pagenow == 'upload.php' && isset( $_REQUEST['upload_to_sandcage'] ) && !empty( $_REQUEST['upload_to_sandcage'] ) ) {
			echo '<div class="error notice is-dismissible"><p>Sorry, this file format is not supported.</p></div>';
		}
	}

	/**
	 * Get the URL of the embeddable my assets page or the same URL loaded in an iframe.
	 *
	 * @param  string $type Optional, default is 'wp_post'. Indicates where in WP the iframe is called from.
	 * @uses   http_build_query()
	 * @uses   wp_enqueue_script()
	 * @uses   plugins_url()
	 * @return string The URL of the embeddable my assets page if in a post, otherwise the same URL loaded in an iframe.
	 */
	public function get_sandcage_my_assets_iframe( $type = 'wp_post' ) {
		if ( empty( $this->_settings['api_key'] ) ) {
			return null;
		}
		$params = array(
			'key'=>$this->_settings['api_key'], 
			'wp_type'=>$type, 
			'wp_plugin_version'=>$this->_sc_wp_version 
			);
		$query = http_build_query( $params );
		if ( $type == 'wp_post' ) {
			return $this->_sc_base . 'embedded_panel/my_assets_embedded_wp?' . $query;
		} else if ( $type == 'wp_my_assets' ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'sandcage-js', plugins_url( '/js/sandcage.js?v=' . $this->_sc_wp_version, __FILE__ ) );
		}
		return '<span id="sandcage-conf"></span>' .
			'<iframe name="sandcage-asset-frame" id="sandcage-asset-frame" src="' . $this->_sc_base . 'embedded_panel/my_assets_embedded_wp?' . $query . '" style="width:100%"></iframe>';
	}

	/**
	 * Prints the plugin usage tips/requirement
	 *
	 * @global $wp_version
	 * @uses   get_home_url()
	 */
	private function _show_help() {
		global $wp_version;

		echo '<span class="help" title="' . __( 'Click for help', 'sandcage-wp' ) . '">' . __( 'Help', 'sandcage-wp' ) . '</span>';
		?>
		<ol class="hide-if-js">
			<li>
				<?php echo sprintf( __( 'To get your SandCage API Key, <a href="%s" target="_blank">sign up for a free account</a>.', 'sandcage-wp' ), $this->_sc_base . 'register?ref=' . urlencode( get_home_url() ) . '&ref_type=wp&ref_type_version=' . $wp_version ); ?>
			</li>
			<li>
				<?php echo sprintf( __( 'In your SandCage account go to "Account" -> "API Key". If you are already logged in click <a href="%s" target="_blank">here</a>.', 'sandcage-wp' ), $this->_sc_base . 'panel/api_key?ref=' . urlencode( get_home_url() ) . '&ref_type=wp&ref_type_version=' . $wp_version ); ?>
			</li>
		</ol>
		<?php
	}

	/**
	 * Handles the callback from a scheduled file processing or deletion
	 * The respective file data is updated or an error is printed
	 * Print JSON encoded array with error message on failure, or attachment metadata on success.
	 */
	public function callback_listener() {
		$arr = array();
		$post_input = file_get_contents( 'php://input' );

		if ( !empty($post_input) ) {

			if ( !isset( $post_input[0] ) ) {
				$arr = array('Sorry, but there was not payload provided.');
			} else {

				$payload = trim( urldecode( $post_input ) );
				$payload_data = json_decode( $payload, true );

				$payload_data = stripslashes_deep( $payload_data );

				if ( !isset( $payload_data['tasks'] ) || ( count( $payload_data['tasks'] ) == 0 ) ) {
					$arr = array('no tasks');
				} else {
					foreach ( $payload_data['tasks'] as $this_task_key=>$this_task_value ) {
						// TODO | Check if the status was success and that the action was not delete
						if ( isset( $this_task_value['reference_id'] ) && !empty( $this_task_value['reference_id'] ) ) {
							$reference_pieces = explode( '|', $this_task_value['reference_id'] );

							if ( ( $reference_pieces[0] == 'wp' ) && ( (int)$reference_pieces[1] == $reference_pieces[1] ) ) {
								$wp_attachment_id = $reference_pieces[1];
								$this_metadata = wp_get_attachment_metadata( $wp_attachment_id );

								if ( isset( $this_metadata['sandcage_file_token'] ) && ( count( $this_metadata['sandcage_file_token'] ) > 0 ) ) {

									$original_attachment = get_post( $wp_attachment_id );
									$original_file_url = $sandcage_file_url = '';
									if ( isset( $this_task_value['cdn_url'] ) && !empty( $this_task_value['cdn_url'] ) ) {
										$sandcage_file_url = $this_task_value['cdn_url'];
									}
									if ( isset( $original_attachment ) && isset( $original_attachment->guid ) ) {
										$original_file_url = $original_attachment->guid;
									}
									if ( empty( $original_file_url ) ) {
										$original_file_url = wp_get_attachment_url( $wp_attachment_id );
									}

									$size_count = 0;
									if ( isset( $this_metadata['sizes'] ) ) {
										$size_count = count( $this_metadata['sizes'] );
									}

									$this_mime = '';
									if ( isset( $this_task_value['mime'] ) && !empty( $this_task_value['mime'] ) ) {
										$this_mime = $this_task_value['mime'];
									} else {
										if ( $size_count > 0 ) {

											foreach ( $this_metadata['sizes'] as $this_size_key=>$this_size_value ) {
												if ( isset( $this_size_value['mime-type'] ) ) {
													$this_mime = $this_size_value['mime-type'];
													break;
												}
											}
										}
									}

									$sandcage_request_id = null;
									if ( isset( $this_task_value['request_id'] ) && !empty( $this_task_value['request_id'] ) ) {
										$sandcage_request_id = $this_task_value['request_id'];
									}

									// TODO | Compare the store request_id with the one returned from the callback

									$width = null;
									if ( isset( $this_task_value['width'] ) && !empty( $this_task_value['width'] ) ) {
										$width = $this_task_value['width'];
									} else if ( isset( $this_metadata['width'] ) ) {
										$width = $this_metadata['width'];
									}

									$height = null;
									if ( isset( $this_task_value['height'] ) && !empty( $this_task_value['height'] ) ) {
										$height = $this_task_value['height'];
									} else if ( isset( $this_metadata['height'] ) ) {
										$height = $this_metadata['height'];
									}

									// TODO | Handle complete and partial errors

									$meta = null;
									$title = '';
									if ( isset( $this_metadata ) && isset( $this_metadata['image_meta'] ) ) {
										$meta = $this_metadata['image_meta'];
										if ( isset( $meta['title'] ) ) {
											$title = $meta['title'];
										}
									}
									if ( ( $title == '' ) && isset( $original_attachment ) && isset( $original_attachment->post_title ) ) {
										$title = $original_attachment->post_title;
									}

									$final_file_url = $original_file_url;

									// the reference id contained image size indication
									if ( count( $reference_pieces ) > 3 ) { 
										if ( ( $size_count > 0 ) && !empty( $sandcage_file_url ) ) {
											foreach ( $this_metadata['sizes'] as $this_size_key=>$this_size_value ) {
												if ( ( $reference_pieces[3] == $this_size_key ) ) {
													$this_metadata['sizes'][$this_size_key]['file'] = $sandcage_file_url;
													break;
												}
											}
										}
									} else {
										$final_file_url = $sandcage_file_url;
										$this_metadata['file'] = $sandcage_file_url;
									}

									$wp_post_id = null;
									$attachment = array(
										'guid'=>$final_file_url,
										'post_mime_type'=>$this_mime,
										'post_title'=>$title,
										'post_content'=>'',
										'ID'=>(int)$wp_attachment_id
										);
									if ( isset( $original_attachment ) && isset( $original_attachment->post_parent ) ) {
										$wp_post_id = $original_attachment->post_parent;
									}
									$attachment['post_parent'] = $wp_post_id;

									$this_attachment_id = wp_insert_attachment( $attachment, $final_file_url, $wp_post_id );
									if ( !is_wp_error( $this_attachment_id ) ) {
										if ( !$meta ) {
											$meta = array(
												'aperture'=>'0',
												'credit'=>'',
												'camera'=>'',
												'caption'=>'',
												'created_timestamp'=>'0',
												'copyright'=>'',  
												'focal_length'=>'0',
												'iso'=>'0',
												'shutter_speed'=>'0',
												'title'=>$title
												);
										}
										$metadata = $this_metadata;
										$metadata['image_meta'] = $meta;
										// $metadata['from_sandcage'] = true;
										$metadata['sandcage_pending'] = false; // TODO | Should only be set to false when all the files/thumbs have been updated
										wp_update_attachment_metadata( $this_attachment_id,  $metadata );
										$arr= $metadata;
									}
								}
							}
						}
					}
				}
			}
		} else {
			$arr = array('Sorry, we could not verify this request.');
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $arr );

		die();
	}
}

// Instantiate our class
$wpSandCage = wpSandCage::getInstance();
