jQuery('form.mailchimp-ajax').submit(function(e){
	e.preventDefault();

	submission = {
		action : 'mailchimp_ajax_subscribe'
	};

	jQuery.each( jQuery(this).serializeArray(), function(index, field){
		submission[ field.name ] = field.value;
	});

	ajaxurl = '/wp-admin/admin-ajax.php'

	var context = this;

	jQuery.post( ajaxurl, submission, function( data ){
		if ( data.success ){
			jQuery('.fields', context).hide();
			jQuery('.response.success', context).show();
		} else {
			jQuery.each( data.errors, function( index, error ){
				jQuery('.response.error ul', context).append( '<li>' + error + '</li>' );
				jQuery('.fields', context).hide();
				jQuery('.response.error', context).show();
				jQuery('.response.error a', context).click(function(e){
					e.preventDefault();
					jQuery('.response.error ul li', context).remove();
					jQuery('.response.error', context).hide();
					jQuery('.fields', context).show();
				});
			});
		}
	}, 'json');

});