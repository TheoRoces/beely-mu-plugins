/**
 * Bandeau de consentement.
 *
 * Les traceurs sont écrits dans la page avec un type MIME que le navigateur
 * n'exécute pas — `text/beely-statistiques`, `text/beely-marketing`. Ce script
 * les convertit en scripts réels au moment où la personne les accepte, et
 * jamais avant.
 */
(function () {
	'use strict';

	const CLEF = 'beely_consentement_v3';

	const cfg = window.beelyConfig || {};
	const categories = Array.isArray(cfg.categories) ? cfg.categories : [];

	const banniere = document.getElementById('beely-cookie-banner');
	const modale = document.getElementById('beely-cookie-modal');
	const voile = document.getElementById('beely-cookie-overlay');

	if (!banniere || !modale) return;

	/** Dernier élément focalisé avant l'ouverture de la modale. */
	let focusAvant = null;

	/* --------------------------------------------------------------- */
	/* Activation des scripts                                          */
	/* --------------------------------------------------------------- */

	/**
	 * Convertit en scripts exécutables ceux d'une catégorie.
	 *
	 * Le nœud est remplacé, pas modifié : changer le `type` d'un `<script>`
	 * déjà présent dans le DOM ne le fait pas s'exécuter — la spécification
	 * ne le prévoit qu'à l'insertion.
	 */
	function activer(categorie) {
		document.querySelectorAll(`script[type="text/beely-${categorie}"]`).forEach((ancien) => {
			const script = document.createElement('script');

			for (const attr of ancien.attributes) {
				if (attr.name !== 'type') script.setAttribute(attr.name, attr.value);
			}

			script.type = 'text/javascript';
			if (ancien.textContent) script.textContent = ancien.textContent;

			ancien.parentNode.insertBefore(script, ancien);
			ancien.remove();
		});
	}

	/* --------------------------------------------------------------- */
	/* Nettoyage                                                       */
	/* --------------------------------------------------------------- */

	/**
	 * Supprime un cookie sur tous les domaines et chemins plausibles.
	 *
	 * Un cookie posé sur `.exemple.fr` ne s'efface pas depuis `exemple.fr` :
	 * il faut cibler exactement le domaine qui l'a déposé. On balaie donc la
	 * chaîne complète des domaines parents, avec et sans `Secure`.
	 */
	function supprimerCookie(nom) {
		const morceaux = window.location.hostname.split('.');
		const domaines = [''];

		while (morceaux.length >= 2) {
			const d = morceaux.join('.');
			domaines.push(d, '.' + d);
			morceaux.shift();
		}

		const expire = 'expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

		domaines.forEach((d) => {
			const suffixe = d ? '; domain=' + d : '';
			document.cookie = `${nom}=; ${expire}${suffixe}`;
			document.cookie = `${nom}=; ${expire}${suffixe}; secure`;
		});
	}

	const PREFIXES_TRACEURS = ['_ga', '_gid', '_gat', '_fbp', '_fbc', '_gcl', '_cl', '_uet'];

	function nettoyer() {
		Object.keys(localStorage)
			.filter((clef) => clef.startsWith('beely_utm') || clef.startsWith('beely_source'))
			.forEach((clef) => localStorage.removeItem(clef));

		document.cookie.split(';').forEach((cookie) => {
			const nom = cookie.split('=')[0].trim();
			if (PREFIXES_TRACEURS.some((p) => nom.startsWith(p))) supprimerCookie(nom);
		});
	}

	/* --------------------------------------------------------------- */
	/* État du consentement                                            */
	/* --------------------------------------------------------------- */

	function lire() {
		try {
			const brut = JSON.parse(localStorage.getItem(CLEF));
			return brut && typeof brut === 'object' ? brut : null;
		} catch (e) {
			return null;
		}
	}

	/**
	 * Applique un état : Consent Mode d'abord, scripts ensuite.
	 *
	 * L'ordre compte. Les balises Google lisent l'état du consentement au
	 * moment de leur exécution : mises en route avant la mise à jour, elles
	 * envoient leur première mesure comme si tout était refusé.
	 */
	function appliquer(etat) {
		if (!etat) return;

		if (typeof gtag === 'function') {
			gtag('consent', 'update', {
				analytics_storage: etat.statistiques ? 'granted' : 'denied',
				ad_storage: etat.marketing ? 'granted' : 'denied',
				ad_user_data: etat.marketing ? 'granted' : 'denied',
				ad_personalization: etat.marketing ? 'granted' : 'denied'
			});
		}

		window.dataLayer = window.dataLayer || [];

		categories.forEach((categorie) => {
			if (!etat[categorie]) return;
			activer(categorie);
			window.dataLayer.push({ event: `beely_consentement_${categorie}` });
		});
	}

	/** Transmet la preuve au serveur. Sans jeton, on n'appelle même pas. */
	function tracer(etat) {
		if (!cfg.ajax_url || !cfg.nonce) return;

		const corps = new FormData();
		corps.append('action', 'beely_consentement');
		corps.append('nonce', cfg.nonce);
		corps.append('consent', JSON.stringify({
			analytics: !!etat.statistiques,
			marketing: !!etat.marketing
		}));

		fetch(cfg.ajax_url, { method: 'POST', body: corps, credentials: 'same-origin' })
			.catch(() => { /* la preuve est secondaire : son échec ne bloque rien */ });
	}

	function enregistrer(choix) {
		const precedent = lire();
		const etat = { necessaires: true, date: new Date().toISOString() };

		categories.forEach((categorie) => {
			etat[categorie] = !!choix[categorie];
		});

		localStorage.setItem(CLEF, JSON.stringify(etat));
		tracer(etat);
		fermer();

		/*
		 * Un retrait exige un rechargement.
		 *
		 * Les scripts déjà convertis tournent : rien, en JavaScript, ne
		 * « décharge » un traceur. Effacer ses cookies sans recharger le laisse
		 * en place, et il les repose dans la seconde.
		 */
		const retrait = categories.some((c) => precedent && precedent[c] && !etat[c]);

		if (retrait) {
			nettoyer();
			window.location.reload();
			return;
		}

		if (categories.every((c) => !etat[c])) nettoyer();

		appliquer(etat);
	}

	/* --------------------------------------------------------------- */
	/* Interface                                                       */
	/* --------------------------------------------------------------- */

	function montrer(element) {
		element.classList.remove('beely-hidden');
		element.removeAttribute('hidden');
	}

	function cacher(element) {
		element.classList.add('beely-hidden');
		element.setAttribute('hidden', '');
	}

	function fermer() {
		[banniere, modale, voile].forEach((el) => el && cacher(el));
		document.removeEventListener('keydown', surTouche);

		if (focusAvant && document.contains(focusAvant)) focusAvant.focus();
		focusAvant = null;
	}

	/** Échap ferme la modale ; Tab y reste enfermé tant qu'elle est ouverte. */
	function surTouche(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			fermer();
			if (!lire()) montrer(banniere);
			return;
		}

		if (e.key !== 'Tab') return;

		const focusables = modale.querySelectorAll('button, input, a[href]');
		if (!focusables.length) return;

		const premier = focusables[0];
		const dernier = focusables[focusables.length - 1];

		if (e.shiftKey && document.activeElement === premier) {
			e.preventDefault();
			dernier.focus();
		} else if (!e.shiftKey && document.activeElement === dernier) {
			e.preventDefault();
			premier.focus();
		}
	}

	function ouvrirModale() {
		const etat = lire();

		categories.forEach((categorie) => {
			const bascule = modale.querySelector(`[data-categorie="${categorie}"]`);
			if (bascule) bascule.checked = !!(etat && etat[categorie]);
		});

		focusAvant = document.activeElement;

		cacher(banniere);
		montrer(modale);
		if (voile) montrer(voile);

		document.addEventListener('keydown', surTouche);
		modale.querySelector('#beely-close-modal')?.focus();
	}

	function choixDepuisModale() {
		const choix = {};

		categories.forEach((categorie) => {
			choix[categorie] = !!modale.querySelector(`[data-categorie="${categorie}"]`)?.checked;
		});

		return choix;
	}

	function tous(valeur) {
		const choix = {};
		categories.forEach((categorie) => { choix[categorie] = valeur; });
		return choix;
	}

	/* --------------------------------------------------------------- */
	/* Démarrage                                                       */
	/* --------------------------------------------------------------- */

	function demarrer() {
		const etat = lire();

		if (etat) appliquer(etat);
		else montrer(banniere);

		document.getElementById('beely-accept-all')?.addEventListener('click', () => enregistrer(tous(true)));
		document.getElementById('beely-reject-all')?.addEventListener('click', () => enregistrer(tous(false)));
		document.getElementById('beely-settings-btn')?.addEventListener('click', ouvrirModale);
		document.getElementById('beely-save-preferences')?.addEventListener('click', () => enregistrer(choixDepuisModale()));

		document.getElementById('beely-close-modal')?.addEventListener('click', () => {
			fermer();
			if (!lire()) montrer(banniere);
		});

		voile?.addEventListener('click', () => {
			fermer();
			if (!lire()) montrer(banniere);
		});

		// Rouvrir les préférences depuis n'importe où — pied de page, page légale.
		document.addEventListener('click', (e) => {
			if (e.target.closest('[data-beely="cookies"]')) {
				e.preventDefault();
				ouvrirModale();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', demarrer);
	} else {
		demarrer();
	}
})();
