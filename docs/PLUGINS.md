# Ajouter un nouveau type de playlist (plugin)

Chaque type de playlist proposé par le tool est une classe PHP qui
implémente `App\Generator\PlaylistGeneratorInterface`. L'autoconfigure
de Symfony tague automatiquement toute classe dans `src/Generator/`
qui implémente cette interface, et `GeneratorRegistry` la rend disponible
dans :

- la liste déroulante du formulaire de définition (`/playlist/new`),
- la commande `bin/console app:playlist:run`,
- le dump du crontab `bin/console app:cron:dump`.

**Aucun changement de configuration n'est nécessaire** : les paramètres
sont stockés en JSON dans la colonne `parameters` de
`playlist_definition`.

## L'interface

```php
namespace App\Generator;

interface PlaylistGeneratorInterface
{
    public function getKey(): string;          // identifiant kebab-case stable
    public function getLabel(): string;        // libellé humain
    public function getDescription(): string;  // description courte
    /** @return ParameterDefinition[] */
    public function getParameterSchema(): array;
    /**
     * @param array<string, mixed> $parameters
     * @return string[] media_file ids ordonnés (≤ $limit)
     */
    public function generate(array $parameters, int $limit): array;
}
```

`ParameterDefinition` accepte les types `int`, `string`, `bool` et
`choice` (avec `$choices` = `valeur => label`). Le formulaire est
construit dynamiquement à partir de ce schéma.

## Exemple complet : « Top des morceaux d'un genre »

Créer le fichier `src/Generator/TopByGenreGenerator.php` :

```php
<?php

namespace App\Generator;

use Doctrine\DBAL\Connection;

class TopByGenreGenerator implements PlaylistGeneratorInterface
{
    public function __construct(
        private readonly Connection $navidromeConnection, // injected via doctrine.dbal.navidrome_connection
    ) {}

    public function getKey(): string
    {
        return 'top-by-genre';
    }

    public function getLabel(): string
    {
        return 'Top morceaux par genre';
    }

    public function getDescription(): string
    {
        return 'Les morceaux les plus écoutés appartenant à un genre donné.';
    }

    public function getParameterSchema(): array
    {
        return [
            new ParameterDefinition(
                name: 'genre',
                label: 'Genre exact (ex. "Jazz")',
                type: ParameterDefinition::TYPE_STRING,
                default: 'Jazz',
            ),
        ];
    }

    public function generate(array $parameters, int $limit): array
    {
        $genre = (string) ($parameters['genre'] ?? '');
        $rows = $this->navidromeConnection->fetchAllAssociative(
            'SELECT mf.id
             FROM media_file mf
             JOIN annotation a ON a.item_id = mf.id AND a.item_type = "media_file"
             WHERE mf.genre = :genre
             ORDER BY a.play_count DESC
             LIMIT :lim',
            ['genre' => $genre, 'lim' => $limit],
            ['lim' => \PDO::PARAM_INT],
        );
        return array_map(static fn ($r) => (string) $r['id'], $rows);
    }
}
```

C'est tout. Recharger l'UI : « Top morceaux par genre » apparaît dans
le dropdown, avec un champ texte pour le genre.

## Bonnes pratiques

- **Réutiliser `App\Navidrome\NavidromeRepository`** quand c'est possible
  (helpers `topTracksInWindow`, `topAllTime`, `neverPlayedRandom`,
  `summarize`). Il gère la détection automatique de `scrobbles` vs
  `annotation` et la résolution du `user_id` Navidrome.
- **Toujours respecter `$limit`** : `LIMIT :lim` dans la requête, pas de
  troncature en PHP qui ferait remonter inutilement des milliers de lignes.
- **Ordre du résultat** : la position dans le tableau retourné est
  conservée dans la playlist Navidrome.
- **Paramètres** : préférer `TYPE_INT` ou `TYPE_CHOICE` à `TYPE_STRING`
  quand c'est possible — la validation est gratuite côté formulaire.
- **Pas d'effet de bord** : `generate()` doit être idempotent et
  read-only (la création de la playlist est faite par `PlaylistRunner`
  via Subsonic, pas par le générateur).

## Tester un nouveau plugin

```php
// tests/Generator/TopByGenreGeneratorTest.php
class TopByGenreGeneratorTest extends \PHPUnit\Framework\TestCase
{
    public function testRespectsLimit(): void
    {
        // construire une DB SQLite de fixture (cf. tests/fixtures)
        // instancier le générateur
        // assert: count($result) <= $limit
    }
}
```

Voir `tests/Generator/TopLastDaysGeneratorTest.php` pour un exemple
complet utilisant une fixture SQLite minimaliste.
