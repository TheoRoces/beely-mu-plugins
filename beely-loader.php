<?php
/**
 * Plugin Name: Beely — chargeur
 * Description: Charge les extensions Beely rangées en sous-dossiers de mu-plugins. WordPress ne charge automatiquement que les fichiers PHP à la racine de mu-plugins : ce chargeur permet de garder chaque extension dans son propre dossier.
 * Version:     1.0.0
 * Author:      Beely
 *
 * Les extensions maison vivent ici plutôt que dans plugins/ pour trois raisons :
 * elles sont actives d'office (aucun risque de désactivation accidentelle par un
 * client), elles se chargent avant les extensions ordinaires, et elles
 * n'apparaissent pas dans la liste des extensions désactivables.
 *
 * Convention : un dossier `beely-<nom>/` contenant `beely-<nom>.php`.
 *
 * @package Beely
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordre de chargement.
 *
 * Explicite plutôt qu'alphabétique : le durcissement doit s'appliquer avant
 * tout le reste, et le pont REST après, pour bénéficier des protections.
 */
foreach ( [ 'beely-hardening', 'beely-seo', 'beely-bridge' ] as $extension ) {
	$file = __DIR__ . "/{$extension}/{$extension}.php";

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

/**
 * Charge les extensions restantes, y compris celles propres au site.
 *
 * Convention : un dossier `<nom>/` contenant `<nom>.php`. Elle vaut pour les
 * extensions du blueprint comme pour celles d'un projet — `<site>-contenu/` se
 * charge sans que le chargeur ait à connaître son nom.
 *
 * Le tri alphabétique rend l'ordre déterministe : deux installations du même
 * site chargent les extensions dans le même ordre.
 */
$loaded = [ 'beely-hardening', 'beely-seo', 'beely-bridge' ];

foreach ( glob( __DIR__ . '/*', GLOB_ONLYDIR ) ?: [] as $directory ) {
	$name = basename( $directory );

	if ( in_array( $name, $loaded, true ) ) {
		continue;
	}

	$file = "{$directory}/{$name}.php";

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
