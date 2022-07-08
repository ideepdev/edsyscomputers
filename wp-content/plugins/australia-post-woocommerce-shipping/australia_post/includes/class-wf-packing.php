<?php

/**
 * WooCommerce Box Packer
 */
class WF_Boxpack {

	private $boxes;
	private $items = array();
	private $packages;
	private $cannot_pack;
	private $mode ='volume_based';
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $mode = null) {
	$this->mode =$mode;
	}

	/**
	 * Output a message
	 */
	public function debug( $message, $type = 'notice') {                        
		echo( esc_attr( $message ) );
	}

	/**
	 * Clear_items function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_items() {
		$this->items = array();
	}

	/**
	 * Clear_boxes function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_boxes() {
		$this->boxes = array();
	}

	/**
	 * Add_item function.
	 *
	 * @access public
	 * @return void
	 */
	public function add_item( $length, $width, $height, $weight, $value = '', $meta = array() ) {
		$this->items[] = new WF_Boxpack_Item( $length, $width, $height, $weight, $value, $meta );
	}

	/**
	 * Add_box function.
	 *
	 * @access public
	 * @param mixed $length
	 * @param mixed $width
	 * @param mixed $height
	 * @param mixed $weight
	 * @return void
	 */
	public function add_box( $length, $width, $height, $weight, $packtype, $box_type = '') {
		$weight        = $weight? $weight : 0 ;
		$new_box       = new WF_Boxpack_Box( $length, $width, $height, $weight, $packtype, $box_type );
		$this->boxes[] = $new_box;
		return $new_box;
	}

	/**
	 * Get_packages function.
	 *
	 * @access public
	 * @return void
	 */
	public function get_packages() {
		return $this->packages ? $this->packages : array();
	}


	/**
	 * Get_cannot_pack function.
	 * function to get unpacked to items
	 *
	 * @access public
	 * @return void
	 */
	public function get_cannot_pack() {
		return $this->cannot_pack ? $this->cannot_pack : array();
	}

	/**
	 * Pack function.
	 *
	 * @access public
	 * @return void
	 */
	public function pack() {
		try {
			// We need items
			if ( sizeof( $this->items ) == 0 ) {
				throw new Exception( 'No items to pack!' );
			}
			$packtype = '';
			// Clear packages
			$this->packages = array();

			// Order the boxes by volume
			$this->boxes = $this->order_boxes( $this->boxes );

			if ( ! $this->boxes ) {
				$this->cannot_pack = $this->items;
				$this->items       = array();
				$packtype          = 'box';
			}

			// Keep looping until packed
			while ( sizeof( $this->items ) > 0 ) {
				$this->items       = $this->order_items($this->items);
				$possible_packages = array();
				$best_package      = '';

				// Attempt to pack all items in each box
				foreach ( $this->boxes as $box ) {
					$possible_packages[] = $box->pack( $this->items , $this->mode );
				}

				// Find the best success rate
				$best_percent = 0;

				foreach ( $possible_packages as $package ) {
					if ( $package->percent > $best_percent ) {
						$best_percent = $package->percent;
					}
				}

				if ( 0 == $best_percent ) {
					$this->cannot_pack = $this->items;
					$this->items       = array();
				} else {
					// Get smallest box with best_percent
					$possible_packages = array_reverse( $possible_packages );

					foreach ( $possible_packages as $package ) {
						if ( $package->percent == $best_percent ) {
							$best_package = $package;
							break; // Done packing
						}
					}

					// Update items array
					$this->items = $best_package->unpacked;

					// Store package
					$this->packages[] = $best_package;
				}
			}

			/* Filtering the packed boxes for repeated same quantity values*/
			foreach ($this->packages as $packed_package) {

				$packed_product_ids_array = array();
				$boxes_packed             = $packed_package->packed;
				foreach ($boxes_packed as $box_packed) {
					$box_packed_meta         = $box_packed->meta;
					$box_packed_meta_data    = $box_packed_meta['data'];
					$box_packed_product_data = $box_packed_meta_data->get_data();
					if (isset($box_packed_product_data['variation_id']) && !empty($box_packed_product_data['variation_id'])) {
						$packed_product_ids_array[] = $box_packed_product_data['variation_id'];
					} elseif (isset($box_packed_product_data['parent_id']) && !empty($box_packed_product_data['parent_id'])) {
						$packed_product_ids_array[] = $box_packed_product_data['parent_id'];
					} else {
						$packed_product_ids_array[] = $box_packed_product_data['id'];
					}
				}

				$packed_product_ids_array_unique         = array_unique($packed_product_ids_array);
				$packed_product_ids_array_content_values = array_count_values($packed_product_ids_array);

				foreach ($packed_product_ids_array_content_values as $packed_product_ids_array_content_values_key => $packed_product_ids_array_content_values_elements) {
					foreach ($boxes_packed as $box_packed) {
						$box_packed_meta         = $box_packed->meta;
						$box_packed_meta_data    = $box_packed_meta['data'];
						$box_packed_product_data = $box_packed_meta_data->get_data();
						if (isset($box_packed_product_data['variation_id']) && ( $box_packed_product_data['variation_id'] == $packed_product_ids_array_content_values_key )) {
							$box_packed->packed_quantity = $packed_product_ids_array_content_values[$packed_product_ids_array_content_values_key];
							break;
						} elseif (isset($box_packed_product_data['parent_id']) && ( $box_packed_product_data['parent_id'] == $packed_product_ids_array_content_values_key )) {
							$box_packed->packed_quantity = $packed_product_ids_array_content_values[$packed_product_ids_array_content_values_key];
							break;
						} elseif ($box_packed_product_data['id'] && ( $box_packed_product_data['id'] == $packed_product_ids_array_content_values_key )) {
							$box_packed->packed_quantity = $packed_product_ids_array_content_values[$packed_product_ids_array_content_values_key];
							break;
						}
					}
				}
				
				$packed_package->packed = $boxes_packed;
			}

			// Items we cannot pack (by now) get packaged individually
			if ( $this->cannot_pack ) {
				foreach ( $this->cannot_pack as $item ) {
					$meta      = $item->meta;
					$meta_data = $meta['data'];
					$item_data = $meta_data->get_data();
					$item_id   = $meta_data->get_id();

					$package               = new stdClass();
					$package->product_id   = ( isset($item_data['product_id']) && !empty($item_data['product_id']) )? $item_data['product_id']: ( ( isset($item_id) && !empty($item_id) )? $item_id: '' );
					$package->variation_id = ( isset($item_data['variation_id']) && !empty($item_data['variation_id']) )? $item_data['variation_id']: '';
					$package->quantity     = ( isset($item_data['quantity']) && !empty($item_data['quantity']) )? $item_data['quantity']: '';
					$package->weight       = $item->get_weight();
					$package->length       = $item->get_length();
					$package->width        = $item->get_width();
					$package->height       = $item->get_height();
					$package->value        = $item->get_value();
					$package->packtype     = $packtype;
					$package->unpacked     = true;
					$this->packages[]      = $package;
				}
			}

		} catch (Exception $e) {
			//echo 'Packing error: ',  $e->getMessage(), "\n";
		}
	}

	/**
	 * Order boxes by weight and volume
	 * $param array $sort
	 *
	 * @return array
	 */
	private function order_boxes( $sort ) {
		if ( ! empty( $sort ) ) {
			uasort( $sort, array( $this, 'box_sorting' ) );
		}
		return $sort;
	}

	/**
	 * Order items by weight and volume
	 * $param array $sort
	 *
	 * @return array
	 */
	private function order_items( $sort ) {
		if ( ! empty( $sort ) ) {
			uasort( $sort, array( $this, 'item_sorting' ) );
		}
		return $sort;
	}

	/**
	 * Order_by_volume function.
	 *
	 * @access private
	 * @return void
	 */
	private function order_by_volume( $sort ) {
		if ( ! empty( $sort ) ) {
			uasort( $sort, array( $this, 'volume_based_sorting' ) );
		}
		return $sort;
	}

	/**
	 * Item_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function item_sorting( $a, $b ) {
		if ( $a->get_volume() == $b->get_volume() ) {
			if ( $a->get_weight() == $b->get_weight() ) {
				return 0;
			}
			return ( $a->get_weight() < $b->get_weight() ) ? 1 : -1;
		}
		return ( $a->get_volume() < $b->get_volume() ) ? 1 : -1;
	}

	/**
	 * Box_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function box_sorting( $a, $b ) {
		if ( $a->get_volume() == $b->get_volume() ) {
			if ( $a->get_max_weight() == $b->get_max_weight() ) {
				return 0;
			}
			return ( $a->get_max_weight() < $b->get_max_weight() ) ? 1 : -1;
		}
		return ( $a->get_volume() < $b->get_volume() ) ? 1 : -1;
	}

	/**
	 * Volume_based_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function volume_based_sorting( $a, $b ) {
		if ( $a->get_volume() == $b->get_volume() ) {
			return 0;
		}
		return ( $a->get_volume() < $b->get_volume() ) ? 1 : -1;
	}

}

/**
 * WF_Boxpack_Box class.
 */
