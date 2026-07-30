<?php
/**
 * Tests du module « informations société » — sans WordPress, sans réseau.
 *
 * Ce qui est vérifié ici, c'est le remplissage : la partie où une erreur ne se
 * voit pas. Un sélecteur trop large abîme un balisage voisin, une valeur non
 * échappée ouvre une injection, et une page sans aucune classe `societe-*` ne
 * doit surtout pas repasser par l'analyseur HTML à chaque requête.
 *
 * Lancement : php blueprint/mu-plugins/beely-societe/tests/test-societe.php
 *
 * @package Beely\Societe
 */

declare( strict_types = 1 );

namespace Beely\Societe;

define( 'ABSPATH', __DIR__ );

/* --- Doublures WordPress --------------------------------------------- */

/** @var array<string, string> Options simulées. */
$options = [];

function reinitialiser( array $depart = [] ): void {
	global $options;

	$options = $depart;
}

function get_option( string $nom, $defaut = '' ) {
	global $options;

	return $options[ $nom ] ?? $defaut;
}

function home_url( string $chemin = '' ): string {
	return 'https://exemple.test' . $chemin;
}

function wp_parse_url( string $url, int $composant = -1 ) {
	$parts = parse_url( $url );

	return PHP_URL_HOST === $composant ? ( $parts['host'] ?? '' ) : $parts;
}

function is_front_page(): bool {
	return false;
}

function add_filter(): void {}
function add_action(): void {}
function wp_json_encode( $valeur, int $options = 0 ) {
	return json_encode( $valeur, $options );
}

require_once __DIR__ . '/../beely-societe.php';

/* --- Harnais --------------------------------------------------------- */

$passed = 0;
$failed = 0;

function test( string $nom, callable $fn ): void {
	global $passed, $failed;

	reinitialiser();

	try {
		$fn();
		$passed++;
		echo "  ✓ {$nom}\n";
	} catch ( \Throwable $e ) {
		$failed++;
		echo "  ✗ {$nom}\n      {$e->getMessage()}\n";
	}
}

function section( string $titre ): void {
	echo "\n{$titre}\n";
}

function assertSame( $attendu, $obtenu, string $message = '' ): void {
	if ( $attendu !== $obtenu ) {
		throw new \RuntimeException(
			sprintf(
				'%sattendu %s, obtenu %s',
				$message ? $message . ' : ' : '',
				var_export( $attendu, true ),
				var_export( $obtenu, true )
			)
		);
	}
}

function assertContient( string $aiguille, string $botte ): void {
	if ( ! str_contains( $botte, $aiguille ) ) {
		throw new \RuntimeException( "« {$aiguille} » absent de : {$botte}" );
	}
}

function assertAbsent( string $aiguille, string $botte ): void {
	if ( str_contains( $botte, $aiguille ) ) {
		throw new \RuntimeException( "« {$aiguille} » présent alors qu’il ne devait pas : {$botte}" );
	}
}

/* --- Lecture des champs ---------------------------------------------- */

section( 'Lecture des champs' );

test( 'un champ absent rend une chaîne vide, jamais null', function (): void {
	assertSame( '', valeur( 'nom' ) );
} );

test( 'le préfixe est ajouté s’il manque', function (): void {
	reinitialiser( [ 'societe_nom' => 'Exemple' ] );

	assertSame( 'Exemple', valeur( 'nom' ) );
	assertSame( 'Exemple', valeur( 'societe_nom' ) );
} );

test( 'les espaces de saisie sont retirés', function (): void {
	reinitialiser( [ 'societe_ville' => "  Niort \n" ] );

	assertSame( 'Niort', valeur( 'ville' ) );
} );

/* --- Adresse ---------------------------------------------------------- */

section( 'Adresse' );

test( 'les morceaux manquants ne laissent pas de virgule flottante', function (): void {
	/*
	 * Le défaut visé : « 12 rue des Halles,  , France ». Il ne saute pas aux
	 * yeux en relecture, et il s'affiche sur toutes les pages d'un site dont
	 * la ville n'a pas été saisie.
	 */
	reinitialiser(
		[
			'societe_adresse' => '12 rue des Halles',
			'societe_pays'    => 'France',
		]
	);

	assertSame( '12 rue des Halles, France', adresse_complete() );
} );

