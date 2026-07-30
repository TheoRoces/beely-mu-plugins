<?php
/**
 * Plugin Name: Beely — cookies
 * Description: Bandeau de consentement et chargement conditionnel des traceurs. Consent Mode v2, granularité statistiques / marketing, preuve de consentement horodatée.
 * Version:     2.0.0
 * Author:      Beely
 *
 * Le principe est simple et il tient en une phrase : **aucun traceur ne part
 * avant le consentement**. Les scripts sont écrits dans la page avec un type
 * MIME que le navigateur n'exécute pas, et ne deviennent des scripts qu'au
 * moment où la personne les accepte.
 *
 * C'est le seul montage qui tienne : injecter le script après coup en
 * JavaScript marche aussi, mais laisse une fenêtre où le traceur est déjà
 * chargé si le script du bandeau échoue.
 *
 * Le bandeau ne s'affiche pas s'il n'y a rien à consentir — un site sans
 * traceur n'a aucune raison de demander la permission de ne rien faire.
 *
 * @package Beely\Cookies
 */

declare( strict_types = 1 );

namespace Beely\Cookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Traceurs pris en charge, avec leur catégorie de consentement.
 *
 * `statistiques` mesure l'audience, `marketing` sert la publicité. La
 * distinction n'est pas cosmétique : la CNIL exige que l'on puisse accepter
 * l'une sans l'autre.
 *
 * @var array<string, array{option:string, id:string, categorie:string, nom:string}>
 */
const TRACEURS = [
	'ga4'        => [ 'option' => 'use_ga4', 'id' => 'ga4_id', 'categorie' => 'statistiques', 'nom' => 'Google Analytics 4' ],
	'google_ads' => [ 'option' => 'use_google_ads', 'id' => 'google_ads_id', 'categorie' => 'marketing', 'nom' => 'Google Ads' ],
	'meta_pixel' => [ 'option' => 'use_meta_pixel', 'id' => 'meta_pixel_id', 'categorie' => 'marketing', 'nom' => 'Pixel Meta' ],
	'clarity'    => [ 'option' => 'use_clarity', 'id' => 'clarity_id', 'categorie' => 'statistiques', 'nom' => 'Microsoft Clarity' ],
	'utm_saver'  => [ 'option' => 'use_utm_saver', 'id' => '', 'categorie' => 'marketing', 'nom' => 'Mémorisation des sources' ],
];

/**
 * Durée de conservation d'une preuve de consentement.
 *
 * Trente-six mois : la durée que la CNIL retient pour la preuve elle-même. Au
 * delà, la preuve ne prouve plus rien d'utile et devient une donnée conservée
 * sans motif.
 */
const CONSERVATION = 36 * MONTH_IN_SECONDS;

/** Nombre maximal de preuves enregistrées par heure et par site. */
const MAX_PREUVES_HEURE = 300;

/* ------------------------------------------------------------------ */
/* Traceurs déclarés                                                   */
/* ------------------------------------------------------------------ */

/**
 * Traceurs effectivement actifs sur ce site.
 *
 * Un traceur coché mais sans identifiant est ignoré : il produirait un script
 * qui ne mesure rien, et surtout une ligne dans la politique de
 * confidentialité qui annonce une collecte qui n'a pas lieu.
 *
 * @return array<string, array{option:string, id:string, categorie:string, nom:string}>
 */
function traceurs_actifs(): array {
	$actifs = [];

	foreach ( TRACEURS as $clef => $traceur ) {
		if ( ! get_option( $traceur['option'] ) ) {
			continue;
		}

		if ( '' !== $traceur['id'] && '' === trim( (string) get_option( $traceur['id'], '' ) ) ) {
			continue;
		}

		$actifs[ $clef ] = $traceur;
	}

	return $actifs;
}

/** Catégories pour lesquelles un consentement est réellement demandé. */
function categories_actives(): array {
	$categories = [];

	foreach ( traceurs_actifs() as $traceur ) {
		$categories[ $traceur['categorie'] ] = true;
	}

	return array_keys( $categories );
}

/** Sommes-nous dans le builder Bricks ? */
function dans_le_builder(): bool {
	return function_exists( 'bricks_is_builder' ) && bricks_is_builder();
}

/* ------------------------------------------------------------------ */
/* Chargement des ressources                                           */
/* ------------------------------------------------------------------ */

/** URL du dossier d'assets de ce mu-plugin. */
function url_assets(): string {
	return content_url( 'mu-plugins/' . basename( __DIR__ ) . '/assets' );
}

