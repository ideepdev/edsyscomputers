<?php

class WF_ausmypost_services {

	public function __construct(){}

	/** Services called from 'services' API without options */
	protected $services = array(
		'B20' => array(
			'name' => 'Express Post(5kg Max)',
			'eligible_for' => 'domestic'
		),
		'B21' => array(
			'name' => 'Express Post (22kg Max)',
			'eligible_for' => 'domestic'
		),
		'B30' => array(
			'name' => 'Parcel Post(5kg)',
			'eligible_for' => 'domestic'
		),
		'B31' => array(
			'name' => 'Parcel Post(22kg)',
			'eligible_for' => 'domestic'
		),
		'BE9PB4' => array(
			'name' => 'Express Post Flat Rate Box (Extra Large)',
			'eligible_for' => 'domestic'
		),
		'BE9PB3' => array(
			'name' => 'Express Post Flat Rate Box (Large)',
			'eligible_for' => 'domestic'
		),
		'BE9PB2' => array(
			'name' => 'Express Post Flat Rate Box (Medium)',
			'eligible_for' => 'domestic'
		),
		'BE9PB1' => array(
			'name' => 'Express Post Flat Rate Box (Small)',
			'eligible_for' => 'domestic'
		),
		'BE9P50' => array(
			'name' => 'Express Post Flat Rate Satchel 5kg (Extra Large)',
			'eligible_for' => 'domestic'
		),
		'BE9P30' => array(
			'name' => 'Express Post Flat Rate Satchel 5kg (Large)',
			'eligible_for' => 'domestic'
		),
		'BE9P10' => array(
			'name' => 'Express Post Flat Rate Satchel 5kg (Medium)',
			'eligible_for' => 'domestic'
		),
		'BE9P05' => array(
			'name' => 'Express Post Flat Rate Satchel 5kg (Small)',
			'eligible_for' => 'domestic'
		),
		'BE1PB4' => array(
			'name' => 'Parcel Post Flat Rate Box (Extra Large)',
			'eligible_for' => 'domestic'
		),
		'BE1PB3' => array(
			'name' => 'Parcel Post Flat Rate Box (Large)',
			'eligible_for' => 'domestic'
		),
		'BE1PB2' => array(
			'name' => 'Parcel Post Flat Rate Box (Medium)',
			'eligible_for' => 'domestic'
		),
		'BE1PB1' => array(
			'name' => 'Parcel Post Flat Rate Box (Small)',
			'eligible_for' => 'domestic'
		),
		'BE1P50' => array(
			'name' => 'Parcel Post Flat Rate Satchel 5kg (Extra Large)',
			'eligible_for' => 'domestic'
		),
		'BE1P30' => array(
			'name' => 'Parcel Post Flat Rate Satchel 5kg (Large)',
			'eligible_for' => 'domestic'
		),
		'BE1P10' => array(
			'name' => 'Parcel Post Flat Rate Satchel 5kg (Medium)',
			'eligible_for' => 'domestic'
		),
		'BE1P05' => array(
			'name' => 'Parcel Post Flat Rate Satchel 5kg (Small)',
			'eligible_for' => 'domestic'
		),
		'I63' => array(
			'name' => 'International Parcels - Economy Air',
			'eligible_for' => 'international'
		),
		'I64' => array(
			'name' => 'International Parcels - Standard Small',
			'eligible_for' => 'international'
		),
		'I65' => array(
			'name' => 'International Parcels - Standard Large',
			'eligible_for' => 'international'
		),
		'I66' => array(
			'name' => 'International Parcels - Express Doc',
			'eligible_for' => 'international'
		),
		'I67' => array(
			'name' => 'International Parcels - Express Merch',
			'eligible_for' => 'international'
		),
	);

	public function get_services() {
		return $this->services;
	}

	public function get_extra_cover() {
		return $this->extra_cover;
	}

	public function get_delivery_confirmation() {
		return $this->delivery_confirmation;
	}

	public function get_non_contrcated_alternate_services() {
		return $this->non_contracted_alternate_services;
	}
}