test( 'code postal et ville sont assemblés sans virgule entre eux', function (): void {
	reinitialiser(
		[
			'societe_adresse'     => '12 rue des Halles',
			'societe_code_postal' => '79000',
			'societe_ville'       => 'Niort',
		]
	);

	assertSame( '12 rue des Halles, 79000 Niort', adresse_complete() );
} );

test( 'une adresse entièrement vide rend une chaîne vide', function (): void {
	assertSame( '', adresse_complete() );
} );

/* --- Téléphone -------------------------------------------------------- */

section( 'Téléphone' );

test( 'un numéro français reçoit son indicatif', function (): void {
	reinitialiser( [ 'societe_telephone' => '05 49 12 34 56' ] );

	assertSame( '+33549123456', telephone_lien() );
} );

test( 'un numéro déjà international n’est pas retouché', function (): void {
	reinitialiser( [ 'societe_telephone' => '+32 2 123 45 67' ] );

	assertSame( '+3221234567', telephone_lien() );
} );

test( 'les points et tirets de saisie disparaissent du lien', function (): void {
	reinitialiser( [ 'societe_telephone' => '05.49.12.34.56' ] );

	assertSame( '+33549123456', telephone_lien() );
} );

test( 'un numéro court n’est pas pris pour un numéro français', function (): void {
	// 3949, 15, 112 : quatre chiffres ou moins, aucun indicatif à ajouter.
	reinitialiser( [ 'societe_telephone' => '3949' ] );

	assertSame( '3949', telephone_lien() );
} );

/* --- Remplissage ------------------------------------------------------ */

section( 'Remplissage du contenu' );

test( 'une page sans classe société ressort à l’identique', function (): void {
	reinitialiser( [ 'societe_nom' => 'Exemple' ] );

	$html = '<p>Bonjour tout le monde.</p>';

	assertSame( $html, remplir( $html ) );
} );

test( 'la valeur remplace le contenu de l’élément', function (): void {
	reinitialiser( [ 'societe_nom' => 'Studio Exemple' ] );

	assertContient( 'Studio Exemple', remplir( '<span class="societe-nom">…</span>' ) );
} );

test( 'un préfixe partagé ne déborde pas sur une autre classe', function (): void {
	/*
	 * `societe-nom` ne doit pas viser `societe-nom-complet`. Une recherche
	 * naïve par `contains(@class, "societe-nom")` les confond, et l'élément
	 * voisin reçoit une valeur qui n'est pas la sienne.
	 */
	reinitialiser( [ 'societe_nom' => 'Studio' ] );

	$sortie = remplir( '<span class="societe-nom-complet">intact</span>' );

	assertContient( 'intact', $sortie );
	assertAbsent( 'Studio', $sortie );
} );

test( 'la classe est reconnue au milieu d’autres', function (): void {
	reinitialiser( [ 'societe_ville' => 'Niort' ] );

	assertContient( 'Niort', remplir( '<span class="u-gras societe-ville is-actif">…</span>' ) );
} );

test( 'un lien reçoit aussi son href', function (): void {
	reinitialiser( [ 'societe_email' => 'contact@exemple.test' ] );

	$sortie = remplir( '<a class="societe-email" href="#">…</a>' );

	assertContient( 'mailto:contact@exemple.test', $sortie );
	assertContient( '>contact@exemple.test<', $sortie );
} );

test( 'le href n’est posé que sur un lien', function (): void {
	reinitialiser( [ 'societe_email' => 'contact@exemple.test' ] );

	assertAbsent( 'href', remplir( '<span class="societe-email">…</span>' ) );
} );

test( 'un champ non renseigné laisse le contenu en place', function (): void {
	// Sinon un site en cours de configuration affiche des blocs vides.
	assertContient( 'à compléter', remplir( '<span class="societe-siret">à compléter</span>' ) );
} );

test( 'une ancre sans valeur devient un span plutôt que de pointer vers #', function (): void {
	/*
	 * Le balisage de départ porte `href="#"` en attendant la valeur. Tant
	 * qu'elle manque, c'est un lien qui ne mène nulle part : il prend le focus
	 * au clavier, s'annonce comme un lien, et recharge la page si on l'active.
	 * Mesuré par `audit-fonctionnel.mjs` sur les pages légales du blueprint.
	 */
	$sortie = remplir( '<a class="societe-email" href="#">[EMAIL]</a>' );

	assertAbsent( '<a', $sortie );
	assertContient( '<span class="societe-email">', $sortie );
	assertContient( '[EMAIL]', $sortie );
} );

