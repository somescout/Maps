<?php

/**
 * A class that holds static helper functions for generic mapping-related functions.
 * 
 * @since 0.1
 * 
 * @file Maps_Mapper.php
 * @ingroup Maps
 * 
 * @author Jeroen De Dauw
 */
final class MapsMapper {
	
	/**
	 * Initialization function. Needs to be called before parameters using Maps defined validation
	 * or formatting functions are handled.
	 */
	public static function initialize() {
		/* TODO
		Validator::addValidationFunction( 'is_map_dimension', array( __CLASS__, 'isMapDimension' ) );
		Validator::addValidationFunction( 'is_location', array( __CLASS__, 'isLocation' ) );
		Validator::addValidationFunction( 'are_locations', array( __CLASS__, 'areLocations' ) );
		*/

		Validator::addOutputFormat( 'mapdimension', array( __CLASS__, 'setMapDimension' ) );
		Validator::addOutputFormat( 'coordinateSet', array( __CLASS__, 'formatLocation' ) );
	}
	
	/**
	 * Returns if the value is a location.
	 * 
	 * @since 0.6
	 * 
	 * @param string $location
	 * @param string $name The name of the parameter.
	 * @param array $parameters Array containing data about the so far handled parameters.
	 * 
	 * @return boolean
	 */
	public static function isLocation( $location, $name, array $parameters, $metaDataSeparator = false ) {
		if ( $metaDataSeparator !== false ) {
			$parts = explode( $metaDataSeparator, $location );
			$location = $parts[0];
		}
		
		if ( MapsGeocoders::canGeocode() ) {
			$geoService = array_key_exists( 'geoservice', $parameters ) ? $parameters['geoservice']['value'] : '';
			$mappingService = array_key_exists( 'mappingservice', $parameters ) ? $parameters['mappingservice']['value'] : false;
			return MapsGeocoders::isLocation( $location, $geoService, $mappingService );
		} else {
			return MapsCoordinateParser::areCoordinates( $location );
		}
	}

