<?php
/**
 * Delivery predicate for the comment webhook.
 *
 * wp_remote_post() returns an array on HTTP errors. Only 2xx counts as delivered.
 */

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @return void
	 */
	function add_action() {}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stand-in so the theme file can load outside WordPress.
	 */
	class WP_Error {
		/**
		 * @param string $message Message.
		 */
		public function __construct( $message ) {
			$this->message = $message;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * @var string
		 */
		private $message;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

require dirname( __DIR__ ) . '/inc/comment-webhook.php';

$failures = array();

$cases = array(
	'transport error' => array( new WP_Error( 'timeout' ), false ),
	'http 401'        => array( array( 'response' => array( 'code' => 401 ) ), false ),
	'http 429'        => array( array( 'response' => array( 'code' => 429 ) ), false ),
	'http 500'        => array( array( 'response' => array( 'code' => 500 ) ), false ),
	'http 204'        => array( array( 'response' => array( 'code' => 204 ) ), true ),
	'http 200'        => array( array( 'response' => array( 'code' => 200 ) ), true ),
	'empty'           => array( false, false ),
);

foreach ( $cases as $name => $case ) {
	$actual = minimalcode_comment_webhook_delivered( $case[0] );
	if ( $actual !== $case[1] ) {
		$failures[] = $name . ' expected ' . var_export( $case[1], true ) . ' got ' . var_export( $actual, true );
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Comment webhook delivery predicate ok.\n";
