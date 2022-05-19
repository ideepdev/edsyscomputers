<?php

class WF_auspost_non_contracted_services{

    public function __construct(){}

    /** Services called from 'services' API without options */
    protected $services = array(
        // Domestic
        'AUS_PARCEL_REGULAR' => array(
            // Name of the service shown to the user
            'name' => 'Regular / Parcel Post',
            // Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
            'alternate_services' => array(
                'AUS_PARCEL_REGULAR_SATCHEL_500G' => array('name' => 'Regular Satchel 500G', 'enable' => false),
                'AUS_PARCEL_REGULAR_SATCHEL_1KG' => array('name' => 'Regular Satchel 1KG', 'enable' => false),
                'AUS_PARCEL_REGULAR_SATCHEL_3KG' => array('name' => 'Regular Satchel 3KG', 'enable' => false),
                'AUS_PARCEL_REGULAR_SATCHEL_5KG' => array('name' => 'Regular Satchel 5KG', 'enable' => false),
                'AUS_LETTER_REGULAR_SMALL'      => array('name' => 'Regular Letter Small', 'enable' => false),
                'AUS_LETTER_REGULAR_MEDIUM'     => array('name' => 'Regular Letter Medium', 'enable' => false),
                'AUS_LETTER_REGULAR_LARGE'      => array('name' => 'Regular Letter Large', 'enable' => false),
                'AUS_LETTER_REGULAR_LARGE_125'  => array('name' => 'Regular Letter Large 125', 'enable' => false),
                'AUS_LETTER_REGULAR_LARGE_250'  => array('name' => 'Regular Letter Large 250', 'enable' => false),
                'AUS_LETTER_REGULAR_LARGE_500'  => array('name' => 'Regular Letter Large 500', 'enable' => false),
            ),
            'eligible_for' => 'domestic'
        ),
        'AUS_PARCEL_EXPRESS' => array(
            // Name of the service shown to the user
            'name' => 'Express Post',
            // Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
            'alternate_services' => array(
                'AUS_PARCEL_EXPRESS_SATCHEL_SMALL' => array('name' => 'Express Satchel Small', 'enable' => false),
                'AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM' => array('name' => 'Express Satchel Medium', 'enable' => false),
                'AUS_PARCEL_EXPRESS_SATCHEL_LARGE' => array('name' => 'Express Satchel Large', 'enable' => false),
                'AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE' => array('name' => 'Express Satchel Extra Large', 'enable' => false),
                'AUS_LETTER_EXPRESS_SMALL' => array('name' => 'Express Letter Small', 'enable' => false),
                'AUS_LETTER_EXPRESS_MEDIUM' => array('name' => 'Express Letter Medium', 'enable' => false),
                'AUS_LETTER_EXPRESS_LARGE' => array('name' => 'Express Letter Large', 'enable' => false),
            ),
            'eligible_for' => 'domestic'
        ),
        'AUS_PARCEL_COURIER' => array(
            // Name of the service shown to the user
            'name' => 'Courier Post',
            // Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
            'alternate_services' => array(
                'AUS_PARCEL_COURIER_SATCHEL_SMALL' => array('name' => 'Courier Satchel Small', 'enable' => false),
                'AUS_PARCEL_COURIER_SATCHEL_MEDIUM' => array('name' => 'Courier Satchel Medium', 'enable' => false),
                'AUS_PARCEL_COURIER_SATCHEL_LARGE' => array('name' => 'Courier Satchel Large', 'enable' => false),
            ),
            'eligible_for' => 'domestic'
        ),
        'INT_PARCEL_COR_OWN_PACKAGING' => array(
            'name' => 'International Post Courier',
            'eligible_for' => 'international'
        ),
        'INT_PARCEL_EXP_OWN_PACKAGING' => array(
            'name' => 'International Post Express',
            'eligible_for' => 'international'
        ),
        'INT_PARCEL_STD_OWN_PACKAGING' => array(
            'name' => 'International Post Standard',
            'eligible_for' => 'international'
        ),
        'INT_PARCEL_AIR_OWN_PACKAGING' => array(
            'name' => 'International Post Economy Air',
            'eligible_for' => 'international'
        ),
        'INT_LETTER_COR_OWN_PACKAGING' => array(
            'name' => 'International Letter Courier',
            'eligible_for' => 'international'
        ),
        'INT_LETTER_EXP_OWN_PACKAGING' => array(
            'name' => 'International Letter Express',
            'eligible_for' => 'international'
        ),
        'INT_LETTER_REG_SMALL_ENVELOPE' => array(
            'name' => 'International Letter Registered Post DL',
            'eligible_for' => 'international'
        ),
        'INT_LETTER_REG_LARGE_ENVELOPE' => array(
            'name' => 'International Letter Registered Post B4',
            'eligible_for' => 'international'
        ),
        'INT_LETTER_AIR_OWN_PACKAGING_LIGHT' => array(
            'name' => 'International Letter Economy Air',
            'alternate_services' => array(
                'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM' => array('name' => 'International Letter Air Own Packaging Medium', 'enable' => false),
                'INT_LETTER_AIR_OWN_PACKAGING_HEAVY' => array('name' => 'International Letter Air Own Packaging Heavy', 'enable' => false),
                'INT_LETTER_AIR_SMALL_ENVELOPE' => array('name' => 'International Letter Air Small Envelope', 'enable' => false),
                'INT_LETTER_AIR_LARGE_ENVELOPE' => array('name' => 'International Letter Air Large Envelope', 'enable' => false)
            ),
            'eligible_for' => 'international'
        ),
        'E34' => array(
            'name' => 'EXPRESS POST',
            'eligible_for' => 'domestic'
        ),
        'T28S' => array(
            'name' => 'PARCEL POST W/SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        'E34S' => array(
            'name' => 'EXPRESS POST W/SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        'RPI6' => array(
            'name' => 'REGISTERED POST INTL 6 ',
            'eligible_for' => 'international'
        ),
        'PTI8' => array(
            'name' => 'PACK & TRACK INT\'L 8 ',
            'eligible_for' => 'international'
        ),
        '7C55' => array(
            'name' => 'PARCEL POST + SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        '7I55' => array(
            'name' => 'EXPRESS POST + SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        'RPI8' => array(
            'name' => 'REGISTERED POST INT\'L 8',
            'eligible_for' => 'international'
        ),
        'ECM8' => array(
            'name' => 'EXPRESS COURIER INT\'L MERCH 8Z',
            'eligible_for' => 'international'
        ),
        'AIR8' => array(
            'name' => 'INTERNATIONAL AIRMAIL 8Z',
            'eligible_for' => 'international'
        ),
        'ECD8' => array(
            'name' => 'EXPRESS COURIER INT\'L DOC 8',
            'eligible_for' => 'international'
        ),
        // Not providing for non-contracted accounts
//      'X1' => array(
//            'name' => 'EXPRESS POST EPARCEL'
//            ),    
        'XS' => array(
            'name' => 'EXPRESS POST SATCHELS',
            'eligible_for' => 'domestic'
        ),
        // Not providing for non-contracted accounts
//      'S1' => array(
//            'name' => 'EPARCEL 1'
//            ),     
        '7B05' => array(
            'name' => 'PARCEL POST + SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        '7H05' => array(
            'name' => 'EXPRESS POST + SIGNATURE',
            'eligible_for' => 'domestic'
        ),
        'NFR' => array(
            'name' => 'NATIONAL FULL RATE',
            'eligible_for' => 'domestic'
        ),
        'XNFR' => array(
            'name' => 'EXPRESS NATIONAL FULL RATE',
            'eligible_for' => 'domestic'
        ),
            /*

              'INT_PARCEL_SEA_OWN_PACKAGING' => array(
              'name' => 'Sea Mail'
              ), */
    );

