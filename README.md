# SOS — Plateforme multiservices d'urgence (Grand Tunis)

Mise en relation rapide entre clients et professionnels vérifiés : dépannage maison, assistance auto,
orientation santé et autres services urgents. Référence : *Cahier des charges v1.0 — 19 octobre 2026*.

## Structure du monorepo

```
sos/
├── backend/        API Symfony 7.4 LTS + API Platform, Doctrine, PostgreSQL/PostGIS
├── mobile/         App React Native (Expo) + TypeScript — parcours Client et Professionnel
├── backoffice/     Back-office web (React + Vite + API Platform Admin)
├── docker/         Init PostgreSQL (extensions postgis, pgcrypto)
└── docker-compose.yml
```

## Prérequis

| Outil    | Version          | Note                                             |
|----------|------------------|--------------------------------------------------|
| PHP      | 8.2+             | Symfony 7.4 (Symfony 8 exige PHP 8.4)            |
| Composer | 2.x              |                                                  |
| Node     | 20.19+ (22 LTS)  | Requis par Expo récent et Vite 7                 |
| Docker   | 24+              | Base PostGIS                                     |

## Démarrage

```bash
docker compose up -d database
```

Base disponible sur `localhost:5433` (db `sos`, user `sos`). URL Doctrine pour `backend/.env.local` :

```
DATABASE_URL="postgresql://sos:sos_dev_password@127.0.0.1:5433/sos?serverVersion=16&charset=utf8"
```

## Principes d'architecture (rappel du cahier des charges)

- **Le backend est l'autorité** (§4.5) : identité, rôle, propriété de la ressource et état métier sont vérifiés à chaque appel.
- **Machine à états des demandes** (§8.4) contrôlée côté serveur :
  `DRAFT → SUBMITTED → SEARCHING → OFFERED → ACCEPTED → IN_PROGRESS → COMPLETED`,
  avec sorties `CANCELLED`, `EXPIRED`, `DISPUTED`. Toute transition invalide est rejetée.
- **Acceptation atomique** (§6.5, §16.3) : un seul professionnel obtient la réservation.
- **Dates en UTC** côté serveur, affichage en heure de Tunis (§8.6). Identifiants UUID, opérations idempotentes.
- **Données sensibles séparées** (§13.4) : documents d'identité, santé, coordonnées privées, facturation.
- **Santé** (§10, §16.8) : accès visible au SAMU (190, à revérifier avant publication), sans attendre le matching.
- **FR / AR avec RTL** dès la conception (§15.4).

## Backend

### Installation (une fois)

```bash
cd backend
composer install
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate -n
php bin/console app:seed-catalog
```

`backend/.env.local` (non versionné) doit définir `APP_SECRET` : il sert aussi à hacher les codes OTP.
Lancer l'API avec le serveur Symfony (le serveur PHP intégré renvoie 404 sur `/api/docs.jsonld`) :

```bash
symfony server:start --no-tls --allow-http --port=8000 --dir=backend
```

Documentation OpenAPI : http://localhost:8000/api/docs

### Connexion OTP en développement

Aucun fournisseur SMS n'est branché (§20). En `dev`, les SMS sont écrits dans `backend/var/sms_outbox.log`
au lieu d'être envoyés ; hors `dev`/`test`, l'envoi échoue explicitement (`App\Sms\UnconfiguredSmsSender`).

| Endpoint | Rôle |
|---|---|
| `POST /api/auth/request-otp` `{phone}` | Code à 6 chiffres par SMS, valable 5 min. Réponse 202 identique que le numéro existe ou non. 3 envois / 10 min par numéro, 20 par IP. |
| `POST /api/auth/verify-otp` `{phone, code}` | 5 essais par code, usage unique. Crée le compte client au premier passage. Renvoie `token` (JWT 15 min) + `refreshToken`. |
| `POST /api/auth/refresh` `{refreshToken}` | Rotation : l'ancien jeton est consommé ; sa réutilisation révoque toute la session. |
| `POST /api/auth/logout` `{refreshToken}` | Révoque le jeton de rafraîchissement. |

### Demandes