class WF_Boxpack_Box {

	/**
	 * ID of the box - given to packages 
	 * 
	 * @var string ID of the box - given to packages 
	*/
	private $id = '';
	
	/**
	 * Name of the box - given to packages
	 * 
	 * @var string name of the box - given to packages 
	*/
	private $name              = '';
	private $maybe_packed_name = '';

	/**
	 * Weight of the box itself 
	 *
	 * @var float Weight of the box itself 
	 */
	private $weight;

	/**
	 * Max allowed weight of box + contents 
	 * 
	 * @var float Max allowed weight of box + contents 
	 * */
	private $max_weight = 0;

	/**
	 * Outer dimension of box sent to shipper
	 * 
	 * @var float Outer dimension of box sent to shipper 
	 */
	private $outer_height;

	/** 
	 * Outer dimension of box sent to shipper
	 * 
	 * @var float Outer dimension of box sent to shipper 
	 */
	private $outer_width;

	/** 
	 * Outer dimension of box sent to shipper
	 * 
	 * @var float Outer dimension of box sent to shipper 
	 */
	private $outer_length;

	/** 
	 * Inner dimension of box used when packing 
	 * 
	 * @var float Inner dimension of box used when packing 
	 */
	private $height;

	/**
	 * Inner dimension of box used when packing
	 * 
	 * @var float Inner dimension of box used when packing 
	 */
	private $width;

