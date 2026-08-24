# TaskFlow Manager API

API REST pour la société fictive **TaskFlow**, application de gestion de projets et de tâches.

**Authentification JWT, gestion des utilisateurs, projets (statuts, archivage, membres), tâches (CRUD, assignation, transitions d'état) et tags (libellés par projet).**

## Stack technique

| Composant        | Version                                 |
|------------------|-----------------------------------------|
| Langage          | **PHP** ≥ 8.2                           |
| Framework        | **Symfony** 7.2                         |
| Base de données  | **MySQL** 8                             |
| Conteneurisation | **Docker** + Docker Compose             |
| Authentification | **Lexik JWT Authentication Bundle** 3.x |
| Tests            | **PHPUnit** 11                          |

---

## Prérequis

### Avec Docker

- **Docker** Engine
- **Docker Compose** (plugin `docker compose`)

### Sans Docker (installation locale)

- **PHP** ≥ 8.2 avec extensions (`pdo_mysql`, `zip`, …)
- **Composer** 2.x
- **MySQL** 8

---

## Fichiers d'environnement

| Fichier       | Versionné ? | Rôle                                                       |
|---------------|-------------|------------------------------------------------------------|
| `.env`        | Oui         | Modèle avec les variables (valeurs vides). Sert d'exemple. |
| `.env.local`  | Non         | Secrets pour une exécution **locale** (PHP hors Docker).   |
| `.env.docker` | Non         | Secrets pour **Docker Compose** (`--env-file`).            |
| `.env.test`   | Oui         | Config des tests (SQLite en mémoire).                      |

Les fichiers `.env.local` et `.env.docker` sont gitignorés : ne jamais y committer de secrets.

---

## Installation avec Docker

### 1) Cloner le dépôt

```bash
git clone https://github.com/palepupet/gdu_taskflow_manager_api.git
cd gdu_taskflow_manager_api
```

### 2) Créer le fichier d'environnement Docker

```bash
cp .env .env.docker
```

Éditer `.env.docker` et renseigner au minimum :

- `APP_SECRET` (chaîne aléatoire, ex. `openssl rand -hex 32`)
- `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`
- `DATABASE_URL`, **important** : le host doit être le nom du service Compose `database`, pas `127.0.0.1`

Exemple :

```dotenv
APP_ENV=dev
APP_SECRET=change_me_to_a_long_random_string
MYSQL_DATABASE=taskflow_manager_api
MYSQL_USER=app
MYSQL_PASSWORD=ChangeMe
MYSQL_ROOT_PASSWORD=ChangeMe
DATABASE_URL="mysql://app:ChangeMe@database:3306/taskflow_manager_api?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

### 3) Build et démarrage

```bash
docker compose --env-file .env.docker up --build
```

En arrière-plan :

```bash
docker compose --env-file .env.docker up --build -d
```

Services démarrés :

| Service    | Rôle             | Accès                                          |
|------------|------------------|------------------------------------------------|
| `api`      | Symfony + Apache | [http://localhost:8000](http://localhost:8000) |
| `database` | MySQL 8          | `127.0.0.1:3307` depuis l'hôte                 |

Attendre quelques secondes que MySQL soit prêt.

### 4) Migrations

```bash
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### 5) Clés JWT

```bash
docker compose --env-file .env.docker exec api php bin/console lexik:jwt:generate-keypair --overwrite
```

Les clés sont créées dans `config/jwt/` (`private.pem`, `public.pem`, non versionnées).

### 6) Fixtures (optionnel)

```bash
docker compose --env-file .env.docker exec api php bin/console doctrine:fixtures:load --no-interaction
```

### 7) Vérifier

- Documentation OpenAPI : [http://localhost:8000/api/doc](http://localhost:8000/api/doc)
- Conteneurs : `docker compose --env-file .env.docker ps`
- Logs API : `docker compose --env-file .env.docker logs -f api`

### Commandes utiles (Docker)

```bash
# Console Symfony dans le conteneur
docker compose --env-file .env.docker exec api php bin/console list

# Shell dans le conteneur API
docker compose --env-file .env.docker exec api bash

# Client MySQL dans le conteneur BDD
docker compose --env-file .env.docker exec database mysql -u app -pChangeMe taskflow_manager_api

# Arrêt (conserve les données MySQL)
docker compose --env-file .env.docker down

# Arrêt + suppression du volume BDD
docker compose --env-file .env.docker down -v
```

Toujours préfixer avec `--env-file .env.docker` pour que Compose charge vos secrets.

### Accès à la base MySQL depuis l'hôte

Le port hôte est **3307** (voir `compose.override.yaml`), pour éviter un conflit avec un MySQL local sur 3306.

| Paramètre       | Valeur                           |
|-----------------|----------------------------------|
| Host            | `127.0.0.1`                      |
| Port            | `3307`                           |
| User / Password | ceux définis dans `.env.docker`  |
| Database        | celle définie dans `.env.docker` |

Le navigateur ne peut pas ouvrir `http://127.0.0.1:3307` (protocole MySQL, pas HTTP). Utiliser un client SQL (DBeaver, etc.) ou la CLI ci-dessus.

---

## Installation locale (sans Docker)

### 1) Cloner et dépendances

```bash
git clone https://github.com/palepupet/gdu_taskflow_manager_api.git
cd gdu_taskflow_manager_api
composer install
```

### 2) Clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

### 3) Environnement

```bash
cp .env .env.local
```

Éditer `.env.local` : `APP_SECRET`, `DATABASE_URL` (host `127.0.0.1` vers votre MySQL local).

### 4) Migrations et fixtures

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

### 5) Lancer le serveur

```bash
symfony server:start
# ou
php -S localhost:8000 -t public
```

---

## Configuration

### Variables d'environnement

| Variable              | Description                                                 |
|-----------------------|-------------------------------------------------------------|
| `APP_ENV`             | Environnement (`dev`, `test`, `prod`)                       |
| `APP_SECRET`          | Secret Symfony (obligatoire, non vide)                      |
| `MYSQL_DATABASE`      | Nom de la base (Docker Compose)                             |
| `MYSQL_USER`          | Utilisateur MySQL (Docker Compose)                          |
| `MYSQL_PASSWORD`      | Mot de passe MySQL (Docker Compose)                         |
| `MYSQL_ROOT_PASSWORD` | Mot de passe root MySQL (Docker Compose)                    |
| `DATABASE_URL`        | URL Doctrine (`@database` en Docker, `@127.0.0.1` en local) |
| `JWT_SECRET_KEY`      | Chemin de la clé privée JWT                                 |
| `JWT_PUBLIC_KEY`      | Chemin de la clé publique JWT                               |
| `JWT_PASSPHRASE`      | Passphrase de la clé privée (vide si aucune)                |
| `CORS_ALLOW_ORIGIN`   | Regex des origines CORS autorisées                          |

### Exemple `DATABASE_URL` (MySQL)

Docker :

```dotenv
DATABASE_URL="mysql://app:ChangeMe@database:3306/taskflow_manager_api?serverVersion=8.0&charset=utf8mb4"
```

Local :

```dotenv
DATABASE_URL="mysql://app:ChangeMe@127.0.0.1:3306/taskflow_manager_api?serverVersion=8.0&charset=utf8mb4"
```

### Environnement de test

Le fichier `.env.test` configure une base **SQLite en mémoire** et une passphrase JWT dédiée aux tests. Aucune action n'est nécessaire pour lancer la suite de tests.

---

## Fixtures de développement

Après `doctrine:fixtures:load`, des comptes de test sont disponibles :

| Email                        | Mot de passe         | Rôle           |
|------------------------------|----------------------|----------------|
| `manager@taskflow.fr`        | `TaskFlowManager123` | Manager        |
| `sophie.martin@taskflow.fr`  | `TaskFlowManager123` | Manager        |
| `user@taskflow.fr`           | `TaskFlowUser123`    | User           |
| `alice.dupont@taskflow.fr`   | `TaskFlowUser123`    | User           |
| `bob.leroy@taskflow.fr`      | `TaskFlowUser123`    | User           |
| `claire.bernard@taskflow.fr` | `TaskFlowUser123`    | User           |
| `david.petit@taskflow.fr`    | `TaskFlowUser123`    | User           |
| `inactive.user@taskflow.fr`  | `TaskFlowUser123`    | User (inactif) |

Jeu de démo chargé :

- **10 projets** : statuts `en cours`, `terminé`, `annulé` (projets archivés inclus), avec `updatedAt` renseigné
- **Tags** sur tous les projets : métier sur les actifs ; `cloturé` sur les terminés ; `en cours` / `fermé` sur les annulés
- **11 tâches** : états `ouvert`, `en cours`, `terminé` ; priorités variées ; assignés ; échéances ; tags liés ; `updatedAt` renseigné

Utile pour tester manuellement les listes, filtres (`POST /projects/search`, `POST /project/{id}/tasks/search`) et les tags.

---

## Documentation API (Swagger)

- OpenAPI interactive : `/api/doc`
- OpenAPI JSON : `/api/doc.json`

Accès public (pas de JWT requis pour ouvrir la doc). Pour tester les routes protégées depuis l'UI : se connecter via `POST /auth/login`, puis coller le token dans Authorize (Bearer).

---

## Qualité du code

| Commande                | Description                                                                                    |
|-------------------------|------------------------------------------------------------------------------------------------|
| `composer phpstan`      | Analyse statique du code (`src/`, `tests/`), détecte erreurs de typage, appels invalides, etc. |
| `composer phpmd`        | PHP Mess Detector, signale complexité excessive, méthodes trop longues, etc. (`phpmd.xml`)     |
| `composer php-cs-fixer` | Applique automatiquement les règles de style de code (`.php-cs-fixer.dist.php`)                |
| `composer test`         | Lance la suite PHPUnit                                                                         |
| `composer check`        | Pipeline complète : PHPStan => PHPMD => PHP-CS-Fixer en mode `--dry-run` => PHPUnit            |

---

# API

## Authentification

### Connexion

```http
POST /auth/login
Content-Type: application/json

{
  "email": "manager@taskflow.fr",
  "password": "TaskFlowManager123"
}
```

Réponse **200** :

```json
{
  "token": "<jwt>"
}
```

### Utilisation du token

Toutes les autres routes nécessitent un header avec le token JWT :

```
Authorization: Bearer <jwt>
```

### Rôles

| Rôle           | Droits principaux                                                             |
|----------------|-------------------------------------------------------------------------------|
| `ROLE_USER`    | Profil `/me`, projets dont il est owner ou membre, lecture des tâches         |
| `ROLE_MANAGER` | Tout ce que fait un user + gestion des utilisateurs, accès à tous les projets |

---

## Routes API

### Auth

| Méthode | Route         | Auth   | Description                |
|---------|---------------|--------|----------------------------|
| `POST`  | `/auth/login` | Public | Connexion, retourne un JWT |

### Profil courant

| Méthode | Route | Auth | Description                      |
|---------|-------|------|----------------------------------|
| `GET`   | `/me` | JWT  | Profil de l'utilisateur connecté |
| `PATCH` | `/me` | JWT  | Modifier prénom / nom            |

### Utilisateurs

| Méthode  | Route        | Auth                 | Description                  |
|----------|--------------|----------------------|------------------------------|
| `POST`   | `/user`      | JWT + `ROLE_MANAGER` | Créer un utilisateur         |
| `GET`    | `/users`     | JWT + `ROLE_MANAGER` | Lister tous les utilisateurs |
| `GET`    | `/user/{id}` | JWT + `ROLE_MANAGER` | Détail d'un utilisateur      |
| `PATCH`  | `/user/{id}` | JWT + `ROLE_MANAGER` | Modifier un utilisateur      |
| `DELETE` | `/user/{id}` | JWT + `ROLE_MANAGER` | Supprimer un utilisateur     |

### Projets

| Méthode  | Route                   | Auth | Description                                    |
|----------|-------------------------|------|------------------------------------------------|
| `GET`    | `/projects`             | JWT  | Liste des projets accessibles (manager : tous) |
| `POST`   | `/projects/search`      | JWT  | Rechercher / filtrer les projets accessibles   |
| `GET`    | `/project/{id}`         | JWT  | Détail d'un projet (tâches, tags, membres…)    |
| `POST`   | `/project`              | JWT  | Créer un projet (le créateur devient owner)    |
| `PATCH`  | `/project/{id}`         | JWT  | Modifier un projet (owner ou manager)          |
| `POST`   | `/project/{id}/members` | JWT  | Ajouter des membres (owner ou manager)         |
| `DELETE` | `/project/{id}/members` | JWT  | Retirer des membres (owner ou manager)         |

> Il n'existe pas de `DELETE /project/{id}`, l'archivage remplace la suppression.

#### Corps JSON utiles (projets)

**Recherche** (`POST /projects/search`), filtres et tri dans le body :

```json
{
  "filters": {
    "status": ["en cours", "terminé"],
    "archived": false
  },
  "sort": {
    "field": "createdAt",
    "order": "desc"
  }
}
```

- Accessible en lecture pour le owner, un membre ou un manager (même règle que `GET /projects`). Un utilisateur ne voit jamais un projet dont il n'est pas membre (sauf manager).
- Tous les champs sont optionnels. Sans filtre (`{ "filters": [] }` ou `{}` valide), retourne tous les projets accessibles.
- `status` : listes de valeurs `en cours`, `terminé`, `annulé` (ou une seule chaîne, normalisée en liste).
- `archived` : booléen strict (`true` ou `false`) ; filtre sur `isArchived`.
- `sort.field` : `id`, `title`, `status`, `createdAt`, `startAt` ou `endAt` (défaut : `id`).
- `sort.order` : `asc` ou `desc` accessibles(défaut : `desc`).
- Réponse : tableau de projets (comme `GET /projects`).

### Tâches

| Méthode  | Route                        | Auth | Description                                         |
|----------|------------------------------|------|-----------------------------------------------------|
| `GET`    | `/project/{id}/tasks`        | JWT  | Liste des tâches d'un projet                        |
| `POST`   | `/project/{id}/tasks`        | JWT  | Créer une tâche (owner ou manager, projet actif)    |
| `POST`   | `/project/{id}/tasks/search` | JWT  | Rechercher/filtrer les tâches d'un projet           |
| `GET`    | `/task/{id}`                 | JWT  | Détail d'une tâche                                  |
| `PATCH`  | `/task/{id}`                 | JWT  | Modifier une tâche (champs métier, `state`, `tags`) |
| `DELETE` | `/task/{id}`                 | JWT  | Supprimer une tâche (owner ou manager)              |
| `POST`   | `/task/{id}/tags/{tagId}`    | JWT  | Associer un tag à une tâche (owner ou manager)      |
| `DELETE` | `/task/{id}/tags/{tagId}`    | JWT  | Retirer un tag d'une tâche (owner ou manager)       |

#### Corps JSON utiles (tâches)

**Création** (`POST /project/{id}/tasks`) :

```json
{
  "title": "Ma tâche",
  "description": "Optionnel",
  "dueAt": "2026-12-31",
  "priority": "élevée",
  "assignee": 3
}
```

**Modification** (`PATCH /task/{id}`) — champs partiels :

```json
{
  "title": "Nouveau titre",
  "state": "en cours",
  "assignee": null,
  "tags": [1, 3]
}
```

- `dueAt` au format `YYYY-MM-DD` (entrée et sortie), comme `startAt` / `endAt` sur les projets.
- `assignee` (entier) à l'entrée, objet utilisateur ou `null` en sortie.
- Assigner un utilisateur à une tâche, l'ajoute automatiquement comme membre du projet s'il ne l'est pas encore.
- `tags` : remplacement complet de la liste des tags (ids du même projet). Absent = tags inchangés. Si [vide] = retire tous les tags.

**Recherche** (`POST /project/{id}/tasks/search`) — filtres et tri dans le body :

```json
{
  "filters": {
    "state": ["ouvert", "en cours"],
    "priority": ["élevée"],
    "dueBefore": "2026-07-01",
    "tags": [1, 3],
    "assignee": 5
  },
  "sort": {
    "field": "dueAt",
    "order": "asc"
  }
}
```

- Accessible en lecture pour le owner, un membre ou un manager (même règle que `GET /project/{id}/tasks`).
- Tous les champs sont optionnels. Sans filtre (`{ "filters": [] }` ou `{}` valide), retourne toutes les tâches du projet.
- `state` / `priority` : listes de valeurs (ou une seule chaîne, normalisée en liste).
- `dueBefore` : date `YYYY-MM-DD` ; ne garde que les tâches avec une échéance ≤ fin de cette journée.
- `tags` : ids de tags du projet ; une tâche match si elle a, au moins, un de ces tags.
- `assignee` : id utilisateur assigné.
- `sort.field` : `id`, `dueAt`, `priority`, `state`, `createdAt` ou `title` (défaut : `id`).
- `sort.order` : `asc` ou `desc` (défaut : `desc`).
- Réponse : tableau de tâches (comme `GET /project/{id}/tasks`).

### Tags

| Méthode  | Route                     | Auth | Description                                         |
|----------|---------------------------|------|-----------------------------------------------------|
| `GET`    | `/project/{id}/tags`      | JWT  | Liste des tags du projet (owner, membre ou manager) |
| `POST`   | `/project/{id}/tags`      | JWT  | Créer un tag (owner ou manager, projet actif)       |
| `PATCH`  | `/tag/{id}`               | JWT  | Renommer un tag (owner ou manager, projet actif)    |
| `DELETE` | `/tag/{id}`               | JWT  | Supprimer un tag (owner ou manager, projet actif)   |
| `POST`   | `/task/{id}/tags/{tagId}` | JWT  | Associer un tag à une tâche (owner ou manager)      |
| `DELETE` | `/task/{id}/tags/{tagId}` | JWT  | Retirer un tag d'une tâche (owner ou manager)       |

Les tags d'un projet sont aussi inclus dans la réponse de `GET /project/{id}`.
Les tags d'une tâche sont inclus dans les réponses tâche (champ `tags`).

#### Corps JSON utiles (tags)

**Liste** (`GET /project/{id}/tags`) :

```json
[
  {
    "id": 2,
    "label": "backend",
    "projectId": 3,
    "createdAt": "2026-07-14T20:49:28+00:00"
  },
  {
    "id": 1,
    "label": "urgent",
    "projectId": 3,
    "createdAt": "2026-07-14T20:50:01+00:00"
  }
]
```

- Accessible en lecture pour le owner, un membre ou un manager (projet archivé inclus).

**Création** (`POST /project/{id}/tags`) :

```json
{
  "label": "urgent"
}
```

Réponse:

```json
{
  "id": 1,
  "label": "urgent",
  "projectId": 3,
  "createdAt": "2026-07-14T20:49:28+00:00"
}
```

**Modification** (`PATCH /tag/{id}`) :

```json
{
  "label": "prioritaire"
}
```

Réponse:

```json
{
  "id": 1,
  "label": "prioritaire",
  "projectId": 3,
  "createdAt": "2026-07-14T20:49:28+00:00"
}
```

- Seuls le owner du projet ou un manager peuvent renommer un tag.
- Projet archivé => erreur.
- Libellé déjà utilisé sur le même projet => `TAG_ALREADY_EXISTS`.

**Suppression** (`DELETE /tag/{id}`) :

- Réponse (corps vide).
- Seuls le owner du projet ou un manager peuvent supprimer un tag, membre => erreur.
- Projet archivé => erreur.
- Tag introuvable => erreur.

---

## Valeurs métier (enums)

| Enum           | Valeurs                         |
|----------------|---------------------------------|
| Statut projet  | `en cours`, `terminé`, `annulé` |
| État tâche     | `ouvert`, `en cours`, `terminé` |
| Priorité tâche | `basse`, `moyenne`, `élevée`    |

Un projet passe en **archivé** (`isArchived: true`) lorsque son statut devient `terminé` ou `annulé`. Un projet archivé est en **lecture seule** (sauf restauration via `PATCH` avec `status: "en cours"`).
