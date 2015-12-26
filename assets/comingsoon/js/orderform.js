//  ====================================================================

//	Theme Name: OrderNow - Responsive Order Form
//	Description: This js file for price calculation
//	Version: 1.0
//	Author: Responsive Expert
//	Author URI: http://www.responsiveexpert.com
//	Tags:
//	
//	---------------------------
//	TEMPLATE SCRIPTS
//	---------------------------
//	
//	TABLE OF CONTENTS
//	---------------------------
//	01. price calculation

//  ====================================================================


(function() {
	"use strict";
	/*! Script for form price calculation */
	var order_total = 0;
    /* default values goes here*/
	var order_type_cost = 0;
	var order_type_title = '';
	var order_additional_options = 0;
	var conversion_inner_page = 0;
	var conversion_inner_page_cost = 0;
	var conversion_cost = 49;
	var extra_options = '';
	var option_value= '';
	/* default coupon code */
	var coupon_code ='HTML5';
	var discount = 0;
	var coupon_value = '';
	var discount_amt = 0;
	var discounted_price = 0;
	var list_options = '';
    /*default discount percentage goes here*/	
    var discount_percentage = 20;
	$(document).ready(function(e)
	{
		/*hide cms section and other sections*/	
		$("#cms-main-category").hide();
		$("#order_concept_area").hide();
		/*	default title goes here*/	
		$('input[name=customer_choice]:text').val('PSD to Responsive HTML5');
		/*	default sub title goes here*/
		$('input[name=customer_sub_choice]:text').val('Basic Pack');
		/*fetch the package price*/	
		order_type_cost = $('#price-pk1').html().replace(/[^0-9]/g, '');
		$('input[name=type_cost]:text').val(order_type_cost);	
		/* update the cart */
		cart_update();
	});
	function cart_update()
	{
		/* calculate the total */
	    order_total=Number(order_type_cost)+order_additional_options+conversion_inner_page_cost;	
		if(discount==1)
		{
			order_total = order_total - (order_total*discount_percentage/100);
		}
		/* Assign the total amount to a text field for form submission */
		$('input[name=order_total_amt]:text').val(order_total);
		/* calculate the discount */
		$('#order_total').html('<span>$</span>'+(Math.ceil(order_total)));
		return false;
	}
	function default_value_setting()
	{
		order_additional_options =0;
		conversion_inner_page =0;
		conversion_inner_page_cost=0;
		/*set the discount price div as null*/	
		$('#dis_price').html('');
		/*set the discount price null*/	
		discount=0;
		$('input[name=type_cost]:text').val(order_type_cost);
		$('input[name=coupon_text]:text').val('');		
		$('input[name=conversion_inner_pages]:text').val(0);
		$('input[name=etaDropDown]:text').val('3 Day');
		$(".pack-add").html('<li id="nothing">No Option Selected</li>');
	}
	$("#order-psd-to-res").on("click",function()
	{	
	    /*fetch the package price*/	
		order_type_cost =$('#price-pk1').html().replace(/[^0-9]/g, '');
		default_value_setting();
		/*default title goes here*/	
	    $('input[name=customer_choice]:text').val('PSD to Responsive HTML5');
        /*default sub title goes here*/	
	    $('input[name=customer_sub_choice]:text').val('Basic Pack');
        /*add active class for current selection*/
		$([$(this)[0], $("#normal-pk1")[0]]).addClass('active');
		/*remove active class from others*/
		$([$("#order-psd-to-ecom")[0], $("#order-concept")[0], $("#normal-pk2")[0]]).removeClass('active');
		/*show some sections*/
		$([$("#html5-pkg1")[0], $("#html5-pkg2")[0], $("#order_summary_box")[0] ,$("#add_options")[0]]).show();
		/*hide some sections*/
		$([$("#cms-main-category")[0], $("#order_concept_area")[0]]).hide();
		//Add summary pack content
		$("#summary-pack").html('<h5>Basic Pack Includes</h5><ul class="pack-in"><li><span><i class="fa fa-desktop"></i></span> Desktop Layout</li><li><span><i class="fa fa-font"></i></span> Custom fonts</li><li><span><i class="fa fa-code"></i></span> W3C Valid HTML</li><li><span><i class="fa fa-file-code-o"></i></span> W3C Valid CSS</li></ul>');
        /*default addon option goes here put each option in <li></li> tag, value and name,data is must*/	
		list_options = '<ul class="list-opt clearfix"><li><span>Advanced HTML Options</span></li></ul>';	
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="Dynamic Menus <span>[$18]</span>" name="html_options[]" class="check-opt" data="18">Dynamic Menus <span>[$18]</span></li>';
		list_options+='<li><input type="checkbox" value="Jquery Implementation [$9]" name="html_options[]" class="check-opt" data="9">Jquery Implementation <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="Commented HTML5 [$8]" name="html_options[]" class="check-opt" data="8">Commented HTML5 <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="SEO semantic coding [$9]" name="html_options[]" class="check-opt" data="9">SEO semantic coding <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="CSS3 Animations [$9]" name="html_options[]" class="check-opt" data="9">CSS3 Animations <span>[$9]</span></li><li>';
		list_options+='<input type="checkbox" value="Load speed optimization [$9]" name="html_options[]" class="check-opt" data="9">Load speed optimization <span>[$9]</span></li>';	
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#order-psd-to-ecom").on("click",function()
	{
		order_type_cost =$('#wrdpress_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();	
		$('input[name=customer_choice]:text').val('PSD to CMS / ecommerce');
		$('input[name=customer_sub_choice]:text').val('Wordpress');
		/*add active class for current selection*/
		$([$(this)[0], $("#cms-wordpress")[0]]).addClass('active');
		/*remove active class from others*/
		$([$("#order-psd-to-res")[0], $("#order-concept")[0], $("#normal-pk2")[0]]).removeClass('active');
		/*show some sections*/
		$([$("#cms-main-category")[0], $("#order_summary_box")[0] ,$("#add_options")[0]]).show();
		/*hide some sections*/
		$([$("#html5-pkg1")[0], $("#html5-pkg2")[0], $("#order_concept_area")[0]]).hide();
		$("#summary-pack").html('<h5>This Includes</h5><ul class="pack-in"><li><span><i class="fa fa-desktop"></i></span> Desktop Layout</li>                              <li><span><i class="fa fa-font"></i></span> Custom fonts</li><li><span><i class="fa fa-code"></i></span> W3C Valid HTML</li><li><span><i class="fa fa-file-code-o"></i></span> W3C Valid CSS</li></ul>');
		list_options = '<ul class="list-opt clearfix"><li><span>Wordpress Options</span></li></ul>';
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="Contact Form <span>[$18]</span>" name="html_options[]" class="check-opt" data="18">Contact Form <span>[$18]</span></li>';	
        list_options+='<li><input type="checkbox" value="Jquery Implementation [$9]" name="html_options[]" class="check-opt" data="9">Jquery Implementation <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="Slider [$8]" name="html_options[]" class="check-opt" data="8">Slider <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="Testimonial [$8]" name="html_options[]" class="check-opt" data="8">Testimonial <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="Cache Plugin [$9]" name="html_options[]" class="check-opt" data="9">Cache Plugin <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="Gallery [$9]" name="html_options[]" class="check-opt" data="9">Gallery <span>[$9]</span></li></ul>';
		$("#add_options").html(list_options);
		if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		cart_update();
		return false;
	});
	$("#order-concept").on("click",function()
	{
		order_type_cost =0;
		default_value_setting();	
		$('input[name=customer_choice]:text').val('Other Services');
		$('input[name=customer_sub_choice]:text').val('');
		/*add active class for current selection*/
		$("#order-concept").addClass('active');
		/*remove active class from others*/
		$([$("#order-psd-to-res")[0], $("#order-psd-to-ecom")[0]]).removeClass('active');
		/*show some sections*/
		$([$("#cms-main-category")[0], $("#order_summary_box")[0] ,$("#add_options")[0], $("#order_concept_area")[0]]).show();
		/*hide some sections*/
		$([$("#html5-pkg1")[0], $("#html5-pkg2")[0], $("#cms-main-category")[0], $("#add_options")[0], $("#order_summary_box")[0]]).hide();
		cart_update();
		return false;
	});	
	$("#cms-wordpress").on("click",function()
	{
		order_type_cost =$('#wrdpress_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();	
		/*add active class for current selection*/
		$("#cms-wordpress").addClass('active');
		/*remove active class from others*/
		$([$("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Wordpress');
		list_options = '<ul class="list-opt clearfix"><li><span>Wordpress Options</span></li></ul>';
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="Contact Form <span>[$18]</span>" name="html_options[]" class="check-opt" data="18">Contact Form <span>[$18]</span></li>';	
		
        list_options+='<li><input type="checkbox" value="Jquery Implementation [$9]" name="html_options[]" class="check-opt" data="9">Jquery Implementation <span>[$9]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Slider [$8]" name="html_options[]" class="check-opt" data="8">Slider <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="Testimonial [$8]" name="html_options[]" class="check-opt" data="8">Testimonial <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="Cache Plugin [$9]" name="html_options[]" class="check-opt" data="9">Cache Plugin <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="Gallery [$9]" name="html_options[]" class="check-opt" data="9">Gallery <span>[$9]</span></li></ul>';
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-joomla").on("click",function()
	{
		order_type_cost =$('#joom_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();
		$("#cms-joomla").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Joomla');
		list_options = '<ul class="list-opt clearfix"><li><span>Advanced HTML Options</span></li></ul>';
        list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="Dynamic Menus <span>[$18]</span>" name="html_options[]" class="check-opt" data="18">Dynamic Menus <span>[$18]</span></li>';
		
		list_options+='<li><input type="checkbox" value="CSS3 Animations [$9]" name="html_options[]" class="check-opt" data="9">CSS3 Animations <span>[$9]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Load speed optimization [$9]" name="html_options[]" class="check-opt" data="9">Load speed optimization <span>[$9]</span></li></ul>';
		
		list_options+='<ul class="list-opt clearfix"><li><span>Joomla Options</span></li></ul>';
		
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="contact form plugin[$10]" name="html_options[]" class="check-opt" data="10">Contact form plugin <span>[$10]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Newsletter [$5]" name="html_options[]" class="check-opt" data="5">Newsletter <span>[$5]</span></li>';
		
        list_options+='<li><input type="checkbox" value="VirtueMart [$48]" name="html_options[]" class="check-opt" data="48">VirtueMart <span>[$48]</span></li>';
		
		list_options+='<li><input type="checkbox" value="EasyBlog [$15]" name="html_options[]" class="check-opt" data="15">EasyBlog  <span>[$15]</span></li>';
		
		list_options+='<li><input type="checkbox" value="JEvents [$16]" name="html_options[]" class="check-opt" data="16">JEvents <span>[$16]</span></li>';
		
		list_options+='<li><input type="checkbox"  value="Event Booking [$16]" name="html_options[]" class="check-opt" data="16">Event Booking <span>[$16]</span></li>';
		
		list_options+='<li><input type="checkbox" value="AdsManager  [$20]" name="html_options[]" class="check-opt" data="20">AdsManager  <span>[$20]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Video gallery  [$9]" name="html_options[]" class="check-opt" data="9">Video gallery  <span>[$9]</span></li>';
		
		list_options+='<li><input type="checkbox" value="JCE Editor [$5]" name="html_options[]" class="check-opt" data="5">JCE Editor  <span>[$5]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Contact Enhanced Component [$9]" name="html_options[]" class="check-opt" data="9">Contact Enhanced Component <span>[$9]</span></li></ul>';				
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-drupal").on("click",function()
	{
		order_type_cost =$('#drup_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();
		$("#cms-drupal").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Drupal');
		// Don't keep duplicate id for checkbox, keep unique name for it
		list_options = '<ul class="list-opt clearfix"><li><span>Drupal Options</span></li>';
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="File Upload [$9]" name="html_options[]" class="check-opt" data="9">File Upload <span>[$9]</span></li>';
		list_options+='<li><input type="checkbox" value="PayPal IPN [$10]" name="html_options[]" class="check-opt" data="10">PayPal IPN <span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Statistics [$5]" name="html_options[]" class="check-opt" data="5">Statistics <span>[$5]</span></li>';
		list_options+='<li><input type="checkbox" value="Blog [$10]" name="html_options[]" class="check-opt" data="10">Blog <span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Views Slideshow [$6]" name="html_options[]" class="check-opt" data="6">Views Slideshow <span>[$6]</span></li>';
		list_options+='<li><input type="checkbox" value="Backup and Migrate [$10]" name="html_options[]" class="check-opt" data="10">Backup and Migrate <span>[$10]</span></li>';		
		list_options+='</ul>';
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-magento").on("click",function()
	{
		order_type_cost =$('#magento_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();	
		$("#cms-magento").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
     	$('input[name=customer_sub_choice]:text').val('Magento');
    	if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		list_options = '<ul class="list-opt clearfix"><li><span>Magento Options</span></li>';
		list_options+='<li><input type="checkbox" value="Banner Slider [$10]" name="html_options[]" class="check-opt" data="10">Banner Slider<span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Quick Contact [$7]" name="html_options[]" class="check-opt" data="7">Quick Contact <span>[$7]</span></li>';
		list_options+='<li><input type="checkbox" value="Multiple Table Rates Extension [$10]" name="html_options[]" class="check-opt" data="10">Multiple Table Rates Extension <span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Magento-Wordpress Integration [$35]" name="html_options[]" class="check-opt" data="35">Magento-Wordpress Integration <span>[$35]</span></li>';
		list_options+='<li><input type="checkbox" value="AddThis[$5]" name="html_options[]" class="check-opt" data="5">AddThis <span>[$5]</span></li>';
		list_options+='</ul>';
        $("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-shopify").on("click",function()
	{
		order_type_cost =$('#shopify_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();
		$("#cms-shopify").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Shopify');
     	if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		list_options = '<ul class="list-opt clearfix"><li><span>Shopify Options</span></li>';
        list_options+='<li><input type="checkbox" value="Video Gallery [$12]" name="html_options[]" class="check-opt" data="12">Video Gallery <span>[$12]</span></li>';
        list_options+='<li><input type="checkbox" value="Product Reviews [$10]" name="html_options[]" class="check-opt" data="10">Product Reviews <span>[$10]</span></li>';
        list_options+='<li><input type="checkbox" value="Social Login & Sharing [$12]" name="html_options[]" class="check-opt" data="12">Social Login & Sharing  <span>[$12]</span></li>';
		list_options+='<li><input type="checkbox" value="Social Commerce Platform [$13]" name="html_options[]" class="check-opt" data="13">Social Commerce Platform <span>[$13]</span></li>';
		list_options+='<li><input type="checkbox" value="Shipping Module [$8]" name="html_options[]" class="check-opt" data="8">Shipping Module <span>[$8]</span></li>';
		list_options+='<li><input type="checkbox" value="Stock manager [$12]" name="html_options[]" class="check-opt" data="12">Stock manager <span>[$12]</span></li>';
		list_options+='<li><input type="checkbox" value="Contact Form [$5]" name="html_options[]" class="check-opt" data="5">Contact Form <span>[$5]</span></li>';
		list_options+='</ul>';
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-prestashop").on("click",function()
	{
		order_type_cost =$('#prestashop_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();	
		$("#cms-prestashop").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-opencart")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Prestashop');
    	if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}	
		list_options = '<ul class="list-opt clearfix"><li><span>Prestashop Options</span></li>';
		list_options+='<li><input type="checkbox" value="Newsletter Module [$10]" name="html_options[]" class="check-opt" data="10">Newsletter Module<span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Blog Module [$20]" name="html_options[]" class="check-opt" data="20">Blog Module <span>[$20]</span></li>';
		list_options+='<li><input type="checkbox" value="Product rating and review module [$15]" name="html_options[]" class="check-opt" data="15">Product rating and review module <span>[$15]</span></li>';
		list_options+='<li><input type="checkbox" value="Subscriptions manager [$12]" name="html_options[]" class="check-opt" data="12">Subscriptions manager <span>[$12]</span></li>';
        list_options+='<li><input type="checkbox" value="Data Migration Module [$12]" name="html_options[]" class="check-opt" data="12">Data Migration Module <span>[$12]</span></li>';
		list_options+='<li><input type="checkbox" value="One page Checkout[$12]" name="html_options[]" class="check-opt" data="12">One page Checkout <span>[$12]</span></li>';
		list_options+='</ul>';	
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-opencart").on("click",function()
	{
		order_type_cost =$('#opencart_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();
		$("#cms-opencart").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-virtuemart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Opencart');
    	if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		list_options = '<ul class="list-opt clearfix"><li><span>Opencart Options</span></li>';
		list_options+='<li><input type="checkbox" value="Module Manager [$10]" name="html_options[]" class="check-opt" data="10">Module Manager <span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Newsletter Subscription [$10]" name="html_options[]" class="check-opt" data="10">Newsletter Subscription <span>[$10]</span></li>';
		list_options+='<li><input type="checkbox" value="Product purchase and sales report [$15]" name="html_options[]" class="check-opt" data="15">Product purchase and sales report <span>[$15]</span></li>';
		list_options+='<li><input type="checkbox" value="Payment Gateway [$16]" name="html_options[]" class="check-opt" data="16">Payment Gateway <span>[$16]</span></li>';
		list_options+='<li><input type="checkbox" value="Shipping Methods [$20]" name="html_options[]" class="check-opt" data="20">Shipping Methods <span>[$20]</span></li>';
		list_options+='<li><input type="checkbox" value="Sitemap [$10]" name="html_options[]" class="check-opt" data="10">Sitemap  <span>[$10]</span></li>';
		list_options+='</ul>';	
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#cms-virtuemart").on("click",function()
	{
		order_type_cost =$('#virtuemart_pkg').html().replace(/[^0-9]/g, '');
		default_value_setting();	
		$("#cms-virtuemart").addClass('active');
		/*remove active class from others*/
		$([$("#cms-wordpress")[0], $("#cms-joomla")[0], $("#cms-drupal")[0], $("#cms-magento")[0], $("#cms-shopify")[0], $("#cms-prestashop")[0], $("#cms-opencart")[0]]).removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Virtuemart');
    	if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		list_options = '<ul class="list-opt clearfix"><li><span>Virtuemart Options</span></li>';
        list_options+='<li><input type="checkbox" value="Multilanguage [$15]" name="html_options[]" class="check-opt" data="15">Multilanguage <span>[$15]</span></li>';
        list_options+='<li><input type="checkbox" value="Newsletter Subscription  [$10]" name="html_options[]" class="check-opt" data="10">Newsletter Subscription <span>[$10]</span></li>';
        list_options+='<li><input type="checkbox" value="Advanced inventory manager [$15]" name="html_options[]" class="check-opt" data="15">Advanced inventory manager <span>[$15]</span></li>';
        list_options+='<li><input type="checkbox" value="Categories Module [$16]" name="html_options[]" class="check-opt" data="16">Categories Module <span>[$16]</span></li>';
		list_options+='<li><input type="checkbox" value="Customer Reviews [$15]" name="html_options[]" class="check-opt" data="15">Customer Reviews <span>[$15]</span></li>';
		list_options+='</ul>';	
		$("#add_options").html(list_options);
		cart_update();
		return false;
	});
	$("#html5-pkg1").on("click",function()
	{
		order_type_cost = $("#price-pk1").html().replace ( /[^\d.]/g, '' );
		default_value_setting();	
		$("#normal-pk1").addClass('active');
		$("#normal-pk2").removeClass('active');
    	$('input[name=customer_sub_choice]:text').val('Basic Pack');
		if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		$("#summary-pack").html('<h5>Basic Pack Includes</h5><ul class="pack-in"><li><span><i class="fa fa-desktop"></i></span> Desktop Layout</li><li><span><i class="fa fa-font"></i></span> Custom fonts</li><li><span><i class="fa fa-code"></i></span> W3C Valid HTML</li><li><span><i class="fa fa-file-code-o"></i></span> W3C Valid CSS</li></ul>');
		list_options = '<ul class="list-opt clearfix"><li><span>Advanced HTML Options</span></li></ul>';	
		list_options+='<ul class="list-opt clearfix"><li><input type="checkbox" value="Dynamic Menus <span>[$18]</span>" name="html_options[]" class="check-opt" data="18">Dynamic Menus <span>[$18]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Jquery Implementation [$9]" name="html_options[]" class="check-opt" data="9">Jquery Implementation <span>[$9]</span></li>';
		
		list_options+='<li><input type="checkbox" value="Commented HTML5 [$8]" name="html_options[]" class="check-opt" data="8">Commented HTML5 <span>[$8]</span></li>';
		
		list_options+='<li><input type="checkbox" value="SEO semantic coding [$9]" name="html_options[]" class="check-opt" data="9">SEO semantic coding <span>[$9]</span></li>';
		
		list_options+='<li><input type="checkbox" value="CSS3 Animations [$9]" name="html_options[]" class="check-opt" data="9">CSS3 Animations <span>[$9]</span></li><li>';
		list_options+='<input type="checkbox" value="Load speed optimization [$9]" name="html_options[]" class="check-opt" data="9">Load speed optimization <span>[$9]</span></li>';	
		$("#add_options").html(list_options);
		$("#add_options").show();  
		cart_update();
		return false;
	});
	$("#html5-pkg2").on("click",function()
	{
		order_type_cost = $("#price-pk2").html().replace ( /[^\d.]/g, '' );
		$("#normal-pk2").addClass('active');
		$("#normal-pk1").removeClass('active');
		default_value_setting();	
    	$('input[name=customer_sub_choice]:text').val('HI-End Pack');
		if($('.check-opt').is(':checked'))
		{
			$('.check-opt').attr('checked', false);
		}
		cart_update();
		$("#summary-pack").html('<h5>HI-End Pack Includes</h5><ul class="pack-in"><li><span><i class="fa fa-desktop"></i></span> Desktop Layout</li> <li><span><i class="fa fa-font"></i></span> Custom fonts</li><li><span><i class="fa fa-code"></i></span> W3C Valid HTML</li><li><span><i class="fa fa-file-code-o"></i></span> W3C Valid CSS</li><li><span><i class="fa fa-file-code-o"></i></span> Bootstrap framework integration </li><li><span><i class="fa fa-file-code-o"></i></span> Compatible with Mobile Devices</li></ul>');
		list_options='<ul class="list-opt clearfix">';
		list_options+='<li><input type="checkbox" value="Internet Explorer 8 compatibility <span>[$19]</span>" name="html_options[]" class="check-opt" data="19">Internet Explorer 8 compatibility <span>[$19]</span></li>';
			
        list_options+='<li><input type="checkbox" value="Optimized for Retina Displays [$18]" name="html_options[]" class="check-opt" data="18">Optimized for Retina Displays <span>[$18]</span></li>';
		
        list_options+='<li><input type="checkbox" value="Link all pages [$5]" name="html_options[]" class="check-opt" data="5">Link all pages <span>[$5]</span></li>';
		
        list_options+='<li><input type="checkbox" value="Commented HTML5 [$8]" name="html_options[]" class="check-opt" data="8">Commented HTML5 <span>[$8]</span></li>';
		
		list_options+='<li><input type="checkbox" value="CSS3 Animations [$9]" name="html_options[]" class="check-opt" data="9">CSS3 Animations <span>[$9]</span></li>';
		
		list_options+='</ul>';
		$("#add_options").html(list_options);
		$("#add_options").show();  
		cart_update();

		return false;
	});	
	// code for discount coupon
    $("#discnt_btn_id").on("click",function()
	{
	    coupon_value = $('input[name=coupon_text]:text').val();
		if((coupon_value==coupon_code) && (discount==0))
		{
			discount=1;
			//calculate discount percenatge
			discount_amt = order_total*discount_percentage/100;
			discounted_price = order_total-discount_amt;
			// actualamount storedin a textbox
			$('input[name=actual_amt]:text').val(order_total);
			$('input[name=order_total_amt]:text').val(discounted_price);
			$('#order_total').html('<span>$</span>'+discounted_price);
			// discount price display on disscount price div
			$('#dis_price').html('<span>$</span>'+order_total+' - '+discount_percentage+'% discount ($'+discount_amt +') = $'+(Math.ceil(discounted_price)));	
		}
		else if(coupon_value==coupon_code)
		{
			// compare coupon code with user entered code
			discount=1; 
		}
		else
		{
		   discount=0; 
		   $('#dis_price').html('Coupon code is not valid');
		}
		cart_update();
		return false;
	});
	// end discount coupon code
	// add and remove check box items on summary
	$(document).delegate('.check-opt', 'click', function()
	{
		var cost='';
		var itemname='';
		var first='';
		if($(this).is(':checked'))
		{
		    cost = $(this).attr('data');
			order_additional_options+= Number(cost);
			itemname = $(this).attr('value');
			first = itemname.slice(0, itemname.indexOf(" "));
			$("#nothing").remove();
			$(".pack-add").append("<li data-cost='"+cost+"' data-id='item_"+first+cost+"'>"+itemname+"</li>");
		}
		else
		{   // minus unchecked value 
			cost = $(this).attr('data');
			itemname = $(this).attr('value');
			first = itemname.slice(0, itemname.indexOf(" "));
			order_additional_options-= Number(cost);
	    	$('[data-id=item_'+first+cost+']').remove();
		}		
		cart_update();
	});
	$('input[name=conversion_inner_pages]:text').keyup(function(e)
	{
		if(isNaN($(this).val()))
		{
			$(this).val(0);
		}
		else
		{
			// calculate ETA based on inner page
			conversion_inner_page = $(this).val();
			conversion_inner_page = Number(conversion_inner_page);
			conversion_inner_page_cost = conversion_inner_page*conversion_cost;
			var con_dropdown_cnt =  $('input[name=etaDropDown]:text');
			$('input[name=innerpage_cost]:text').val(conversion_inner_page_cost);			
			if(conversion_inner_page==0 )
				 con_dropdown_cnt.val('3 Day');
			else if(conversion_inner_page==1 || conversion_inner_page==2 )
				 con_dropdown_cnt.val('4 Day');
			else if(conversion_inner_page==3 || conversion_inner_page==4 )
				con_dropdown_cnt.val('5 Day');
			else if(conversion_inner_page==5 || conversion_inner_page==6 )
				con_dropdown_cnt.val('6 Day');
			else if(conversion_inner_page==7 || conversion_inner_page==8 )
				con_dropdown_cnt.val('7 Day');
			else if(conversion_inner_page==9 || conversion_inner_page==10 )
				con_dropdown_cnt.val('8 Day');
			else
				con_dropdown_cnt.val('Let me estimate');
			cart_update();
		}
	});	
	// on click order button submit the form
	$('#order_btn_id').on("click",function(e)
	{
	 e.preventDefault();
	 $('#order_frm').submit();
	});
	// on click order button of basic form then submit the order form 
	$('#order_btn_id2').on("click",function(e) {
	 e.preventDefault();
	 // change the action of the basic form to thankyou.php
 	 $('#order_frm').get(0).setAttribute('action', 'thankyou.php'); //change the action on submit
     $('#order_frm').submit();
	});
	$('input:text, textarea').keyup(function(e) {
			$('#error_order').css('display','none');
		});	
	$('#order_frm').submit(function(e)
	{
		if($('input[name=customer_name]:text').val()=='')
		{
			$('#error_order').css('display','block').html('Please enter your name');
			$('input[name=customer_name]:text').focus();
			return false;
		}
		else if($('input[name=customer_email]:text').val()=='')
		{
			$('#error_order').css('display','block').html('Please enter your email');
			$('input[name=customer_email]:text').focus();
			return false;
		}
		else if(!validate_email($('input[name=customer_email]:text').val()))
		{
			$('#error_order').css('display','block').html('Please enter your valid email');
			$('input[name=customer_email]:text').focus();
			return false;
		}	
		else if($('textarea[name=customer_message]').val()=='')
		{
			$('#error_order').css('display','block').html('Please enter your project description');
			$('textarea[name=customer_message]').focus();
			return false;
		}
		else
		{
			return true;
		}
	});
	function validate_email(email) 
	{
	   var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
	   return reg.test(email);
	}

})(jQuery);