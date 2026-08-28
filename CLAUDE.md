# CLAUDE.md

## Le projet

**Rasengan V3** — application web Symfony 7.3 (PHP 8.2+) de collection de cartes à
l'effigie d'animes et de films. Interface et données en français.

Boucle de jeu :
1. un **admin** fait tourner une **roue** (`/admin/wheel/...`) qui tire une rareté puis
   une carte, et l'attribue à un joueur (« drop ») ;
2. le joueur consulte sa **collection** et le **catalogue**, marque des cartes en
   **wishlist** ;
3. les joueurs **échangent** leurs doublons (`/echanges`) ;
4. certaines cartes **mythiques** se débloquent en consommant d'autres cartes (recettes) ;
5. des **badges** (collectionneur, ancienneté) sont recalculés automatiquement ;
6. chaque drop / échange peut être poussé sur **Discord** via webhook.

## Environnement local (à lire avant toute commande)

`php`, `composer` et `docker` **ne sont pas dans le PATH**. Utiliser les chemins absolus :

| Outil | Chemin |
|---|---|
| PHP 8.4.15 (WAMP) | `C:/wamp64/bin/php/php8.4.15/php.exe` |
| Composer 2.10 | `C:/Users/Loris/bin/composer.phar` (à lancer avec le PHP ci-dessus) |

D'autres versions de PHP sont installées sous `C:/wamp64/bin/php/` (8.0 → 8.5) ;
le projet exige `>= 8.2`.

**PostgreSQL** : le `php.ini` de WAMP n'active **pas** `pdo_pgsql` (seuls `pdo_mysql` /
`pdo_sqlite` le sont), alors que `DATABASE_URL` pointe sur Postgres 16. Les DLL sont
présentes dans `ext/` : les charger à la volée plutôt que modifier `php.ini` :

```bash
PHP="C:/wamp64/bin/php/php8.4.15/php.exe"
$PHP -d extension=pdo_pgsql -d extension=pgsql bin/console dbal:run-sql "SELECT 1"
```

La base elle-même vient de `compose.yaml` (image `postgres:16-alpine`, port 5432).
Sans serveur démarré, toute commande touchant la base échoue en `Connection refused` :
c'est attendu, ce n'est pas un bug du code. Les commandes qui ne touchent pas la base
(`about`, `debug:router`, `debug:container`, `lint:twig`, `cache:clear`) fonctionnent.

## Commandes utiles

```bash
PHP="C:/wamp64/bin/php/php8.4.15/php.exe"
COMPOSER="C:/Users/Loris/bin/composer.phar"

$PHP $COMPOSER install --no-scripts   # dépendances (vendor/ est gitignoré)
$PHP bin/console about                # état du projet
$PHP bin/console debug:router         # toutes les routes
$PHP bin/console lint:twig templates  # vérifier les templates
$PHP bin/console cache:clear
$PHP bin/console doctrine:migrations:migrate      # nécessite la base
$PHP bin/console app:recalculate-collector-badges # recalcul des badges
$PHP -S localhost:8000 -t public public/index.php # serveur de dev sans Symfony CLI
$PHP vendor/bin/phpunit                           # suite de tests
```

## Tests

**PHPUnit 10.5** (`phpunit/phpunit` + `symfony/phpunit-bridge` en `require-dev`), lancé
avec `$PHP vendor/bin/phpunit`. Configuration dans `phpunit.dist.xml` (suite = tout
`tests/`, bootstrap `tests/bootstrap.php`, env de test dans `.env.test`).

`symfony/test-pack` **ne s'installe pas** : il tire `browser-kit` → `dom-crawler`, dont
toutes les versions sont bloquées par un avis de sécurité (CVE-2026-45071). Les tests
fonctionnels HTTP (`WebTestCase`) ne sont donc pas disponibles pour l'instant ; s'en tenir
aux tests unitaires (`TestCase`) et, si besoin, aux tests de service (`KernelTestCase`,
fourni par framework-bundle).

La suite ne contient qu'un test : `tests/DiscordNotifierAttachmentIdsTest.php`, qui
couvre la récupération des ids de pièces jointes Discord depuis les URLs d'embed.

## Stack et dépendances

- **Symfony 7.3** : framework-bundle, twig-bundle, form, validator, security-bundle,
  http-client, asset, console, dotenv, runtime, mime, yaml.
- **Doctrine** : ORM 3, DBAL 3, doctrine-bundle, migrations-bundle. Mapping par
  **attributs** dans `src/Entity`, `auto_mapping: true`.
