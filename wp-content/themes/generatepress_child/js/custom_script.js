jQuery(document).ready(function(){
// jQuery("volleyextension_widget").css("display", "none");
// 	jQuery("#volleyextension_widget .WidgetPopup_vly-frame__247S8").css("background", "black !important");
	

    var width = jQuery(window).width(); 
    if (width >= 670) {
        jQuery( ".woocommerce-loop-product__title" ).addClass( "selected-page" );
        if(jQuery(".woocommerce-loop-product__title").hasClass("selected-page")){

                jQuery( ".woocommerce-products-header" ).append( "<div class='grid-list'><i class='fas fa-bars'></i><i class='fas fa-th'></i></div>" );

                console.log(sessionStorage.getItem("List"));
                if(sessionStorage.getItem("List") == null){
                    sessionStorage.setItem("List", true);
                    console.log("The page loaded the first time");
                }
            
                jQuery(".grid-list .fa-th").click(function(){
                    sessionStorage.setItem("List", false);
                    console.log(sessionStorage.getItem("List"));
                    location.reload();
                });
            
                if((sessionStorage.getItem("List") == "false")){
                  jQuery(".grid-list .fa-th").addClass("icon-glow");
                  jQuery(".products").removeClass( "column-active" );
                  jQuery(".products li").removeClass( "list-product" );
                  jQuery(".products a").removeClass( "a-active" );
                  jQuery(".inside-wc-product-image").removeClass( "image-active" );
                  jQuery(".woocommerce-loop-category__title").removeClass( "font-active" );
                  jQuery(".woocommerce-loop-product__title").removeClass( "font-active" );
                  jQuery(".products .button").removeClass( "button-active" );           

                }
            
                jQuery(".grid-list .fa-bars").click(function(){
                    sessionStorage.setItem("List", true);
                    console.log(sessionStorage.getItem("List"));
                    location.reload();
                });
            
                if(sessionStorage.getItem("List") == "true"){
                    jQuery(".grid-list .fa-bars").addClass("icon-glow");
                    jQuery(".products").addClass( "column-active" );
                    jQuery(".products li").addClass( "list-product" );
                    jQuery(".products a").addClass( "a-active" );
                    jQuery(".inside-wc-product-image").addClass( "image-active" );
                    jQuery(".woocommerce-loop-category__title").addClass( "font-active" );
                    jQuery(".woocommerce-loop-product__title").addClass( "font-active" );
                    jQuery(".products .button").addClass( "button-active" );
                } 
        } 
    }
}); 


// jQuery(document).ready(function() { 
//   jQuery(window).load(function() { 
//      jQuery("h4").css("background", "#e5dddd");
// 		jQuery("#main .inside-article").css("border", "1px solid grey");
	
//   });
// });