    protected $extra_cover = array(
        'INT_PARCEL_AIR_OWN_PACKAGING' => 500,
        'INT_PARCEL_STD_OWN_PACKAGING' => 5000,
        'INT_PARCEL_COR_OWN_PACKAGING' => 5000,
        'INT_PARCEL_EXP_OWN_PACKAGING' => 5000,
        'AUS_PARCEL_REGULAR' => 5000,
        'AUS_PARCEL_COURIER' => 5000,
        'AUS_PARCEL_EXPRESS' => 5000,
        'AUS_PARCEL_COURIER' => 5000
    );

    protected $delivery_confirmation = array(
        'INT_PARCEL_SEA_OWN_PACKAGING',
        'INT_PARCEL_AIR_OWN_PACKAGING',
        'INT_PARCEL_STD_OWN_PACKAGING',
        'AUS_PARCEL_REGULAR',
        'AUS_PARCEL_EXPRESS',
    );

    //satchel/letter rates (alternate rates)  
    protected $non_contracted_alternate_services = array(
        'AUS_PARCEL_EXPRESS_SATCHEL_SMALL' => array('name' => 'Express Satchel Small', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM' => array('name' => 'Express Satchel Medium', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_PARCEL_EXPRESS_SATCHEL_LARGE' => array('name' => 'Express Satchel Large', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE' => array('name' => 'Express Satchel Extra Large', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_LETTER_EXPRESS_SMALL' => array('name' => 'Express Letter Small', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_LETTER_EXPRESS_MEDIUM' => array('name' => 'Express Letter Medium', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_LETTER_EXPRESS_LARGE' => array('name' => 'Express Letter Large', 'enable' => false, 'main_service' => 'AUS_PARCEL_EXPRESS'),
        'AUS_PARCEL_REGULAR_SATCHEL_500G' => array('name' => 'Regular Satchel 500G', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_PARCEL_REGULAR_SATCHEL_1KG' => array('name' => 'Regular Satchel 1KG', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_PARCEL_REGULAR_SATCHEL_3KG' => array('name' => 'Regular Satchel 3KG', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_PARCEL_REGULAR_SATCHEL_5KG' => array('name' => 'Regular Satchel 5KG', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_SMALL' => array('name' => 'Regular Letter Small', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_MEDIUM' => array('name' => 'Regular Letter Medium', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_LARGE' => array('name' => 'Regular Letter Large', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_LARGE_125' => array('name' => 'Regular Letter Large 125', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_LARGE_250' => array('name' => 'Regular Letter Large 250', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_LETTER_REGULAR_LARGE_500' => array('name' => 'Regular Letter Large 500', 'enable' => false, 'main_service' => 'AUS_PARCEL_REGULAR'),
        'AUS_PARCEL_COURIER_SATCHEL_SMALL' => array('name' => 'Courier Satchel Small', 'enable' => false, 'main_service' => 'AUS_PARCEL_COURIER'),
        'AUS_PARCEL_COURIER_SATCHEL_MEDIUM' => array('name' => 'Courier Satchel Medium', 'enable' => false, 'main_service' => 'AUS_PARCEL_COURIER'),
        'AUS_PARCEL_COURIER_SATCHEL_LARGE' => array('name' => 'Courier Satchel Large', 'enable' => false, 'main_service' => 'AUS_PARCEL_COURIER'),
        'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM' => array('name' => 'International Letter Air Own Packaging Medium', 'enable' => false, 'main_service' => 'INT_LETTER_AIR_OWN_PACKAGING_LIGHT'),
        'INT_LETTER_AIR_OWN_PACKAGING_HEAVY' => array('name' => 'International Letter Air Own Packaging Heavy', 'enable' => false, 'main_service' => 'INT_LETTER_AIR_OWN_PACKAGING_LIGHT'),
        'INT_LETTER_AIR_SMALL_ENVELOPE' => array('name' => 'International Letter Air Small Envelope', 'enable' => false, 'main_service' => 'INT_LETTER_AIR_OWN_PACKAGING_LIGHT'),
        'INT_LETTER_AIR_LARGE_ENVELOPE' => array('name' => 'International Letter Air Large Envelope', 'enable' => false, 'main_service' => 'INT_LETTER_AIR_OWN_PACKAGING_LIGHT')
    );

    public function get_services(){
        return $this->services;
    }

    public function get_extra_cover(){
        return $this->extra_cover;
    }

    public function get_delivery_confirmation(){
        return $this->delivery_confirmation;
    }

    public function get_non_contrcated_alternate_services(){
        return $this->non_contracted_alternate_services;
    }
}