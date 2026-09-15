# Gestion de stock

Application PHP/MySQL de gestion de produits, stock, commandes, fournisseurs et comptes utilisateurs.

## Demarrage rapide avec Docker

Prerequis : Docker Desktop (Windows/macOS) ou Docker Engine avec Compose (Linux).

```bash
docker compose up --build
```

Ouvrez ensuite [http://localhost:8080](http://localhost:8080).

Au premier demarrage, MariaDB importe automatiquement `bd/otechnologie.sql`.

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

Le serveur doit disposer de PHP 7.4 avec PDO MySQL et d'une base MySQL/MariaDB. Les variables suivantes permettent de configurer la connexion :

| Variable | Valeur par defaut |
| --- | --- |
| `DB_HOST` | `localhost` |
| `DB_NAME` | `Otechnologie` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | vide |

Importez `bd/otechnologie.sql`, puis servez le dossier du projet avec Apache.
