# TaskFlow Manager API

API REST pour la société fictive **TaskFlow**, application de gestion de projets et de tâches.

**Authentification JWT, gestion des utilisateurs, projets (statuts, archivage, membres) et tâches (CRUD, assignation, transitions d'état).**

## Stack technique

| Composant        | Version                                 |
|------------------|-----------------------------------------|
| Langage          | **PHP** ≥ 8.2                           |
| Framework        | **Symfony** 7.2                         |
| Authentification | **Lexik JWT Authentication Bundle** 3.x |
| Tests            | **PHPUnit** 11                          |

---

## Prérequis

- **PHP** ≥ 8.2 avec extensions
- **Composer** 2.x
---

## Installation

### 1) Cloner le dépôt

```bash
git clone https://github.com/palepupet/gdu_taskflow_manager_api.git
cd gdu_taskflow_manager_api
```

### 2) Installer les dépendances

```bash
composer install
```

### 3) Générer les clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

Les clés sont créées dans `config/jwt` (`private.pem`, `public.pem`).

### 4) Configurer l'environnement

```bash
cp .env .env.local
```

Éditer `.env.local` et renseigner au minimum `APP_SECRET` et `DATABASE_URL`.

### 5) Créer la BDD

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 6) Charger les fixtures (Optionnel)

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 7) Lancer le serveur

Avec le CLI Symfony :

```bash
symfony server:start
```

Ou avec le serveur PHP intégré :

```bash
php -S localhost:8000 -t public
```

---

## Configuration

### Variables d'environnement

| Variable         | Description                                    |
|------------------|------------------------------------------------|
| `APP_ENV`        | Environnement (`dev`, `test`, `local`, `prod`) |
| `APP_SECRET`     | Secret Symfony (à définir dans `.env.local`)   |
| `DATABASE_URL`   | URL de connexion Doctrine                      |
| `JWT_SECRET_KEY` | Path vers la clé privée JWT                    |
| `JWT_PUBLIC_KEY` | Path vers la clé publique JWT                  |
| `JWT_PASSPHRASE` | Passphrase de la clé privée (si applicable)    |

### Exemple `DATABASE_URL` (PostgreSQL)

```dotenv
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

### Environnement de test

Le fichier `.env.test` configure une base **SQLite en mémoire** et une passphrase JWT dédiée aux tests. Aucune action n'est nécessaire pour lancer la suite de tests.

---

## Fixtures de développement

Après `doctrine:fixtures:load`, des comptes de test sont disponibles :

| Email | Mot de passe | Rôle |
|---|---|---|
| `manager@taskflow.fr` | `TaskFlowManager123` | Manager |
| `user@taskflow.fr` | `TaskFlowUser123` | User |

D'autres utilisateurs et projets de démonstration sont également créés.

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
| `GET`    | `/project/{id}`         | JWT  | Détail d'un projet                             |
| `POST`   | `/project`              | JWT  | Créer un projet (le créateur devient owner)    |
| `PATCH`  | `/project/{id}`         | JWT  | Modifier un projet (owner ou manager)          |
| `POST`   | `/project/{id}/members` | JWT  | Ajouter des membres (owner ou manager)         |
| `DELETE` | `/project/{id}/members` | JWT  | Retirer des membres (owner ou manager)         |

> Il n'existe pas de `DELETE /project/{id}`, l'archivage remplace la suppression.

### Tâches

| Méthode  | Route                 | Auth | Description                                      |
|----------|-----------------------|------|--------------------------------------------------|
| `GET`    | `/project/{id}/tasks` | JWT  | Liste des tâches d'un projet                     |
| `POST`   | `/project/{id}/tasks` | JWT  | Créer une tâche (owner ou manager, projet actif) |
| `GET`    | `/task/{id}`          | JWT  | Détail d'une tâche                               |
| `PATCH`  | `/task/{id}`          | JWT  | Modifier une tâche (champs métier et/ou `state`) |
| `DELETE` | `/task/{id}`          | JWT  | Supprimer une tâche (owner ou manager)           |

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
  "assignee": null
}
```

- `dueAt` au format `YYYY-MM-DD` (entrée et sortie), comme `startAt` / `endAt` sur les projets.
- `assignee` (entier) à l'entrée, objet utilisateur ou `null` en sortie.
- Assigner un utilisateur à une tâche, l'ajoute automatiquement comme membre du projet s'il ne l'est pas encore.

---

## Valeurs métier (enums)

| Enum           | Valeurs                         |
|----------------|---------------------------------|
| Statut projet  | `en cours`, `terminé`, `annulé` |
| État tâche     | `ouvert`, `en cours`, `terminé` |
| Priorité tâche | `basse`, `moyenne`, `élevée`    |

Un projet passe en **archivé** (`isArchived: true`) lorsque son statut devient `terminé` ou `annulé`. Un projet archivé est en **lecture seule** (sauf restauration via `PATCH` avec `status: "en cours"`).
