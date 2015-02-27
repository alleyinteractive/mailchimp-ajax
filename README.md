## MailChimp AJAX

A basic plugin handle subscription requests to a MailChimp list and display error/success message(s).

### Usage

1. Define `MAILCHIMP_AJAX_API_KEY` and `MAILCHIMP_AJAX_LIST_ID` in your theme or `wp-config.php`
1. Activate the plugin
1. Add the form to your template with `if ( class_exists( 'MailChimp_Ajax' ) ){ MailChimp_Ajax::render_form(); }`
1. Apply your own CSS to the form fields, response messages, etc

### Limitations

1. Only supports one MailChimp list ID for the site
1. The ajax request is hard coded to `/wp-admin/admin-ajax.php`

### Extending with filters

#### mailchimp_ajax_template_file

Specify file path to form template; defaults to `subscribe-form.html` in the plugin.

#### mailchimp_ajax_form_id

Specify an ID attribute for the form; default is empty string

#### mailchimp_ajax_email_field

Specify HTML for the field that collects the email address; the `name` attribute MUST be set to `subscribe-email`

Default is

````
<input class="email-field" name="subscribe-email" type="email" placeholder="' . esc_attr__( 'Email Address', 'mailchimp-ajax' ) . '" />
````

#### mailchimp_ajax_subscribe_button

Specify HTML for the subscribe button

Default is

````
'<input type="submit" value="' . esc_attr__( 'Subscribe', 'mailchimp-ajax' ) . '" />'
````

#### mailchimp_ajax_error_msg

Text that appears on the button that clears the fields after a failed entry; default is `Try again`. Note that you would also see specific error messages like "This email is already subscribed"

#### mailchimp_ajax_success_msg

Success message after a subscription; default is `Success! Check your email for a confirmation link.`

#### mailchimp_ajax_custom_fields

This is an important one! It allows you to pass an array of fields to collect first name, last name, or any other MailChimp merge tags. Here is an example:

````
add_filter( 'mailchimp_ajax_custom_fields', function(){
	return array(
		'subscribe-firstname' => array(
			'merge_tag' => 'FNAME',
			'html' => '<input class="form-control" name="subscribe-firstname" type="text" placeholder="' . esc_attr__( 'First Name', 'neiman' ) . '" />',
		),
		'subscribe-lastname' => array(
			'merge_tag' => 'LNAME',
			'sanitizer' => 'sanitize_text_field',
			'html' => '<input class="form-control" name="subscribe-lastname" type="text" placeholder="' . esc_attr__( 'Last Name', 'neiman' ) . '" />',
		),
	);
});
````
*NOTES*

* The output in the form simply concatenates the `html` elements from each field in the provided order
* For each field in the array, the key *MUST* match the `name` attribute of the `html` element
* The `sanitizer` is optional and defaults to `sanitize_text_field()`

#### mailchimp_ajax_custom_fields_html

You can use this in two ways. If you _do not_ use `mailchimp_ajax_custom_fields`, you can specify some arbitrary HTML here, although the fields would not get passed on to MailChimp. If you _do_ use `mailchimp_ajax_custom_fields`, you can play with the `$fields_html` string after it has been created.

### Using with a test list

If you want to use a different MailChimp list in your dev environment, the easiest thing is to:

`define( 'MAILCHIMP_AJAX_LIST_ID', 'dev_list_id' );` in your local `wp-config.php`

then in your theme `functions.php`

```
if ( ! defined( 'MAILCHIMP_AJAX_LIST_ID' ) ){
	define( ''MAILCHIMP_AJAX_LIST_ID', 'prod_list_id' );
}
```