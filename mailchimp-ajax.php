<?php
/*
Plugin Name: MailChimp AJAX
Description: Handles MailChimp AJAX requests. You are responsible for setting up the HTML form and nonce, making the request, and handling the AJAX response.
Author: Josh Kadis
Version: 0.0.1
*/
class MailChimp_Ajax {

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		add_action( 'wp_ajax_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
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
			die( json_encode( array(
				'success' => false,
				'errors' => array( 'Nonce was not verified.' )
			) ) );
		}

		// check provided fields
		$success = true;
		$errors = array();
		switch ( true ){

			case empty( $_POST['subscribe-firstname'] ):
				$success = false;
				$errors[] = 'Missing First Name field';
				// don't break here because we want to continue checking other fields


			case empty( $_POST['subscribe-lastname'] ):
				$success = false;
				$errors[] = 'Missing Last Name field';
				// don't break here because we want to continue checking other fields

			case empty( $_POST['subscribe-email'] ):
				$success = false;
				$errors[] = 'Missing Email field';
				break;
				
			case ! filter_var( $_POST['subscribe-email'], FILTER_VALIDATE_EMAIL ):
				$success = false;
				$errors[] = 'Invalid Email address';
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

		// get API key and list ID from wp_options
		$settings = get_option( 'mailchimp_ajax_settings' );
		if ( empty( $settings['api_key'] ) ){
			$success = false;
			$errors[] = '<code>mailchimp_ajax_settings</code> missing API key';
		}

		if ( empty( $settings['list_id'] ) ){
			$success = false;
			$errors[] = '<code>mailchimp_ajax_settings</code> missing lsit ID';
		}
		if ( ! $success ){
			die( json_encode( array(
				'success' => false,
				'errors' => $errors
			) ) );
		}

		// init MailChimp API
		$mc_api_path = __DIR__ . '/mailchimp-api/src/Mailchimp.php';
		if ( ! file_exists( $mc_api_path ) ){
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
			$option_added = false;
		} else {
			$settings = array(
				'api_key' => MAILCHIMP_AJAX_API_KEY,
				'list_id' => MAILCHIMP_AJAX_LIST_ID
			);
			$option_added = add_option( 'mailchimp_ajax_settings', $settings, '', 'no' );
		}
	
		if ( ! $option_added ){
			deactivate_plugins( 'mailchimp-ajax' );
			wp_die( $this->notice_activation_failed() );
		}
	}

	function notice_activation_failed(){
		return 'Failed to add MailChimp AJAX settings. <code>MAILCHIMP_AJAX_API_KEY</code> and <code>MAILCHIMP_AJAX_LIST_ID</code> must be defined in theme, and <code>mailchimp_ajax_settings</code> must be available in <code>wp_options</code>.';
	}

}

new MailChimp_Ajax();