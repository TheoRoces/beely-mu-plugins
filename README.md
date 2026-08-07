# Canal de mise à jour

Contenu produit automatiquement. Une modification faite ici ne survit pas à la
publication suivante.

## Ce que porte une version

- l’archive de l’extension — les tests n’y sont pas ;
- son empreinte, relue avant toute écriture sur le disque ;
- sa signature, relue lorsque la clé publique est présente.

L’étiquette de version commence par le nom de l’extension, ce dépôt en
rassemblant plusieurs.

## En dépannage

Le site installe seul. À la main, l’archive se pose dans
`wp-content/mu-plugins/`.

Attention au fichier de chargement : il se place directement dans ce dossier,
WordPress n’exécutant que les fichiers PHP qu’il y trouve au premier niveau.
