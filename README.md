# Navidrome Playlist Generator

Outil web self-hosted (Symfony 7) qui lit la base SQLite de
[Navidrome](https://www.navidrome.org/) en lecture seule pour calculer
différents tops de morceaux et créer les playlists correspondantes dans
Navidrome via son API Subsonic.

Fonctionnalités principales :

- **Système de plugins** : chaque type de playlist (« top des X derniers jours »,
  « morceaux jamais écoutés », « top du mois de mai il y a X années »…) est
  une simple classe PHP. En ajouter un nouveau = créer un fichier dans
  `src/Generator/`. Voir [`docs/PLUGINS.md`](docs/PLUGINS.md).
- **Configuration en base** (SQLite locale, Doctrine ORM) : nom, paramètres,
  planning cron, limite, activation par playlist. Géré via l'UI.
- **Génération automatique** par cron interne (supercronic) qui se rafraîchit
  toutes les 5 minutes pour refléter les changements faits dans l'UI.
- **Auth UI** : un seul couple login/mot de passe défini dans `.env`, pas de
  base utilisateurs.
- **Détection automatique** de la table `scrobbles` (Navidrome ≥ 0.55, fin
  2025) pour des stats exactes ; fallback sur `annotation.play_date` sinon.
- **Image Docker** unique qui sert au choix l'UI web ou le démon cron via
  la variable `APP_MODE`.

## Quickstart Docker (production / self-host)

```bash
# 1. Récupérer le compose et l'éditer
curl -O https://raw.githubusercontent.com/kgaut/navidrome-playlist-generator/main/docker-compose.example.yml
cp docker-compose.example.yml docker-compose.yml

# 2. Créer un .env avec vos secrets
cat > .env <<EOF
APP_SECRET=$(openssl rand -hex 32)
NAVIDROME_URL=http://navidrome:4533
NAVIDROME_USER=admin
NAVIDROME_PASSWORD=...                  # mot de passe Navidrome
APP_AUTH_USER=admin
APP_AUTH_PASSWORD=...                   # mot de passe pour l'UI
NAVIDROME_DATA_DIR=/srv/navidrome/data  # dossier qui contient navidrome.db
EOF

# 3. Lancer les deux services (web + cron)
docker compose up -d

# 4. Ouvrir l'UI
open http://localhost:8080
```

Au premier lancement, 4 définitions de playlist d'exemple sont créées
**désactivées** : Top 7j, Top 30j, Top mois passé, Top année passée. Vous
pouvez les éditer puis les activer.

### Variables d'environnement

| Variable             | Obligatoire | Description                                                              |
|----------------------|-------------|--------------------------------------------------------------------------|
| `APP_SECRET`         | oui         | Secret Symfony (32 caractères hex). `openssl rand -hex 32`.              |
| `APP_ENV`            | non (`prod`)| `prod` ou `dev`.                                                         |
| `APP_MODE`           | non (`web`) | `web` (FrankenPHP) ou `cron` (supercronic).                              |
| `NAVIDROME_DB_PATH`  | oui         | Chemin du fichier SQLite Navidrome dans le conteneur. Bind-mounter `:ro`.|
| `NAVIDROME_URL`      | oui         | URL HTTP(S) de Navidrome (sans slash final).                             |
| `NAVIDROME_USER`     | oui         | Utilisateur Navidrome dont on lit les écoutes et qui possède les playlists. |
| `NAVIDROME_PASSWORD` | oui         | Mot de passe de cet utilisateur.                                         |
| `APP_AUTH_USER`      | oui         | Identifiant pour se connecter à l'UI du tool.                            |
| `APP_AUTH_PASSWORD`  | oui         | Mot de passe pour se connecter à l'UI du tool.                           |
| `DATABASE_URL`       | non         | DSN Doctrine pour la DB locale du tool. Défaut : SQLite dans `var/data.db`. |
| `CRON_REGEN_INTERVAL`| non (`300`) | Intervalle en secondes entre 2 régénérations du crontab (mode cron).     |

### Mise à jour

```bash
docker compose pull
docker compose up -d
```

Les migrations Doctrine sont jouées automatiquement à chaque démarrage
(idempotent). Le volume `playlist-data` préserve la configuration entre
redémarrages.

## Développement local avec Lando (recommandé)

[Lando](https://lando.dev/) fournit l'environnement Symfony complet
(PHP 8.3 + nginx + Composer 2) sans installer quoi que ce soit sur la
machine hôte.

```bash
git clone https://github.com/kgaut/navidrome-playlist-generator
cd navidrome-playlist-generator

# Copier la base SQLite Navidrome (ou créer un symlink)
cp /chemin/vers/navidrome.db var/navidrome.db

lando start          # premier lancement : pull des images + composer install
lando migrate        # crée la DB locale du tool
lando seed           # insère les 4 définitions d'exemple

# UI accessible sur :
#   https://playlist-generator.lndo.site
```

Commandes utiles :

| Commande Lando             | Effet                                                          |
|----------------------------|----------------------------------------------------------------|
| `lando symfony cache:clear`| Vider le cache Symfony.                                        |
| `lando composer require X` | Installer un package.                                          |
| `lando test`               | Lancer PHPUnit.                                                |
| `lando migrate`            | Jouer les migrations Doctrine.                                 |
| `lando seed`               | Réinsérer les fixtures (idempotent).                           |
| `lando playlist-run "Top 30 derniers jours" --dry-run` | Tester une définition. |
| `lando cron-dump`          | Voir le crontab généré pour supercronic.                       |

Le service `cron` lance supercronic en local, identique au mode prod.

Pour activer Xdebug : éditer `.lando.yml` (`xdebug: debug`) puis
`lando rebuild -y`.

## Développement local sans Lando

Pré-requis : PHP 8.2+, ext-pdo_sqlite, Composer 2, [Symfony CLI](https://symfony.com/download).

```bash
composer install
cp .env.dist .env.local              # ajuster les valeurs
mkdir -p var
cp /chemin/vers/navidrome.db var/navidrome.db
php bin/console doctrine:migrations:migrate -n
php bin/console app:fixtures:seed
symfony serve                        # https://127.0.0.1:8000
```

## Ajouter un nouveau type de playlist (plugin)

Voir [`docs/PLUGINS.md`](docs/PLUGINS.md). En résumé : créer une classe
qui implémente `App\Generator\PlaylistGeneratorInterface` dans
`src/Generator/`, elle est auto-détectée et apparaît immédiatement dans
le dropdown de l'UI.

## Schéma Navidrome utilisé (lecture seule)

| Table          | Colonnes lues                                                |
|----------------|--------------------------------------------------------------|
| `media_file`   | id, title, album, artist, album_artist, duration, year       |
| `annotation`   | user_id, item_id, item_type, play_count, play_date           |
| `user`         | id, user_name (résolution `NAVIDROME_USER` → user_id Subsonic)|
| `scrobbles`    | media_file_id, user_id, submission_time (Navidrome ≥ 0.55)   |

Si la table `scrobbles` n'existe pas, le tool retombe sur
`annotation.play_date`, qui ne contient que la date du **dernier** play.
Les tops « par fenêtre temporelle » deviennent donc approximatifs ; un
bandeau d'avertissement est affiché dans l'UI dans ce cas.

## Création de playlists

Toutes les playlists sont créées via l'API Subsonic de Navidrome
(`createPlaylist.view`). Le tool **n'écrit jamais directement** dans la
SQLite Navidrome. Avantages : aucun risque de corruption ou de conflit
de lock, fonctionne même si Navidrome tourne en parallèle.

L'option « remplacer la playlist existante » utilise
`getPlaylists.view` + `deletePlaylist.view` pour retirer l'ancienne du
même nom appartenant au même utilisateur, puis recrée la nouvelle.

## Licence

MIT.
