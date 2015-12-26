//  ====================================================================
//	Theme Name: Lumen - Multi-purpose Bootstrap Template
//	Theme URI: http://themeforest.net/user/responsiveexperts
//	Description: This javascript file is using as a settings file of FLEX SLIDER.
//	Version: 1.0
//	Author: Responsive Experts
//	Author URI: http://themeforest.net/user/responsiveexperts
//	Tags:
//  ====================================================================



(function() {
	"use strict";
	
	
	// ----------------------- Banner Slider JS ----------------------
	// ---------------------------------------------------------------
	
	$('.banner-area').flexslider({
		animation: "fade",
		start: function(slider){
		  $('body').removeClass('loading');
		}
	});
	

})(jQuery);