| Endpoint | Accès |
|---|---|
| `GET /api/categories`, `/api/services`, `/api/service-areas` | Public, éléments actifs seulement (§3.2). |
| `POST /api/requests` | Client. Crée et soumet la demande ; zone trouvée par PostGIS si `latitude`/`longitude` ; en-tête `Idempotency-Key` pour rejouer sans doublon. |
| `GET /api/requests`, `GET /api/requests/{id}` | Ses propres demandes ; support : sauf santé ; admin : toutes. |
| `POST /api/requests/{id}/cancel` `{reason}` | Propriétaire ou admin. 409 si l'état ne le permet plus. |

Les transitions sont définies dans `config/packages/workflow.yaml` et appliquées uniquement par
`App\Domain\Request\ServiceRequestStateMachine`, qui trace chaque changement (`service_request_transition`).

### Matching et offres (§8.1–8.3, §5.8)

Le moteur (`src/Matching/`) sollicite les professionnels **par vagues**, en tâche de fond via Symfony Messenger
(transport Doctrine, table `messenger_messages`) et Scheduler. Un seul worker suffit, à lancer à côté de l'API :

```bash
php bin/console messenger:consume async scheduler_matching -vv
```

- **À la soumission** : `POST /api/requests` enregistre la demande puis envoie `DispatchServiceRequest` sur le
  transport `async` ; la réponse 201 renvoie donc `SUBMITTED`, et le worker lance aussitôt la première vague.
  Si l'envoi du message échoue, la demande reste `SUBMITTED` et le passage périodique la reprend.
- **Passage périodique** : `MatchingSchedule` émet `MatchingTick` toutes les `app.matching.tick_seconds` (10 s) sur
  `scheduler_matching` (vagues suivantes, réservations échues, expirations). Un seul worker doit consommer ce
  transport (sans `symfony/lock`, chacun émettrait ses propres passages : sans danger grâce au verrou par demande,
  mais inutile). Les échecs du transport `async` (3 relances) vont dans `failed` : `messenger:failed:show`.
- **En production**, garder le worker en vie avec supervisor (ou systemd), en le recyclant régulièrement :

  ```ini
  [program:sos-messenger]
  command=php /srv/sos/backend/bin/console messenger:consume async scheduler_matching --time-limit=3600 --memory-limit=128M
  autorestart=true
  stopwaitsecs=20
  ```

  Après un déploiement : `php bin/console messenger:stop-workers` (supervisor relance le worker sur le nouveau code).
- **Sans worker** (dépannage, ou en dev sans Messenger) : `php bin/console app:matching:dispatch [--watch=10]` fait
  le même passage, à la main ou depuis cron (`* * * * * php bin/console app:matching:dispatch`) ; les demandes
  soumises attendent alors ce passage.

À chaque passage, pour chaque demande `SUBMITTED`, `SEARCHING` ou `OFFERED` (sous verrou de la ligne) :
`SUBMITTED → SEARCHING` (`start_search`) ; sollicitations sans réponse dans le délai → `expired` ;
réservation non confirmée à temps → `OFFERED → SEARCHING` (`reopen_search`) ; s'il n'y a plus personne en attente,
la vague suivante part, et si plus aucun professionnel n'est éligible la demande passe `EXPIRED` (`expire`).