	/** 
	 * Inner dimension of box used when packing
	 * 
	 * @var float Inner dimension of box used when packing 
	 */
	private $length;

	/** 
	 * Dimension is stored here if adjusted during packing
	 * 
	 * @var float Dimension is stored here if adjusted during packing 
	 */
	private $packed_height;
	private $maybe_packed_height = null;

	/** 
	 * Dimension is stored here if adjusted during packing
	 * 
	 * @var float Dimension is stored here if adjusted during packing 
	 */
	private $packed_width;
	private $maybe_packed_width = null;

	/** 
	 * Dimension is stored here if adjusted during packing
	 * 
	 * @var float Dimension is stored here if adjusted during packing 
	 */
	private $packed_length;
	private $maybe_packed_length = null;

	/** 
	 *  Volume of the box
	 * 
	 * @var float Volume of the box 
	 */
	private $volume;

	/** 
	 * Array Valid box types which affect packing
	 * 
	 * @var Array Valid box types which affect packing 
	 */
	private $valid_types = array( 'box', 'tube', 'envelope', 'packet' );

	/** 
	 * This box type
	 * 
	 * @var string This box type
	 */
	private $type = 'box';

	/** 
	 * This box pack type
	 * 
	 * @var string This box pack type 
	 */
	private $packtype;

