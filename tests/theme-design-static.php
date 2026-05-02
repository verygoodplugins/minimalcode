<?php
/**
 * Static regression checks for the drunk.support newspaper UI.
 *
 * This catches deploys where rsync succeeds but the WordPress theme still
 * serves the old MinimalCode visual system.
 */

$root = dirname( __DIR__ );

$checks = array(
	'header.php'            => array( 'ticker', 'masthead', 'nav-item', 'brand-logo' ),
	'index.php'             => array( 'layout wrap', 'lede', 'entry-hash', 'month-rule' ),
	'single.php'            => array( 'single wrap', 'post-meta-rail', 'post-headline', 'aj-banner' ),
	'assets/css/custom.css' => array( '--paper', '--hot', '.ticker', '.masthead', '.entry-hash' ),
);

$forbidden_css = array(
	'.single {'              => 'WordPress adds body.single on posts; target .single.wrap instead.',
	'.layout, .single {'     => 'WordPress body.single must not inherit layout grid rules.',
	'.layout, .single '      => 'Responsive selectors must target .single.wrap.',
	'.single > aside'        => 'Child selectors must target .single.wrap.',
	'.single { display'      => 'WordPress body.single must not become the layout grid.',
);

$failures = array();

foreach ( $checks as $relative_path => $needles ) {
	$path = $root . '/' . $relative_path;
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		$failures[] = $relative_path . ' could not be read';
		continue;
	}

	foreach ( $needles as $needle ) {
		if ( false === strpos( $contents, $needle ) ) {
			$failures[] = $relative_path . ' missing ' . $needle;
		}
	}
}

$css_path = $root . '/assets/css/custom.css';
$css_contents = file_get_contents( $css_path );

if ( false !== $css_contents ) {
	foreach ( $forbidden_css as $needle => $message ) {
		if ( false !== strpos( $css_contents, $needle ) ) {
			$failures[] = 'assets/css/custom.css has unsafe selector ' . $needle . ' — ' . $message;
		}
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Newspaper UI markers present.\n";
