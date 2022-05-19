<?php
/*
*   PD box sizes for Satchels ( < 5KG ) and Envelopes
****************************************************************************
*   These dimensions are obtained from the Australia Post. For some of the Satchels,
*   the height is provided by the comparing short girth of the item
*   with satchel_width_allawance and long girth of the item with satchel_length_allawance
*   Satchel girth
*   1) ((Satchel Width x 2) - 1)
*   2) ((Satchel Length x 2) - 1)
*   Package Girth
*   1) (Shortest + Mid Dimension)*2
*   2) (Shortest + Longest Dimension)*2
*   Package Girth <= Satchel girth
****************************************************************************
*/
return array(
	//Satchels
	'Flat Rate Satchel 5kg (Extra Large)'    =>  array(
		'order'         => 1,
		'name'          => 'Flat Rate Satchel 5kg (Extra Large)',
		'outer_length'  => 51,
		'outer_width'   => 44,
		'outer_height'  => 5,
		'inner_length'  => 51,
		'inner_width'   => 44,
		'inner_height'  => 5,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Satchel 5kg (Large)'     =>  array(
		'order'         => 2,
		'name'          => 'Flat Rate Satchel 5kg (Large)',
		'outer_length'  => 40.5,
		'outer_width'   => 31.5,
		'outer_height'  => 5,
		'inner_length'  => 40.5,
		'inner_width'   => 31.5,
		'inner_height'  => 5,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Satchel 5kg (Medium)'     =>  array(
		'order'         => 3,
		'name'          => 'Flat Rate Satchel 5kg (Medium)',
		'outer_length'  => 39,
		'outer_width'   => 27,
		'outer_height'  => 5,
		'inner_length'  => 39,
		'inner_width'   => 27,
		'inner_height'  => 5,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Satchel 5kg (Small)'  =>  array(
		'order'         => 4,
		'name'          => 'Flat Rate Satchel 5kg (Small)',
		'outer_length'  => 35.5,
		'outer_width'   => 22.5,
		'outer_height'  => 5,
		'inner_length'  => 35.5,
		'inner_width'   => 22.5,
		'inner_height'  => 5,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Box (Extra Large)'   =>  array(
		'order'         => 5,
		'name'          => ' Flat Rate Box (Extra Large)',
		'outer_length'  => 44,
		'outer_width'   => 27.7,
		'outer_height'  => 16.8,
		'inner_length'  => 44,
		'inner_width'   => 27.7,
		'inner_height'  => 16.8,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Box (Large)'  =>  array(
		'order'         => 6,
		'name'          => 'Flat Rate Box (Large)',
		'outer_length'  => 39,
		'outer_width'   => 28,
		'outer_height'  => 14,
		'inner_length'  => 39,
		'inner_width'   => 28,
		'inner_height'  => 14,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	'Flat Rate Box (Medium)'   =>  array(
		'order'         => 7,
		'name'          => 'Flat Rate Box (Medium)',
		'outer_length'  => 24,
		'outer_width'   => 19,
		'outer_height'  => 12,
		'inner_length'  => 24,
		'inner_width'   => 19,
		'inner_height'  => 12,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
	
	'Flat Rate Satchel 5kg (Small)'     =>  array(
		'order'         => 8,
		'name'          => 'Flat Rate Satchel 5kg (Small)',
		'outer_length'  => 22,
		'outer_width'   => 16,
		'outer_height'  => 7,
		'inner_length'  => 22,
		'inner_width'   => 16,
		'inner_height'  => 7,
		'box_weight'    => 0,
		'max_weight'    => 5,
		'is_enabled'    => false,
		'is_letter'     => false,
		'pack_type'     => 'PD',//Pre-defined
		'eligible_for'  => 'Domestic'
	),
);