- **Éligibilité** (`ProfessionalEligibility`, revérifiée au moment d'accepter) : compte actif, profil vérifié,
  service proposé, zone de la demande couverte (`professional_service_area`), `Availability::isAvailableAt(now)`,
  justificatif de qualification valide (`ProfessionalDocument::isValidOn`) si `Service::requiresQualification`,
  ni intervention en cours ni réservation sur une autre demande, et jamais l'auteur de la demande.
- **Classement** (`CandidateFinder`, SQL PostGIS) : distance `ST_Distance` (géographie) entre la demande et la position
  courante du professionnel, à défaut son point de départ ; puis `responseRate`, puis `ratingAverage`.
- **Réglages** (`config/services.yaml`) : `app.matching.wave_size` (3), `app.matching.offer_response_seconds` (90),
  `app.matching.reservation_seconds` (300), `app.matching.tick_seconds` (10), `app.availability.default_minutes` (240)
  et `max_minutes` (720).
- **Notifications** (`App\Notification\OfferNotifier`, §6.4, §12.5) : chaque `RequestOffer` d'une vague crée une
  `Notification` `offer.received`, canal `push`, statut `pending`, enregistrée sous le même verrou que l'offre. Payload :
  `offerId`, `requestReference`, `categoryCode`, `serviceCode`, `serviceName`, `areaName`, `approximateDistanceKm`
  (demi-km supérieur), `urgency`, `travelFeeMillimes`, `respondBefore`. Jamais l'adresse, le repère, la description
  ni les coordonnées. L'envoi push lui-même (lecture des `pending`, `markSent`/`markFailedAttempt`) reste à brancher.

| Endpoint | Accès |
|---|---|
| `GET` / `PATCH /api/professional/availability` `{available, durationMinutes?, latitude?, longitude?}` | Professionnel. `PATCH` en `application/merge-patch+json` ; la disponibilité expire et doit être reconfirmée (§6.3). |
| `GET /api/professional/requests` | Professionnel : ses sollicitations encore ouvertes et sa réservation en cours. Zone et distance arrondie au demi-km seulement : jamais d'adresse, de repère ni de coordonnées (§6.4). |
| `POST /api/offers/{id}/accept` `{travelFeeMillimes?}` | Professionnel sollicité : réserve la demande (`receive_offer`), frais ≤ ceux annoncés. Client propriétaire : confirme (`accept_offer`) et crée l'`Intervention`. |
| `POST /api/offers/{id}/decline` | Professionnel sollicité : décline (vague suivante si plus personne n'est en attente). Client propriétaire : refuse le professionnel (`reopen_search`). |
| `GET /api/requests/{id}/offers` | Propriétaire (ou support/admin comme pour la demande) : offres réservées, confirmées ou refusées, avec nom et note du professionnel. |

**Acceptation exclusive** (§6.5, §16.3) : chaque décision passe par `RequestLock` (transaction +
`SELECT … FOR UPDATE` sur `service_request`, puis relecture de l'état). Le perdant d'une course reçoit
`409 « Demande déjà réservée par un autre professionnel. »` ; une demande expirée ou annulée, un délai de réponse
dépassé ou une réservation échue donnent aussi un 409 explicite (§16.5). Réservation, confirmation et refus client
sont écrits dans le journal d'audit.

### Modèle de données (§13)

Comptes : `User` (rôles multiples), `ClientProfile`, `ClientAddress`, `ProfessionalProfile`, `ProfessionalDocument`, `Availability`.
Catalogue : `ServiceCategory`, `Service`, `ProfessionalService`, `ServiceArea` (contours PostGIS).
Parcours : `ServiceRequest`, `ServiceRequestTransition`, `RequestOffer`, `Intervention`, `Quote` (versionné).
Argent : `Payment`, `Commission` (une seule par intervention). Suivi : `Review`, `Complaint`, `Notification`.
Sécurité : `AuditLog` (chaîné par hash, ajout seul imposé par trigger), `OtpChallenge`, `RefreshToken`.
Montants en millimes (entiers, 1 TND = 1000), dates `timestamptz` en UTC, identifiants UUID v7.

### Tests

```bash
cd backend
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
php bin/phpunit
```

Tests unitaires (téléphone, devis, commission, offres), intégration (machine à états, journal d'audit,
matching PostGIS : éligibilité §16.2, classement, vagues, expiration ; passage planifié et notifications) et
fonctionnels HTTP (auth OTP, catalogue, demandes, diffusion après soumission, disponibilité, offres, autorisations,
§16.5) sur la base `sos_test`. En test, le transport `async` est `in-memory://` : les messages restent en file et
chaque test les traite quand il veut (`DrivesMatching::consumeAsyncMessages()`), ce qui garde le 201 en `SUBMITTED`.
`OfferAcceptRaceTest` lance deux acceptations dans deux processus PHP (deux connexions PostgreSQL) bloqués sur le même
verrou, puis le relâche : un seul obtient la réservation, l'autre reçoit le 409.

### Reste à faire côté backend

Envoi des notifications push (les lignes `Notification` `pending` sont créées, aucun fournisseur n'est branché),
élargissement progressif du
rayon (§5.8), mise à jour du taux de réponse, équité de distribution (§8.2), parcours d'intervention et devis via l'API
(`/api/interventions/{id}/status`, §6.6), profil et justificatifs professionnels (§14.4), endpoints admin (§14.5),
stockage des justificatifs.
