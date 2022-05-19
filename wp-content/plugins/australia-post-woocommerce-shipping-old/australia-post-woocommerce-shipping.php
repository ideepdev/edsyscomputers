<?php
/*
    Plugin Name: ELEX WooCommerce Australia Post Shipping With Tracking
    Plugin URI: https://elextensions.com/plugin/woocommerce-australia-post-shipping-plugin-with-print-label-tracking/
    Description: Australia Post API Real-time Shipping Rates and Tracking.
    Version: 2.5.8
    WC requires at least: 2.6.0
    WC tested up to: 5.4
    Author: ELEX
    Author URI: https://elextensions.com/
    Copyright: 2019 ELEX.
    Text Domain: wf-shipping-auspost
*/

if (!defined('ABSPATH')) {
    exit;
}

function wf_au_post_pre_activation_check()
{
    // Checking if the WooCommerce is active or not
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__("Oops! WooCommerce plugin must be active for WooCommerce Australia Post Shipping to work   ", "wf-shipping-auspost"), "", array('back_link' => 1));
    }

    //check if basic version is there
    if (is_plugin_active('woo-australia-post-shipping-method/australia-post-woocommerce-shipping.php')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__("Oops! You tried installing the premium version without deactivating and deleting the basic version. Kindly deactivate and delete Australia Post(Basic) Woocommerce Extension and then try again", "wf_australia_post"), "", array('back_link' => 1));
    }
}
register_activation_hook(__FILE__, 'wf_au_post_pre_activation_check');

if (!defined('WF_AUSTRALIA_POST_ID')) {
    define("WF_AUSTRALIA_POST_ID", "wf_australia_post");
}

if (!defined('WF_AUSTRALIA_POST_URL')) {
    define("WF_AUSTRALIA_POST_URL", plugin_dir_url(__FILE__));
}

if (!defined('ELEX_AUSPOST_LABELS')) {
    define('ELEX_AUSPOST_LABELS', WP_CONTENT_DIR . '/ELEX_AusPost_Labels/');
}

if (!defined('ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION')) {
    if (in_array('elex-australia-for-woocommerce-auto-label-generate-email-add-on/elex-aus-post-for-woocommerce-auto-label-generate-email-add-on.php', get_option('active_plugins'))) {
        define("ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION_PATH", ABSPATH . PLUGINDIR . "/elex-australia-for-woocommerce-auto-label-generate-email-add-on/");
        define('ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION', TRUE);
    } else {
        define('ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION', FALSE);
    }
}
/**
 * Localisation
 */
load_plugin_textdomain('wf_australia_post', false, dirname(plugin_basename(__FILE__)) . '/languages/');


