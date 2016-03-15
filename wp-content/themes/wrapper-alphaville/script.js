/**
 * script.js
 */

/**
 * Handle the popup windows for the share buttons
 */
jQuery(function($) {
	$('body').on('click', 'li.sharelink a', function(){
		var popup_width  = screen.width - 100;
		var popup_height = screen.height - 100;

		if ( $(this).attr('class').indexOf('twitter') != -1 ) {
			popup_width  = 560;
			popup_height = 250;
		} else if ( $(this).attr('class').indexOf('facebook') != -1 ) {
			popup_width  = 640;
			popup_height = 300;
		} else if ( $(this).attr('class').indexOf('googleplus') != -1 ) {
			popup_width  = 640;
			popup_height = 360;
		} else if ( $(this).attr('class').indexOf('linkedin') != -1 ) {
			popup_width  = 560;
			popup_height = 400;
		} else if (  $(this).attr('class').indexOf('stumbleupon') != -1 ) {
			popup_width  = 950;
			popup_height = 940;
		} else if ( $(this).attr('class').indexOf('reddit') != -1 ) {
			popup_width  = 800;
			popup_height = 720;
		} else if ( $(this).attr('class').indexOf('tumblr') != -1 ) {
			popup_width  = 610;
			popup_height = 500;
		}

		var left   = ($(window).width()  - popup_width)/2;
		var top	= ($(window).height() - popup_height)/4;
		var params = 'width='+popup_width+', height='+popup_height+', top='+top+', left='+left;
		var title  = 'Share on ' + $(this).attr('class');
		share_window = window.open($(this).attr('href'), title, params);
		if (window.focus) {share_window.focus()}

		return false;
	 });
});

/**
 * Twitter "follow" buttons
 */
jQuery(function($) {
	!function(d,s,id){
		var js,fjs=d.getElementsByTagName(s)[0];
		if(!d.getElementById(id)){
			js=d.createElement(s);
			js.id=id;
			js.src="http://platform.twitter.com/widgets.js";
			fjs.parentNode.insertBefore(js,fjs);
		}
	}(document,"script","twitter-wjs");
});

/**
 * Widget tabbed content
 */
jQuery(function($) {
	$(".tabbed-content-menu .tab").click ( function() {

		// Menu items
		$('.tab', $(this).closest('.tabbed-content')).removeClass('active-tab');
		$(this).addClass('active-tab');

		// Related panels
		var related_panel = "." + $(this).attr("rel");
		$(".tabbed-content-panel", $(this).closest('.tabbed-content')).removeClass('active-panel');
		$(related_panel, $(this).closest('.tabbed-content')).addClass('active-panel');
	});
});

/**
 * Show-more functionality (for widgets)
 */
jQuery(function($) {
	$(".widget .show-more").click(function() {
		$(this).hide().closest('.tabbed-content-panel').find(".initially-hidden").show()
	});
	$(".widget .show-fewer").click(function() {
		$(this).closest(".initially-hidden").hide().closest('.tabbed-content-panel').find(".show-more").show();
	});
});

/**
 * Popup overlays
 * 
 * To make a popup overlay, create an anchor or button with a class of "overlayButton".
 * Make the related popup overlay element a child or sibling of the overlay button, and give it a class of "overlay".
 */ 

jQuery(function($) {
	$(document).on('click', '.overlayButton', function(event){

		// If it's invisible or not positioned left: 0px, show the overlay.
		show_overlay = false;
		
		if ($(this).hasClass('shareButton')) {
			overlay = $(this).nextAll('.overlay:first');
		} else {		
			overlay = $(this).parent().nextAll('.overlay:first');
			myPosX = $(this).offset().left - $('.inner').offset().left + 10; //arrow offset
			$(overlay).children('.overlay .overlayArrow').css('margin-left',myPosX);
		}

		if(overlay.css("left") != "0px" || overlay.css("display") != "block"){
			show_overlay = true;
		}

		$('.overlay').hide();
		$('.overlayButton').css('z-index', '1'); 
		
		if(show_overlay){
			$(overlay).hide(0).css('left','0px').css('z-index', '2000').fadeIn(100); 
		}

		return false;
	});

	$(document).on('click', '.overlay', function(event){
		// Stop the click event propagation to prevent document.click events closing the overlay.
		event.stopPropagation();
	});

	$(document).on('click', function(event){
		$('.overlay').hide(); 
	});
});


/**
 * Fix IE9 title bug
 *
 * This is a workaround for bug where IE9 does not correctly populate document.title if the
 * <title> tag is not the first tag after the <head> tag.
 */
if (!document.title && typeof document.getElementsByTagName('title')[0] === 'object') {
    document.title = document.getElementsByTagName('title')[0].innerHTML;
}