- **KnpPaginatorBundle** (pagination des listes), **SymfonyCasts** reset-password et
  verify-email (bundles déclarés), **twig/extra-bundle** + `twig/string-extra`.
- **paragonie/sodium_compat** (secrets Symfony).
- Dev : **maker-bundle**, **phpunit/phpunit 10.5**, **symfony/phpunit-bridge**.
- Front : **pas de bundler** (ni Webpack Encore, ni AssetMapper, ni npm — aucun
  `package.json`). Le CSS/JS est écrit à la main dans `public/css/` et `public/js/`,
  inclus via `{{ asset(...) }}`. Polices Google, Boxicons et Font Awesome sont chargés
  depuis des CDN dans `templates/base.html.twig`.

## Architecture

```
src/
  Controller/   23 contrôleurs, routes en attributs #[Route]
  Entity/       18 entités Doctrine
  Repository/   repositories Doctrine (DQL / QueryBuilder)
  Service/      logique métier réutilisable (voir plus bas)
  Form/         Symfony Forms (Anime, Film, CardAnime, CardFilm, Badge, profil, inscription)
  Command/      commandes console (hash de mots de passe, recalcul badges)
config/packages/  doctrine, security, twig, cache, csrf, validator...
migrations/       migrations Doctrine (versions horodatées)
templates/        Twig, un dossier par domaine, tous étendent base.html.twig
public/           point d'entrée index.php, css/, js/, images/
```

Les contrôleurs sont **épais** : une grande partie de la logique (mythiques, roue,
catalogue) vit directement dans le contrôleur, pas dans un service. Ne pas supposer
qu'un service existe : vérifier avant.

### Services (`src/Service`)

- **TradeService** — cœur des échanges : `proposeTrade`, `accept`, `decline`, `cancel`,
  `counter`. À l'acceptation il verrouille les lignes (`lockUserCards`), transfère les
  cartes, **invalide** les autres offres devenues impossibles
  (`invalidateImpossibleOffers`) et nettoie les wishlists.
- **WishlistService** — ajout/retrait, ids en wishlist, et `findTradeSuggestions()` qui
  croise « mes doublons » avec « ce que l'autre veut ».
- **BadgeService** — `refreshCollectorBadges()` / `refreshSeniorityBadges()` (paliers) et
  `getBadgeShowcase()` pour la vitrine du profil.
- **RarityStatsService** — répartition de la collection par rareté (barres du profil).
- **DiscordNotifier** — webhooks Discord (drops et échanges). Regroupe les drops
  successifs d'un même joueur en **batch** (TTL 1800 s, stocké en cache), édite le message
  existant, joint l'image de la carte, mentionne le joueur via son `discordId`, couleur
  d'embed selon la rareté. Câblé dans `config/services.yaml` avec
  `DISCORD_DROP_WEBHOOK_URL` / `DISCORD_TRADE_WEBHOOK_URL`.

### Modèle de domaine

- **User** — `pseudo`, `email` (identifiant de connexion), `password`, `isAdmin` + `roles`
  JSON, `discordId`, `dateCreation`, image/titre de collection. Collections :
  `userCardAnimes`, `userCardFilms`, `userCardJeus`, `badges` (ManyToMany ordonné par
  `position`), wishlists ManyToMany (`user_wishlist_anime`, `user_wishlist_film`,
  `user_wishlist_jeu`).
- **Anime / Film / Jeu** — séries, films et jeux vidéo regroupant les cartes.
- **CardAnime / CardFilm / CardJeu** — la carte : `nom`, `description`, `imagePath`,
  `quantity` (tirage total), `rarity`, plus ses `requirements` si elle est mythique. Les
  trois familles sont **strictement parallèles** : toute évolution sur l'une doit être
  répercutée sur les autres (entité, repository, contrôleur, template).
- **Rarities** — `libelle` (Communes, Rares, Épiques, Legendaires, Mythiques) et
  `quantiteParDefaut`. Les ids 1→4 sont utilisés en dur dans les poids de la roue.
- **UserCardAnime / UserCardFilm / UserCardJeu** — possession : `user`, `card`,
  `quantity`, `obtainedAt`. `quantity > 1` = doublon échangeable.
