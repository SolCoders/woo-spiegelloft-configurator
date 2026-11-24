<?php
/** German display text shared by the configurator, cart and order attributes. */
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

class WCS_Storefront_Text {
	/** Translate built-in labels without changing selection keys or customer input. */
	public static function label( string $text ): string {
		$labels = array(
			'width' => 'Breite', 'height' => 'Höhe', 'diameter' => 'Durchmesser',
			'top width' => 'Obere Breite', 'bottom width' => 'Untere Breite',
			'left height' => 'Linke Höhe', 'right height' => 'Rechte Höhe',
			'none' => 'Keine Auswahl', 'yes' => 'Ja', 'no' => 'Nein',
			'size selection' => 'Maßauswahl', 'light color' => 'Lichtfarbe',
			'makeup mirror' => 'Schminkspiegel', 'clock' => 'Uhr', 'sockets' => 'Steckdosen',
			'side distance' => 'Seitlicher Abstand', 'bottom distance' => 'Abstand zur Unterkante',
			'switch position' => 'Schalterposition', 'socket color' => 'Steckdosenfarbe',
			'socket position' => 'Steckdosenposition',
		);
		return $labels[ strtolower( trim( str_replace( '_', ' ', $text ) ) ) ] ?? $text;
	}

	/** Translate default rules saved by earlier plugin versions. */
	public static function message( string $text ): string {
		$messages = array(
			'Illuminated makeup mirror requires minimum dimensions of 500 x 500 mm.' => 'Ein beleuchteter Schminkspiegel erfordert Mindestmaße von 500 × 500 mm.',
			'Choose the makeup mirror type.' => 'Bitte wählen Sie die Art des Schminkspiegels.',
			'Choose the makeup mirror position.' => 'Bitte wählen Sie die Position des Schminkspiegels.',
			'Enter the makeup mirror side distance.' => 'Bitte geben Sie den seitlichen Abstand des Schminkspiegels ein.',
			'Enter the makeup mirror bottom distance.' => 'Bitte geben Sie den Abstand des Schminkspiegels zur Unterkante ein.',
			'Makeup mirror side distance is outside the allowed range.' => 'Der seitliche Abstand des Schminkspiegels liegt außerhalb des zulässigen Bereichs.',
			'Makeup mirror bottom distance is outside the allowed range.' => 'Der Abstand des Schminkspiegels zur Unterkante liegt außerhalb des zulässigen Bereichs.',
			'Choose a light color for the illuminated makeup mirror.' => 'Bitte wählen Sie eine Lichtfarbe für den beleuchteten Schminkspiegel.',
			'Choose the switch position.' => 'Bitte wählen Sie die Schalterposition.',
			'Choose the touch sensor position.' => 'Bitte wählen Sie die Position des Touchsensors.',
			'Choose the digital clock position.' => 'Bitte wählen Sie die Position der Digitaluhr.',
			'Choose the socket color.' => 'Bitte wählen Sie die Steckdosenfarbe.',
			'Choose the socket position.' => 'Bitte wählen Sie die Steckdosenposition.',
			'Makeup mirror requires at least three sides of 400 mm or more.' => 'Ein Schminkspiegel erfordert mindestens drei Seiten mit jeweils mindestens 400 mm.',
			'Glass shelf requires a usable top or bottom width.' => 'Eine Glasablage erfordert eine geeignete obere oder untere Breite.',
		);
		if ( isset( $messages[ $text ] ) ) { return $messages[ $text ]; }
		if ( preg_match( '/^(Width|Height|Diameter|Top width|Bottom width|Left height|Right height) is outside this product range\.$/', $text, $match ) ) {
			return self::label( $match[1] ) . ' liegt außerhalb des zulässigen Bereichs für dieses Produkt.';
		}
		return $text;
	}

	/** Only translate presentation properties; stored identifiers stay unchanged. */
	public static function config( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) { $data[ $key ] = self::config( $value ); }
			elseif ( is_string( $value ) && in_array( $key, array( 'label', 'title', 'name', 'placeholder', 'desc' ), true ) ) { $data[ $key ] = self::label( $value ); }
			elseif ( 'message' === $key && is_string( $value ) ) { $data[ $key ] = self::message( $value ); }
		}
		return $data;
	}

	/** Blank selections and placeholder rows do not belong in cart/order attributes. */
	public static function filled( $value, bool $exclude_placeholders = true ): bool {
		$empty = $exclude_placeholders ? array( '', 'none', '---', 'keine auswahl' ) : array( '' );
		return is_scalar( $value ) && ! in_array( strtolower( trim( (string) $value ) ), $empty, true );
	}

	/** German WooCommerce feedback only while submitting the configurator. */
	public static function woocommerce_message( string $translated, string $original, string $domain ): string {
		if ( 'woocommerce' !== $domain ) { return $translated; }
		$messages = array(
			'Sorry, this product cannot be purchased.' => 'Dieses Produkt kann derzeit nicht gekauft werden.',
			'The selected product is invalid.' => 'Das ausgewählte Produkt ist ungültig.',
			'Please choose product options&hellip;' => 'Bitte wählen Sie die Produktoptionen aus.',
			'You cannot add another "%s" to your cart.' => 'Sie können „%s“ nicht erneut zum Warenkorb hinzufügen.',
			'You cannot add &quot;%s&quot; to the cart because the product is out of stock.' => 'Sie können „%s“ nicht zum Warenkorb hinzufügen, da das Produkt nicht vorrätig ist.',
			'You cannot add that amount of &quot;%1$s&quot; to the cart because there is not enough stock (%2$s remaining).' => 'Die gewünschte Menge von „%1$s“ ist nicht vorrätig. Verfügbar: %2$s.',
			'You cannot add that amount to the cart &mdash; we have %1$s in stock and you already have %2$s in your cart.' => 'Diese Menge kann nicht hinzugefügt werden. Vorrätig: %1$s. Bereits im Warenkorb: %2$s.',
			'View cart' => 'Warenkorb ansehen',
		);
		return $messages[ $original ] ?? $translated;
	}
}
