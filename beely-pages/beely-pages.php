<?php
/**
 * Plugin Name: Beely — panneau des pages
 * Description: Arborescence des pages dans le builder Bricks : créer, renommer, déplacer, dupliquer, définir la page d'accueil, et renseigner le SEO — sans quitter la page qu'on regarde.
 * Version:     2.0.0
 * Author:      Beely
 *
 * Le panneau natif de Bricks liste les pages à plat, sans hiérarchie, sans
 * réordonnancement et sans champ SEO : le moindre changement d'arborescence
 * oblige à ressortir du builder. Ce panneau met les deux au même endroit.
 *
 * Il n'invente aucun champ SEO : il écrit dans ceux de `beely-seo`, qui reste
 * la seule source. Le panneau avait ses propres métas — `meta_title`,
 * `meta_desc` — et deux modules émettaient alors la même balise, avec deux
 * valeurs différentes selon l'ordre d'exécution.
 *
 * @package Beely\Pages
 */

declare( strict_types = 1 );

namespace Beely\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Préfixe des métas SEO — celui de `beely-seo`, jamais un autre. */
const PREFIXE_SEO = '_beely_seo_';

/** Action de sécurité partagée par les points d'entrée du panneau. */
const NONCE = 'beely_pages';

/** Nombre maximal de lignes acceptées dans un réordonnancement. */
const MAX_LIGNES_ORDRE = 500;

/* ------------------------------------------------------------------ */
/* Garde commune                                                       */
/* ------------------------------------------------------------------ */

/**
 * Vérifie droit et jeton, ou coupe court.
 *
 * Chaque point d'entrée l'appelle en première ligne. Les regrouper évite le
 * défaut classique : une action ajoutée plus tard à laquelle on oublie l'une
 * des deux vérifications.
 *
 * @param string $capacite Droit exigé.
 */
function exiger( string $capacite = 'edit_pages' ): void {
	if ( ! current_user_can( $capacite ) ) {
		wp_send_json_error( [ 'message' => __( 'Droits insuffisants.', 'beely' ) ], 403 );
	}

	check_ajax_referer( NONCE, 'nonce' );
}

/**
 * Identifiant transmis par le navigateur.
 *
 * @param string $champ Nom du champ dans la requête.
 */
function id_demande( string $champ = 'page_id' ): int {
	return (int) ( $_POST[ $champ ] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/** Identifiant de la page d'accueil, ou 0. */
function id_accueil(): int {
	return (int) get_option( 'page_on_front' );
}

/* ------------------------------------------------------------------ */
/* Lecture de l'arborescence                                           */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_lire',
	static function (): void {
		exiger();

		$pages = get_pages(
			[
				'sort_column'  => 'menu_order',
				'sort_order'   => 'asc',
				'post_type'    => 'page',
				'hierarchical' => true,
				'post_status'  => [ 'publish', 'draft', 'pending', 'private' ],
			]
		) ?: [];

		$sortie = [];

		foreach ( $pages as $page ) {
			$id       = (int) $page->ID;
			$vignette = (int) get_post_thumbnail_id( $id );

			$sortie[] = [
				'id'            => $id,
				'title'         => html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ),
				'parent'        => (int) $page->post_parent,
				'slug'          => (string) get_post_field( 'post_name', $id ),
				'status'        => (string) get_post_status( $id ),
				'permalink'     => (string) get_permalink( $id ),
				'seo_title'     => (string) get_post_meta( $id, PREFIXE_SEO . 'title', true ),
				'seo_desc'      => (string) get_post_meta( $id, PREFIXE_SEO . 'description', true ),
				'seo_canonical' => (string) get_post_meta( $id, PREFIXE_SEO . 'canonical', true ),
				'noindex'       => (bool) get_post_meta( $id, PREFIXE_SEO . 'noindex', true ),
				'thumbnail_id'  => $vignette,
				'thumbnail_url' => $vignette ? (string) wp_get_attachment_image_url( $vignette, 'medium' ) : '',
			];
		}

		wp_send_json_success(
			[
				'pages'         => $sortie,
				'front_page_id' => id_accueil(),
			]
		);
	}
);

/* ------------------------------------------------------------------ */
/* Slug                                                                */
/* ------------------------------------------------------------------ */

