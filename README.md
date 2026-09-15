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

```bash
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

Importez `bd/otechnologie.sql`, puis servez le dossier du projet avec Apache.
