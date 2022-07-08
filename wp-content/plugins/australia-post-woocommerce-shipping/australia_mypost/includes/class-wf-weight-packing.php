<?php

/**
 * WooCommerce Weight Box Packer
 */
class Elex_Weight_Boxpack {

	private $boxes;
	private $items;
	private $packages;
	private $cannot_pack;
	private $strategy;
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $strategy) {
		$this->strategy = $strategy;
		switch ($strategy) {
			case 'pack_ascending':
				include_once 'class-wf-weight-strategy-ascend.php';
				$this->pack_obj =   new WeightBoxPackAscend();
				break;
			case 'pack_simple':
				include_once 'class-wf-weight-strategy-simple.php';
				$this->pack_obj =   new WeightBoxPackSimple();
				break;
			default:
				include_once 'class-wf-weight-strategy-descend.php';
				$this->pack_obj =   new WeightBoxPackDescend();
				break;
		}

	}

	/**
	 * clear_items function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_items() {
		$this->items = array();
	}

	/**
	 * clear_boxes function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_boxes() {
		$this->boxes = array();
	}

	/**
	 * add_item function.
	 *
	 * @access public
	 * @return void
	 */
	public function add_item( $weight, $product_data, $quantity, $price ) {
		$this->items[] = new Elex_Weight_Boxpack_Item($weight, $product_data, $quantity , $price);
	}

	/**
	 * add_box function.
	 *
	 * @access public
	 * @param mixed $length
	 * @param mixed $width
	 * @param mixed $height
	 * @param mixed $weight
	 * @return void
	 */
	public function add_weight_box( $length, $width, $height, $min_weight = 0, $max_weight = 0) {
		$new_box       = new Elex_Weight_Boxpack_Box($length, $width, $height, $min_weight, $max_weight);
		$this->boxes[] = $new_box;
		return $new_box;
	}

	/**
	 * get_packages function.
	 *
	 * @access public
	 * @return void
	 */
	public function get_packages() {
		return $this->packages ? $this->packages : array();
	}

	/**
	 * get_packages function.
	 *
	 * @access public
	 * @return void
	 */
	public function get_not_packed_items() {
		return $this->cannot_pack ? $this->cannot_pack : array();
	}

	/**
	 * pack function.
	 *
	 * @access public
	 * @return void
	 */
	public function pack() {
		try {
			// We need items
			if (is_array($this->items)) {
				if (sizeof($this->items) == 0) {
					throw new Exception('No items to pack!');
				}
			}   
			$current_order = get_option('current_order_data');

			$this->items = $this->pack_obj->sort_pack_items( $this->items );
			if (!empty($current_order)) {
				$items_in_the_order      = $current_order->get_items();
				$value_item_in_the_order = 0;
				
				//Obtaining discounted prices of the products
				foreach ($this->items as $this_item) {
					$this_item_meta      = $this_item->meta;
					$this_item_meta_data = $this_item_meta['data'];
					$this_item_id        = $this_item_meta_data->get_id();
					foreach ($items_in_the_order as $item_in_the_order) {
						if (WC()->version < '2.7.0') {
							$this_item->final_price = $item_in_the_order['line_total'];
						} else {
							$order_item_id = $item_in_the_order->get_variation_id();
							if (!empty($order_item_id)) {
								if ($order_item_id == $this_item_id) {
									$this_item->final_price = $item_in_the_order->get_total() / $item_in_the_order->get_quantity();
								}
							} else {
								if ($item_in_the_order->get_product_id() == $this_item_id) {
									$this_item->final_price = $item_in_the_order->get_total() / $item_in_the_order->get_quantity();
								}
							}
						}
					}
				}
			}

			// Clear packages
			$this->packages = array();

			// Order the boxes by volume
			$this->boxes = $this->order_boxes($this->boxes);

			if (!$this->boxes) {
				$this->cannot_pack = $this->items;
				$this->items       = array();
			}

			// Keep looping until packed
			if (is_array($this->items)) {
				while (sizeof($this->items) > 0) {
					$this->items = $this->pack_obj->sort_pack_items( $this->items );

					$possible_packages = array();
					$best_package      = '';

					// Attempt to pack all items in each box
					foreach ($this->boxes as $box) {
						$possible_packages[] = $box->pack($this->items);
					}

					// Find the best success rate
					$best_percent = 0;
					foreach ($possible_packages as $package) {
						if (isset($package->percent) && !empty($package->percent)) {
							if ($package->percent > $best_percent) {
								$best_percent = $package->percent;
								break;
							}
						}
					}

					if ($best_percent == 0) {
						$this->cannot_pack = $this->items;
						$this->items       = array();
					} else {
						// Get smallest box with best_percent
						$possible_packages = array_reverse($possible_packages);

						foreach ($possible_packages as $package) {
							if (isset($package->percent) && !empty($package->percent)) {
								if ($package->percent == $best_percent) {
									$best_package = $package;
									break; // Done packing
								}
							}
						}

						// Update items array
						$this->items = $best_package->unpacked;

						// Store package
						$this->packages[] = $best_package;
					}
				}
			}

			// Items we cannot pack (by now) get packaged individually
			if ($this->cannot_pack) {
				foreach ($this->cannot_pack as $item) {
					$package           = new stdClass();
					$package->id       = '';
					$package->weight   = $item->get_weight();
					$package->length   = '';
					$package->width    = '';
					$package->height   = '';
					$package->value    = $item->get_value();
					$package->unpacked = true;
					$this->packages[]  = $package;
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
	private function order_boxes( $sort) {
		if (!empty($sort)) {
			uasort($sort, array($this, 'box_sorting'));
		}
		return $sort;
	}

	/**
	 * Order items by weight and volume
	 * $param array $sort
	 *
	 * @return array
	 */
	private function order_items( $sort) {
		if (!empty($sort)) {
			uasort($sort, array($this, 'item_sorting'));
		}
		return $sort;
	}

	/**
	 * order_by_volume function.
	 *
	 * @access private
	 * @return void
	 */
	private function order_by_volume( $sort) {
		if (!empty($sort)) {
			uasort($sort, array($this, 'volume_based_sorting'));
		}
		return $sort;
	}

	/**
	 * item_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function item_sorting( $a, $b) {
		if ($a->get_weight() == $b->get_weight()) {
			return 0;
		}
			return ( $a->get_weight() < $b->get_weight() ) ? 1 : -1;
	}

	/**
	 * box_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function box_sorting( $a, $b) {
		if ($a->get_volume() == $b->get_volume()) {
			if ($a->get_max_weight() == $b->get_max_weight()) {
				return 0;
			}
			return ( $a->get_max_weight() < $b->get_max_weight() ) ? 1 : -1;
		}
		return ( $a->get_volume() < $b->get_volume() ) ? 1 : -1;
	}

	/**
	 * volume_based_sorting function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function volume_based_sorting( $a, $b) {
		if ($a->get_volume() == $b->get_volume()) {
			return 0;
		}
		return ( $a->get_volume() < $b->get_volume() ) ? 1 : -1;
	}

}

/**
 * Elex_Weight_Boxpack_Box class.
 */
class Elex_Weight_Boxpack_Box {

	/** @var string ID of the box - given to packages */
	private $id = '';

	/** @var string name of the box - given to packages */
	private $name = '';

	/** @var float Weight of the box itself */
	private $weight;

	/** @var float Min allowed weight of box */
	private $min_weight = 0;

	/** @var float Max allowed weight of box + contents */
	private $max_weight = 0;

	/** @var float Inner dimension of box used when packing */
	private $height;

	/** @var float Inner dimension of box used when packing */
	private $width;

	/** @var float Inner dimension of box used when packing */
	private $length;

	/** @var float Dimension is stored here if adjusted during packing */
	private $packed_height;
	private $maybe_packed_height = null;

	/** @var float Dimension is stored here if adjusted during packing */
	private $packed_width;
	private $maybe_packed_width = null;

	/** @var float Dimension is stored here if adjusted during packing */
	private $packed_length;
	private $maybe_packed_length = null;

	/** @var float Volume of the box */
	private $volume;

	/** @var string This box type */
	private $type = 'box';

	/** @var string This box pack type */
	private $packtype;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $length, $width, $height, $min_weight = 0, $max_weight = 0) {
		$dimensions = array($length, $width, $height);

		sort($dimensions);

		$this->length     = $dimensions[2];
		$this->width      = $dimensions[1];
		$this->height     = $dimensions[0];
		$this->min_weight = $min_weight;
		$this->max_weight = $max_weight;
	}

	/**
	 * set_id function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_id( $id) {
		$this->id = $id;
	}

	/**
	 * set_name function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_name( $name) {
		$this->name = $name;
	}
	/**
	 * Set the type of box
	 *
	 * @param string $type
	 */
	public function set_type( $type) {
		if (in_array($type, $this->valid_types)) {
			$this->type = $type;
		}
	}

	/**
	 * Get max weight.
	 *
	 * @return float
	 */
	public function get_max_weight() {
		return floatval($this->max_weight);
	}

	/**
	 * Get min weight.
	 *
	 * @return float
	 */
	public function get_min_weight() {
		return floatval($this->min_weight);
	}

	/**
	 * set_max_weight function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_max_weight( $max_weight) {
		$this->max_weight = $max_weight;
	}

	/**
	 * set_min_weight function.
	 *
	 * @access public
	 * @param mixed $weight
	 * @return void
	 */
	public function set_min_weight( $min_weight) {
		$this->min_weight = $min_weight;
	}

	/**
	 * set_inner_dimensions function.
	 *
	 * @access public
	 * @param mixed $length
	 * @param mixed $width
	 * @param mixed $height
	 * @return void
	 */
	public function set_inner_dimensions( $length, $width, $height) {
		$dimensions = array($length, $width, $height);

		sort($dimensions);

		$this->length = $dimensions[2];
		$this->width  = $dimensions[1];
		$this->height = $dimensions[0];
	}

	/**
	 * See if an item fits into the box.
	 *
	 * @param object $item
	 * @return bool
	 */
	public function can_fit( $item) {

		$can_fit = ( $this->get_min_weight() <= $item->get_weight() && $this->get_max_weight() >= $item->get_weight() ) ? true : false;
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
	 * pack function.
	 *
	 * @access public
	 * @param mixed $items
	 * @return object Package
	 */
	public function pack( $items) {
		$packed        = array();
		$unpacked      = array();
		$packed_weight = $this->get_weight();
		$packed_volume = 0;
		$packed_value  = 0;

		$this->reset_packed_dimensions();

		while (sizeof($items) > 0) {
			$item = array_shift($items);

			// Check dimensions
			if (!$this->can_fit($item)) {
				$unpacked[] = $item;
				continue;
			}

			// Check volume
			if ( $item->get_weight()  < $this->get_min_weight()) {
				$unpacked[] = $item;
				continue;
			}

			// Check max weight
			if (( $packed_weight + $item->get_weight() ) > $this->get_max_weight() && $this->get_max_weight() > 0) {
				$unpacked[] = $item;
				continue;
			}

			

			// Pack
			$packed[]       = $item;
			$packed_weight += $item->get_weight();
			$packed_value  += $item->get_value();
		}

		// Get weight of unpacked items
		$unpacked_weight = 0;
		$unpacked_volume = 0;

		foreach ($unpacked as $item) {
			$unpacked_weight += $item->get_weight();
		}
		$box_weight = $this->max_weight;

		$package           = new stdClass();
		$package->id       = $this->id;
		$package->name     = $this->name;
		$package->packed   = $packed;
		$package->unpacked = $unpacked;
		$package->weight   = $packed_weight;
		$package->length   = $this->get_box_length();
		$package->width    = $this->get_box_width();
		$package->height   = $this->get_box_height();
		$package->value    = $packed_value;
		$package->percent  = ( $packed_weight / $box_weight ) * 100 ;
		return $package;
	}

	/**
	 * get_volume function.
	 *
	 * @return float
	 */
	public function get_volume() {
		if ($this->volume) {
			return $this->volume;
		} else {
			return floatval($this->get_height() * $this->get_width() * $this->get_length());
		}
	}

	/**
	 * get_height function.
	 *
	 * @return float
	 */
	public function get_height() {
		return $this->height;
	}

	/**
	 * get_packtype function.
	 *
	 * @return string
	 */
	public function get_packtype() {
		if ($this->packtype != null) {
			return $this->packtype;
		}

	}
	/**
	 * set_packtype function.
	 *
	 * @return string
	 */
	public function set_packtype( $packtype) {
		$this->packtype = $packtype;
	}

	/**
	 * get_id function.
	 *
	 * @return float
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * get_width function.
	 *
	 * @return float
	 */
	public function get_width() {
		return $this->width;
	}

	/**
	 * get_width function.
	 *
	 * @return float
	 */
	public function get_length() {
		return $this->length;
	}

	/**
	 * get_weight function.
	 *
	 * @return float
	 */
	public function get_weight() {
		return $this->weight;
	}

	/**
	 * get_outer_height
	 *
	 * @return float
	 */
	public function get_box_height() {
		return $this->height;
	}

	/**
	 * get_outer_width
	 *
	 * @return float
	 */
	public function get_box_width() {
		return $this->width;
	}

	/**
	 * get_outer_length
	 *
	 * @return float
	 */
	public function get_box_length() {
		return $this->length;
	}

	/**
	 * get_packed_height
	 *
	 * @return float
	 */
	public function get_packed_height() {
		return $this->packed_height;
	}

	/**
	 * get_packed_width
	 *
	 * @return float
	 */
	public function get_packed_width() {
		return $this->packed_width;
	}

	/**
	 * get_width get_packed_length.
	 *
	 * @return float
	 */
	public function get_packed_length() {
		return $this->packed_length;
	}
}

/**
 * Elex_Weight_Boxpack_Item class.
 */
class Elex_Weight_Boxpack_Item {

	public $weight;
	public $height;
	public $width;
	public $length;
	public $product_data;
	public $quantity;
	public $price;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $weight, $product_data, $quantity, $price ) {
		
		$this->weight       = $weight;
		$this->product_data = $product_data;
		$this->quantity     = $quantity;
		$this->price        = $price;
	}
	/**
	 * get_width function.
	 *
	 * @access public
	 * @return void
	 */
	function get_weight() {
		return $this->weight;
	}

	/**
	 * get_value function.
	 *
	 * @access public
	 * @return void
	 */
	function get_value() {
		return $this->price;
	}

	/**
	 * get_meta function.
	 *
	 * @access public
	 * @return void
	 */
	function get_meta( $key = '') {
		if ($key) {
			if (isset($this->product_data[$key])) {
				return $this->product_data[$key];
			} else {
				return null;
			}
		} else {
			return array_filter((array) $this->product_data);
		}
	}
}