/**
 * Un slug est-il déjà pris au même niveau de l'arborescence ?
 *
 * La comparaison porte sur `post_parent` : deux pages peuvent porter le même
 * slug si elles n'ont pas le même parent, WordPress les distinguant par leur
 * chemin complet.
 */
function slug_pris( string $slug, int $parent = 0, int $sauf = 0 ): bool {
	global $wpdb;

	$existant = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'page' AND post_status <> 'trash'
			   AND post_name = %s AND post_parent = %d AND ID <> %d
			 LIMIT 1",
			$slug,
			$parent,
			$sauf
		)
	);

	return null !== $existant;
}

add_action(
	'wp_ajax_beely_pages_verifier_slug',
	static function (): void {
		exiger();

		$slug = sanitize_title( wp_unslash( (string) ( $_POST['slug'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $slug ) {
			wp_send_json_error( [ 'message' => __( 'Slug vide.', 'beely' ) ], 400 );
		}

		wp_send_json_success(
			[
				'available' => ! slug_pris( $slug, id_demande( 'parent_id' ), id_demande( 'exclude_id' ) ),
			]
		);
	}
);

/* ------------------------------------------------------------------ */
/* Écriture des champs SEO                                             */
/* ------------------------------------------------------------------ */

/**
 * Reporte les champs SEO du formulaire sur la page.
 *
 * Un champ vidé est **supprimé**, pas enregistré vide : `beely-seo` distingue
 * « pas de valeur propre à cette page, prends le modèle du site » de « valeur
 * volontairement vide ». Une chaîne vide stockée produisait une balise
 * `<meta name="description" content="">`, ce qui est pire que pas de balise.
 */
function ecrire_seo( int $id ): void {
	$champs = [
		'title'       => sanitize_text_field( wp_unslash( (string) ( $_POST['seo_title'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		'description' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['seo_desc'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		'canonical'   => esc_url_raw( wp_unslash( (string) ( $_POST['seo_canonical'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
	];

	foreach ( $champs as $nom => $valeur ) {
		if ( '' === $valeur ) {
			delete_post_meta( $id, PREFIXE_SEO . $nom );
			continue;
		}

		update_post_meta( $id, PREFIXE_SEO . $nom, $valeur );
	}

	if ( empty( $_POST['noindex'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		delete_post_meta( $id, PREFIXE_SEO . 'noindex' );
	} else {
		update_post_meta( $id, PREFIXE_SEO . 'noindex', 1 );
	}

	// Image à la une : absente de la requête = on n'y touche pas.
	if ( ! isset( $_POST['_thumbnail_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$vignette = id_demande( '_thumbnail_id' );

	if ( $vignette > 0 ) {
		set_post_thumbnail( $id, $vignette );
	} else {
		delete_post_thumbnail( $id );
	}
}

/* ------------------------------------------------------------------ */
/* Création                                                            */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_creer',
	static function (): void {
		exiger();

		$titre  = sanitize_text_field( wp_unslash( (string) ( $_POST['post_title'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slug   = sanitize_title( wp_unslash( (string) ( $_POST['post_name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parent = id_demande( 'post_parent' );
		$statut = sanitize_key( wp_unslash( (string) ( $_POST['post_status'] ?? 'publish' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $titre || '' === $slug ) {
			wp_send_json_error( [ 'message' => __( 'Le nom et le slug sont obligatoires.', 'beely' ) ], 400 );
		}

		if ( ! in_array( $statut, [ 'publish', 'draft' ], true ) ) {
			$statut = 'draft';
		}

		if ( 0 !== $parent && $parent === id_accueil() ) {
			wp_send_json_error( [ 'message' => __( 'La page d’accueil ne peut pas avoir d’enfants.', 'beely' ) ], 400 );
		}

		if ( slug_pris( $slug, $parent ) ) {
			wp_send_json_error( [ 'message' => __( 'Ce slug est déjà utilisé à ce niveau.', 'beely' ) ], 400 );
		}

		$id = wp_insert_post(
			[
				'post_type'   => 'page',
				'post_status' => $statut,
				'post_parent' => $parent,
				'post_title'  => $titre,
				'post_name'   => $slug,
				'menu_order'  => 0,
			],
			true
		);

		if ( is_wp_error( $id ) ) {
			wp_send_json_error( [ 'message' => $id->get_error_message() ], 500 );
		}

		ecrire_seo( (int) $id );

		wp_send_json_success( [ 'new_id' => (int) $id ] );
	}
);

/* ------------------------------------------------------------------ */
/* Mise à jour                                                         */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_modifier',
	static function (): void {
		exiger();

		$id = id_demande();

		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Page introuvable ou droits insuffisants.', 'beely' ) ], 403 );
		}

		$titre   = sanitize_text_field( wp_unslash( (string) ( $_POST['post_title'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slug    = sanitize_title( wp_unslash( (string) ( $_POST['post_name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parent  = id_demande( 'post_parent' );
		$statut  = sanitize_key( wp_unslash( (string) ( $_POST['post_status'] ?? 'publish' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$accueil = ( $id === id_accueil() );

		if ( '' === $titre ) {
			wp_send_json_error( [ 'message' => __( 'Le nom est obligatoire.', 'beely' ) ], 400 );
		}

		if ( ! in_array( $statut, [ 'publish', 'draft' ], true ) ) {
			$statut = 'draft';
		}

		/*
		 * La page d'accueil ne se déplace pas et ne se dépublie pas.
		 *
		 * Son slug non plus : WordPress ne le sert pas — elle répond sur `/` —
		 * mais le modifier casserait les redirections de ses anciennes URL. Le
		 * champ est désactivé côté navigateur ; cette garde vaut pour qui
		 * contournerait l'interface.
		 */
		if ( $accueil ) {
			$parent = 0;
			$statut = 'publish';
		} elseif ( '' === $slug ) {
			wp_send_json_error( [ 'message' => __( 'Le slug est obligatoire.', 'beely' ) ], 400 );
		}

		if ( 0 !== $parent && $parent === id_accueil() ) {
			wp_send_json_error( [ 'message' => __( 'La page d’accueil ne peut pas avoir d’enfants.', 'beely' ) ], 400 );
		}

		/*
		 * Une page ne peut pas devenir son propre descendant.
		 *
		 * Sans ce contrôle, choisir un de ses enfants comme parent détache la
		 * branche entière : les pages existent toujours, plus aucune requête ne
		 * les remonte, et le panneau ne les affiche plus. Rien ne le signale.
		 */
		if ( 0 !== $parent && in_array( $parent, descendants( $id ), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Une page ne peut pas être placée sous l’une de ses propres sous-pages.', 'beely' ) ], 400 );
		}

		if ( ! $accueil && slug_pris( $slug, $parent, $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Ce slug est déjà utilisé à ce niveau.', 'beely' ) ], 400 );
		}

		$args = [
			'ID'          => $id,
			'post_title'  => $titre,
			'post_parent' => $parent,
			'post_status' => $statut,
		];

		if ( ! $accueil ) {
			$args['post_name'] = $slug;
		}

		$resultat = wp_update_post( $args, true );

		if ( is_wp_error( $resultat ) ) {
			wp_send_json_error( [ 'message' => $resultat->get_error_message() ], 500 );
		}

		ecrire_seo( $id );

		wp_send_json_success( [ 'updated' => $id ] );
	}
);

/**
 * Identifiants de toutes les pages situées sous celle-ci.
 *
 * @param int $id         Page de départ.
 * @param int $profondeur Garde-fou : une arborescence déjà circulaire — cas
 *                        qu'une base restaurée à la main produit — ferait
 *                        sinon tourner la récursion jusqu'à épuisement.
 * @return list<int>
 */
function descendants( int $id, int $profondeur = 0 ): array {
	if ( $profondeur > 20 ) {
		return [];
	}

	$sortie = [];

	$enfants = get_children(
		[
			'post_parent' => $id,
			'post_type'   => 'page',
			'numberposts' => -1,
			'post_status' => 'any',
		]
	);

	foreach ( $enfants as $enfant ) {
		$sortie[] = (int) $enfant->ID;
		$sortie   = array_merge( $sortie, descendants( (int) $enfant->ID, $profondeur + 1 ) );
	}

	return $sortie;
}

/* ------------------------------------------------------------------ */
/* Réordonnancement                                                    */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_ordonner',
	static function (): void {
		exiger();

		$brut = json_decode( wp_unslash( (string) ( $_POST['order'] ?? '[]' ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! is_array( $brut ) ) {
			wp_send_json_error( [ 'message' => __( 'Format inattendu.', 'beely' ) ], 400 );
		}

		/*
		 * Le nombre de lignes est borné.
		 *
		 * Chaque ligne déclenche un `wp_update_post`, qui purge des caches et
		 * lance les accroches d'enregistrement. Sans borne, une requête forgée à
		 * cent mille lignes occupe le serveur aussi longtemps qu'elle veut, avec
		 * un simple compte éditeur.
		 */
		if ( count( $brut ) > MAX_LIGNES_ORDRE ) {
			wp_send_json_error( [ 'message' => __( 'Trop de pages dans une seule opération.', 'beely' ) ], 400 );
		}

		$accueil  = id_accueil();
		$rangs    = [];
		$modifies = 0;

		foreach ( $brut as $ligne ) {
			$id = (int) ( $ligne['id'] ?? 0 );

			if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			$parent = (int) ( $ligne['parent'] ?? 0 );

			// La page d'accueil reste à la racine, en tête.
			if ( $id === $accueil ) {
				$parent   = 0;
				$rang     = 0;
				$rangs[0] = max( 1, $rangs[0] ?? 0 );
			} else {
				if ( $parent === $accueil ) {
					$parent = 0;
				}

				$rangs[ $parent ] = $rangs[ $parent ] ?? 0;
				$rang             = $rangs[ $parent ]++;
			}

			$actuel = get_post( $id );

			// N'écrire que ce qui change : chaque écriture coûte une purge de cache.
			if ( $actuel instanceof \WP_Post
				&& (int) $actuel->post_parent === $parent
				&& (int) $actuel->menu_order === $rang ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $id,
					'post_parent' => $parent,
					'menu_order'  => $rang,
				]
			);

			$modifies++;
		}

		wp_send_json_success( [ 'updated' => $modifies ] );
	}
);

/* ------------------------------------------------------------------ */
/* Page d'accueil                                                      */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_definir_accueil',
	static function (): void {
		exiger( 'manage_options' );

		$id = id_demande();

		if ( $id <= 0 || 'page' !== get_post_type( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Page introuvable.', 'beely' ) ], 404 );
		}

		$enfants = get_children(
			[
				'post_parent' => $id,
				'post_type'   => 'page',
				'numberposts' => 1,
				'post_status' => 'any',
			]
		);

		if ( $enfants ) {
			wp_send_json_error( [ 'message' => __( 'Cette page a des sous-pages : elle ne peut pas devenir la page d’accueil.', 'beely' ) ], 400 );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		wp_update_post(
			[
				'ID'          => $id,
				'post_parent' => 0,
				'menu_order'  => 0,
				'post_status' => 'publish',
			]
		);

		wp_send_json_success( [ 'homepage_id' => $id ] );
	}
);

/* ------------------------------------------------------------------ */
/* Duplication                                                         */
/* ------------------------------------------------------------------ */

/**
 * Duplique une page et toute sa descendance.
 *
 * @param int    $source     Page à copier.
 * @param int    $parent     Parent de la copie.
 * @param bool   $racine     Vrai pour la page de tête de la copie.
 * @param string $statut     Statut voulu pour la page de tête.
 * @param int    $profondeur Garde-fou de récursion.
 * @return int Identifiant de la copie, ou 0.
 */
function dupliquer( int $source, int $parent = 0, bool $racine = false, string $statut = 'draft', int $profondeur = 0 ): int {
	if ( $profondeur > 20 ) {
		return 0;
	}

	$page = get_post( $source );

	if ( ! $page instanceof \WP_Post ) {
		return 0;
	}

	$statut_copie = $racine ? $statut : $page->post_status;

	$copie = wp_insert_post(
		[
			'post_type'     => $page->post_type,
			'post_parent'   => $parent,
			'post_content'  => $page->post_content,
			'post_excerpt'  => $page->post_excerpt,
			'menu_order'    => $page->menu_order,
			'ping_status'   => $page->ping_status,
			'post_password' => $page->post_password,
			'post_title'    => $racine ? $page->post_title . ' (copie)' : $page->post_title,
			'post_status'   => $statut_copie,
			'post_name'     => wp_unique_post_slug(
				$racine ? $page->post_name . '-copie' : $page->post_name,
				0,
				$statut_copie,
				'page',
				$parent
			),
		],
		true
	);

	if ( is_wp_error( $copie ) || ! $copie ) {
		return 0;
	}

	$copie = (int) $copie;

	/*
	 * Les métas sont recopiées, sauf celles qui décrivent l'original.
	 *
	 * `_edit_lock` et `_edit_last` disent qui édite quoi et depuis quand :
	 * recopiées, elles font croire que la copie est verrouillée par quelqu'un.
	 * `_wp_old_slug` porte les anciennes URL de l'original — recopiée, elle
	 * crée deux pages qui revendiquent la même redirection.
	 */
	foreach ( get_post_meta( $source ) as $clef => $valeurs ) {
		if ( in_array( $clef, [ '_edit_lock', '_edit_last', '_wp_old_slug' ], true ) ) {
			continue;
		}

		foreach ( (array) $valeurs as $valeur ) {
			add_post_meta( $copie, $clef, maybe_unserialize( $valeur ) );
		}
	}

	/*
	 * L'URL canonique de l'original n'est pas celle de la copie.
	 *
	 * Recopiée telle quelle, elle demande aux moteurs d'ignorer la copie au
	 * profit de l'original — sur une page qu'on vient justement de créer pour
	 * être différente. Le champ repart vide.
	 */
	delete_post_meta( $copie, PREFIXE_SEO . 'canonical' );

	$enfants = get_children(
		[
			'post_parent' => $source,
			'post_type'   => 'page',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		]
	);

	foreach ( $enfants as $enfant ) {
		dupliquer( (int) $enfant->ID, $copie, false, $statut, $profondeur + 1 );
	}

	return $copie;
}

add_action(
	'wp_ajax_beely_pages_dupliquer',
	static function (): void {
		exiger();

		$id = id_demande();

		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Page introuvable ou droits insuffisants.', 'beely' ) ], 403 );
		}

		$source = get_post( $id );

		if ( ! $source instanceof \WP_Post ) {
			wp_send_json_error( [ 'message' => __( 'Page introuvable.', 'beely' ) ], 404 );
		}

		$copie = dupliquer( $id, (int) $source->post_parent, true, 'draft' );

		if ( ! $copie ) {
			wp_send_json_error( [ 'message' => __( 'La duplication a échoué.', 'beely' ) ], 500 );
		}

		wp_send_json_success( [ 'new_id' => $copie ] );
	}
);

/* ------------------------------------------------------------------ */
/* Suppression                                                         */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_beely_pages_supprimer',
	static function (): void {
		exiger( 'delete_pages' );

		$id = id_demande();

		if ( $id <= 0 || ! current_user_can( 'delete_post', $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Page introuvable ou droits insuffisants.', 'beely' ) ], 403 );
		}

		if ( id_accueil() === $id ) {
			wp_send_json_error( [ 'message' => __( 'La page d’accueil ne peut pas être supprimée.', 'beely' ) ], 400 );
		}

		$enfants = get_children(
			[
				'post_parent' => $id,
				'post_type'   => 'page',
				'numberposts' => 1,
				'post_status' => 'any',
			]
		);

		if ( $enfants ) {
			wp_send_json_error( [ 'message' => __( 'Cette page contient des sous-pages.', 'beely' ) ], 400 );
		}

		if ( ! wp_trash_post( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'La mise à la corbeille a échoué.', 'beely' ) ], 500 );
		}

		wp_send_json_success( [ 'deleted' => $id ] );
	}
);

/* ------------------------------------------------------------------ */
/* Chargement dans le builder                                          */
/* ------------------------------------------------------------------ */

/**
 * URL du dossier d'assets de ce mu-plugin.
 *
 * `plugins_url()` ne convient pas : elle vise `wp-content/plugins`, alors que
 * le fichier vit dans `mu-plugins`. Le chemin est donc construit depuis
 * `content_url()`, en reprenant le nom réel du dossier — pas une constante,
 * pour que renommer le dossier ne casse rien en silence.
 */
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
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		wp_enqueue_style(
			'beely-pages',
			url_assets() . '/panneau.css',
			[ 'bricks-frontend' ],
			version_asset( 'panneau.css' )
		);

		wp_enqueue_script(
			'beely-pages',
			url_assets() . '/panneau.js',
			[],
			version_asset( 'panneau.js' ),
			true
		);

		// Le sélecteur de média sert à choisir l'image de partage.
		wp_enqueue_media();

		wp_localize_script(
			'beely-pages',
			'BeelyPages',
			[
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( NONCE ),
				'peutSupprimer'      => current_user_can( 'delete_pages' ),
				'peutDefinirAccueil' => current_user_can( 'manage_options' ),
			]
		);
	}
);
