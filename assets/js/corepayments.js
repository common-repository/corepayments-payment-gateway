var successCallback = function(data) {
	var checkout_form = $( 'form.woocommerce-checkout' );
	checkout_form.find('#corepayments_token').val(data.token);
	checkout_form.off( 'checkout_place_order', tokenRequest );
	checkout_form.submit(); 
};
 
var errorCallback = function(data) {
    console.log(data);
};
 
var tokenRequest = function() {
 	return false;
 
};
 
jQuery(function($){
	var checkout_form = $( 'form.woocommerce-checkout' );
	checkout_form.on( 'checkout_place_order', tokenRequest );
});