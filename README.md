## MailChimp AJAX

A basic plugin handle subscription requests to a MailChimp list and display error/success message(s).

### Usage

1. Define `MAILCHIMP_AJAX_API_KEY` and `MAILCHIMP_AJAX_LIST_ID` in your theme
1. Activate the plugin
1. Add the form to your template with `if ( class_exists( 'MailChimp_Ajax' ) ){ MailChimp_Ajax::render_form(); }`
1. Apply your own CSS to the form fields, response messages, etc

### Limitations

1. Only supports one MailChimp list ID for the site
1. First Name field is mapped to `FNAME` merge tag
1. Last Name field is mapped to `LNAME` merge tag
1. No other fields (other than the email address) are currently supported
1. The ajax request is hard coded to `/wp-admin/admin-ajax.php`
