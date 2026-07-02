# TaskFlow Manager API

API REST back-end - application de gestion de projet.

---

## Prérequis

- **PHP** ≥ 8.2
- **Composer** 2.x
- **MySQL** 8.x (à configurer lors des prochaines étapes)

## Stack

| Composant | Version / choix |
|---|---|
| Framework | Symfony **7.2** |
| PHP | **8.2+** |

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/palepupet/gdu_taskflow_manager_api.git
cd gdu_taskflow_manager_api
```

### 2. Installer les dépendances

```bash
composer install
```

### 2.1. Clés JWT (Lexik JWT)
Après `composer install`, générer la paire de clés:

```bash
php bin/console lexik:jwt:generate-keypair
```

### 3. Configurer l'environnement

Copier les variables locales si besoin (fichier non versionné) :

```bash
cp .env .env.local
```

### 4. Lancer le serveur de développement

Avec le CLI Symfony :

```bash
symfony server:start
```

Ou avec le serveur PHP intégré :

```bash
php -S localhost:8000 -t public
```
