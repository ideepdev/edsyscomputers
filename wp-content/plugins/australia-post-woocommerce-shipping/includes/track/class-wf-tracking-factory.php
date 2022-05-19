<?php

/**
 * The is the factory which creates shipment tracking objects
 */
class WfTrackingFactory {
	public static function init() {
		WfTrackingFactory::wf_include_once( 'WfTrackingAbstract', 'class-wf-tracking-common.php' );
		WfTrackingFactory::wf_include_once( 'WfTrackingAbstract', 'class-wf-tracking-abstract.php' );
	}

    public static function create( $shipment_source_obj ) {
        switch ( $shipment_source_obj->shipping_service ) {
			case '':
                $tracking_obj = null;
				break;
			default:
				WfTrackingFactory::wf_include_once( 'WfTrackingDefault', 'class-wf-tracking-default.php' );
				$tracking_obj = new WfTrackingDefault();
				break;
        }

		if( $tracking_obj != null ) {
			$tracking_obj->init ( $shipment_source_obj );
		}

        return $tracking_obj;
    }

	private static function wf_include_once( $class_name, $file_name ) {
		if ( ! class_exists( $class_name ) ) {
			include_once ( $file_name );
		}
	}
}

?>