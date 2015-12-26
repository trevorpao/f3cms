//  ====================================================================
//	Theme Name: Lumen - Multi-purpose Bootstrap Template
//	Theme URI: http://themeforest.net/user/responsiveexperts
//	Description: This javascript file is using as a settings file. This file includes the sub scripts for the javascripts used in this template.
//	Version: 1.0
//	Author: Responsive Experts
//	Author URI: http://themeforest.net/user/responsiveexperts
//	Tags:
//  ====================================================================

//	TABLE OF CONTENTS
//	---------------------------
//	 01. Preloader
//	 02. Scroll To Top
//   03. Adding fixed position to header
//	 04. Menu Toggle
//	 05. Animations

//  ====================================================================


(function() {
	"use strict";
	
	// -------------------- 01. Preloader ---------------------
	// --------------------------------------------------------

	$(window).load(function() {
	$("#loader").fadeOut();
	$("#mask").delay(1000).fadeOut("slow");
	});
	
	// ------------------- 02. Scroll To Top ------------------
	// --------------------------------------------------------
	
	$(function() {
		$('a[href*=#]:not([href=#])').click(function() {
			$('.menu-cont li a').parent().removeClass('active');
			$(this).parent().addClass('active');
			if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') || location.hostname == this.hostname) {
	
				var target = $(this.hash);
				target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
				if (target.length) {
					$('html,body').animate({
						scrollTop: target.offset().top
					}, 1000);
					return false;
				}
			}
			
		});
	});
	
	// --------- 03. Adding fixed position to header ---------- 
	// --------------------------------------------------------
	
	$(document).scroll(function() {
		if ($(document).scrollTop() >= 1) {
		  $('.header-area').addClass('navbar-fixed-top');
		} else {
		  $('.header-area').removeClass('navbar-fixed-top');
		}
	});
	
	// -------------------- 04. Menu Toggle -------------------
	// --------------------------------------------------------
	
	$( ".toggle-btn" ).click(function() {
		$( ".nav-main" ).toggle();
	});
	
	// -------------------- 05. Animations --------------------
	// --------------------------------------------------------

	$('.animated').appear(function() {
		var elem = $(this);
		var animation = elem.data('animation');
		if ( !elem.hasClass('visible') ) {
			var animationDelay = elem.data('animation-delay');
			if ( animationDelay ) {
				setTimeout(function(){
					elem.addClass( animation + " visible" );
				}, animationDelay);
			} else {
				elem.addClass( animation + " visible" );
			}
		}
	});
	

})(jQuery);


