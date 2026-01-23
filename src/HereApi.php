<?php
/**
 * HERE API Library
 *
 * PHP version 8.2 or higher is required
 *
 * @package    HereApi
 * @subpackage HereApi
 * @author     Josh Mckibbin <joshmckibbin@gmail.com>
 * @license    https://opensource.org/licenses/MIT MIT License
 * @link       https://github.com/joshmckibbin/here-api-php
 */

namespace JMckibbin\HereApi;

/**
 * HERE API Class
 *
 * This class provides methods to interact with the HERE Traffic API
 * and to generate map images based on polyline coordinates.
 *
 * @category APIs
 * @package  HereApi
 * @author   Josh Mckibbin <joshmckibbin@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/joshmckibbin/here-api-php
 */
class HereApi {

    const TRAFFIC_API_URL = 'https://data.traffic.hereapi.com/v7';
    const MAP_API_URL     = 'https://image.maps.hereapi.com/mia/v3';

    private static $_apikey;
    public $polyline = null;


    /**
     * Create the HERE Library
     *
     * @param string $api_key The HERE API Key.
     * @param array  $coords  The coordinates as an array of arrays.
     *
     * @throws \Exception If the HERE API Key is not defined.
     */
    public function __construct( string $api_key = '', array $coords = [] ) {
        if ( empty( $api_key ) ) {
            throw new \Exception('HERE API Key is required');
        }

        self::$_apikey = $api_key;

        if ( ! empty( $coords) ) {
            $this->polyline = self::encode_polyline($coords);
        }
    }


    /**
     * Encode Polyline
     * 
     * @param array $coords    The coordinates
     * @param int   $precision The number of decimal places
     *                         to use in the provided coordinates
     * 
     * @return string The encoded polyline
     */
    public static function encode_polyline(array $coords, int $precision = 6): string {
        return FlexiblePolyline::encode($coords, $precision, 0);
    }


    /**
     * Decode Polyline
     * 
     * @param string $polyline The polyline
     * 
     * @return array The decoded polyline as an array of coordinates
     */
    public static function decode_polyline(string $polyline): array {
        $data = FlexiblePolyline::decode($polyline);

        return $data['polyline'];
    }


    /**
     * Create csv coordinate string from polyline
     * 
     * @param string $polyline The polyline
     * 
     * @return string The coordinate string as CSV
     */
    public function csv_coords(string $polyline): string {
        $coords = self::decode_polyline($polyline);
        $str = '';
        foreach ($coords as $coord) {
            $str .= implode(',', $coord) . ',';
        }

        return rtrim($str, ',');
    }


    /**
     * Make a CURL request
     * 
     * @param string $endpoint The API endpoint
     * @param array  $params   The parameters to be sent
     * @param array  $options  Additional CURL options
     * @param string $method   The request method (GET, POST)
     * 
     * @return array
     */
    public function request(
        string $endpoint,
        $params = [],
        $options = [],
        string $method = 'GET'
    ) {

        // Add the API Key to the parameters
        $params['apiKey'] = self::$_apikey;

        // Build the request URL
        $request_url = $endpoint;
        if (!empty($params) ) {
            $request_url .= '?' . http_build_query($params);
        }

        // Initialize the CURL
        $ch = curl_init();

        // Set the CURL options
        curl_setopt_array(
            $ch, array(
            CURLOPT_URL => $request_url, 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CUSTOMREQUEST => $method,
            ) + (array) $options 
        );

        $response = curl_exec($ch);

        if ( curl_errno($ch) ) {
            throw new \Exception( 'CURL Error: ' . curl_error( $ch ) );
        }

        return json_decode( $response, true );
    }

    /**
     * Return traffic flow data for a specified polyline coordinate
     * 
     * @param int    $radius       The radius in meters
     * @param string $minJamFactor The minimum jamFactor (0.0 - 10.0)
     * 
     * @return array The traffic flow data
     */
    public function flow(int $radius = 50, $minJamFactor = '0.0'): array {

        $params = array(
            'in' => sprintf('corridor:%s;r=%d', $this->polyline, $radius),
            'locationReferencing' => 'shape',
            'minJamFactor' => $minJamFactor
        );

        return $this->request(self::TRAFFIC_API_URL . '/flow', $params);
    }


