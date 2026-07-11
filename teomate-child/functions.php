<?php
/**
 * TEO Mate — funcțiile temei child.
 * Tot codul PHP propriu al site-ului stă aici sau în fișiere incluse de aici,
 * niciodată în tema părinte (s-ar pierde la actualizări).
 */

// Încarcă stilurile temei child după cele ale părintelui (prioritate 20 > implicit 10).
add_action( 'wp_enqueue_scripts', 'teomate_child_styles', 20 );
function teomate_child_styles() {
	wp_enqueue_style(
		'teomate-child',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
