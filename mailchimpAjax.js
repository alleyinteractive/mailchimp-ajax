jQuery('form.mailchimp-ajax').submit(function(e){
	e.preventDefault();

	submission = {
		action : 'mailchimp_ajax_subscribe'
	};

	// parse form values
	jQuery.each( jQuery(this).serializeArray(), function(index, field){
		submission[ field.name ] = field.value;
	});

	ajaxurl = '/wp-admin/admin-ajax.php';

	// in case there are multiple forms on the page,
	// only show results in the form that was submitted
	var context = this;

	// make AJAX call
	jQuery.post( ajaxurl, submission, function( data ){

		//if we have a callback function bound to the context, lets call that instead of doing other stuff
	    if ( jQuery( context ).data('events') !== null && jQuery( context ).data('events').mailchimpAjaxCallback !== 'undefined' ) {
	    	jQuery( context ).trigger( 'mailchimpAjaxCallback', data );
	    } else {
	    	if ( data.success ){
				// it worked! hide form fields and show success message
				jQuery('.fields', context).hide();
				jQuery('.response.error', context).hide();
				jQuery('.response.success', context).show();
			} else {
				//clear out previous errors
				jQuery('.response.error ul', context).html("");

				// it failed! insert the errors into the page
				jQuery.each( data.errors, function( index, error ){
					jQuery('.response.error ul', context).append( '<li>' + error + '</li>' );
				});

				// then hide the form fields and show the list of errors
				jQuery('.fields', context).hide();
				jQuery('.response.error', context).show();

				// if you click "Try Again", clear the errors and show the form fields
				jQuery('.response.error a', context).click(function(e){
					e.preventDefault();
					jQuery('.response.error ul li', context).remove();
					jQuery('.response.error', context).hide();
					jQuery('.fields', context).show();
				});
			}
	    }
	}, 'json');

});