    /**
     * Return traffic flow data for a specified polyline coordinate
     * 
     * @param int    $radius The radius in meters
     * @param string $type   The type of incident
     *                       (accident, congestion, construction, etc.)
     * 
     * @return array The traffic incidents data
     */
    public function incidents(int $radius = 50, ?string $type = null) : array {

        $params = array(
            'in' => sprintf('corridor:%s;r=%d', $this->polyline, $radius),
            'locationReferencing' => 'shape',
            'type' => $type
        );

        return $this->request(self::TRAFFIC_API_URL . '/incidents', $params);
    }

    /**
     * Validate map image format
     * 
     * @param string $format The image format (png, jpeg, png8)
     * 
     * @return bool True if valid, false otherwise
     */
    private static function validate_map_format(string $format): bool {
        $valid_formats = ['png', 'jpeg', 'png8'];
        return in_array( $format, $valid_formats, true );
    }

    /**
     * Return a map image
     * 
     * @param string $polyline The coordinates as a polyline
     * @param string $format   The image format (png, jpeg, png8)
     * @param int    $width    The image width
     * @param int    $height   The image height
     * 
     * @throws \Exception If the map format is invalid
     * @return string The URL of the map image
     */
    public function get_map( string $polyline, string $format = 'png', int $width = 1200, int $height = 1200 ): string {
        if ( ! self::validate_map_format($format) ) {
            throw new \Exception('Invalid map format: ' . $format);
        }

        $dimensions = $width . 'x' . $height;

        $request_url = self::MAP_API_URL
            . '/base/mc/overlay:padding=32/'
            . $dimensions
            . '/'
            . $format;

        $overlay = 'line:' . $polyline . ';color=%2300FF00;width=6';

        $params = array(
            'apiKey' => self::$_apikey,
            'overlay' => $overlay,
        );

        return $request_url . '?' . http_build_query($params);
    }

    /**
     * Generate a file extension based on the map format
     * 
     * @param string $format The image format (png, jpeg, png8)
     * 
     * @return string The file extension
     */
    public static function get_map_extension(string $format): string {
        switch ($format) {
            case 'png':
            case 'png8':
                return 'png';
            case 'jpeg':
                return 'jpg';
            default:
                throw new \Exception('Invalid map format: ' . $format);
        }
    }

    /**
     * Save the map image for the given links
     * 
     * @param array $links The links containing points to create the map image
     * @param string $path  The path to save the image
     * @param string $format The image format (png, jpeg, png8)
     * 
     * @throws \Exception If the path is not writable
     * @return string The URL of the saved map image
     */
    public function map_save( array $links, string $path = '', string $format = 'png' ) : string {
        if ( empty( $path ) ) {
            $path = sys_get_temp_dir();
        }

        if ( ! is_writable( $path ) ) {
            throw new \Exception( 'The image path is not writable: ' . $path );
        }

        if ( ! self::validate_map_format( $format ) ) {
            throw new \Exception( 'Invalid map format: ' . $format );
        }

        foreach ( $links as $key => $link ) {
            foreach ( $link['points'] as $p ) {
                $points[$key][] = $p;
            }
        }

        foreach ( $points[$key] as $p ) {
            $c[$key][] = [$p['lat'], $p['lng']];
        }

        $polyline = self::encode_polyline( $c[$key] );
        $ext = $this->get_map_extension( $format );
        $image = $path . '/' . 'map-' . hash( 'md5', $polyline ) . '.' . $ext;

        // Check if the Image Exists
        if ( ! file_exists( $image ) ) {
            // Get and save the map image
            $map = $this->get_map( $polyline, $format );
            file_put_contents( $image, file_get_contents( $map ) );
        }
        return $image;
    }
}

