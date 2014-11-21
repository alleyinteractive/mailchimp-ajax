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

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		add_action( 'wp_ajax_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		$this->_is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$this->_internal_error = __( 'Internal error. Please try again later.', 'mailchimp-ajax' );
	}

	public static function render_form(){
		$form = file_get_contents( __DIR__ . '/subscribe-form.html' );
		printf(
			$form,
			__( 'First name' , 'mailchimp-ajax' ),
			__( 'Last name' , 'mailchimp-ajax' ),
			__( 'Email address' , 'mailchimp-ajax' ),
			esc_attr( wp_create_nonce( 'mailchimp_ajax_subscribe' ) ),
			__( 'Subscribe me' , 'mailchimp-ajax' ),
			__( 'Try again', 'mailchimp-ajax' ),
			__( 'Success! Check your email for a confirmation link.', 'mailchimp-ajax' )
		);
	}

	function enqueue_script(){
		wp_register_script( 'mailchimp-ajax', plugin_dir_url( __FILE__ ) . '/mailchimpAjax.js', array( 'jquery' ), false, true );
		wp_enqueue_script( 'mailchimp-ajax' );
	}

	/**
	 * validate request, attempt to initialize MailChimp API and subscribe the submitted info
	 */
	function subscribe(){

		// check nonce
		if ( ! wp_verify_nonce( $_POST['subscribe-nonce'], 'mailchimp_ajax_subscribe' ) ){
			$msg = $this->_is_debug ? __( 'Nonce was not verified', 'mailchimp-ajax' ) : $this->_internal_error;
			die( json_encode( array(
				'success' => false,
				'errors' => array( $msg )
			) ) );
		}

		// check provided fields
		$success = true;
		$errors = array();
		switch ( true ){

			case empty( $_POST['subscribe-firstname'] ):
				$success = false;
				$errors[] = __( 'Missing First Name field', 'mailchimp-ajax' );
				// don't break here because we want to continue checking other fields


			case empty( $_POST['subscribe-lastname'] ):
				$success = false;
				$errors[] = __( 'Missing Last Name field', 'mailchimp-ajax' );
				// don't break here because we want to continue checking other fields

			case empty( $_POST['subscribe-email'] ):
				$success = false;
				$errors[] = __( 'Missing Email field', 'mailchimp-ajax' );
				break;
				
			case ! filter_var( $_POST['subscribe-email'], FILTER_VALIDATE_EMAIL ):
				$success = false;
				$errors[] = __( 'Invalid Email address', 'mailchimp-ajax' );
				break;				
		}

		$first_name = sanitize_text_field( $_POST['subscribe-firstname'] );
		$last_name = sanitize_text_field( $_POST['subscribe-lastname'] );
		$email = sanitize_email( $_POST['subscribe-email'] );

		if ( ! $success ){
			die( json_encode( array(
				'success' => false,
				'errors' => $errors
			) ) );
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
				'errors' => $errors
			) ) );
		}

		$settings = array(
			'api_key' => MAILCHIMP_AJAX_API_KEY,
			'list_id' => MAILCHIMP_AJAX_LIST_ID
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
					'email' => $email
				),
				array(
					'FNAME' => $first_name,
					'LNAME' => $last_name
				)
			);
		} catch( Mailchimp_Error $e ){
			die( json_encode( array(
				'success' => false,
				'errors' => array( $e->getMessage() )
			) ) );
		}

		// return response
		die( json_encode( array(
			'success' => true
		) ) );

	}

	function activate_plugin(){
		if (! defined( 'MAILCHIMP_AJAX_API_KEY' ) || ! defined( 'MAILCHIMP_AJAX_LIST_ID' ) ){
			deactivate_plugins( 'mailchimp-ajax' );
			wp_die( $this->notice_activation_failed() );
		}
	}

	function notice_activation_failed(){
		return sprintf(
					__('Failed to activate MailChimp AJAX. %s and %s must be defined.', 'mailchimp-ajax' ),
					'<code>MAILCHIMP_AJAX_LIST_ID</code>',
					'<code>MAILCHIMP_AJAX_LIST_ID</code>'
				);
	}

}

new MailChimp_Ajax();