	/** 
	 * This box type 
	 * 
	 * @var string This box type 
	 */
	private $boxtype;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $length, $width, $height, $weight, $packtype ) {
		$dimensions = array( $length, $width, $height );
		// sort( $dimensions );

		$this->outer_length = $this->length = $dimensions[0];
		$this->outer_width  = $this->width  = $dimensions[1];
		$this->outer_height = $this->height = $dimensions[2];
		$this->weight       = $weight;
		$this->packtype     = $packtype;
	}

	/**
	 * Set_id function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Set the volume to a specific value, instead of calculating it.
	 *
	 * @param float $volume
	 */
	public function set_volume( $volume ) {
		$this->volume = floatval( $volume );
	}

	/**
	 * Set the type of box
	 *
	 * @param string $type
	 */
	public function set_type( $type ) {
		if ( in_array( $type, $this->valid_types ) ) {
			$this->type = $type;
		}
	}

	/**
	 * Get max weight.
	 *
	 * @return float
	 */
	public function get_max_weight() {
		return floatval( $this->max_weight );
	}

	/**
	 * Set_max_weight function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_max_weight( $weight ) {
		$this->max_weight = $weight;
	}

	/**
	 * Set_inner_dimensions function.
	 *
	 * @access public
	 * @param mixed $length
	 * @param mixed $width
	 * @param mixed $height
	 * @return void
	 */
	public function set_inner_dimensions( $length, $width, $height ) {
		$dimensions = array( $length, $width, $height );

		sort( $dimensions );

		$this->length = $dimensions[2];
		$this->width  = $dimensions[1];
		$this->height = $dimensions[0];
	}
	
	
	/**
	 * Set_name function.
	 *
	 * @access public
	 * @param mixed $name
	 * @return void
	 */
	public function set_name( $name) {
		$this->name = $name;
	}
	
	/**
	 * Get_name function.
	 *
	 * @access public
	 * @return mixed $name
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set_boxtype function.
	 *
	 * @access public
	 * @param mixed $boxtype
	 * @return void
	 */
	public function set_boxtype( $boxtype) {
		$this->boxtype = $boxtype;
	}

	/**
	 * Get_boxtype function.
	 *
	 * @access public
	 * @return mixed $name
	 */
	public function get_boxtype() {
		return $this->boxtype;
	}

	/**
	 * See if an item fits into the box.
	 *
	 * @param object $item
	 * @return bool
	 */
	public function can_fit( $item ) {
		switch ( $this->type ) {
			// Tubes are designed for long thin items so see if the item meets that criteria here.
			case 'tube':
				$can_fit = ( $this->get_length() >= $item->get_length() && $this->get_width() >= $item->get_width() && $this->get_height() >= $item->get_height() && $item->get_volume() < $this->get_volume() ) ? true : false;
				$can_fit = $can_fit && $item->get_length() >= ( ( $item->get_width() + $this->get_height() ) * 2 );
				break;
			// Packets are flexible
			case 'packet':
				$can_fit = ( $this->get_packed_length() >= $item->get_length() && $this->get_packed_width() >= $item->get_width() && $item->get_volume() < $this->get_volume() ) ? true : false;

				if ( $can_fit && $item->get_height() > $this->get_packed_height() ) {
					$this->maybe_packed_height = $item->get_height();
					$this->maybe_packed_length = $this->get_packed_length() - ( $this->maybe_packed_height - $this->get_height() );
					$this->maybe_packed_width  = $this->get_packed_width()  - ( $this->maybe_packed_height - $this->get_height() );

					$can_fit = ( $this->maybe_packed_height < $this->maybe_packed_width && $this->maybe_packed_length >= $item->get_length() && $this->maybe_packed_width >= $item->get_width() ) ? true : false;
				}
				break;
			// Boxes are easy
			default:
				$can_fit = ( $this->get_length() >= $item->get_length() && $this->get_width() >= $item->get_width() && $this->get_height() >= $item->get_height() && $item->get_volume() <= $this->get_volume() ) ? true : false;
				break;
		}

		return $can_fit;
	}

	/**
	 * Reset packed dimensions to originals
	 */
	private function reset_packed_dimensions() {
		$this->packed_length = $this->length;
		$this->packed_width  = $this->width;
		$this->packed_height = $this->height;
	}

	/**
	 * Pack function.
	 *
	 * @access public
	 * @param mixed $items
	 * @return object Package
	 */
	public function pack( $items, $mode = null) {
		$packed         = array();
		$unpacked       = array();
		$packed_name    = '';
		$new_box_weight = $this->get_weight();
		$packed_weight  = empty($new_box_weight)? 0: $new_box_weight;
		$packed_volume  = 0;
		$packed_value   = 0;

		$this->reset_packed_dimensions();

		while ( sizeof( $items ) > 0 ) {
			$item = array_shift( $items );

			// Check dimensions
			if ( ! $this->can_fit( $item ) ) {
				$unpacked[] = $item;
				continue;
			}
			
			// Check volume
			if ( ( $packed_volume + $item->get_volume() ) > $this->get_volume() ) {
				$unpacked[] = $item;
				continue;
			}
			
			// Check max weight
			if ( ( ( $packed_weight + $item->get_weight() ) > $this->get_max_weight() ) && $this->get_max_weight() > 0 ) {
				$unpacked[] = $item;
				continue;
			}

			// Pack
			$packed[]       = $item;
			$packed_volume += $item->get_volume();
			$packed_weight += $item->get_weight();
			$packed_value  += $item->get_value();

			// Adjust dimensions if needed, after this item has been packed inside
			if ( ! is_null( $this->maybe_packed_height ) ) {
				$this->packed_height       = $this->maybe_packed_height;
				$this->packed_length       = $this->maybe_packed_length;
				$this->packed_width        = $this->maybe_packed_width;
				$this->maybe_packed_height = null;
				$this->maybe_packed_length = null;
				$this->maybe_packed_width  = null;
			}
		}

		// Get weight of unpacked items
		$unpacked_weight = 0;
		$unpacked_volume = 0;
		foreach ( $unpacked as $item ) {
			$unpacked_weight += $item->get_weight();
			$unpacked_volume += $item->get_volume();
		}

		$package           = new stdClass();
		$package->name     = $this->get_name();
		$package->id       = $this->id;
		$package->packed   = $packed;
		$package->unpacked = $unpacked;
		$package->weight   = $packed_weight;
		$package->volume   = $packed_volume;
		$package->length   = $this->get_outer_length();
		$package->width    = $this->get_outer_width();
		$package->height   = $this->get_outer_height();
		$package->packtype = $this->get_packtype();
		$package->boxtype  = $this->get_boxtype();
		$package->value    = $packed_value;

		// Calculate packing success % based on % of weight and volume of all items packed
		if ($mode=='new_algorithm') {   
			$box_volume       =$this->packed_length * $this->packed_width * $this->packed_height ;
			$package->percent =( ( $packed_volume / $box_volume ) * 100  * sizeof($packed) )- $box_volume ;
		} else {
			$package->percent = ( $packed_weight / ( $packed_weight + $unpacked_weight ) ) * ( $packed_volume / ( $packed_volume + $unpacked_volume ) ) * 100;      
		}

		
		

		return $package;
	}

	/**
	 * Get_volume function.
	 *
	 * @return float
	 */
	public function get_volume() {
		if ( $this->volume ) {
			return $this->volume;
		} else {
			return floatval( $this->get_height() * $this->get_width() * $this->get_length() );
		}
	}

	/**
	 * Get_height function.
	 *
	 * @return float
	 */
	public function get_height() {
		return $this->height;
	}

	/**
	 * Get_packtype function.
	 *
	 * @return string
	 */
	public function get_packtype() {
		return $this->packtype;
	}
	/**
	 * Set_packtype function.
	 *
	 * @return string
	 */
	public function set_packtype( $packtype ) {
		$this->packtype = $packtype;
	}

	/**
	 * Get_width function.
	 *
	 * @return float
	 */
	public function get_width() {
		return $this->width;
	}

	/**
	 * Get_length function.
	 *
	 * @return float
	 */
	public function get_length() {
		return $this->length;
	}

	/**
	 * Get_weight function.
	 *
	 * @return float
	 */
	public function get_weight() {
		return $this->weight;
	}

	/**
	 * Get_outer_height
	 *
	 * @return float
	 */
	public function get_outer_height() {
		return $this->outer_height;
	}

	/**
	 * Get_outer_width
	 *
	 * @return float
	 */
	public function get_outer_width() {
		return $this->outer_width;
	}

	/**
	 * Get_outer_length
	 *
	 * @return float
	 */
	public function get_outer_length() {
		return $this->outer_length;
	}

	/**
	 * Get_packed_height
	 *
	 * @return float
	 */
	public function get_packed_height() {
		return $this->packed_height;
	}

	/**
	 * Get_packed_width
	 *
	 * @return float
	 */
	public function get_packed_width() {
		return $this->packed_width;
	}

	/**
	 * Get_packed_length.
	 *
	 * @return float
	 */
	public function get_packed_length() {
		return $this->packed_length;
	}
}

