<?php
/**
 * Plugin Name: Beely — informations société
 * Description: Coordonnées, mentions légales et traceurs déclarés en un seul endroit. Alimente le pied de page, les pages légales, les données structurées et le bandeau de cookies.
 * Version:     2.0.0
 * Author:      Beely
 *
 * Le principe : **une information, un endroit**. Le numéro de téléphone est
 * saisi une fois ; le pied de page, la page de contact, les mentions légales et
 * les données structurées le lisent tous au même endroit. Changer d'adresse
 * devient une modification, pas une chasse.
 *
 * Le remplissage se fait côté serveur : un élément portant `.societe-telephone`
 * reçoit sa valeur dans le HTML servi. La version précédente le faisait en
 * JavaScript — la page partait donc avec des espaces vides, que les moteurs de
 * recherche indexaient tels quels, et que personne ne voyait clignoter.
 *
 * @package Beely\Societe
 */

declare( strict_types = 1 );

namespace Beely\Societe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Champs déclarés, par groupe.
 *
 * `type` décide du contrôle affiché et de l'assainissement appliqué. `aide`
 * n'est pas décoratif : sans exemple, « Immatriculation » se remplit
 * différemment à chaque site.
 *
 * @var array<string, array{titre:string, champs:array<string, array{label:string, type:string, aide?:string}>}>
 */
const GROUPES = [
	'identite' => [
		'titre'  => 'Identité',
		'champs' => [
			'societe_nom'          => [ 'label' => 'Nom commercial', 'type' => 'text' ],
			'societe_raison'       => [ 'label' => 'Raison sociale', 'type' => 'text', 'aide' => 'Le nom juridique, s’il diffère du nom commercial.' ],
			'societe_forme'        => [ 'label' => 'Forme juridique', 'type' => 'text', 'aide' => 'SAS, SARL, entrepreneur individuel…' ],
			'societe_capital'      => [ 'label' => 'Capital social', 'type' => 'text', 'aide' => 'Par exemple : 10 000 €' ],
			'societe_siret'        => [ 'label' => 'SIRET', 'type' => 'text', 'aide' => '14 chiffres.' ],
			'societe_tva'          => [ 'label' => 'N° de TVA intracommunautaire', 'type' => 'text', 'aide' => 'FR suivi de 11 caractères.' ],
			'societe_rcs'          => [ 'label' => 'Immatriculation', 'type' => 'text', 'aide' => 'Par exemple : RCS Niort B 510 909 807' ],
			'societe_ape'          => [ 'label' => 'Code APE / NAF', 'type' => 'text' ],
		],
	],
	'contact'  => [
		'titre'  => 'Coordonnées',
		'champs' => [
			'societe_email'        => [ 'label' => 'Adresse e-mail', 'type' => 'email' ],
			'societe_telephone'    => [ 'label' => 'Téléphone', 'type' => 'tel', 'aide' => 'Saisi comme il doit s’afficher ; le lien d’appel est construit tout seul.' ],
			'societe_adresse'      => [ 'label' => 'Adresse — rue', 'type' => 'text' ],
			'societe_code_postal'  => [ 'label' => 'Code postal', 'type' => 'text' ],
			'societe_ville'        => [ 'label' => 'Ville', 'type' => 'text' ],
			'societe_pays'         => [ 'label' => 'Pays', 'type' => 'text' ],
			'societe_horaires'     => [ 'label' => 'Horaires', 'type' => 'textarea', 'aide' => 'Une ligne par jour ou par plage.' ],
		],
	],
	'legal'    => [
		'titre'  => 'Responsabilités et hébergement',
		'champs' => [
			'societe_directeur'    => [ 'label' => 'Directeur de la publication', 'type' => 'text' ],
			'societe_dpo'          => [ 'label' => 'Contact données personnelles', 'type' => 'text', 'aide' => 'Adresse e-mail à laquelle adresser une demande RGPD.' ],
			'societe_hebergeur'    => [ 'label' => 'Hébergeur — nom', 'type' => 'text' ],
			'societe_hebergeur_adresse' => [ 'label' => 'Hébergeur — adresse', 'type' => 'textarea' ],
			'societe_hebergeur_tel' => [ 'label' => 'Hébergeur — téléphone', 'type' => 'tel' ],
			'societe_mediateur'    => [ 'label' => 'Médiateur de la consommation', 'type' => 'textarea', 'aide' => 'Obligatoire si vous vendez à des particuliers.' ],
		],
	],
	'reseaux'  => [
		'titre'  => 'Réseaux sociaux',
		'champs' => [
			'societe_linkedin'     => [ 'label' => 'LinkedIn', 'type' => 'url' ],
			'societe_instagram'    => [ 'label' => 'Instagram', 'type' => 'url' ],
			'societe_facebook'     => [ 'label' => 'Facebook', 'type' => 'url' ],
			'societe_youtube'      => [ 'label' => 'YouTube', 'type' => 'url' ],
		],
	],
];