/** Version d'un fichier statique, basée sur sa date de modification. */
function version_asset( string $fichier ): string {
	$chemin = __DIR__ . '/assets/' . $fichier;
	$mtime  = is_readable( $chemin ) ? filemtime( $chemin ) : false;

	return false === $mtime ? '2.0.0' : (string) $mtime;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( dans_le_builder() || ! traceurs_actifs() ) {
			return;
		}

		wp_enqueue_style( 'beely-cookies', url_assets() . '/banniere.css', [], version_asset( 'banniere.css' ) );
		wp_enqueue_script( 'beely-cookies', url_assets() . '/banniere.js', [], version_asset( 'banniere.js' ), true );

		wp_localize_script(
			'beely-cookies',
			'beelyConfig',
			[
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'beely_consentement' ),
				'categories' => categories_actives(),
			]
		);
	}
);

/* ------------------------------------------------------------------ */
/* Preuve de consentement                                              */
/* ------------------------------------------------------------------ */

/**
 * Enregistre la preuve d'un choix.
 *
 * Trois choses ont changé par rapport à un journal en fichier CSV, et chacune
 * réparait un vrai problème :
 *
 * 1. **Un jeton est exigé.** Le point d'entrée était ouvert à tous et écrivait
 *    une ligne par appel : n'importe qui pouvait remplir le disque depuis une
 *    boucle, et le fichier de journal n'a pas de taille maximale.
 * 2. **Un rythme maximal.** Le jeton écarte les robots, pas une personne qui
 *    clique cent fois. La borne horaire, si.
 * 3. **L'adresse IP est anonymisée pour de bon.** L'ancienne troncature ne
 *    reconnaissait que l'IPv4 : une adresse IPv6 — la majorité du trafic mobile
 *    en France — était journalisée entière, ce qui en fait une donnée
 *    personnelle conservée sans base légale.
 */
add_action( 'wp_ajax_beely_consentement', __NAMESPACE__ . '\\enregistrer_preuve' );
add_action( 'wp_ajax_nopriv_beely_consentement', __NAMESPACE__ . '\\enregistrer_preuve' );

/** Traite l'enregistrement d'une preuve de consentement. */
function enregistrer_preuve(): void {
	check_ajax_referer( 'beely_consentement', 'nonce' );

	$choix = json_decode( wp_unslash( (string) ( $_POST['consent'] ?? '' ) ), true );

	if ( ! is_array( $choix ) ) {
		wp_send_json_error( [ 'message' => 'format' ], 400 );
	}

	// Rythme : au-delà de la borne, on répond « pris en compte » sans écrire.
	$compteur = (int) get_transient( 'beely_preuves_heure' );

	if ( $compteur >= MAX_PREUVES_HEURE ) {
		wp_send_json_success( [ 'stored' => false ] );
	}

	set_transient( 'beely_preuves_heure', $compteur + 1, HOUR_IN_SECONDS );

	$preuves = get_option( 'beely_preuves_consentement', [] );

	if ( ! is_array( $preuves ) ) {
		$preuves = [];
	}

	$preuves[] = [
		'date'        => gmdate( 'c' ),
		'ip'          => anonymiser_ip( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
		'statistiques' => ! empty( $choix['analytics'] ),
		'marketing'   => ! empty( $choix['marketing'] ),
		'agent'       => substr( sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ), 0, 200 ),
	];

	// Les preuves périmées sortent au fil de l'eau, sans tâche planifiée.
	$limite  = time() - CONSERVATION;
	$preuves = array_values(
		array_filter(
			$preuves,
			static fn ( array $preuve ): bool => strtotime( $preuve['date'] ?? '' ) > $limite
		)
	);

	/*
	 * `autoload = false` : cette option peut peser plusieurs centaines de
	 * kilo-octets, et elle n'est lue que depuis l'écran d'export. Chargée à
	 * chaque requête, elle coûterait ce poids sur chaque page du site.
	 */
	update_option( 'beely_preuves_consentement', $preuves, false );

	wp_send_json_success( [ 'stored' => true ] );
}

/**
 * Anonymise une adresse IP, v4 comme v6.
 *
 * IPv4 : le dernier octet tombe. IPv6 : on ne garde que le préfixe de 48 bits,
 * ce qui désigne un opérateur et une région, plus un abonné.
 */
function anonymiser_ip( string $ip ): string {
	if ( '' === $ip ) {
		return '';
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$morceaux = explode( '.', $ip );
		$morceaux[3] = '0';

		return implode( '.', $morceaux );
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		/*
		 * L'adresse est développée en 128 bits avant d'être tronquée.
		 *
		 * Un découpage sur « : » ne sait pas lire la compression : `2001::1234`
		 * s'y lit en trois morceaux, et rendait `2001::1234::` — une adresse
		 * qui n'existe pas, et qui ne désigne pas le préfixe voulu. `::1`
		 * devenait `::1::`. Vérifié : trois cas sur cinq produisaient une IPv6
		 * invalide, et la compression est la forme normale d'écriture.
		 *
		 * On garde les 48 premiers bits — opérateur et région, pas l'abonné.
		 */
		$binaire = inet_pton( $ip );

		if ( false === $binaire ) {
			return '';
		}

		return (string) inet_ntop( substr( $binaire, 0, 6 ) . str_repeat( "\0", 10 ) );
	}

	// Ni l'un ni l'autre : on ne conserve rien plutôt que de conserver au hasard.
	return '';
}