test( 'une destination écrite à la main est respectée', function (): void {
	// On retire un lien mort, pas un lien que quelqu'un a voulu.
	$sortie = remplir( '<a class="societe-email" href="/contact/">Nous écrire</a>' );

	assertContient( 'href="/contact/"', $sortie );
} );

test( 'un span sans valeur n’est pas touché', function (): void {
	// Le retrait ne vise que les ancres : un span n'a pas de href à perdre.
	assertContient( '[EMAIL]', remplir( '<span class="societe-email">[EMAIL]</span>' ) );
} );

test( 'une ancre renseignée garde bien son href', function (): void {
	// Le contrôle inverse du précédent : le retrait ne doit pas déborder.
	reinitialiser( [ 'societe_email' => 'contact@exemple.test' ] );

	assertContient( 'mailto:contact@exemple.test', remplir( '<a class="societe-email" href="#">…</a>' ) );
} );

test( 'une valeur ne peut pas injecter de balise', function (): void {
	/*
	 * `textContent` échappe ce qu'on lui donne. Le vérifier explicitement :
	 * un passage à `innerHTML` pour « permettre le gras » rouvrirait un XSS
	 * exploitable par tout compte pouvant modifier les réglages.
	 */
	reinitialiser( [ 'societe_nom' => '<script>alert(1)</script>' ] );

	$sortie = remplir( '<span class="societe-nom">…</span>' );

	assertAbsent( '<script>', $sortie );
	assertContient( '&lt;script&gt;', $sortie );
} );

test( 'les accents du texte environnant traversent le remplissage intacts', function (): void {
	/*
	 * `DOMDocument` interprète le HTML en ISO-8859-1 par défaut. Sans la
	 * déclaration d'encodage, « Château » ressort en « ChÃ¢teau » — et le
	 * défaut touche **tout le texte de la page**, pas seulement les champs
	 * remplis, puisque le contenu entier repasse par l'analyseur.
	 *
	 * Le contrôle porte donc sur du texte qui n'a pas été écrit par le module :
	 * une assertion sur la valeur insérée ne prouverait rien, celle-ci venant
	 * d'une option PHP déjà en UTF-8.
	 */
	reinitialiser( [ 'societe_ville' => 'Niort' ] );

	$sortie = remplir( '<p>Été à Châteauneuf</p><span class="societe-ville">…</span>' );

	assertContient( 'Été à Châteauneuf', $sortie );
	assertContient( 'Niort', $sortie );
} );

test( 'le balisage autour n’est pas réécrit', function (): void {
	reinitialiser( [ 'societe_nom' => 'Studio' ] );

	$sortie = remplir( '<div class="c-pied"><ul><li><span class="societe-nom">…</span></li></ul></div>' );

	assertContient( '<div class="c-pied">', $sortie );
	assertContient( '<li>', $sortie );
	assertContient( 'Studio', $sortie );
} );

test( 'plusieurs éléments de la même classe sont tous remplis', function (): void {
	reinitialiser( [ 'societe_telephone' => '05 49 12 34 56' ] );

	$sortie = remplir( '<span class="societe-telephone">a</span><span class="societe-telephone">b</span>' );

	assertSame( 2, substr_count( $sortie, '05 49 12 34 56' ) );
} );

test( 'un contenu vide ne déclenche aucun traitement', function (): void {
	assertSame( '', remplir( '' ) );
	assertSame( '   ', remplir( '   ' ) );
} );

test( 'l’année courante est disponible sans réglage', function (): void {
	// Sert au « © 2026 » d'un pied de page, qu'on oublie sinon de mettre à jour.
	assertContient( gmdate( 'Y' ), remplir( '<span class="societe-annee">2000</span>' ) );
} );

/* --- Résultat --------------------------------------------------------- */

echo "\n";
echo $failed > 0
	? "{$passed} test(s) réussi(s), {$failed} échec(s).\n"
	: "{$passed} test(s) réussi(s), 0 échec.\n";

exit( $failed > 0 ? 1 : 0 );