/**
 * Traceurs déclarables, avec leur format d'identifiant.
 *
 * `beely-cookies` lit ces mêmes options : les déclarer ici et les consommer
 * là-bas évite d'avoir deux écrans qui prétendent régler la même chose.
 *
 * @var array<string, array{label:string, exemple:string}>
 */
const TRACEURS = [
	'ga4'        => [ 'label' => 'Google Analytics 4', 'exemple' => 'G-XXXXXXXXXX' ],
	'google_ads' => [ 'label' => 'Google Ads', 'exemple' => 'AW-XXXXXXXXXX' ],
	'meta_pixel' => [ 'label' => 'Pixel Meta', 'exemple' => '15 chiffres' ],
	'clarity'    => [ 'label' => 'Microsoft Clarity', 'exemple' => 'XXXXXXXXXX' ],
];

/* ------------------------------------------------------------------ */
/* Lecture                                                             */
/* ------------------------------------------------------------------ */

/**
 * Valeur d'un champ, ou chaîne vide.
 *
 * @param string $champ Nom de l'option, avec ou sans le préfixe `societe_`.
 */
function valeur( string $champ ): string {
	if ( ! str_starts_with( $champ, 'societe_' ) ) {
		$champ = 'societe_' . $champ;
	}

	return trim( (string) get_option( $champ, '' ) );
}

/**
 * Adresse postale sur une ligne.
 *
 * Les morceaux absents sont écartés : sans cela, un site sans code postal
 * affiche « 12 rue des Halles,  , Niort » — la virgule flottante trahit
 * l'assemblage automatique.
 */
function adresse_complete(): string {
	$ville = trim( valeur( 'code_postal' ) . ' ' . valeur( 'ville' ) );

	return implode( ', ', array_filter( [ valeur( 'adresse' ), $ville, valeur( 'pays' ) ] ) );
}

/** Numéro de téléphone au format d'un lien `tel:`. */
function telephone_lien(): string {
	$brut = (string) preg_replace( '/[^\d+]/', '', valeur( 'telephone' ) );

	// Un numéro français saisi « 05 49 … » devient « +335 49 … » sans indicatif.
	if ( str_starts_with( $brut, '0' ) && 10 === strlen( $brut ) ) {
		return '+33' . substr( $brut, 1 );
	}

	return $brut;
}

/* ------------------------------------------------------------------ */
/* Remplissage du front, côté serveur                                  */
/* ------------------------------------------------------------------ */

/**
 * Classes reconnues dans le contenu, et ce qu'elles reçoivent.
 *
 * @return array<string, array{valeur:string, lien?:string}>
 */
