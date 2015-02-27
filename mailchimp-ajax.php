<?php
/*
Plugin Name: MailChimp AJAX
Description: Handles MailChimp AJAX requests. You are responsible for setting up the HTML form and nonce, making the request, and handling the AJAX response.
Author: Josh Kadis
Version: 0.0.1
*/
class MailChimp_Ajax {
	private $_is_debug = false;
	private $_internal_error = '';
	public static $allowed_html = array(
		'form' => array(
			'class' => true,
			'id' => true,
		),
		'input' => array(
			'type' => true,
			'class' => true,
			'id' => true,
			'name' => true,
			'placeholder' => true,
			'value' => true,
		),
		'select' => array(
			'class' => true,
			'id' => true,
			'name' => true,
		),
		'option' => array(
			'value' => true,
		),
	);

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		add_action( 'wp_ajax_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		$this->_is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$this->_internal_error = __( 'Internal error. Please try again later.', 'mailchimp-ajax' );
	}

	public static function render_form(){
		$template_file = apply_filters( 'mailchimp_ajax_template_file', __DIR__ . '/subscribe-form.html' );
		$template = file_get_contents( $template_file );
		$html = sprintf(
			$template,
			apply_filters( 'mailchimp_ajax_form_id', '' ),
			self::_custom_fields_html(),
			apply_filters( 'mailchimp_ajax_email_field', '<input class="email-field" name="subscribe-email" type="email" placeholder="' . esc_attr__( 'Email Address', 'mailchimp-ajax' ) . '" />' ),
			esc_attr( wp_create_nonce( 'mailchimp_ajax_subscribe' ) ),
			apply_filters( 'mailchimp_ajax_subscribe_button', '<input type="submit" value="' . esc_attr__( 'Subscribe', 'mailchimp-ajax' ) . '" />' ),
			apply_filters( 'mailchimp_ajax_error_msg', esc_html__( 'Try again', 'mailchimp-ajax' ) ),
			apply_filters( 'mailchimp_ajax_success_msg', esc_html__( 'Success! Check your email for a confirmation link.', 'mailchimp-ajax' ) )
		);

		// set kses array and echo the form
		self::_update_allowed_html();
		echo wp_kses( $html, self::$allowed_html );
	}

	private static function _custom_fields_html(){
		$fields = apply_filters( 'mailchimp_ajax_custom_fields', array() );
		if ( empty( $fields ) ){
			return apply_filters( 'mailchimp_ajax_custom_fields_html', '' );
		}

		$fields_html = '';
		foreach ( $fields as $field ){
			$fields_html .= $field['html'];
		}

		return apply_filters( 'mailchimp_ajax_custom_fields_html', $fields_html, $fields );
	}

	/**
	 * setup allowed HTML tags array for rendering form
	 */
	private static function _update_allowed_html(){
		self::$allowed_html = array_merge( wp_kses_allowed_html( 'post' ), self::$allowed_html );
	}

	function enqueue_script(){
		wp_register_script( 'mailchimp-ajax', plugin_dir_url( __FILE__ ) . '/mailchimpAjax.js', array( 'jquery' ), false, true );
		wp_enqueue_script( 'mailchimp-ajax' );
	}

