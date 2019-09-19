

jQuery( function( $ ) {

	affirm.ui.ready( function() {
		affirm.config.extend({"merchant":  JSON.parse(merchantData)});
		affirm.config.extend({"checkout": {"metadata": metaData }});
		var checkoutButton = affirm.ui.components.create("checkout-button", {"style":{height: 50, width:200}})
		checkoutButton.on('click', validateRequiredOptions )
		checkoutButton.render("#checkout-now")
	})
})


validateRequiredOptions = function(){

	if(document.getElementsByName('variation_id').length > 0 ) {
		var variation_id = document.getElementsByName('variation_id')[0].value

		if (variation_id == 0 || variation_id == '' || variation_id == null) {
			window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
			return {blockModal: true}
		} else {
			jQuery.get(productSkuURL+'?variation_id='+variation_id , function(data, status){
				console.log(data)
			})
		}

		return {blockModal: false, items : items}
	} else {
		return {blockModal: false, items : items}
	}


}