- **CardAnimeRequirement / CardFilmRequirement / CardJeuRequirement** — recette d'une mythique : carte requise
  (ou `placeholderNom` si la carte n'existe pas encore), `quantityRequired`, et des
  `alternativeCards` acceptées en remplacement.
- **TradeOffer / TradeOfferItem** — offre avec statuts `pending`, `accepted`, `declined`,
  `cancelled`, `countered`, `invalidated`, contre-offres chaînées via `parentOffer`.
  Un item porte `owner` + `cardAnime` **ou** `cardFilm` **ou** `cardJeu` + `quantity`.
- **Badge** — `name`, `icon`, `type`, `level`, `objective`, `rarity`, `position`.
- **RememberMeToken** — persistance du « se souvenir de moi ».

### Sécurité

`config/packages/security.yaml` : provider entité sur `User.email`, `form_login` avec CSRF,
remember-me 7 jours. **Tout est protégé sauf** `/`, `/login`, `/register`, `/logout`.
Les zones admin sont en plus sous `#[IsGranted('ROLE_ADMIN')]` et préfixées `/admin`
(exception : `/card/...`, qui gère aussi de l'administration de cartes — vérifier
l'attribut du contrôleur).

### Roue (drops)

`WheelController::RARITY_WEIGHTS` = 40/35/20/5 (commune / rare / épique / légendaire) et un
mode `RARITY_WEIGHTS_333` = 33/33/33/1. Les mythiques ne se tirent pas : elles se craftent.
Les raretés sans carte disponible sont exclues avant le tirage ; l'attribution passe par
`app_wheel_*_confirm`, qui crée ou incrémente le `UserCard*`, rafraîchit les badges,
nettoie la wishlist et notifie Discord. Le rendu visuel est dans `public/js/wheel.js`.

### Jeux vidéo (section non encore ouverte aux joueurs)

La famille **Jeu / CardJeu / UserCardJeu / CardJeuRequirement** est complète et
fonctionnelle (roue, attribution, catalogue, wishlist, mythiques, échanges, badges,
stats, drops Discord — type `Jeu vidéo`), mais **volontairement fermée aux joueurs** en
attendant le lancement :

- dans `templates/catalogue/index.html.twig`, la troisième tuile reste « ????? », porte
  la classe `card-locked`, n'a pas de `data-url` et est exclue du listener JS : elle
  n'est **pas cliquable** ;
- les routes joueur de la section (`app_catalogue_jeu_cards`,
  `app_catalogue_all_jeu_cards`, `app_mythic_jeu_*`, `app_wishlist_toggle_jeu`) portent
  `#[IsGranted('ROLE_ADMIN')]` ;
- la liste des jeux dans le catalogue et l'option « Jeux vidéo » du filtre de section
  d'une collection ne s'affichent que pour un admin.

**Pour ouvrir la section aux joueurs** : retirer les `#[IsGranted('ROLE_ADMIN')]` de ces
routes, retirer la classe `card-locked` de la tuile et lui rendre son `data-url` +
son libellé, puis lever les `is_granted('ROLE_ADMIN')` des templates concernés.

Les images de cartes de jeu vidéo vont dans `public/images/Cartes/Jeux_<Rareté>/`
(comme `Films_<Rareté>/` pour les films).

## Conventions

- Code, commentaires, libellés d'UI et noms de routes/templates en **français**
  (`app_trade_new`, `/echanges`, `templates/trade/`).
- Routes déclarées en **attributs PHP**, jamais dans `config/routes.yaml`.
- Autowiring/autoconfiguration complets (`config/services.yaml`) : un service de
  `src/Service` s'injecte directement dans un contrôleur.
- Les images de cartes sont rangées par rareté sous `public/images/Cartes/<Rareté>/`
  (`Films_<Rareté>/` pour les films, `Jeux_<Rareté>/` pour les jeux vidéo) ; l'upload
  calcule le dossier cible à partir du libellé de rareté.
- `AGENTS.md` est une **copie** de ce fichier : modifier les deux ensemble.
- Ne pas committer `.env` (il contient l'`APP_SECRET`, les identifiants de base et les
  webhooks Discord réels) et ne pas relayer son contenu.

## Fichiers à ignorer

Ignore le contenu du dossier `public/images/Cartes/` (et tous ses sous-dossiers : Communes, Rares, Épiques, Légendaires, Mythiques, Films_*, Jeux_*, Events, etc.).

- Ne pas lire, lister en détail, analyser ou décrire ces images.
- Ce sont des assets binaires (illustrations de cartes) sans intérêt pour la compréhension du code.
- Si une tâche nécessite de référencer ces fichiers (ex: ajout/suppression d'une carte), se contenter des chemins/noms de fichiers sans ouvrir les images.

Ignore également `vendor/`, `var/` et `composer.lock` (dépendances installées et caches) :
ne pas les parcourir pour comprendre le code applicatif.