	/**
	 * validate request, attempt to initialize MailChimp API and subscribe the submitted info
	 */
	function subscribe(){

		$this->_check_required_fields();

		$email = sanitize_email( $_POST['subscribe-email'] );
		$custom_merge_vars = $this->_setup_custom_merge_vars();

		$settings = array(
			'api_key' => MAILCHIMP_AJAX_API_KEY,
			'list_id' => MAILCHIMP_AJAX_LIST_ID,
		);

		// init MailChimp API
		$mc_api_path = __DIR__ . '/mailchimp-api/src/Mailchimp.php';
		if ( ! file_exists( $mc_api_path ) ){
			$msg = $this->_is_debug ? __( 'Missing MailChimp API library', 'mailchimp-ajax' ) : $this->_internal_error;
			die( json_encode( array(
				'success' => false,
				'errors' => array( 'Missing MailChimp API library' )
			) ) );
		}
		require_once( $mc_api_path );
		try {
			$mc_api = new Mailchimp( $settings['api_key'] );
		} catch( Mailchimp_Error $e ){
			die( json_encode( array(
				'success' => false,
				'errors' => array( $e->getMessage() )
			) ) );
		}

		// add subscriber
		try{
			$mc_api->lists->subscribe(
				$settings['list_id'],
				array(
					'email' => $email,
				),
				$custom_merge_vars
			);
		} catch( Mailchimp_Error $e ){
			die( json_encode( array(
				'success' => false,
				'errors' => array( $e->getMessage() )
			) ) );
		}

		// return response
		die( json_encode( array(
			'success' => true,
		) ) );

	}

	/**
	 * check nonce, email address, list ID, API key
	 */
	private function _check_required_fields(){
		// check provided fields
		$success = true;
		$errors = array();

		// check nonce
		if ( ! wp_verify_nonce( $_POST['subscribe-nonce'], 'mailchimp_ajax_subscribe' ) ){
			$success = false;
			$errors[] = $this->_is_debug ? __( 'Nonce was not verified', 'mailchimp-ajax' ) : $this->_internal_error;
		}

		// check email address
		if ( empty( $_POST['subscribe-email'] ) ){
			$success = false;
			$errors[] = __( 'Missing Email field', 'mailchimp-ajax' );
		} elseif ( ! filter_var( $_POST['subscribe-email'], FILTER_VALIDATE_EMAIL ) ){
			$success = false;
			$errors[] = __( 'Invalid Email address', 'mailchimp-ajax' );
		}

		// get API key and list ID from constants
		if ( ! defined( 'MAILCHIMP_AJAX_API_KEY' ) ){
			$success = false;
			$errors[] = $this->_is_debug ? __( '<code>MAILCHIMP_AJAX_API_KEY</code> is not defined', 'mailchimp-ajax' ) : $this->_internal_error;
		}

		if ( ! defined( 'MAILCHIMP_AJAX_LIST_ID' ) ){
			$success = false;
			$errors[] = $this->_is_debug ? __( '<code>MAILCHIMP_AJAX_LIST_ID</code> is not defined', 'mailchimp-ajax' ) : $this->_internal_error;
		}

		if ( ! $success ){
			die( json_encode( array(
				'success' => false,
				'errors' => $errors,
			) ) );
		}
	}

	/**
	 * builds an array of merge tags to send to MailChimp API based on custom fields
	 */
	private function _setup_custom_merge_vars(){
		$fields = apply_filters( 'mailchimp_ajax_custom_fields', array() );
		if ( empty( $fields ) ){
			return null;
		}

		$merge_vars = array();

		foreach ( $fields as $name => $field ){
			$sanitizer = is_callable( $field['sanitizer'] ) ? $field['sanitizer'] : 'sanitize_text_field';
			$merge_vars[ $field['merge_tag'] ] = call_user_func( $sanitizer, $_POST[ $name ] );
		}

		return $merge_vars;
	}

	function activate_plugin(){
		if ( ! defined( 'MAILCHIMP_AJAX_API_KEY' ) || ! defined( 'MAILCHIMP_AJAX_LIST_ID' ) ){
			deactivate_plugins( 'mailchimp-ajax' );
			wp_die( $this->notice_activation_failed() );
		}
	}

	function notice_activation_failed(){
		return sprintf(
					__( 'Failed to activate MailChimp AJAX. %s and %s must be defined.', 'mailchimp-ajax' ),
					'<code>MAILCHIMP_AJAX_LIST_ID</code>',
					'<code>MAILCHIMP_AJAX_LIST_ID</code>'
				);
	}

}

new MailChimp_Ajax();