/**
 * WF_Boxpack_Item class.
 */
class WF_Boxpack_Item {

	public $weight;
	public $height;
	public $width;
	public $length;
	public $volume;
	public $value;
	public $meta;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $length, $width, $height, $weight, $value = '', $meta = array() ) {
		$dimensions = array( $length, $width, $height );

		sort( $dimensions );

		$this->length = $dimensions[2];
		$this->width  = $dimensions[1];
		$this->height = $dimensions[0];

		$this->volume = $width * $height * $length;
		$this->weight = $weight;
		$this->value  = $value;
		$this->meta   = $meta;
	}


	/**
	 * Get_volume function.
	 *
	 * @access public
	 * @return void
	 */
	function get_volume() {
		return $this->volume;
	}

	/**
	 * Get_height function.
	 *
	 * @access public
	 * @return void
	 */
	function get_height() {
		return $this->height;
	}

	/**
	 * Get_width function.
	 *
	 * @access public
	 * @return void
	 */
	function get_width() {
		return $this->width;
	}

	/**
	 * Get_length function.
	 *
	 * @access public
	 * @return void
	 */
	function get_length() {
		return $this->length;
	}

	/**
	 * Get_weight function.
	 *
	 * @access public
	 * @return void
	 */
	function get_weight() {
		return $this->weight;
	}

	/**
	 * Get_value function.
	 *
	 * @access public
	 * @return void
	 */
	function get_value() {
		return $this->value;
	}

	/**
	 * Get_meta function.
	 *
	 * @access public
	 * @return void
	 */
	function get_meta( $key = '' ) {
		if ( $key ) {
			if ( isset( $this->meta[ $key ] ) ) {
				return $this->meta[ $key ];
			} else {
				return null;
			}
		} else {
			return array_filter( (array) $this->meta );
		}
	}
}