function correspondances(): array {
	return [
		'societe-nom'        => [ 'valeur' => valeur( 'nom' ) ],
		'societe-raison'     => [ 'valeur' => valeur( 'raison' ) ?: valeur( 'nom' ) ],
		'societe-forme'      => [ 'valeur' => valeur( 'forme' ) ],
		'societe-capital'    => [ 'valeur' => valeur( 'capital' ) ],
		'societe-siret'      => [ 'valeur' => valeur( 'siret' ) ],
		'societe-tva'        => [ 'valeur' => valeur( 'tva' ) ],
		'societe-rcs'        => [ 'valeur' => valeur( 'rcs' ) ],
		'societe-ape'        => [ 'valeur' => valeur( 'ape' ) ],
		'societe-email'      => [ 'valeur' => valeur( 'email' ), 'lien' => 'mailto:' . valeur( 'email' ) ],
		'societe-telephone'  => [ 'valeur' => valeur( 'telephone' ), 'lien' => 'tel:' . telephone_lien() ],
		'societe-adresse'    => [ 'valeur' => adresse_complete() ],
		'societe-ville'      => [ 'valeur' => trim( valeur( 'code_postal' ) . ' ' . valeur( 'ville' ) ) ],
		'societe-horaires'   => [ 'valeur' => valeur( 'horaires' ) ],
		'societe-directeur'  => [ 'valeur' => valeur( 'directeur' ) ],
		'societe-dpo'        => [ 'valeur' => valeur( 'dpo' ), 'lien' => 'mailto:' . valeur( 'dpo' ) ],
		'societe-hebergeur'  => [ 'valeur' => valeur( 'hebergeur' ) ],
		'societe-hebergeur-adresse' => [ 'valeur' => valeur( 'hebergeur_adresse' ) ],
		'societe-hebergeur-tel'     => [ 'valeur' => valeur( 'hebergeur_tel' ) ],
		'societe-mediateur'  => [ 'valeur' => valeur( 'mediateur' ) ],
		'societe-site'       => [ 'valeur' => (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'lien' => home_url( '/' ) ],
		'societe-annee'      => [ 'valeur' => gmdate( 'Y' ) ],
	];
}

/**
 * Remplit les éléments porteurs d'une classe `societe-*` dans le HTML servi.
 *
 * Le remplacement passe par `DOMDocument`, pas par une expression régulière :
 * une classe peut être en deuxième position, entourée d'autres, et le contenu
 * à remplacer peut contenir des chevrons. Un motif qui gère tous ces cas
 * n'existe pas — celui qui prétend le faire échoue en silence sur le premier
 * balisage inhabituel.
 *
 * @param string $html Contenu de la page.
 * @return string
 */
function remplir( string $html ): string {
	if ( '' === trim( $html ) || ! str_contains( $html, 'societe-' ) ) {
		return $html;
	}

	$toutes = correspondances();

	$correspondances = array_filter(
		$toutes,
		static fn ( array $entree ): bool => '' !== $entree['valeur']
	);

	/*
	 * Les champs vides sont traités aussi, mais différemment : leur contenu de
	 * remplacement reste visible — c'est ce qui montre à l'éditeur du site où
	 * une information manque — alors que le lien mort qui les porte, lui, doit
	 * partir.
	 */
	$vides = array_filter(
		$toutes,
		static fn ( array $entree ): bool => '' === $entree['valeur'] && isset( $entree['lien'] )
	);

	if ( ! $correspondances && ! $vides ) {
		return $html;
	}

	$document = new \DOMDocument();

	// Le HTML servi est un fragment : les avertissements de libxml sur les
	// balises non fermées ou les éléments inconnus n'apprennent rien ici.
	$precedent = libxml_use_internal_errors( true );

	$charge = $document->loadHTML(
		'<?xml encoding="UTF-8"?><div id="beely-racine">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);

	libxml_clear_errors();
	libxml_use_internal_errors( $precedent );

	if ( ! $charge ) {
		return $html;
	}

	$xpath   = new \DOMXPath( $document );
	$touches = 0;

	foreach ( $correspondances as $classe => $entree ) {
		// `concat` encadre la liste d'espaces : sans cela, `societe-nom`
		// correspondrait aussi à `societe-nom-complet`.
		$noeuds = $xpath->query(
			sprintf( '//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $classe )
		);

		if ( ! $noeuds ) {
			continue;
		}

		foreach ( $noeuds as $noeud ) {
			if ( ! $noeud instanceof \DOMElement ) {
				continue;
			}

			$noeud->textContent = $entree['valeur'];

			if ( isset( $entree['lien'] ) && 'a' === strtolower( $noeud->nodeName ) ) {
				$noeud->setAttribute( 'href', $entree['lien'] );
			}

			$touches++;
		}
	}

	/*
	 * Un champ non renseigné n'est plus une ancre du tout.
	 *
	 * Le balisage de départ porte `href="#"` en attendant la valeur. Tant
	 * qu'elle manque, c'est un lien qui ne mène nulle part : il reçoit le focus
	 * au clavier, s'annonce comme un lien à un lecteur d'écran, et recharge la
	 * page si on l'active.
	 *
	 * Retirer le seul `href` ne suffit pas : il resterait un `<a>` sans
	 * destination, c'est-à-dire du balisage qui n'exprime plus rien. L'élément
	 * devient un `<span>`, avec ses classes et son texte — l'emplacement reste
	 * visible pour qui doit le remplir, sans promettre une destination.
	 */
	foreach ( $vides as $classe => $entree ) {
		$noeuds = $xpath->query(
			sprintf( '//a[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $classe )
		);

		if ( ! $noeuds ) {
			continue;
		}

		// La liste est vivante : remplacer un nœud pendant l'itération fausse
		// le parcours. On la fige avant de toucher au document.
		$ancres = [];

		foreach ( $noeuds as $noeud ) {
			if ( $noeud instanceof \DOMElement ) {
				$ancres[] = $noeud;
			}
		}

		foreach ( $ancres as $ancre ) {
			$href = $ancre->getAttribute( 'href' );

			// Une destination écrite à la main est respectée.
			if ( '' !== $href && '#' !== $href ) {
				continue;
			}

			$span = $document->createElement( 'span' );

			foreach ( iterator_to_array( $ancre->attributes ) as $attribut ) {
				if ( 'href' === $attribut->nodeName || 'target' === $attribut->nodeName || 'rel' === $attribut->nodeName ) {
					continue;
				}

				$span->setAttribute( $attribut->nodeName, $attribut->nodeValue );
			}

			while ( $ancre->firstChild ) {
				$span->appendChild( $ancre->firstChild );
			}

			$ancre->parentNode?->replaceChild( $span, $ancre );
			$touches++;
		}
	}

	if ( 0 === $touches ) {
		return $html;
	}

	$racine = $document->getElementById( 'beely-racine' );

	if ( ! $racine ) {
		return $html;
	}

	$sortie = '';

	foreach ( $racine->childNodes as $enfant ) {
		$sortie .= $document->saveHTML( $enfant );
	}

	return $sortie;
}

/*
 * Le remplissage s'applique au contenu rendu, pas à la page entière.
 *
 * Passer tout le document dans `DOMDocument` coûterait une analyse complète du
 * HTML à chaque requête, y compris sur les pages qui ne contiennent aucune
 * classe `societe-*`. Le filtre sort d'ailleurs immédiatement quand la chaîne
 * n'apparaît pas.
 */
foreach ( [ 'the_content', 'widget_text', 'bricks/frontend/render_data' ] as $filtre ) {
	add_filter( $filtre, __NAMESPACE__ . '\\remplir', 20 );
}

/* ------------------------------------------------------------------ */
/* Données structurées                                                 */
/* ------------------------------------------------------------------ */

/**
 * Décrit l'organisation en JSON-LD.
 *
 * C'est ce que lisent les moteurs pour rattacher un site à une entreprise
 * réelle — et ce qui alimente le panneau latéral d'un résultat de recherche.
 * Les champs vides sont omis : un `telephone` vide vaut moins que pas de
 * champ du tout, il fait douter du reste.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( ! is_front_page() || '' === valeur( 'nom' ) ) {
			return;
		}

		$donnees = array_filter(
			[
				'@context'    => 'https://schema.org',
				'@type'       => 'Organization',
				'name'        => valeur( 'raison' ) ?: valeur( 'nom' ),
				'alternateName' => valeur( 'raison' ) ? valeur( 'nom' ) : '',
				'url'         => home_url( '/' ),
				'email'       => valeur( 'email' ),
				'telephone'   => telephone_lien(),
				'vatID'       => valeur( 'tva' ),
				'taxID'       => valeur( 'siret' ),
			]
		);

		$adresse = array_filter(
			[
				'@type'           => 'PostalAddress',
				'streetAddress'   => valeur( 'adresse' ),
				'postalCode'      => valeur( 'code_postal' ),
				'addressLocality' => valeur( 'ville' ),
				'addressCountry'  => valeur( 'pays' ),
			]
		);

		// Le `@type` seul ne fait pas une adresse : sans rue ni ville, on l'omet.
		if ( count( $adresse ) > 1 ) {
			$donnees['address'] = $adresse;
		}

		$reseaux = array_values(
			array_filter(
				[ valeur( 'linkedin' ), valeur( 'instagram' ), valeur( 'facebook' ), valeur( 'youtube' ) ]
			)
		);

		if ( $reseaux ) {
			$donnees['sameAs'] = $reseaux;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $donnees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	},
	20
);

/* ------------------------------------------------------------------ */
/* Écran de réglages                                                   */
/* ------------------------------------------------------------------ */

/** Fonction d'assainissement adaptée au type d'un champ. */
function nettoyeur( string $type ): string {
	return match ( $type ) {
		'email'    => 'sanitize_email',
		'url'      => 'esc_url_raw',
		'textarea' => 'sanitize_textarea_field',
		default    => 'sanitize_text_field',
	};
}

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			__( 'Informations société', 'beely' ),
			__( 'Société', 'beely' ),
			'manage_options',
			'beely-societe',
			__NAMESPACE__ . '\\ecran',
			'dashicons-building',
			25
		);
	}
);

add_action(
	'admin_init',
	static function (): void {
		foreach ( GROUPES as $clef => $groupe ) {
			add_settings_section( "beely_{$clef}", $groupe['titre'], '__return_false', 'beely-societe' );

			foreach ( $groupe['champs'] as $nom => $champ ) {
				register_setting( 'beely_societe', $nom, [ 'sanitize_callback' => nettoyeur( $champ['type'] ) ] );

				add_settings_field(
					$nom,
					$champ['label'],
					static function () use ( $nom, $champ ): void {
						$valeur = (string) get_option( $nom, '' );

						if ( 'textarea' === $champ['type'] ) {
							printf(
								'<textarea name="%s" rows="4" class="large-text">%s</textarea>',
								esc_attr( $nom ),
								esc_textarea( $valeur )
							);
						} else {
							printf(
								'<input type="%s" name="%s" value="%s" class="regular-text">',
								esc_attr( $champ['type'] ),
								esc_attr( $nom ),
								esc_attr( $valeur )
							);
						}

						if ( isset( $champ['aide'] ) ) {
							printf( '<p class="description">%s</p>', esc_html( $champ['aide'] ) );
						}
					},
					'beely-societe',
					"beely_{$clef}"
				);
			}
		}

		/* --- Traceurs --- */

		add_settings_section(
			'beely_traceurs',
			__( 'Outils de mesure', 'beely' ),
			static function (): void {
				printf(
					'<p>%s</p>',
					esc_html__( 'Un outil coché sans identifiant est ignoré. Le bandeau de consentement s’adapte à ce qui est déclaré ici : sans aucun outil, il ne s’affiche pas.', 'beely' )
				);
			},
			'beely-societe'
		);

		foreach ( TRACEURS as $clef => $traceur ) {
			register_setting( 'beely_societe', "use_{$clef}", 'absint' );
			register_setting( 'beely_societe', "{$clef}_id", 'sanitize_text_field' );

			add_settings_field(
				"use_{$clef}",
				$traceur['label'],
				static function () use ( $clef, $traceur ): void {
					printf(
						'<label><input type="checkbox" name="use_%1$s" value="1" %2$s> %3$s</label>
						 <input type="text" name="%1$s_id" value="%4$s" placeholder="%5$s" class="regular-text" style="margin-left:16px;max-width:220px">',
						esc_attr( $clef ),
						checked( 1, (int) get_option( "use_{$clef}" ), false ),
						esc_html__( 'Activer', 'beely' ),
						esc_attr( (string) get_option( "{$clef}_id", '' ) ),
						esc_attr( $traceur['exemple'] )
					);
				},
				'beely-societe',
				'beely_traceurs'
			);
		}

		register_setting( 'beely_societe', 'use_utm_saver', 'absint' );

		add_settings_field(
			'use_utm_saver',
			__( 'Mémorisation des sources', 'beely' ),
			static function (): void {
				printf(
					'<label><input type="checkbox" name="use_utm_saver" value="1" %s> %s</label><p class="description">%s</p>',
					checked( 1, (int) get_option( 'use_utm_saver' ), false ),
					esc_html__( 'Activer', 'beely' ),
					esc_html__( 'Retient d’où vient un visiteur (campagne, publicité) pour attribuer les demandes de contact.', 'beely' )
				);
			},
			'beely-societe',
			'beely_traceurs'
		);
	}
);

/** Affiche l'écran de réglages. */
function ecran(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'beely' ) );
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Informations société', 'beely' ); ?></h1>

		<p>
			<?php esc_html_e( 'Ces informations alimentent le pied de page, les pages légales, la page de contact et les données structurées. Saisies ici, elles n’ont à être modifiées nulle part ailleurs.', 'beely' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'beely_societe' );
			do_settings_sections( 'beely-societe' );
			submit_button( __( 'Enregistrer', 'beely' ) );
			?>
		</form>

		<h2><?php esc_html_e( 'Comment les afficher', 'beely' ); ?></h2>

		<p>
			<?php esc_html_e( 'Posez une classe sur n’importe quel élément dans Bricks : son contenu est rempli au moment où la page est servie.', 'beely' ); ?>
		</p>

		<table class="widefat striped" style="max-width:760px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Classe', 'beely' ); ?></th>
					<th><?php esc_html_e( 'Contenu affiché', 'beely' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( correspondances() as $classe => $entree ) : ?>
					<tr>
						<td><code><?php echo esc_html( $classe ); ?></code></td>
						<td>
							<?php
							echo '' === $entree['valeur']
								? '<em>' . esc_html__( 'non renseigné', 'beely' ) . '</em>'
								: esc_html( $entree['valeur'] );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'Sur un lien, l’adresse e-mail et le téléphone renseignent aussi l’attribut href.', 'beely' ); ?>
		</p>
	</div>
	<?php
}
