<?php
/*
 * Template Name: Advertising page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>
    </div>
</div>
<style>
    .inside-article{
        padding-bottom: 13px !important;
    }
    .wrapper-page,
    .site-footer{
        float:left;
        width:100%;
    }
    @media only screen and (max-width: 1900px){
        .hide-on-small-screens{
            display: none;
        }
    }
    @media only screen and (min-width: 1900px){
        #page{
            max-width: 1255px !important; 
            float: left;
        }
    }
</style>
<?php  

$page_id  = get_the_ID();
$leftbannerThumbnailID   = get_post_meta($page_id,'left_side_banner', true);
$rightbannerThumbnailID  = get_post_meta($page_id,'right_side_banner', true);
$bottombannerThumbnailID = get_post_meta($page_id,'bottom_side_banner', true);

$leftBannerImage = $rightBannerImage = $bottomBannerImage = "";
if( !empty($leftbannerThumbnailID) ){
    $image = wp_get_attachment_image_src( $leftbannerThumbnailID, 'full' );
    if( isset($image[0]) ){
        $leftBannerImage = '<img class="size-full wp-image-28828 aligncenter" src="'.$image[0].'" alt=""/>';
    }
}

if( !empty($rightbannerThumbnailID) ){
    $image = wp_get_attachment_image_src( $rightbannerThumbnailID, 'full' );
    if( isset($image[0]) ){
        $rightBannerImage = '<img class="size-full wp-image-28828 aligncenter" src="'.$image[0].'" alt=""/>';
    }
}

if( !empty($bottombannerThumbnailID) ){
    $image = wp_get_attachment_image_src( $bottombannerThumbnailID, 'full' );
    if( isset($image[0]) ){
        $bottomBannerImage = '<img class="size-full wp-image-28828 aligncenter" src="'.$image[0].'" alt=""/>';
    }
}
?>
<div class="wrapper-page">
    <div class="col1 hide-on-small-screens" style="float: left; margin-top: 20px;">
        <?php echo $leftBannerImage; ?>
    </div>
    <div class="site grid-container container hfeed" id="page">
        <div class="site-content" id="content">
            <div <?php generate_do_attr( 'content' ); ?>>
                <main <?php generate_do_attr( 'main' ); ?>>
                    <?php
                    /**
                     * generate_before_main_content hook.
                     *
                     * @since 0.1
                     */
                    do_action( 'generate_before_main_content' );

                    if ( generate_has_default_loop() ) {
                        while ( have_posts() ) :

                            the_post();

                            generate_do_template_part( 'page' );

                        endwhile;
                    }

                    /**
                     * generate_after_main_content hook.
                     *
                     * @since 0.1
                     */
                    do_action( 'generate_after_main_content' );
                    ?>
                    <p>
                        <?php echo $bottomBannerImage; ?>
                    </p>
                </main>
            </div>
        </div>
    </div>
    <div class="col3 hide-on-small-screens" style="float: left; margin-top: 20px;">
        <?php echo $rightBannerImage; ?>
    </div>
</div>
<?php
/**
 * generate_after_primary_content_area hook.
 *
 * @since 2.0
 */
do_action( 'generate_after_primary_content_area' );

generate_construct_sidebars();

get_footer();