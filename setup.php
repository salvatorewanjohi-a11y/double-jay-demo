<?php
/**
 * Builds the Double Jay Barbershop (TJ & U Complex, 1st floor, Rabai Road near Rubis, Buruburu) booking store
 * inside WordPress Playground. Run by the blueprint after WooCommerce and the theme are installed.
 * Service photos are read from /wordpress/dj-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
function dj_sideload( $tmp, $name, $parent, $title ) {
	$up   = wp_upload_dir();
	$file = wp_unique_filename( $up['path'], $name );
	$dest = trailingslashit( $up['path'] ) . $file;
	if ( ! @rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
		return new WP_Error( 'dj_copy', 'Could not copy ' . $name );
	}
	$type = wp_check_filetype( $file );
	$size = @getimagesize( $dest ) ?: [ 0, 0 ];
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'], 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
	return $id;
}

// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
if ( defined( 'DJ_FAST' ) && DJ_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'Double Jay Barbershop',
	'blogdescription'                        => 'Where every look is crafted to perfection. Precision cuts and timeless style on the 1st floor of the TJ & U Complex, along Rabai Road near Rubis, Buruburu.',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'TJ & U Complex, 1st floor, Rabai Road',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'no',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => 'disabled',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_checkout_company_field'     => 'hidden',
	'woocommerce_checkout_address_2_field'   => 'hidden',
	'woocommerce_enable_coupons'             => 'no',
	'woocommerce_calc_discounts_sequentially' => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Service menus (order = order on the home page, in the price list and in the booking engine). They follow the
// sections of the price list in their SERVICES highlight on Instagram.
$cat = [];
$i   = 0;
foreach ( [
	'haircuts'    => [ 'Haircuts & Fades', 'Skin fades, taper fades, executive cuts and buzz cuts, every one finished with a crisp line-up.' ],
	'beard-shave' => [ 'Beard & Shave', 'Beard grooming and sharp line-ups, and the hot towel shave.' ],
	'colour'      => [ 'Colour & Texture', 'Black shampoo dye, Creme of Nature, blonde, platinum and peach colour, and the texturizer for waves and curl.' ],
	'grooming'    => [ 'Grooming & Spa', 'Face scrub, the full facial, a hair wash and the back massage. More than a cut.' ],
	'hands-feet'  => [ 'Manicure & Pedicure', 'Clean, tidy hands and fresh feet, done in the shop.' ],
	'kids'        => [ 'Junior Kings', 'Gentle, patient cuts for young gentlemen, with a small design if they want one.' ],
	'packages'    => [ 'Signature Package', 'The full Double Jay treatment in one visit.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Payment: nothing is paid online. The M-Pesa till isn't public, so it is sent on confirmation (PLACEHOLDER).
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay at the barbershop (M-Pesa or cash)',
	'description'        => 'Reserve your chair now and pay after your cut, by Lipa na M-Pesa or cash.',
	'instructions'       => 'We will call, SMS or WhatsApp to confirm your appointment. Pay by M-Pesa or cash after your cut.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );
update_option( 'woocommerce_bacs_settings', [
	'enabled'         => 'yes',
	'title'           => 'M-Pesa deposit to hold my chair',
	'description'     => 'Good for colour sessions and the Signature Package. We send our M-Pesa till when we confirm; the deposit comes off your bill.',
	'instructions'    => 'We will call, SMS or WhatsApp to confirm your appointment and send our M-Pesa till for the deposit. The balance is paid after your cut.',
	'account_details' => '',
] );
update_option( 'woocommerce_bacs_accounts', [] );
update_option( 'woocommerce_cheque_settings', [ 'enabled' => 'no' ] );
update_option( 'woocommerce_gateway_order', [ 'cod' => 0, 'bacs' => 1 ] );

// Services. Every price is Double Jay's own, from the "Barbershop services and prices" list in their SERVICES
// highlight on Instagram. The pedicure reads "Ksh 1,00" there (a typo): KSh 1,000 is a PLACEHOLDER to confirm.
// The hot towel shave, facial and hair wash are in their reels but not on the list: 'ask' => true shows
// "price in the shop" until the owner adds a price. Times are typical durations: PLACEHOLDERS (they publish none).
// The Signature Package adds up four list prices. 'options' => [ label => price ] is a choice.
$products = [
	// Haircuts & fades.
	[
		'slug' => 'adult-haircut', 'name' => 'Adult Haircut', 'cats' => [ 'haircuts' ],
		'badge' => 'Most booked', 'perk' => 'Precision every time', 'perks' => 'Skin fade or taper | Crisp line-up | Brushed-in finish', 'time' => 'About 40 min', 'mins' => 40,
		'clip' => 'fade',
		'short' => 'Skin fade, taper fade, executive cut or buzz cut: one price for every style, finished with a razor-sharp line-up.',
		'desc' => 'Your hair is your crown. Wear it sharp. Sit back in the chair and tell your barber the look: a skin fade blended down to the skin, a clean taper, a sharp executive cut for the office, or a low-maintenance buzz. We shape the line-up with clippers and a straight edge, clean you up and brush it in. Walk in, sit back, and walk out a legend.',
		'options' => [ 'Skin fade' => 400, 'Taper fade' => 400, 'Executive cut' => 400, 'Buzz cut' => 400 ], 'attr' => 'Style',
		'included' => [ 'Chat about the look you want', 'Clipper cut and fade blended to your style', 'Line-up with clippers and straight edge', 'Neck clean-up', 'Brush-in and finish' ],
		'specs' => [ 'Time' => 'About 40 minutes', 'Price' => 'KSh 400, any style', 'Where' => 'TJ & U Complex, 1st floor, Rabai Road', 'Payment' => 'After your cut, M-Pesa or cash' ],
		'upsells' => [ 'beard-grooming', 'face-scrub', 'colour-cut' ],
	],
	// Beard & shave.
	[
		'slug' => 'beard-grooming', 'name' => 'Beard Grooming', 'cats' => [ 'beard-shave' ],
		'badge' => 'Sharp lines', 'perk' => 'Shaped & lined', 'perks' => 'Trim & shape | Razor line-up | Cheek & neck lines', 'time' => 'About 15 min', 'mins' => 15,
		'clip' => 'shave',
		'short' => 'Your beard trimmed, shaped and lined up with a straight razor: clean cheeks, a sharp neckline and even length.',
		'desc' => 'A good beard is all in the lines. Your barber trims it to an even length, shapes it to your face, and lines up the cheeks and neck with a straight razor for that crisp edge. The perfect add-on to any haircut.',
		'price' => 200,
		'included' => [ 'Trim to an even length', 'Shape to suit your face', 'Cheek and neck lines with a straight razor', 'Clean-up' ],
		'specs' => [ 'Time' => 'About 15 minutes', 'Good with' => 'Any haircut', 'Payment' => 'After your cut, M-Pesa or cash' ],
		'upsells' => [ 'adult-haircut', 'hot-towel-shave' ],
	],
	[
		'slug' => 'hot-towel-shave', 'name' => 'Hot Towel Shave', 'cats' => [ 'beard-shave' ], 'ask' => true,
		'badge' => 'The classic', 'perk' => 'Smooth & refreshed', 'perks' => 'Hot towel | Steam | Straight-razor shave', 'time' => 'About 20 min', 'mins' => 20,
		'short' => 'The old-school ritual: steam and a hot towel to soften the beard, then a smooth, close straight-razor shave.',
		'desc' => 'Bro, life is tough, but your face doesn\'t have to be. The steamer and a hot towel open the pores and soften the hair, then your barber shaves you close and clean with a straight razor and finishes with a cool towel. Ask for the price in the shop.',
		'price' => 0,
		'included' => [ 'Steam to open the pores', 'Hot towel', 'Straight-razor shave', 'Cool towel finish' ],
		'specs' => [ 'Time' => 'About 20 minutes', 'Price' => 'Confirmed in the shop', 'Good with' => 'Face scrub and a haircut' ],
		'upsells' => [ 'face-scrub', 'beard-grooming' ],
	],
	// Colour & texture.
	[
		'slug' => 'colour-cut', 'name' => 'Haircut & Dye', 'cats' => [ 'colour' ],
		'badge' => 'Jet black', 'perk' => 'Fresh cut, deep colour', 'perks' => 'Haircut included | Black shampoo dye | Creme of Nature', 'time' => 'About 1 hr', 'mins' => 60,
		'short' => 'A fresh haircut with colour: black shampoo dye for a deep, even black, or Creme of Nature dye.',
		'desc' => 'Cover the greys or bring back a deep, even black. Your barber cuts first, then colours: black shampoo dye for a quick refresh, or Creme of Nature for a richer, longer-lasting colour. Tell us about any allergies or scalp sensitivity before we start.',
		'options' => [ 'Haircut + black shampoo dye' => 800, 'Haircut + Creme of Nature dye' => 1000 ], 'attr' => 'Dye',
		'included' => [ 'Adult haircut in your style', 'Dye applied evenly', 'Rinse', 'Line-up and finish' ],
		'specs' => [ 'Time' => 'About 1 hour', 'Allergies' => 'Tell us before we start', 'Payment' => 'After your cut, M-Pesa or cash' ],
		'upsells' => [ 'beard-grooming', 'coloured-dye' ],
	],
	[
		'slug' => 'coloured-dye', 'name' => 'Coloured Dye: Blonde, Platinum & More', 'cats' => [ 'colour' ],
		'badge' => 'Stand out', 'perk' => 'Where hair becomes art', 'perks' => 'Blonde to platinum | Peach & custom colour | Designs', 'time' => 'About 2 hrs', 'mins' => 120,
		'clip' => 'platinum',
		'short' => 'Why blend in when you can stand out? Blonde, platinum, peach or a custom colour, for gents, ladies and everyone in between.',
		'desc' => 'From blonde to platinum, blond to peach, or a graffiti-style design: our colour work is all over our Instagram. We lift the colour, tone it to the shade you want, rinse, condition and style it. Colour takes time, so book ahead and come with a clean, healthy scalp.',
		'price' => 1500,
		'included' => [ 'Colour consultation', 'Lightening and toning to your shade', 'Rinse and condition', 'Styling finish' ],
		'specs' => [ 'Time' => 'About 2 hours', 'Shades' => 'Blonde, platinum, peach and custom', 'Deposit' => 'An M-Pesa deposit can hold a colour session', 'Aftercare' => 'Shampoo twice a week, condition every day' ],
		'upsells' => [ 'adult-haircut', 'hair-wash' ],
	],
	[
		'slug' => 'texturizer', 'name' => 'Texturizer', 'cats' => [ 'colour' ],
		'badge' => 'Waves & curl', 'perk' => 'Defined texture', 'perks' => 'Softens the hair | Defines waves & curl | Easy to brush', 'time' => 'About 45 min', 'mins' => 45,
		'short' => 'A mild texturizing treatment that softens the hair and brings out defined waves and curls, brushed in by your barber.',
		'desc' => 'A texturizer relaxes the hair just enough to make it soft and manageable, so your natural waves and curls come through defined and easy to brush. We apply it, time it carefully, rinse it out, condition and brush it in. Pair it with a fresh cut.',
		'price' => 500,
		'included' => [ 'Scalp check', 'Texturizer applied and timed', 'Rinse and conditioner', 'Brush-in' ],
		'specs' => [ 'Time' => 'About 45 minutes', 'Scalp' => 'Skip scratching or a fresh shave the day before', 'Payment' => 'After your cut, M-Pesa or cash' ],
		'upsells' => [ 'adult-haircut', 'hair-wash' ],
	],
	// Grooming & spa.
	[
		'slug' => 'face-scrub', 'name' => 'Face Scrub', 'cats' => [ 'grooming' ],
		'badge' => 'Skin care', 'perk' => 'Evict the stress', 'perks' => 'Deep scrub | Fresh, clean skin | Hot towel', 'time' => 'About 15 min', 'mins' => 15,
		'short' => 'A deep scrub to lift dead skin and grime, then a hot towel: fresh, clean skin after your cut.',
		'desc' => 'Pull up for a deep scrub to evict the stress. Your barber works a scrub over the face to lift dead skin and grime, wipes it off with a hot towel and leaves your skin feeling fresh and clean. Great after a shave.',
		'price' => 500,
		'included' => [ 'Cleanse', 'Exfoliating face scrub', 'Hot towel wipe' ],
		'specs' => [ 'Time' => 'About 15 minutes', 'Good with' => 'Hot towel shave or any haircut' ],
		'upsells' => [ 'facial', 'back-massage' ],
	],
	[
		'slug' => 'facial', 'name' => 'Full Facial', 'cats' => [ 'grooming' ], 'ask' => true,
		'badge' => 'Pamper', 'perk' => 'From cleanse to glow', 'perks' => 'Cleanse & scrub | Charcoal mask | Toner & massage', 'time' => 'About 45 min', 'mins' => 45,
		'clip' => 'facial',
		'short' => 'Skincare isn\'t just a luxury, it\'s self-care: cleanse, scrub, extraction, a charcoal peel-off mask, toner and a face massage.',
		'desc' => 'At Double Jay we go beyond the cut. Our full facial is a pamper session from cleanse to glow: an apricot scrub, steam and extraction, a charcoal peel-off mask to draw out the dirt, toner and a relaxing face massage. Ask for the price in the shop.',
		'price' => 0,
		'included' => [ 'Cleanse and apricot scrub', 'Steam and extraction', 'Charcoal peel-off mask', 'Toner and moisturiser', 'Face massage' ],
		'specs' => [ 'Time' => 'About 45 minutes', 'Price' => 'Confirmed in the shop', 'For' => 'Gents and ladies' ],
		'upsells' => [ 'face-scrub', 'back-massage' ],
	],
	[
		'slug' => 'back-massage', 'name' => 'Back Massage', 'cats' => [ 'grooming' ],
		'badge' => 'Soft life', 'perk' => 'Melt the tension', 'perks' => 'Back, neck & shoulders | Massage oil | Pure relief', 'time' => 'About 30 min', 'mins' => 30,
		'clip' => 'massage',
		'short' => 'A firm back, neck and shoulder massage with oil, the kind that has you questioning all your life choices.',
		'desc' => 'Come for the cut, stay for the soft life. Your therapist works the back, neck and shoulders with warm oil, easing out the knots from long days at the desk, behind the wheel or at the gym in the building.',
		'price' => 1000,
		'included' => [ 'Back, neck and shoulder massage', 'Massage oil', 'Hot towel wipe-down' ],
		'specs' => [ 'Time' => 'About 30 minutes', 'Good with' => 'Any haircut or facial', 'Payment' => 'After your session, M-Pesa or cash' ],
		'upsells' => [ 'face-scrub', 'facial' ],
	],
	[
		'slug' => 'hair-wash', 'name' => 'Hair Wash', 'cats' => [ 'grooming' ], 'ask' => true,
		'badge' => 'Fresh', 'perk' => 'So good it gets emotional', 'perks' => 'Shampoo at the basin | Scalp rub | Conditioner', 'time' => 'About 10 min', 'mins' => 10,
		'short' => 'A shampoo and scalp rub at the wash basin, so good it might make you emotional.',
		'desc' => 'Lean back at our wash basins for a proper shampoo, a scalp rub and conditioner. Perfect before a cut or after colour. Ask for the price in the shop.',
		'price' => 0,
		'included' => [ 'Shampoo at the basin', 'Scalp rub', 'Conditioner', 'Towel dry' ],
		'specs' => [ 'Time' => 'About 10 minutes', 'Price' => 'Confirmed in the shop', 'Good with' => 'Any haircut or colour' ],
		'upsells' => [ 'adult-haircut', 'texturizer' ],
	],
	// Manicure & pedicure.
	[
		'slug' => 'manicure', 'name' => 'Manicure', 'cats' => [ 'hands-feet' ],
		'badge' => 'Clean hands', 'perk' => 'Neat & tidy', 'perks' => 'Soak & cuticles | Trim, file & buff | Hand massage', 'time' => 'About 40 min', 'mins' => 40,
		'short' => 'Clean, tidy hands: a soak, cuticle care, nails trimmed, filed and buffed, and a hand massage.',
		'desc' => 'First impressions start with a handshake. We soak and tidy the cuticles, trim, file and buff the nails, then scrub and massage the hands. No polish unless you ask for it.',
		'price' => 800,
		'included' => [ 'Hand soak', 'Cuticle care', 'Trim, file and buff', 'Hand scrub and massage' ],
		'specs' => [ 'Time' => 'About 40 minutes', 'Good with' => 'Pedicure', 'Payment' => 'After your session, M-Pesa or cash' ],
		'upsells' => [ 'pedicure' ],
	],
	[
		'slug' => 'pedicure', 'name' => 'Pedicure', 'cats' => [ 'hands-feet' ],
		'badge' => 'Fresh feet', 'perk' => 'Soak, scrub, relax', 'perks' => 'Foot soak | Heels smoothed | Foot massage', 'time' => 'About 50 min', 'mins' => 50,
		'short' => 'Feet in the warm soak, nails trimmed, rough heels smoothed and a foot massage while you relax.',
		'desc' => 'Your feet carry you all week. Put them in our pedicure soak, then we trim and clean the nails, smooth rough heels, scrub and massage the feet. Book it with a manicure for the full set.',
		'price' => 1000,
		'included' => [ 'Warm foot soak', 'Nails trimmed and cleaned', 'Rough heels smoothed', 'Foot scrub and massage' ],
		'specs' => [ 'Time' => 'About 50 minutes', 'Good with' => 'Manicure', 'Payment' => 'After your session, M-Pesa or cash' ],
		'upsells' => [ 'manicure' ],
	],
	// Junior kings.
	[
		'slug' => 'kids-haircut', 'name' => 'Kids Haircut', 'cats' => [ 'kids' ],
		'badge' => 'Junior kings', 'perk' => 'Young kings stay fresh', 'perks' => 'Patient barbers | Clean & simple | Small designs', 'time' => 'About 30 min', 'mins' => 30,
		'short' => 'Gentle, patient cuts for young gentlemen: clean and simple for school, or with a small design.',
		'desc' => 'Sharp, simple and stylish. Our barbers take their time with little ones so the cut is calm and neat, from a clean school cut to a fade with a small design. Parents, book your own cut at the same time and sit side by side.',
		'price' => 200,
		'included' => [ 'Clipper cut to the style you choose', 'Gentle line-up', 'Small design on request', 'Clean-up' ],
		'specs' => [ 'Time' => 'About 30 minutes', 'Ages' => 'Young kings of every age', 'Parents' => 'Book your cut alongside' ],
		'upsells' => [ 'adult-haircut' ],
	],
	// Signature package: four list prices added up.
	[
		'slug' => 'double-jay-signature', 'name' => 'The Double Jay Signature', 'cats' => [ 'packages' ],
		'badge' => 'Best of the house', 'perk' => 'Walk out a legend', 'perks' => 'Haircut | Beard grooming | Face scrub | Back massage', 'time' => 'About 1 hr 40 min', 'mins' => 100,
		'short' => 'The full treatment: a haircut in your style, beard grooming, a deep face scrub and a back massage.',
		'desc' => 'Walk out looking like soft life is your portion. The Signature is everything in one sitting: a precision cut and line-up, beard grooming with a straight razor, a deep face scrub with a hot towel, and a back massage to finish. The price adds up our list prices for the four.',
		'price' => 2100,
		'included' => [ 'Adult haircut in your style (KSh 400)', 'Beard grooming (KSh 200)', 'Face scrub (KSh 500)', 'Back massage (KSh 1,000)' ],
		'specs' => [ 'Time' => 'About 1 hour 40 minutes', 'Deposit' => 'Optional M-Pesa deposit to hold the time', 'Payment' => 'After your session, M-Pesa or cash' ],
		'upsells' => [ 'hot-towel-shave', 'facial' ],
	],
];

function dj_attach_images( $pid, $slug, $name ) {
	$num   = fn( $f ) => preg_match( '#^' . preg_quote( $slug, '#' ) . '(?:-(\d+))?\.webp$#', basename( $f ), $m ) ? (int) ( $m[1] ?? 1 ) : 0;
	$files = array_values( array_filter( glob( "/wordpress/dj-images/$slug*.webp" ) ?: [], fn( $f ) => $num( $f ) > 0 ) );
	usort( $files, fn( $a, $b ) => $num( $a ) <=> $num( $b ) );
	$ids = [];
	foreach ( $files as $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$n   = $num( $file );
		$att = dj_sideload( $tmp, $base, $pid, $name . ( $n > 1 ? ' – photo ' . $n : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[ $n ] = $att;
	}
	return $ids; // photo number => attachment id
}

$ids = [];
foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['options'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_virtual( true );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['short'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	// The home page's "Most booked" row shows the featured services.
	$p->set_featured( in_array( $d['slug'], [ 'adult-haircut', 'double-jay-signature', 'coloured-dye', 'kids-haircut' ], true ) );
	foreach ( [ 'badge', 'perk', 'perks', 'time', 'mins', 'clip' ] as $k ) {
		if ( ! empty( $d[ $k ] ) ) $p->update_meta_data( '_dj_' . $k, $d[ $k ] );
	}
	$p->update_meta_data( '_dj_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	$p->update_meta_data( '_dj_included', implode( "\n", $d['included'] ) );
	if ( ! empty( $d['ask'] ) ) $p->update_meta_data( '_dj_ask', 'yes' );

	if ( $variable ) {
		$a = new WC_Product_Attribute();
		$a->set_name( $d['attr'] );
		$a->set_options( array_keys( $d['options'] ) );
		$a->set_position( 0 );
		$a->set_visible( true );
		$a->set_variation( true );
		$p->set_attributes( [ $a ] );
		$p->set_default_attributes( [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ] );
	} else {
		$p->set_regular_price( (string) $d['price'] );
	}
	$pid = $p->save();
	$ids[ $d['slug'] ] = $pid;

	$imgs = dj_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		// Written as meta: a second full product save here slowed the import.
		set_post_thumbnail( $pid, $imgs[1] ?? reset( $imgs ) );
		update_post_meta( $pid, '_product_image_gallery', implode( ',', array_values( array_diff_key( $imgs, [ 1 => true ] ) ) ) );
	}

	if ( $variable ) {
		$n = 0;
		foreach ( $d['options'] as $label => $price ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$v->set_attributes( [ sanitize_title( $d['attr'] ) => $label ] );
			$v->set_virtual( true );
			$v->set_regular_price( (string) $price );
			$v->set_menu_order( $n++ );
			$v->save();
		}
		WC_Product_Variable::sync( $pid );
	}
}

// "Pairs well with" on each service page.
foreach ( $products as $d ) {
	if ( ! empty( $d['upsells'] ) ) {
		update_post_meta( $ids[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $ids[ $s ] ?? 0, $d['upsells'] ) ) ) );
	}
}

// Pages.
function dj_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
dj_page( 'book', 'Book your cut', "<!-- wp:paragraph -->\n<p>Choose your services and any add-ons, pick your barber, a day and a time, and see your total as you go. Reserve it here, or send the summary to our WhatsApp and we confirm your chair.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[dj_booking]\n<!-- /wp:shortcode -->" );
dj_page( 'price-list', 'Services & pricing', "<!-- wp:paragraph -->\n<p>Every service at Double Jay with its price and typical time. Tap Book on anything to add it to your appointment.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[dj_price_list]\n<!-- /wp:shortcode -->" );
dj_page( 'lookbook', 'Gallery & looks', "<!-- wp:paragraph -->\n<p>Recent cuts, colour, line-ups and grooming from our chairs. See a look you like? Tap Get this look and it goes straight into your booking.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[dj_lookbook]\n<!-- /wp:shortcode -->" );

// Cart and checkout read as an appointment.
foreach ( [ 'shop' => 'All services', 'cart' => 'Your appointment', 'checkout' => 'Confirm your appointment' ] as $page => $title ) {
	$id = wc_get_page_id( $page );
	if ( $id > 0 ) wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
}

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