	/**
	 * Returns if the values are a locations.
	 * 
	 * @param string $locations
	 * @param string $name The name of the parameter.
	 * @param array $parameters Array containing data about the so far handled parameters.
	 * 
	 * @return boolean
	 */	
	public static function areLocations( $locations, $name, array $parameters, $metaDataSeparator = false ) {
		$locations = (array)$locations;
		
		foreach ( $locations as $location ) {
			if ( !self::isLocation( $location, $name, $parameters, $metaDataSeparator ) ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Formats a location to a coordinate set in a certain representation.
	 * 
	 * @since 0.6
	 * 
	 * @param string $locations
	 * @param string $name The name of the parameter.
	 * @param array $parameters Array containing data about the so far handled parameters.
	 */		
	public static function formatLocation( &$location, $name, array $parameters ) {
		if ( self::geocoderIsAvailable() ) {
			// TODO
			//$geoService = array_key_exists( 'geoservice', $parameters ) ? $parameters['geoservice']['value'] : '';
			//$mappingService = array_key_exists( 'mappingservice', $parameters ) ? $parameters['mappingservice']['value'] : false;			
			$location = MapsGeocoders::attemptToGeocodeToString( $location/*, $geoService, $mappingService*/ );
		} else {
			$location = MapsCoordinateParser::parseAndFormat( $location );
		}
	}
	
	/**
	 * @deprecated Method moved to MapsMappingServices. Will be removed in 0.7.
	 */
	public static function getValidService( $service, $feature ) {
		MapsMappingServices::getValidServiceInstance( $service, $feature );
	}

	/**
	 * Determines if a value is a valid map dimension, and optionally corrects it.
	 *
	 * @since 0.6
	 *
	 * @param string or number $value The value as it was entered by the user.
	 * @param string $dimension Must be width or height.
	 * @param boolean $correct If true, the value will be corrected when invalid. Defaults to false.
	 * @param number $default The default value for this dimension. Must be set when $correct = true.
	 *
	 * @return boolean
	 */
	public static function isMapDimension( &$value, $name, array $parameters, $dimension, $correct = false, $default = 0 ) {
		global $egMapsSizeRestrictions;

		// See if the notation is valid.
		if ( !preg_match( '/^\d+(\.\d+)?(px|ex|em|%)?$/', $value ) ) {
			if ( $correct ) {
				$value = $default;
			} else {
				return false;
			}
		}

		// Determine the minimum and maximum values.
		if ( preg_match( '/^.*%$/', $value ) ) {
			if ( count( $egMapsSizeRestrictions[$dimension] ) >= 4 ) {
				$min = $egMapsSizeRestrictions[$dimension][2];
				$max = $egMapsSizeRestrictions[$dimension][3];
			} else {
				// This is for backward compatibility with people who have set a custom min and max before 0.6.
				$min = 1;
				$max = 100;
			}
		} else {
			$min = $egMapsSizeRestrictions[$dimension][0];
			$max = $egMapsSizeRestrictions[$dimension][1];
		}

		// See if the actual value is withing the limits.
		$number = preg_replace( '/[^0-9]/', '', $value );
		if ( $number < $min ) {
			if ( $correct ) {
				$value = $min;
			} else {
				return false;
			}
		} else if ( $number > $max ) {
			if ( $correct ) {
				$value = $max;
			} else {
				return false;
			}
		}

		// If this is a 'correct the value call', add 'px' if no unit has been provided.
		if ( $correct ) {
			if ( !preg_match( '/(px|ex|em|%)$/', $value ) ) {
				$value .= 'px';
			}
		}

		return true;
	}

	/**
	 * Corrects the provided map demension value when not valid.
	 *
	 * @since 0.6
	 *
	 * @param string or number $value The value as it was entered by the user.
	 * @param string $dimension Must be width or height.
	 * @param number $default The default value for this dimension.
	 */
	public static function setMapDimension( &$value, $name, array $parameters, $dimension, $default ) {
		self::isMapDimension( $value, $name, $parameters, $dimension, true, $default );
	}

	/**
	 * Returns a boolean indicating if MapsGeocoders is available.
	 *
	 * @deprecated - use MapsGeocoders::canGeocode() instead
	 *
	 * @return Boolean
	 */
	public static function geocoderIsAvailable() {
		return MapsGeocoders::canGeocode();
	}
	
	/**
	 * Add a JavaScript file out of skins/common, or a given relative path.
	 * 
	 * This is a copy of the native function in OutputPage to work around a pre 1.16 bug.
	 * Should be used for adding external files, like the Google Maps API.
	 * 
	 * @param OutputPage $out
	 * @param string $file
	 */
	public static function addScriptFile( OutputPage $out, $file ) {
		global $wgStylePath, $wgStyleVersion;
		if( substr( $file, 0, 1 ) == '/' || preg_match( '#^[a-z]*://#i', $file ) ) {
			$path = $file;
		} else {
			$path =  "{$wgStylePath}/common/{$file}";
		}
		$out->addScript( Html::linkedScript( wfAppendQuery( $path, $wgStyleVersion ) ) );		
	}
	
	/**
	 * This function returns the definitions for the parameters used by every map feature.
	 *
	 * @return array
	 */
	public static function getCommonParameters() {
		global $egMapsAvailableServices, $egMapsAvailableGeoServices, $egMapsDefaultGeoService, $egMapsMapWidth, $egMapsMapHeight;

		return array(
			'mappingservice' => array(
				'criteria' => array(
					'in_array' => MapsMappingServices::getAllServiceValues()
				),
				'aliases' => array( 'service' ),
			),
			'geoservice' => array(
				'criteria' => array(
					'in_array' => $egMapsAvailableGeoServices
				),
				'dependencies' => array( 'mappingservice' ),
			),
			'zoom' => array(
				'type' => 'integer',
				'criteria' => array(
					'not_empty' => array()
				),
				'default' => 'null'
			),
			'width' => array(
				'criteria' => array(
					'is_map_dimension' => array( 'width' ),
				),
				'output-type' => array( 'mapdimension', 'width', $egMapsMapWidth )
			),
			'height' => array(
				'criteria' => array(
					'is_map_dimension' => array( 'height' ),
				),
				'output-type' => array( 'mapdimension', 'height', $egMapsMapHeight )
			),
		);
	}
	
}