/* ------------------------------------------------------------------ */
/* Injection des traceurs                                              */
/* ------------------------------------------------------------------ */

/*
 * Consent Mode v2, posé avant tout le reste.
 *
 * Google demande que l'état par défaut soit déclaré **avant** le chargement de
 * ses balises. Sans cela, les premières mesures partent avec le consentement
 * implicite, et la mise à jour arrive trop tard.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( dans_le_builder() ) {
			return;
		}

		$actifs = traceurs_actifs();

		if ( ! $actifs ) {
			return;
		}

		if ( isset( $actifs['ga4'] ) || isset( $actifs['google_ads'] ) ) {
			?>
			<script id="beely-consent-mode">
				window.dataLayer = window.dataLayer || [];
				function gtag(){ dataLayer.push(arguments); }
				gtag('consent', 'default', {
					ad_storage: 'denied',
					ad_user_data: 'denied',
					ad_personalization: 'denied',
					analytics_storage: 'denied',
					wait_for_update: 500
				});
			</script>
			<?php
		}

		if ( isset( $actifs['ga4'] ) ) {
			$identifiant = (string) get_option( 'ga4_id' );
			?>
			<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $identifiant ); ?>" type="text/beely-statistiques"></script>
			<script type="text/beely-statistiques">
				gtag('js', new Date());
				gtag('config', '<?php echo esc_js( $identifiant ); ?>');
			</script>
			<?php
		}

		if ( isset( $actifs['google_ads'] ) ) {
			?>
			<script type="text/beely-marketing">
				gtag('config', '<?php echo esc_js( (string) get_option( 'google_ads_id' ) ); ?>');
			</script>
			<?php
		}

		if ( isset( $actifs['meta_pixel'] ) ) {
			?>
			<script type="text/beely-marketing">
				!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
				fbq('init', '<?php echo esc_js( (string) get_option( 'meta_pixel_id' ) ); ?>');
				fbq('track', 'PageView');
			</script>
			<?php
		}

		if ( isset( $actifs['clarity'] ) ) {
			?>
			<script type="text/beely-statistiques">
				(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window,document,'clarity','script','<?php echo esc_js( (string) get_option( 'clarity_id' ) ); ?>');
			</script>
			<?php
		}

		if ( isset( $actifs['utm_saver'] ) ) {
			/*
			 * La propagation des paramètres d'URL n'écrit rien et ne dépose rien :
			 * elle se contente de recopier `?utm_source=…` sur les liens internes
			 * de la page. Elle ne relève donc d'aucun consentement.
			 *
			 * La **mémorisation** dans le stockage local, elle, en relève : elle
			 * écrit sur le terminal. Elle porte le type `beely-marketing`.
			 */
			?>
			<script id="beely-propager-utm">
				(function () {
					function propager() {
						var params = new URLSearchParams(window.location.search);
						if (!Array.from(params).length) return;

						document.querySelectorAll('a[href]').forEach(function (lien) {
							try {
								var href = lien.getAttribute('href');
								if (!href || /^(#|mailto:|tel:|javascript:)/i.test(href)) return;

								var cible = new URL(lien.href, window.location.origin);
								if (cible.origin !== window.location.origin) return;

								params.forEach(function (v, k) { cible.searchParams.set(k, v); });
								lien.href = cible.toString();
							} catch (e) { /* lien non analysable : on le laisse tel quel */ }
						});
					}

					function observer() {
						if (!document.body) return;
						// Le rappel est différé : sans cela, chaque lien réécrit
						// déclenche l'observateur, qui réécrit, en boucle.
						var enAttente = false;
						new MutationObserver(function () {
							if (enAttente) return;
							enAttente = true;
							requestAnimationFrame(function () { enAttente = false; propager(); });
						}).observe(document.body, { childList: true, subtree: true });
					}

					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', function () { propager(); observer(); });
					} else {
						propager();
						observer();
					}
				})();
			</script>
			<script type="text/beely-marketing">
				(function () {
					try {
						var garder = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','fbclid'];
						var params = new URLSearchParams(window.location.search);
						var vu = false;

						params.forEach(function (v, k) {
							if (garder.indexOf(k) !== -1 && v) { localStorage.setItem('beely_' + k, v); vu = true; }
						});

						if (vu) localStorage.setItem('beely_source_date', new Date().toISOString());
					} catch (e) { /* stockage indisponible : la source n'est pas mémorisée */ }
				})();
			</script>
			<?php
		}
	},
	1
);

