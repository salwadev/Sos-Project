# Urgence Tunisie (projet SOS) — Document de passation

*Rédigé le 22 octobre 2026, pour reprendre le projet sur un nouveau PC. Mis à jour le 27 octobre 2026.*
Référence fonctionnelle : *Cahier des charges — Application tunisienne multiservices d'urgence*, v1.0 du 19 octobre 2026
(fichier `Cahier_des_charges_application_multiservices_urgence_Tunisie.pdf`, dans `Téléchargements` de l'ancien PC).

---

## 0. À savoir avant de commencer

1. **Tout le code est sur GitHub** depuis le 27/10/2026 : le travail mobile qui était resté dans la Corbeille de
   l'ancien PC a été récupéré et poussé (commit `59e3965`). La Corbeille de l'ancien PC peut être vidée.

2. **Le cahier des charges** (PDF ci-dessus) et les **maquettes** (écrans client 01 et 02, espace pro 03,
   dashboard admin) ne sont pas dans le dépôt : les copier à part.

3. Ce qui n'est pas dans Git et doit être **recréé** sur chaque PC (rien à copier, voir §3) : `backend/.env.local`
   (secret), les clés JWT `backend/config/jwt/*.pem`, `mobile/.env.local` (adresse de l'API), la base de données
   Docker (on la recharge avec les commandes de démo), les `node_modules` et `vendor`.

---

## 1. Le projet en bref

Plateforme de mise en relation rapide entre des clients et des **professionnels vérifiés et réellement disponibles**
pour des urgences du quotidien dans le **Grand Tunis** : dépannage maison (plomberie, électricité, serrurerie…),
assistance auto, orientation santé (dentiste de garde, médecin) et autres services. Promesse : prix transparent,
accord du client avant tout travail, suivi de l'intervention, commission prélevée sur chaque intervention terminée.

| Partie | Techno | Dossier |
|---|---|---|
| API | Symfony 7.4 LTS + API Platform 5, Doctrine ORM 3, PostgreSQL 16 + PostGIS 3.4, JWT (lexik), Messenger + Scheduler | `backend/` |
| App mobile (client + pro) | Expo SDK 58, React Native 0.88, Expo Router, TypeScript, FR/AR avec RTL | `mobile/` |
| Back-office | React 19 + Vite 8 + API Platform Admin (react-admin) | `backoffice/` |
| Base de données | Docker `postgis/postgis:16-3.4`, port **5433** | `docker-compose.yml` |

**Dépôt GitHub** : https://github.com/salwadev/Sos-Project (branche `main`) :
`67d825c` import initial, `203a954` inscription pro + cycle d'intervention, `eb6fc8d` comptes de démo, connexion
back-office, début du branchement mobile, `59e3965` parcours mobile client et pro branchés sur l'API, service
« Ménage à domicile », paiement en espèces.

---

## 2. Où on en est

| Module | État | Détail |
|---|---|---|
| Base de données (§13) | ✅ Fait | Toutes les entités, 2 migrations, PostGIS, journal d'audit chaîné et en ajout seul |
| Connexion OTP + JWT (§5.1, §14.1) | ✅ Fait | Codes SMS hachés, limites d'essais/envois, JWT 15 min, refresh à usage unique |
| Machine à états des demandes (§8.4) | ✅ Fait | `config/packages/workflow.yaml`, transitions tracées, 409 sinon |
| Demandes client (§5.3, §14.3) | ✅ Fait | Création idempotente, zone PostGIS, lecture limitée au propriétaire, annulation |
| Matching par vagues (§8.1–8.3, §5.8) | ✅ Fait | Éligibilité, classement par distance PostGIS, vagues de 3, expirations, worker Messenger |
| Acceptation exclusive (§6.5, §16.3) | ✅ Fait | Verrou `SELECT … FOR UPDATE`, test de course à deux processus |
| Intervention, devis, clôture (§5.5–5.7, §6.6, §9) | ✅ Fait | Étapes, devis versionnés, commission unique, contestation, avis |
| Inscription et vérification pro (§6.1, §6.2, §7.2) | ✅ Fait | Profil, dépôt de justificatifs, décisions admin journalisées |
| Comptes de démo / rôle admin | ✅ Fait | `app:seed-demo`, `app:user:grant-role` |
| Service « Ménage à domicile » | ✅ Fait | Code `housekeeping`, catégorie Maison. Aucun pro de démo ne le propose encore |
| Paiement en espèces (§9.3) | ✅ Fait | `Payment` `cash_direct` déclaré à la confirmation de fin, confirmé par le pro (« J'ai reçu le paiement en espèces ») ; visible client, pro et Relevés |
| Tests backend | ✅ 127 tests verts | Unitaires, intégration, HTTP (dernier passage le 27/10/2026) |
| App mobile — écrans | ✅ Fait | Maquettes 01, 02 (client) et 03 (pro) reproduites, FR/AR |
| App mobile — branchement API | ✅ Fait, 🟡 non testé | Tout le parcours client et pro est branché ; TypeScript et lint OK, **jamais testé sur téléphone** |
| Back-office | 🟡 Partiel | Connexion OTP admin/support + vérification des professionnels. Le dashboard « Vue d'ensemble » de la maquette n'est pas fait |
| Notifications push | ❌ À faire | Les lignes `Notification` sont créées, aucun envoi |
| Fournisseur SMS réel | ❌ À faire | En dev les SMS vont dans `backend/var/sms_outbox.log` |
| Vraie carte / géolocalisation | ❌ À faire | Carte SVG illustrative, quartier choisi dans une liste |
| Déploiement (staging/prod) | ❌ À faire | Rien d'hébergé |

**Dernière étape terminée** : branchement complet de l'app mobile, service ménage et paiement en espèces, tout
poussé sur GitHub. **Prochaine étape logique** : tester le parcours complet sur téléphone, corriger le CORS (§7),
puis le dashboard admin.

---

## 3. Installer sur le nouveau PC (Windows)

### 3.1 Logiciels

| Outil | Version | Remarque |
|---|---|---|
| Git | récent | Au premier `git push`, Git Credential Manager ouvre le navigateur pour se connecter à GitHub (compte `salwadev`) |
| PHP | 8.2+ (8.4 OK) | `winget install PHP.PHP.8.4`, puis voir ci-dessous pour `php.ini` |
| Composer | 2.x | Pas sur winget : `scoop install composer` |
| Symfony CLI | 5.x | Pas sur winget : `scoop install symfony-cli`. Pour `symfony server:start` |
| Node.js | **22 LTS** (≥ 20.19, 24 OK) | Expo SDK 58 et Vite 8 refusent Node 18 |
| Docker Desktop | récent | Pour PostGIS |
| Expo Go ou émulateur Android | | Pour tester l'app sur téléphone |

**php.ini** : PHP installé par winget n'en a pas. À côté de `php.exe` (`php --ini` donne le dossier), copier
`php.ini-development` en `php.ini`, puis : décommenter `extension_dir = "ext"` ; activer `extension=pdo_pgsql`,
`pgsql`, `intl`, `fileinfo`, `mbstring`, `openssl`, `sodium`, `curl`, `zip` ; `upload_max_filesize = 10M`,
`post_max_size = 12M`, `memory_limit = 512M`. Vérifier avec `php -m`.

**Scoop** (pour Composer et Symfony CLI) : dans PowerShell, `irm get.scoop.sh | iex`. Si PowerShell refuse ensuite
la commande `scoop`, lancer une fois `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.
Après toutes ces installations, **ouvrir un nouveau terminal** (le PATH a changé).

### 3.2 Récupérer le code

```bash
git clone https://github.com/salwadev/Sos-Project.git sos
cd sos
```

### 3.3 Base de données

```bash
docker compose up -d database
```

PostGIS sur `localhost:5433`, base `sos`, utilisateur `sos`, mot de passe de dev `sos_dev_password`
(valeurs de `docker-compose.yml`, surchargeables via un `.env` à la racine, modèle `.env.example`).

### 3.4 Backend

```bash
cd backend
composer install
```

Créer `backend/.env.local` (jamais commité) avec un secret aléatoire — il sert aussi à hacher les codes OTP :

```bash
php -r "echo 'APP_SECRET='.bin2hex(random_bytes(32)).PHP_EOL;" > .env.local
```

Puis :

```bash
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate -n
php bin/console app:seed-demo
```

`app:seed-demo` charge aussi le catalogue (catégories, services, zones) et crée les comptes de démo (§4).

### 3.5 Lancer (un terminal par commande, depuis la racine `sos/`)

```bash
symfony server:start --no-tls --allow-http --port=8000 --dir=backend
```

```bash
php backend/bin/console messenger:consume async scheduler_matching -vv
```

```bash
npm --prefix backoffice install
npm --prefix backoffice run dev
```

```bash
cd mobile
npm install
npx expo start
```

- API + doc OpenAPI : http://localhost:8000/api/docs
- Back-office : http://localhost:5173
- Mobile : touche `a` (émulateur Android), `w` (navigateur), ou QR code dans Expo Go.
  URL de l'API dans `mobile/.env.local` (modèle `mobile/.env.example`) : émulateur Android `http://10.0.2.2:8000/api`,
  téléphone physique `http://<IP du PC>:8000/api` (lancer alors l'API avec `--listen-ip=0.0.0.0`).
  Relancer `npx expo start -c` après tout changement de cette variable.

### 3.6 Tests backend

```bash
cd backend
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
php bin/phpunit
```

---

## 4. Comptes de démo et test manuel

Code OTP : lire la dernière ligne du numéro dans `backend/var/sms_outbox.log` (valable 5 min, 3 envois / 10 min).

| Compte | Téléphone | Rôle |
|---|---|---|
| Admin | `+21650000001` | `ROLE_ADMIN` (+ support) — back-office |
| Client | `+21650000002` | « Amira » |
| Pro | `+21650000011` | Sami B. — plomberie |
| Pro | `+21650000012` | Hela K. — plomberie + électricité |
| Pro | `+21650000013` | Marsa Dépannage SARL — plomberie |

Les pros sont vérifiés, couvrent La Marsa et sont disponibles 12 h (relancer `app:seed-demo` pour les remettre
dans cet état). Dans l'app, saisir les 8 chiffres sans `+216`.

**Parcours complet dans l'app** : client › Maison › Plomberie › quartier La Marsa › « Rechercher un professionnel » ;
le worker sollicite les 3 plombiers (≤ 10 s) ; pro › « Proposer et réserver » ; client › « Confirmer ce professionnel » ;
pro › Missions › « Je pars », « Je suis sur place », devis ; client › accepter le devis ; pro › « Commencer les travaux »,
« Déclarer les travaux terminés » ; client › « Confirmer la fin » puis avis ; pro › la commission apparaît dans Relevés.

Créer un vrai admin : `php backend/bin/console app:user:grant-role +216XXXXXXXX ROLE_ADMIN` (ajouter `--create`
si le numéro ne s'est jamais connecté).

---

## 5. Architecture

### 5.1 Principes (repris du cahier des charges)

- **Le backend est l'autorité** (§4.5) : chaque endpoint vérifie identité, rôle, propriété et état métier
  (voters + machine à états). Masquer un écran côté mobile n'est jamais une protection.
- **Dates en UTC** côté serveur (`timestamptz`), affichage heure de Tunis côté client.
- **Montants en millimes** (entiers ; 1 TND = 1 000), devise `TND`.
- **Identifiants UUID v7**, références lisibles `UT-XXXXXX` pour les demandes.
- **Données sensibles séparées** : justificatifs (stockage privé), santé (support sans accès par défaut),
  adresse exacte révélée au pro seulement après confirmation du client.
- **Journal d'audit** (`AuditLog`) : chaque entrée est chaînée par hash à la précédente, et un trigger PostgreSQL
  refuse toute modification ou suppression.

### 5.2 Machine à états d'une demande (§8.4)

`DRAFT → SUBMITTED → SEARCHING → OFFERED → ACCEPTED → IN_PROGRESS → COMPLETED`, sorties `CANCELLED`, `EXPIRED`,
`DISPUTED`. Transitions : `submit`, `start_search`, `receive_offer`, `reopen_search`, `accept_offer`, `start_work`,
`complete`, `dispute`, `resolve_dispute`, `cancel`, `expire` — définies dans `backend/config/packages/workflow.yaml`
et appliquées uniquement par `App\Domain\Request\ServiceRequestStateMachine` (historique dans
`service_request_transition`). Toute transition non prévue → **409**.

### 5.3 Organisation du backend (`backend/src/`)

| Dossier | Contenu |
|---|---|
| `Entity/`, `Enum/`, `Repository/` | Modèle §13 (User, profils, catalogue, demandes, offres, interventions, devis, paiements, commissions, avis, réclamations, notifications, audit, OTP, refresh tokens) |
| `Auth/`, `Sms/` | OTP (HMAC), émission JWT/refresh, envoi SMS (outbox en dev, mémoire en test, aucun fournisseur en prod) |
| `Domain/` | Machine à états, vérification des pros, utilitaires (Clock, GeoPoint) |
| `Matching/` | Éligibilité, `CandidateFinder` (SQL PostGIS), vagues, `RequestLock`, messages Messenger, planification |
| `Intervention/` | Étapes, devis, clôture, commission (`CommissionPolicy`), voter |
| `Notification/` | `OfferNotifier` (crée les notifications, sans envoi réel) |
| `Storage/` | Stockage local privé des justificatifs (`var/private/<env>/documents`) |
| `ApiResource/`, `State/` | Endpoints API Platform (DTO d'entrée + processors) |
| `Security/`, `Doctrine/` | Voters, user checker, filtres de collections |
| `Audit/` | `AuditLogger` + vérification de la chaîne |
| `Command/` | `app:seed-catalog`, `app:seed-demo`, `app:user:grant-role`, `app:matching:dispatch` |

Réglages métier dans `backend/config/services.yaml` : taille de vague (3), délai de réponse pro (90 s),
réservation (300 s), passage du planificateur (10 s), disponibilité par défaut (240 min, max 720), taux de commission
(1 000 points de base = 10 %, surchargeable par catégorie).

### 5.4 Endpoints principaux (doc complète : `/api/docs`)

- **Auth** : `POST /api/auth/request-otp`, `verify-otp`, `refresh`, `logout`
- **Catalogue (public)** : `GET /api/categories`, `/api/services`, `/api/service-areas`
- **Client** : `POST/GET /api/requests`, `GET /api/requests/{id}`, `POST /api/requests/{id}/cancel`,
  `GET /api/requests/{id}/offers`, `POST /api/offers/{id}/accept|decline`
- **Pro** : `POST/GET /api/professional/profile`, `POST /api/professional/documents`,
  `GET/PATCH /api/professional/availability`, `GET /api/professional/requests`, `POST /api/offers/{id}/accept|decline`
- **Intervention** : `GET /api/interventions[/{id}]`, `POST /api/interventions/{id}/status`, `…/quotes`,
  `POST /api/quotes/{id}/accept|reject`, `POST /api/interventions/{id}/confirm-completion`, `…/dispute`, `…/reviews`,
  `POST /api/interventions/{id}/payment/confirm-cash` (pro assigné, après la clôture)
- **Admin** : `GET /api/admin/professionals[/{id}]`, `PATCH /api/admin/professionals/{id}/verification`

### 5.5 Mobile (`mobile/src/`)

- `app/` : routes Expo Router — `index` (bienvenue), `verify-otp`, `client/(tabs)` (Accueil, Demandes, Aide, Profil)
  + `new-request`, `search`, `proposal`, `tracking`, `quote`, `review` ; `pro/(tabs)` (Accueil, Missions, Relevés,
  Profil) + `request`, `mission`, `onboarding`.
- `api/` (client fetch avec renouvellement du jeton, endpoints, types), `auth/` (session, `expo-secure-store`),
  `i18n/` (`fr.ts`, `ar.ts` — traduction arabe à faire relire), `components/` (UI kit : Button, Card, Badge, Avatar,
  Timeline, FakeMap SVG…), `constants/theme.ts` (teal `#14A098`, navy `#123B4E`).
- En attendant les push, les écrans de suivi relisent l'API toutes les 5 s.
- Limites : pas de géolocalisation ni de vraie carte ; dépôt de justificatifs depuis la version web seulement ;
  en version web la création de demande échoue car le CORS backend n'autorise pas l'en-tête `Idempotency-Key`
  (à ajouter dans `allow_headers` de `backend/config/packages/nelmio_cors.yaml`).

### 5.6 Back-office (`backoffice/src/`)

Connexion OTP réservée à `ROLE_ADMIN`/`ROLE_SUPPORT` (`auth.ts`, `LoginPage.tsx`), JWT renouvelé automatiquement,
écran « Vérification des professionnels » (`professionals.tsx`) avec décision approuver/rejeter/suspendre/réactiver.
`Dashboard.tsx` est minimal : le tableau de bord de la maquette (KPI, carte de couverture, vérifications prioritaires,
demandes récentes) reste à faire.

---

## 6. Pièges connus (gagnent du temps)

- **API** : lancer avec `symfony server:start`, pas `php -S` (le serveur intégré renvoie 404 sur `/api/docs.jsonld`).
- **php.ini** : sans `extension=pdo_pgsql`, Doctrine échoue avec « could not find driver ».
- **Expo** : ne pas lancer avec `CI=1` (désactive la surveillance des fichiers) ; après avoir déplacé/renommé un
  dossier de routes ou installé un paquet, relancer `npx expo start -c`.
- **PowerShell** : `npm create vite@latest x -- --template react-ts` perd le `--` ; utiliser `npx create-vite …` ou bash.
- **PowerShell 5** : `Set-Content -Encoding utf8` ajoute un BOM qui casse les fichiers `.env` de Symfony.
- **Tests** : deux suites lancées en même temps sur la même base se vident les tables ; isoler avec
  `TEST_TOKEN=_moi` (base `sos_test_moi`) et `APP_CACHE_DIR`.
- **Messenger** : sans worker, les demandes restent `SUBMITTED` ; faire `php bin/console app:matching:dispatch`.
- **Pro suspendu puis réactivé** : il a perdu sa disponibilité ; relancer `app:seed-demo` ou `PATCH /api/professional/availability`.

---

## 7. Reste à faire (par priorité)

1. **Tester le parcours complet sur un vrai téléphone** (Android + iOS), en arabe aussi (`I18nManager` /
   redémarrage), y compris le paiement en espèces. Ajouter le ménage à un pro de démo dans `app:seed-demo` pour
   pouvoir tester ce service.
2. **CORS** : ajouter `Idempotency-Key` aux en-têtes autorisés (version web de l'app).
3. **Dashboard admin** « Vue d'ensemble » (maquette) + autres écrans admin : demandes, catégories/zones/paramètres,
   réclamations et résolution des contestations (`resolve_dispute`, ajustement de commission), journal d'audit (§7).
4. **Notifications push** (expo-notifications + FCM/APNs) : envoyer les `Notification` `pending`, gérer les jetons
   d'appareil ; repli SMS (§12.5, §19).
5. **Fournisseur SMS** tunisien pour les OTP (implémenter `App\Sms\SmsSender`).
6. **Carte et géolocalisation** (expo-location + fournisseur de carte), élargissement progressif du rayon (§5.8),
   équité de distribution et taux de réponse (§8.2).
7. **Stockage objet privé** à liens signés + analyse antivirus des justificatifs (§12.6) ; justificatifs requis par
   métier, expiration automatique.
8. **Mobile** : `expo-document-picker` pour les justificatifs sur téléphone, photos jointes à la demande (endpoint à
   créer), canal de contact contrôlé client ↔ pro (§5.6).
9. **Déploiement** : environnements dev/staging/prod, hébergement, secrets, sauvegardes, supervisor pour le worker
   Messenger, CI (§12.8, §15.7).

### Décisions qui ne sont pas techniques (§20)

Nom de marque définitif (les maquettes disent « Urgence Tunisie »), entité juridique, taux et assiette de commission,
modalités de paiement, règles d'annulation et pénalités, niveau de vérification par métier, fournisseurs
(SMS, push, carte), hébergement, politique de conservation des données, démarches **INPDP**, textes juridiques
(CGU, confidentialité), organisation du support. Revérifier le numéro du SAMU (190) avant publication.

---

## 8. Note pour reprendre avec Claude Code

Sur le nouveau PC, ouvrir Claude Code dans le dossier `sos/` et lui donner ce document (ou le README).
Préférences connues : ne jamais pousser sur Git sans demande explicite ; demander avant toute installation ou
désinstallation de paquet. La mémoire de Claude est locale à l'ancien PC (`C:\Users\SalwaHilali\.claude\`) :
elle ne suit pas, d'où ce document.
