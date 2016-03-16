jQuery(document).ready(function() {
	jQuery("#author_regions .region").click(function(){
		var region = jQuery(this).attr('id');
		jQuery('.author_panel').addClass('hidden');
		jQuery(this).siblings().removeClass('selected');
		jQuery(this).addClass('selected');

		jQuery('#panel'+region).removeClass('hidden');
	});
	
	jQuery('#backselector').click(function(){
		jQuery('.author_panel').addClass('hidden');
		jQuery('#author_regions .region').removeClass('selected');
		jQuery('#panelregion_global').removeClass('hidden')
	});
});