/* ------------------------------------------------------------------ */
/* Bandeau                                                             */
/* ------------------------------------------------------------------ */

/** URL de la politique de confidentialité, telle que le site la déclare. */
function url_confidentialite(): string {
	$page = (int) get_option( 'wp_page_for_privacy_policy' );

	if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
		return (string) get_permalink( $page );
	}

	$slug = trim( (string) get_option( 'company_privacy_slug', '' ), '/' );

	return home_url( '/' . ( '' !== $slug ? $slug : 'politique-de-confidentialite' ) );
}

add_action(
	'wp_footer',
	static function (): void {
		if ( dans_le_builder() ) {
			return;
		}

		$categories = categories_actives();

		if ( ! $categories ) {
			return;
		}

		$libelles = [
			'statistiques' => [
				__( 'Statistiques', 'beely' ),
				__( 'Mesure d’audience : combien de personnes visitent le site, et quelles pages elles consultent.', 'beely' ),
			],
			'marketing'    => [
				__( 'Marketing', 'beely' ),
				__( 'Mesure de l’efficacité de nos campagnes, et adaptation des publicités que vous voyez ailleurs.', 'beely' ),
			],
		];
		?>
		<div id="beely-cookie-overlay" class="beely-hidden" hidden></div>

		<div id="beely-cookie-banner" class="beely-hidden" role="dialog" aria-modal="false"
			aria-labelledby="beely-cookie-titre" aria-describedby="beely-cookie-texte" hidden>
			<div class="beely-cookie-content">
				<h2 id="beely-cookie-titre"><?php esc_html_e( 'Votre choix sur les cookies', 'beely' ); ?></h2>
				<p id="beely-cookie-texte">
					<?php esc_html_e( 'Nous utilisons des cookies pour mesurer l’audience du site. Rien n’est déposé sans votre accord.', 'beely' ); ?>
					<a href="<?php echo esc_url( url_confidentialite() ); ?>"><?php esc_html_e( 'En savoir plus', 'beely' ); ?></a>
				</p>
			</div>
			<div class="beely-cookie-actions">
				<button type="button" id="beely-accept-all" class="btn-beely-primary"><?php esc_html_e( 'Tout accepter', 'beely' ); ?></button>
				<button type="button" id="beely-reject-all" class="btn-beely-secondary"><?php esc_html_e( 'Tout refuser', 'beely' ); ?></button>
				<button type="button" id="beely-settings-btn" class="btn-beely-link"><?php esc_html_e( 'Personnaliser', 'beely' ); ?></button>
			</div>
		</div>

		<div id="beely-cookie-modal" class="beely-hidden" role="dialog" aria-modal="true"
			aria-labelledby="beely-modal-titre" hidden>
			<div class="beely-modal-header">
				<h2 id="beely-modal-titre"><?php esc_html_e( 'Préférences de confidentialité', 'beely' ); ?></h2>
				<button type="button" id="beely-close-modal" aria-label="<?php esc_attr_e( 'Fermer', 'beely' ); ?>">&#10005;</button>
			</div>

			<div class="beely-modal-body">
				<div class="beely-consent-item">
					<div class="beely-item-header">
						<span><?php esc_html_e( 'Nécessaires', 'beely' ); ?></span>
						<span class="beely-status-locked"><?php esc_html_e( 'Toujours actifs', 'beely' ); ?></span>
					</div>
					<p><?php esc_html_e( 'Indispensables au fonctionnement du site et à la mémorisation de vos choix.', 'beely' ); ?></p>
				</div>

				<?php foreach ( $categories as $categorie ) : ?>
					<?php [ $titre, $description ] = $libelles[ $categorie ]; ?>
					<div class="beely-consent-item">
						<div class="beely-item-header">
							<label for="beely-consent-<?php echo esc_attr( $categorie ); ?>"><?php echo esc_html( $titre ); ?></label>
							<label class="beely-switch">
								<input type="checkbox" id="beely-consent-<?php echo esc_attr( $categorie ); ?>"
									data-categorie="<?php echo esc_attr( $categorie ); ?>">
								<span class="beely-slider"></span>
							</label>
						</div>
						<p><?php echo esc_html( $description ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="beely-modal-footer">
				<button type="button" id="beely-save-preferences" class="btn-beely-primary"><?php esc_html_e( 'Enregistrer mes choix', 'beely' ); ?></button>
			</div>
		</div>
		<?php
	}
);