/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || (is_multisite() && is_plugin_active_for_network('woocommerce/woocommerce.php'))) {

    include_once('australia-post-deprecated-functions.php');

    if (!function_exists('wf_get_settings_url')) {
        function wf_get_settings_url()
        {
            return version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
        }
    }

    if (!function_exists('wf_plugin_override')) {
        add_action('plugins_loaded', 'wf_plugin_override');
        function wf_plugin_override()
        {
            if (!function_exists('WC')) {
                function WC()
                {
                    return $GLOBALS['woocommerce'];
                }
            }
        }
    }

    if (!function_exists('wf_get_shipping_countries')) {
        function wf_get_shipping_countries()
        {
            $woocommerce = WC();
            $shipping_countries = method_exists($woocommerce->countries, 'get_shipping_countries')
                ? $woocommerce->countries->get_shipping_countries()
                : $woocommerce->countries->countries;
            return $shipping_countries;
        }
    }

    if (!class_exists('wf_au_post_woocommerce_shipping_setup')) {

        class wf_au_post_woocommerce_shipping_setup
        {
            public function __construct()
            {
                $this->wf_init();
                add_action('init', array($this, 'init'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'wf_plugin_action_links'));
                add_action('woocommerce_shipping_init', array($this, 'wf_australia_post_init'));
                add_action('admin_footer', array($this, 'wf_auspost_add_bulk_action_links'), 10); // Used 'admin_footer' action slug to add bulk actions in 'shop-order' page
                add_action('admin_footer', array($this, 'elex_auspost_add_startrack_bulk_action_links'), 10); // Used 'admin_footer' action slug to add bulk actions in 'shop-order' page
                add_filter('woocommerce_shipping_methods', array($this, 'wf_australia_post_add_method'));
                add_filter('admin_enqueue_scripts', array($this, 'wf_australia_post_scripts'));
                include_once('includes/class-wf-australia-post-shipping-custom-checkout-fields.php');
            }

            public function init()
            {
                if (!class_exists('wf_order')) {
                    include_once(__DIR__ . '/includes/class-wf-legacy.php');
                }

                if (!file_exists(ELEX_AUSPOST_LABELS)) {
                    mkdir(ELEX_AUSPOST_LABELS, 0777, true);
                }
            }

            public function wf_init()
            {
                include_once('includes/class-wf-tracking-admin.php');
                $this->settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                $this->contracted_rates = isset($this->settings['contracted_rates']) && ($this->settings['contracted_rates'] == 'yes') ? true : false;
                if ($this->contracted_rates) {
                    include_once('includes/class-wf-australia-post-shipping-admin.php');
                }

                if (is_admin()) {
                    //include api manager
                    include_once('includes/wf_api_manager/wf-api-manager-config.php');
                }
            }

            public function wf_plugin_action_links($links)
            {
                $plugin_links = array(
                    '<a href="' . admin_url('admin.php?page=' . wf_get_settings_url() . '&tab=shipping&section=wf_australia_post') . '">' . __('Settings', 'wf_australia_post') . '</a>',
                    '<a href="https://elextensions.com/plugin/woocommerce-australia-post-shipping-plugin-with-print-label-tracking/" target="_blank">' . __('Documentation', 'wf_australia_post') . '</a>',
                    '<a href="https://elextensions.com/support/" target="_blank">' . __('Support', 'wf_australia_post') . '</a>'
                );

                return array_merge($plugin_links, $links);
            }

            public function wf_australia_post_init()
            {
                include_once('includes/class-wf-australia-post-shipping.php');
                include_once('includes/class-elex-australia-post-functions.php');
            }

            public function wf_australia_post_add_method($methods)
            {
                $methods[] = 'wf_australia_post_shipping';
                return $methods;
            }

            public function wf_australia_post_scripts()
            {


                $page = (isset($_GET['page']) ? $_GET['page'] : '');
                if ($page == 'auspost_manifest') {
                    wp_enqueue_script('bootstrap_js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/modal.min.js', basename(__FILE__)), array(), '2.0.0', true);
                    wp_enqueue_style('model_css', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/modal.min.css'));
                }

                wp_enqueue_script('jquery-ui-sortable');
                wp_enqueue_script('jquery.validate.min', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/jquery.validate.min.js', basename(__FILE__)), array(), '2.0.0', true);
                wp_enqueue_script('elex-auspost-custom', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/elex-auspost-custom.js', basename(__FILE__)), array(), '2.0.0', true);
                wp_enqueue_script('wf_common', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/wf_common.js', basename(__FILE__)), array(), '2.0.0', true);
                wp_localize_script('elex-auspost-custom', 'elex_auspost_custom', array('contracted_rates' => ''));
                wp_enqueue_style('font-awesome.min', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/font-awesome/css/font-awesome.css'));

                wp_enqueue_script('paging', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/paging.js'));
            }

            /* Callback function for adding bulk actions in 'shop-order' page */
            function wf_auspost_add_bulk_action_links()
            {
                global $post_type;
                if ('shop_order' == $post_type) {
                    $settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                    if (!empty($settings)) {
                        $enable_shipping_label = isset($settings['enabled_label']) ? $settings['enabled_label'] : 'yes';
                        if ($enable_shipping_label === 'yes') {
?>
                            <script type="text/javascript">
                                jQuery(document).ready(function() {
                                    jQuery('<option>').val('create_auspost_shipment').text('<?php _e('Create Australia Post Shipment', 'wf-shipping-auspost') ?>').appendTo("select[name='action']");
                                });
                            </script>
                        <?php
                        }
                    }
                }
            }

            /* Callback function for adding bulk actions in 'shop-order' page */
            function elex_auspost_add_startrack_bulk_action_links()
            {
                global $post_type;
                if ('shop_order' == $post_type) {
                    $settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                    if (!empty($settings)) {
                        $enable_shipping_label = isset($settings['enabled_label']) ? $settings['enabled_label'] : 'yes';
                        if ($enable_shipping_label === 'yes' && isset($settings['wf_australia_post_starTrack_rates_selected']) && ($settings['wf_australia_post_starTrack_rates_selected'] == true)) {
                        ?>
                            <script type="text/javascript">
                                jQuery(document).ready(function() {
                                    jQuery('<option>').val('create_auspost_startrack_shipment').text('<?php _e('Create StarTrack Shipment', 'wf-shipping-auspost') ?>').appendTo("select[name='action']");
                                });
                            </script>
                <?php
                        }
                    }
                }
            }
        }
        new wf_au_post_woocommerce_shipping_setup();
    }

    if (!class_exists('wf_custom_woocommerce_fields')) {
        class  wf_custom_woocommerce_fields
        {

            public function __construct()
            {
                if (is_admin()) {
                    $this->init();
                    add_action('woocommerce_product_options_shipping', array($this, 'wf_additional_product_shipping_options'));
                    add_action('woocommerce_process_product_meta', array($this, 'wf_save_additional_product_shipping_options'));
                }
            }

            function wf_additional_product_shipping_options()
            {
                $this->settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                $is_rates_contracted = $this->settings['contracted_rates'];

                $dangerous_goods_description_startrack = (isset($this->settings['dangerous_goods_descriptions']) && !empty($this->settings['dangerous_goods_descriptions'])) ? $this->settings['dangerous_goods_descriptions'] : array();

                $dangerous_goods_un_numbers = array();
                if (!empty($dangerous_goods_description_startrack)) {
                    foreach ($dangerous_goods_description_startrack as $dangerous_goods_key => $dangerous_goods_value) {
                        $dangerous_goods_title = $dangerous_goods_value['technical_name'] . ' ' . $dangerous_goods_key;
                        $dangerous_goods_un_numbers[$dangerous_goods_key] = __($dangerous_goods_title, 'wf-shipping-auspost');
                    }
                }

                //Tariff code field
                woocommerce_wp_text_input(array(
                    'id' => '_wf_tariff_code',
                    'label' => __('Tariff Code (ELEX Australia Post)', 'wf-shipping-auspost'),
                    'description' => __('The Harmonized Commodity Description and Coding System, also known as the Harmonized System (HS) of tariff nomenclature is an internationally standardized system of names and numbers to classify traded products.'),
                    'desc_tip' => 'true',
                    'placeholder' => ''
                ));

                //Country of origin
                woocommerce_wp_text_input(array(
                    'id' => '_wf_country_of_origin',
                    'label' => __('Country of Origin (ELEX Australia Post)', 'wf-shipping-auspost'),
                    'description' => __('A note on the country of origin can be updated here. ', 'wf-shipping-auspost'),
                    'desc_tip' => 'true',
                    'placeholder' => ''
                ));

                woocommerce_wp_text_input(array(
                    'id' => '_wf_export_declaration_number',
                    'label' => __('Export Declaration Number (ELEX Australia Post)', 'wf-shipping-auspost'),
                    'description' => __('This will be part of the Shipping Label. ', 'wf-shipping-auspost'),
                    'desc_tip' => 'true',
                    'placeholder' => 'Enter export declaration number'
                ));

                //product shipping description
                woocommerce_wp_text_input(array(
                    'id' => '_wf_shipping_description',
                    'label' => __('Shipping Description (ELEX Australia Post)', 'wf-shipping-auspost'),
                    'description' => __('A note on shipping product. This will be part of the Shipping Label. ', 'wf-shipping-auspost'),
                    'desc_tip' => 'true',
                    'placeholder' => ''
                ));
                woocommerce_wp_text_input(array(
                    'id' => '_elex_aus_post_commodity_uuid',
                    'label' => __('Commodity uuid (ELEX Australia Post)', 'wf-shipping-auspost'),
                    'description' => __('This will be part of the International Export. ', 'wf-shipping-auspost'),
                    'desc_tip' => 'true',
                    'placeholder' => 'Enter commodity uuid'
                ));

                if ($is_rates_contracted) {

                    woocommerce_wp_checkbox(array(
                        'id' => '_dangerous_goods_check_auspost_elex',
                        'label' => __('Dangerous Goods (ELEX Australia Post)', 'wf-shipping-auspost'),
                        'description' => __('Enable this option to include Dangerous goods declaration. ', 'wf-shipping-auspost'),
                        'desc_tip' => false,
                    ));

                    // Dangerous Goods Description for Express Service Types
                    woocommerce_wp_select(array(
                        'id' => '_dangerous_goods_desciption_auspost_elex',
                        'label' => __('Dangerous Goods Declaration (ELEX Australia Post)', 'wf-shipping-auspost'),
                        'options' => array(
                            'UN2910' => __('Radio Active Excepted Limited Quantity UN2910', 'wf-shipping-auspost'),
                            'UN2911' => __('Radio Active Excepted Instruments/Articles UN2911', 'wf-shipping-auspost'),
                            'UN3373' => __('Bio-Substance B UN3373', 'wf-shipping-auspost'),
                            'UN3481' => __('Lithium Ion/Polymer Contained in Equipment UN3481', 'wf-shipping-auspost'),
                            'UN3091' => __('Lithium Metal & Alloy contained in Equipment UN3091', 'wf-shipping-auspost'),
                        ),
                        'description' => __('Please choose the type of Dangerous goods.', 'wf-shipping-auspost'),
                        'desc_tip' => false,
                    ));

                    if (isset($this->settings['enable_dangerous_goods_configuration_startrack']) && $this->settings['enable_dangerous_goods_configuration_startrack'] == 'yes') {
                        //Dangerous Goods Check
                        woocommerce_wp_checkbox(array(
                            'id' => '_dangerous_goods_check_startrack_auspost_elex',
                            'label' => __('Dangerous Goods (StarTrack) (ELEX Australia Post)', 'wf-shipping-auspost'),
                            'description' => __('Enable this option to include Dangerous goods declaration. ', 'wf-shipping-auspost'),
                            'desc_tip' => false,
                        ));

                        // Dangerous Goods Description for Express Service Types
                        woocommerce_wp_select(array(
                            'id' => '_dangerous_goods_desciption_startrack_auspost_elex',
                            'label' => __('Dangerous Goods Description (StarTrack) (ELEX Australia Post)', 'wf-shipping-auspost'),
                            'options' => $dangerous_goods_un_numbers,
                            'description' => __('Please choose the type of Dangerous goods.', 'wf-shipping-auspost'),
                            'desc_tip' => false,
                        ));
                    }

                    //Dangerous Goods Check
                    woocommerce_wp_checkbox(array(
                        'id' => 'age_check_auspost_elex',
                        'label' => __('Visual check of age (ELEX Australia Post)', 'wf-shipping-auspost'),
                        'description' => __('Order recipient\'s age must be over 18', 'wf-shipping-auspost'),
                        'desc_tip' => false,
                    ));
                }

                ?>
                <script>
                    function showHideDangerousGoodsDescription() {
                        <?php if ($is_rates_contracted) : ?>
                            if (jQuery('#_dangerous_goods_check_auspost_elex').is(":checked")) {
                                jQuery('._dangerous_goods_desciption_auspost_elex_field').show();
                            } else {
                                jQuery('._dangerous_goods_desciption_auspost_elex_field').hide();
                            }
                        <?php endif; ?>
                    }


                    function showHideDangerousGoodsDescriptionStarTrack() {
                        <?php if ($is_rates_contracted) : ?>
                            <?php if (isset($this->settings['wf_australia_post_starTrack_rates_selected']) || ($this->settings['wf_australia_post_starTrack_rates_selected'] == true)) : ?>
                                if (jQuery('#_dangerous_goods_check_startrack_auspost_elex').is(':checked')) {
                                    jQuery('._dangerous_goods_desciption_startrack_auspost_elex_field').show();
                                } else {
                                    jQuery('._dangerous_goods_desciption_startrack_auspost_elex_field').hide();
                                }
                            <?php endif; ?>
                        <?php endif; ?>
                    }

                    jQuery(document).ready(function() {
                        showHideDangerousGoodsDescription();
                        showHideDangerousGoodsDescriptionStarTrack();
                        jQuery('#_dangerous_goods_check_auspost_elex').change(function() {
                            showHideDangerousGoodsDescription();
                        });

                        jQuery('#_dangerous_goods_check_startrack_auspost_elex').change(function() {
                            showHideDangerousGoodsDescriptionStarTrack();
                        });
                    });
                </script>
<?php
            }

            function wf_save_additional_product_shipping_options($post_id)
            {
                //Tariff code value
                if (isset($_POST['_wf_tariff_code'])) {
                    update_post_meta($post_id, '_wf_tariff_code', esc_attr($_POST['_wf_tariff_code']));
                }
                //Country of manufacture
                if (isset($_POST['_wf_country_of_origin'])) {
                    update_post_meta($post_id, '_wf_country_of_origin', esc_attr($_POST['_wf_country_of_origin']));
                }

                if (isset($_POST['_wf_export_declaration_number'])) {
                    update_post_meta($post_id, '_wf_export_declaration_number', esc_attr($_POST['_wf_export_declaration_number']));
                }

                if (isset($_POST['_elex_aus_post_commodity_uuid'])) {
                    update_post_meta($post_id, '_elex_aus_post_commodity_uuid', esc_attr($_POST['_elex_aus_post_commodity_uuid']));
                }

                //Country of manufacture
                if (isset($_POST['_wf_shipping_description'])) {
                    update_post_meta($post_id, '_wf_shipping_description', esc_attr($_POST['_wf_shipping_description']));
                }

                // Save Dangerous Goods check
                if (isset($_POST['_dangerous_goods_check_auspost_elex'])) {
                    update_post_meta($post_id, '_dangerous_goods_check_auspost_elex', esc_attr($_POST['_dangerous_goods_check_auspost_elex']));
                } else {
                    update_post_meta($post_id, '_dangerous_goods_check_auspost_elex', '');
                }

                // Save Dangerous goods description 
                if (isset($_POST['_dangerous_goods_desciption_auspost_elex'])) {
                    update_post_meta($post_id, '_dangerous_goods_desciption_auspost_elex', esc_attr($_POST['_dangerous_goods_desciption_auspost_elex']));
                }

                // Save StarTrack Dangerous goods description 
                if (isset($_POST['_dangerous_goods_desciption_startrack_auspost_elex'])) {
                    update_post_meta($post_id, '_dangerous_goods_desciption_startrack_auspost_elex', esc_attr($_POST['_dangerous_goods_desciption_startrack_auspost_elex']));
                }

                // Save Dangerous goods check StarTrack
                if (isset($_POST['_dangerous_goods_check_startrack_auspost_elex'])) {
                    update_post_meta($post_id, '_dangerous_goods_check_startrack_auspost_elex', esc_attr($_POST['_dangerous_goods_check_startrack_auspost_elex']));
                } else {
                    update_post_meta($post_id, '_dangerous_goods_check_startrack_auspost_elex', FALSE);
                }


                // Save StarTrack Dangerous goods description StarTrack
                if (isset($_POST['_dangerous_goods_desciption_startrack_auspost_elex'])) {
                    update_post_meta($post_id, '_dangerous_goods_desciption_startrack_auspost_elex', esc_attr($_POST['_dangerous_goods_desciption_startrack_auspost_elex']));
                }

                // Save visual check option for AusPost
                if (isset($_POST['age_check_auspost_elex'])) {
                    update_post_meta($post_id, 'age_check_auspost_elex', esc_attr($_POST['age_check_auspost_elex']));
                } else {
                    update_post_meta($post_id, 'age_check_auspost_elex', FALSE);
                }
            }

            public function init()
            {
                include_once('includes/class-wf-tracking-admin.php');
                if (is_admin()) {
                    $settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                    $contracted_rates = !empty($settings) && is_array($settings) && isset($settings['contracted_rates']) && ($settings['contracted_rates'] == 'yes') ? true : false;
                    if ($contracted_rates) {
                        include_once('includes/class-wf-australia-post-shipping-admin.php');
                    }
                    //include api manager
                    include_once('includes/wf_api_manager/wf-api-manager-config.php');
                }
            }
        }
        new wf_custom_woocommerce_fields();
    }
}
if (!defined('ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS')) {  //Add-on for woocommerce return label.
    if (in_array('elex-australia-post-woocommerce-return-labels-addon/elex-australia-post-woocommerce-return-labels-addon.php', get_option('active_plugins'))) {
        define('ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS', TRUE);
    } else {
        define('ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS', FALSE);
    }
}
//Return label add-on path
if (defined('ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS')) {
    if (!defined('ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH')) {
        define("ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH", WP_PLUGIN_DIR . "/elex-australia-post-woocommerce-return-labels-addon/");
    }
}


add_action('admin_menu', 'register_auspost_bulk_order_manifest_menu');

function register_auspost_bulk_order_manifest_menu()
{
    add_submenu_page('woocommerce', 'AusPost Manifest', 'AusPost Manifest', 'manage_woocommerce', 'auspost_manifest', 'auspost_bulk_order_manifest_menu_page');
}

function auspost_bulk_order_manifest_menu_page()
{
    include_once('includes/manifest_html.php');
}
