<?php
/*
Plugin Name: MailChimp AJAX
Description: Handles MailChimp AJAX requests. You are responsible for setting up the HTML form, making the request, and handling the AJAX response.
Author: Josh Kadis
Version: 0.0.1
*/
class MailChimp_Ajax {

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
		add_action( 'wp_ajax_nopriv_mailchimp_ajax_subscribe', array( $this, 'subscribe' ) );
	}

	function subscribe(){
		error_log( 'subscribing' );
	}

	function deactivate_plugin(){
		delete_option( 'mailchimp_ajax_settings' );
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