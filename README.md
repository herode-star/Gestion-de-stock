# Gestion de stock

Application PHP/MySQL de gestion de produits, stock, ventes, fournisseurs et comptes utilisateurs. La nouvelle interface est responsive, securisee et concue pour des personnes sans connaissances informatiques.

Fonctions principales :

- assistant de configuration au premier demarrage ;
- tableau de bord et alertes de stock faible ;
- ajout, recherche et modification des produits ;
- ventes avec deduction automatique du stock ;
- fournisseurs et comptes administrateurs ;
- sauvegarde des donnees en un clic ;
- centre AI et statistiques avec recommandations de stock ;
- journal d'activite pour suivre les changements importants ;
- interface utilisable sur telephone, tablette et ordinateur.

## Demarrage rapide avec Docker

Prerequis : Docker Desktop (Windows/macOS) ou Docker Engine avec Compose (Linux).

Copiez `.env.example` vers `.env`, puis remplissez `DB_PASSWORD` et `DB_ROOT_PASSWORD` avec deux mots de passe différents. Pour une installation existante, utilisez les mots de passe actuels de la base : modifier `.env` ne change pas les comptes déjà créés dans MariaDB.

```bash
cp .env.example .env
# Remplissez les deux mots de passe dans .env avant de continuer.
docker compose up --build
```

Ouvrez ensuite [http://localhost:8080](http://localhost:8080).

Au premier demarrage, MariaDB importe automatiquement `bd/otechnologie.sql`.
L'assistant vous demandera ensuite le nom du commerce, votre email, un mot de passe et la devise. Aucune modification de code ou de base de donnees n'est necessaire.

Un guide pas a pas en creole haitien est disponible dans [`GUIDE-ITILIZATE.md`](GUIDE-ITILIZATE.md).

## AI avancee (optionnel)

Le centre de statistiques fonctionne sans abonnement grâce aux analyses locales. Pour activer les questions libres avec OpenAI, copiez `.env.example` vers `.env`, puis ajoutez votre cle API :

```env
OPENAI_API_KEY=votre_cle
OPENAI_MODEL=gpt-5-mini
```

Redemarrez ensuite l'application avec `docker compose up -d --build`. Le fichier `.env` ne doit jamais etre publie sur GitHub.

Pour arreter l'application :

```bash
docker compose down
```

Pour repartir avec une base de donnees neuve :

```bash
docker compose down --volumes
docker compose up --build
```

## Configuration sans Docker

Le serveur doit disposer de PHP 8.2 ou plus recent avec PDO MySQL et d'une base MySQL/MariaDB. Les variables suivantes permettent de configurer la connexion :

| Variable | Valeur par defaut |
| --- | --- |
| `DB_HOST` | `localhost` |
| `DB_NAME` | `Otechnologie` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | vide |

Importez `bd/otechnologie.sql`, puis servez le dossier du projet avec Apache. Appliquez aussi les restrictions de `docker/apache-directory-index.conf` en adaptant le chemin du projet ; elles protègent les fichiers privés et les anciennes bibliothèques.

## Mise à jour et sauvegarde complète

Avant une mise à jour, conservez une sauvegarde SQL et une copie des photos :

```bash
mkdir -p sauvegardes
docker compose exec -T database sh -c 'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction Otechnologie' > sauvegardes/stock.sql
docker compose cp app:/var/www/html/produit ./sauvegardes/photos
```

Ces fichiers contiennent des données privées. Gardez-les hors du dépôt.
La sauvegarde JSON de l'interface est un export métier, sans comptes ni fichiers photo ; elle ne remplace pas cette sauvegarde complète.

Pour restaurer une sauvegarde complète **sur une installation de récupération séparée**, arrêtez l'application pendant l'import, puis :

```bash
docker compose stop app
docker compose exec -T database sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" Otechnologie' < sauvegardes/stock.sql
docker compose start app
docker compose cp ./sauvegardes/photos/. app:/var/www/html/produit/
docker compose exec -u root app chown -R www-data:www-data /var/www/html/produit
```

L'import remplace les tables existantes : ne le lancez pas sur des données à conserver.
Voir le guide créole pour la migration des photos d'une ancienne installation sans volume.
Le service écoute par défaut uniquement sur `localhost`. Ne l'exposez pas directement à Internet ; une installation distante nécessite HTTPS et une configuration dédiée.

## Vérification

La workflow `Stock checks` construit l'image Docker et vérifie la configuration initiale, la connexion, les pages principales, l'ajout/modification de produits, une vente, le refus du stock insuffisant et des valeurs invalides, les conflits de stock, l'export JSON et les protections d'accès. Elle utilise une base jetable.

Les anciennes URL redirigent vers l'espace de gestion actuel. L'ancien catalogue et checkout de démonstration sont retirés ; leur code reste dans l'historique Git. Une installation neuve importe encore les produits de démonstration du fichier SQL historique.
