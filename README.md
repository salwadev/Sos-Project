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

## Tester à la main

Parcours complet en local, sans SQL : comptes de démonstration, back-office admin, puis une demande client
jusqu'aux professionnels. Prérequis : base démarrée et backend installé (voir [Backend › Installation](#installation-une-fois)).

**1. Créer les comptes de démonstration** (idempotent, refusé en `prod`) :

```bash
cd backend
php bin/console app:seed-demo
```

La commande charge aussi le catalogue (`app:seed-catalog`) et affiche :

| Compte | Téléphone | Détail |
|---|---|---|
| Admin | `+21650000001` | `ROLE_ADMIN` (donc aussi support) |
| Client | `+21650000002` | « Amira » |
| Professionnel | `+21650000011` | Sami B. — plomberie, pièce d'identité approuvée |
| Professionnel | `+21650000012` | Hela K. — plomberie et électricité, identité + qualification approuvées |
| Professionnel | `+21650000013` | Marsa Dépannage SARL — plomberie, identité + registre du commerce approuvés |

Les trois professionnels sont vérifiés, couvrent La Marsa et sont disponibles 12 h : relancer la commande remet
cet état (re-vérifie, prolonge la disponibilité) sans créer de doublon. Les numéros sont fictifs ; en `dev` aucun SMS
ne part.

**2. Lancer l'API, le worker et le back-office** (trois terminaux, depuis la racine) :

```bash
symfony server:start --no-tls --allow-http --port=8000 --dir=backend
```

```bash
php backend/bin/console messenger:consume async scheduler_matching -vv
```

(Sans worker, `php backend/bin/console app:matching:dispatch` fait un passage à la main.)

```bash
npm --prefix backoffice run dev
```

(`npm install` dans `backoffice/` la première fois ; `backoffice/.env.example` donne `VITE_API_ENTRYPOINT`.)

**3. Se connecter** : le code OTP à 6 chiffres est écrit dans `backend/var/sms_outbox.log` (dernière ligne du numéro),
valable 5 min, 3 envois par numéro toutes les 10 min.

```bash
grep '+21650000001' backend/var/sms_outbox.log | tail -1
```

**4. Back-office** — http://localhost:5173, numéro `+21650000001`, « Recevoir un code », puis le code.

- Seuls `ROLE_ADMIN` et `ROLE_SUPPORT` entrent ; un client reçoit « Accès réservé à l'administration et au support ».
- Menu **Vérification des professionnels** (admin) : file filtrée par statut (défaut « En attente », filtre « Vérifié »
  pour voir les trois comptes de démo). La fiche montre métiers, zones et justificatifs (bouton « Ouvrir », fichier
  servi avec le jeton) ; le formulaire « Décision » statue sur les justificatifs et applique l'action
  (approuver / rejeter / suspendre / réactiver) en un seul `PATCH /api/admin/professionals/{id}/verification`, motif obligatoire.
- Le JWT (15 min) est renouvelé tout seul via `/api/auth/refresh` ; un 401 malgré tout ramène à l'écran de connexion.
- Pour remplir la file « En attente » : se connecter par OTP avec un nouveau numéro (ex. `+21650000021`), puis
  `POST /api/professional/profile` et `POST /api/professional/documents` (type `identity`), voir
  [Inscription et vérification des professionnels](#inscription-et-vérification-des-professionnels-61-62-72-144-145).

**5. Une demande de bout en bout** (bash + curl, API sur le port 8000) :

```bash
API=http://localhost:8000/api
login() {  # login <téléphone> → affiche le JWT
  curl -s -X POST $API/auth/request-otp -H 'Content-Type: application/json' -d "{\"phone\":\"$1\"}" > /dev/null
  code=$(grep "$1" backend/var/sms_outbox.log | tail -1 | grep -oE 'est [0-9]{6}' | grep -oE '[0-9]{6}')
  curl -s -X POST $API/auth/verify-otp -H 'Content-Type: application/json' -d "{\"phone\":\"$1\",\"code\":\"$code\"}" | sed -E 's/.*"token":"([^"]+)".*/\1/'
}
CLIENT=$(login +21650000002)
curl -s -X POST $API/requests -H "Authorization: Bearer $CLIENT" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"serviceCode":"plumbing","urgency":"asap","description":"Fuite sous evier de cuisine","addressLine":"Rue des Orangers, La Marsa","latitude":36.88,"longitude":10.33}'
```

Le worker lance la première vague : les trois plombiers sont sollicités (`electricity` ne sollicite que Hela K.).
Un professionnel suspendu puis réactivé dans le back-office a perdu sa disponibilité (la suspension la coupe) :
relancer `app:seed-demo` ou faire `PATCH /api/professional/availability`. Garder ces commandes dans le même terminal
(`$CLIENT` resservira) : au-delà de 3 codes en 10 min pour un numéro, `login` ne renvoie plus de jeton.
Côté professionnel puis client :

```bash
PRO=$(login +21650000012)
curl -s $API/professional/requests -H "Authorization: Bearer $PRO" -H 'Accept: application/json'   # → id de l'offre
curl -s -X POST $API/offers/<offre>/accept -H "Authorization: Bearer $PRO" -H 'Content-Type: application/json' -d '{}'
curl -s -X POST $API/offers/<offre>/accept -H "Authorization: Bearer $CLIENT" -H 'Content-Type: application/json' -d '{}'   # confirmation → interventionId
```

La suite (étapes, devis, clôture, avis) est décrite dans [Intervention, devis et clôture](#intervention-devis-et-clôture-55-57-66-9-161-164).

**Amorcer un vrai admin** (n'importe quel environnement, journalisé `user.role_granted` dans l'audit) :

```bash
php backend/bin/console app:user:grant-role +216XXXXXXXX ROLE_ADMIN
```

Le compte doit s'être connecté une fois par OTP, sinon ajouter `--create`. Rôles possibles : `ROLE_ADMIN`,
`ROLE_SUPPORT`, `ROLE_CLIENT` ; le rôle professionnel passe par `POST /api/professional/profile` puis la vérification.
Le rôle s'applique dès la requête suivante (rôles relus en base) ; se reconnecter pour que le back-office le voie.

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

### Intervention, devis et clôture (§5.5–5.7, §6.6, §9, §16.1, §16.4)

Une fois l'offre confirmée par le client (demande `ACCEPTED`), l'`Intervention` suit son cours via `src/Intervention/`.
Comme pour les offres, chaque décision passe par `RequestLock` puis revérifie l'état relu en base ; un état qui ne
permet pas l'action renvoie un 409 avec un `detail` explicite (`InterventionConflict`). Les droits sont portés par
`InterventionVoter` : `WORK` = professionnel assigné, `CLIENT` = client propriétaire de la demande, `VIEW` = les deux,
l'administration, et le support sauf demandes santé.

| Endpoint | Accès |
|---|---|
| `GET /api/interventions`, `GET /api/interventions/{id}` | Client propriétaire, professionnel assigné (+ admin, support hors santé). La vue (`InterventionView`) révèle au professionnel l'**adresse exacte** (ligne, repère, latitude/longitude) seulement après la confirmation du client et tant que la demande est `ACCEPTED` ou `IN_PROGRESS` (§6.4). La commission n'est montrée qu'au professionnel et à l'admin. |
| `POST /api/interventions/{id}/status` `{status, estimatedArrivalMinutes?}` | Professionnel assigné : `on_the_way` → `on_site` → `working` → `completion_declared`, uniquement vers l'avant (les deux premières étapes peuvent être omises). `estimatedArrivalMinutes` seulement avec `on_the_way` : `estimatedArrivalAt` est une estimation (§5.6). `working` exige un devis accepté et aucun en attente, et applique `start_work` (ACCEPTED → IN_PROGRESS) ; `completion_declared` n'est possible qu'après `working`, sans devis en attente. |
| `POST /api/interventions/{id}/quotes` `{lines: [{label, amountMillimes}], explanation?}` | Professionnel assigné, jusqu'à `working` inclus : nouvelle version (201). `explanation` obligatoire dès la version 2 (§6.6) ; une version encore en attente est marquée `superseded`. |
| `POST /api/quotes/{id}/accept`, `POST /api/quotes/{id}/reject` | Client propriétaire seulement : aucune modification de prix n'est acceptée sans cette action (§16.4). Après un refus, la dernière version acceptée reste celle qui fait foi ; toutes les versions sont conservées avec `proposedAt`/`decidedAt`. |
| `POST /api/interventions/{id}/confirm-completion` | Client propriétaire, après `completion_declared` : `complete` (IN_PROGRESS → COMPLETED) et calcul de la `Commission`, **une seule fois** (vérification sous verrou + contrainte unique ; une seconde confirmation renvoie 409). Un `Payment` en espèces (`cash_direct`, `declared`) est enregistré pour le total du devis accepté. |
| `POST /api/interventions/{id}/payment/confirm-cash` | Professionnel assigné, après la clôture : confirme avoir reçu les espèces (`declared` → `confirmed`), une seule fois (409 sinon), journalisé `payment.cash_confirmed`. Le paiement est visible dans `payment` de l'intervention, pour le client comme pour le pro. |
| `POST /api/interventions/{id}/dispute` `{reason}` | Client propriétaire, pendant les travaux ou après la clôture : `dispute` (→ DISPUTED). Trace horodatée : transition avec motif dans `service_request_transition` et `Complaint` ouverte pour le support (§5.7, §7.4). L'intervention est alors gelée ; une commission déjà calculée est conservée en attendant la résolution. |
| `POST /api/interventions/{id}/reviews` `{rating 1–5, comment?}` | Client propriétaire, intervention terminée et confirmée (demande `COMPLETED`, sans contestation) ; un seul avis par intervention (201, puis 409). Met à jour la note moyenne du professionnel. |

Toutes ces opérations renvoient l'`InterventionView` à jour (statuts et horodatages UTC, devis, `acceptedQuote`,
`pendingQuote`, commission, avis). Si le client annule une demande `ACCEPTED`, l'intervention passe `cancelled`.

**Commission** (§9.4, `CommissionPolicy`) : assiette = total du devis accepté qui fait foi (frais de déplacement compris
s'ils y figurent en ligne) ; taux en points de base `app.commission.default_rate_basis_points` (1000 = 10 %),
surchargeable par code de catégorie avec `app.commission.rate_basis_points_by_category` ; arrondi au millime. Base et
taux restent à valider contractuellement.

**Audit** : `intervention.status_changed`, `quote.proposed`, `quote.accepted`, `quote.rejected`,
`intervention.completed` (assiette, taux, montant), `intervention.disputed` (identifiant de la réclamation, sans le
motif), `review.published`.

### Inscription et vérification des professionnels (§6.1, §6.2, §7.2, §14.4, §14.5)

Tout compte connecté peut devenir professionnel : `POST /api/professional/profile` crée le profil, ajoute
`ROLE_PROFESSIONAL` (les rôles se cumulent, il reste client) et laisse le profil `pending`. Le matching l'ignore tant
que l'administration ne l'a pas approuvé (`ProfessionalEligibility` : « profil non vérifié »).

| Endpoint | Accès |
|---|---|
| `POST /api/professional/profile` `{displayName, legalStatus, services: [{serviceCode, travelFeeMillimes, diagnosticFeeMillimes?}], serviceAreaCodes, baseLatitude, baseLongitude}` | Tout compte connecté. Crée ou remplace le profil (200 dans les deux cas). Métiers et zones absents de la requête sont retirés. Une zone pas encore ouverte peut être couverte. |
| `GET /api/professional/profile` | Professionnel : son profil, ses métiers, zones et justificatifs (métadonnées). |
| `POST /api/professional/documents` (`multipart/form-data` : `file`, `type`, `expiresAt?`) | Professionnel. `type` ∈ `identity`, `qualification`, `company_registration`, `insurance`, `other`. PDF, JPEG ou PNG (type lu dans le contenu via `fileinfo`), 10 Mio au plus, pas déjà expiré. 201, statut `pending`. |
| `GET /api/professional/documents/{id}/file` | Le professionnel propriétaire et l'admin seulement : ni un autre professionnel, ni le support (§16.6, §4.4). Pièce jointe, `Cache-Control: no-store` ; les consultations admin sont journalisées. |
| `GET /api/admin/professionals?verificationStatus=pending&page=1` | Admin. File de vérification, plus anciens d'abord, 30 par page ; inclut le téléphone. |
| `GET /api/admin/professionals/{id}` | Admin. |
| `PATCH /api/admin/professionals/{id}/verification` `{action?, reason, documents?: [{id, status, rejectionReason?, expiresAt?}]}` | Admin, `application/merge-patch+json`. Voir ci-dessous. |

- **Décisions** (`App\Domain\Professional\ProfessionalVerifier`) : `approve` (pending ou rejected → verified),
  `reject` (pending → rejected), `suspend` (verified → suspended, coupe aussi la disponibilité), `reactivate`
  (suspended → verified). Autre cas : 409. `reason` est toujours obligatoire (conservé dans `verificationNote`).
- **Justificatifs** : `approved` ou `rejected` (motif de rejet obligatoire), l'admin peut fixer `expiresAt`. Ils sont
  traités avant l'action, ce qui permet de valider la pièce d'identité et d'approuver le profil d'un seul appel.
- **Conditions** pour `approve`/`reactivate` : pièce d'identité approuvée et valide, plus l'extrait du registre pour
  une entreprise. Les qualifications restent exigées service par service au matching (`Service::requiresQualification`).
- Tout se fait sous verrou de la ligne `professional_profile` et dans une seule transaction avec le journal d'audit
  (`professional.document_reviewed`, `professional.verification_changed` avec action, avant/après et motif) :
  une décision refusée n'écrit rien. L'inscription, les mises à jour et les dépôts sont aussi journalisés.
- **Côté professionnel** : modifier un profil rejeté le renvoie en vérification ; sur un profil vérifié, changer le nom
  affiché ou le statut juridique le renvoie aussi en `pending` (métiers, zones et tarifs non) ; un profil suspendu ne
  peut pas être modifié (409).
- **Stockage** (`App\Storage\DocumentStorage`) : `LocalDocumentStorage` écrit dans `backend/var/private/<env>/documents`
  (`app.documents.storage_dir`), hors de `public/`, sous une clé générée (jamais le nom d'origine), et n'est lu que par
  le contrôleur ci-dessus. En production : brancher un stockage objet privé à liens signés courts (§12.6). Penser à
  `upload_max_filesize`/`post_max_size` ≥ 10 Mio dans `php.ini`.

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
§16.5 ; intervention : parcours complet §16.1 de la soumission à la commission unique, adresse révélée après
confirmation, étapes, devis versionnés §16.4, contestation, avis) sur la base `sos_test`. En test, le transport `async` est `in-memory://` : les messages restent en file et
chaque test les traite quand il veut (`DrivesMatching::consumeAsyncMessages()`), ce qui garde le 201 en `SUBMITTED`.
`OfferAcceptRaceTest` lance deux acceptations dans deux processus PHP (deux connexions PostgreSQL) bloqués sur le même
verrou, puis le relâche : un seul obtient la réservation, l'autre reçoit le 409.
`ProfessionalOnboardingApiTest` et `ProfessionalVerificationApiTest` couvrent l'inscription, les dépôts (type réel,
taille, expiration, stockage privé), les autorisations §16.6, les décisions admin journalisées et le parcours
inscription → vérification → sollicitation par le matching.
`SeedDemoCommandTest` et `GrantRoleCommandTest` vérifient les comptes de démonstration (connexion OTP de l'admin,
IRI JSON-LD de la file admin, sollicitation des trois plombiers, idempotence, refus en `prod`) et l'amorçage d'un admin
journalisé.

Deux suites lancées en même temps sur la même base se vident mutuellement les tables. Pour une exécution isolée
(autre session, autre terminal), donner une base, un cache et un répertoire de justificatifs à part :
`TEST_TOKEN=_moi` (base `sos_test_moi`, à créer et migrer avec les commandes ci-dessus) et `APP_CACHE_DIR=/tmp/sos-cache`.

### Reste à faire côté backend

Envoi des notifications push (les lignes `Notification` `pending` sont créées, aucun fournisseur n'est branché),
élargissement progressif du
rayon (§5.8), mise à jour du taux de réponse, équité de distribution (§8.2), autres endpoints admin (§14.5 :
demandes, catégories, réclamations), stockage objet privé à liens signés et analyse antivirus des justificatifs
(§12.6), justificatifs requis configurables par métier (§7.2), passage automatique des justificatifs échus en
`expired` avec relance du professionnel. Côté intervention : notifications des changements
d'étape et de devis, résolution des contestations par le support (`resolve_dispute`, ajustement de commission) et délai
de contestation (§9.5), canal de contact contrôlé (§5.6), annulation par le professionnel après confirmation.

## Mobile

App Expo (SDK 58, Expo Router) dans `mobile/` : parcours client et professionnel branchés sur l'API, en français et
en arabe (RTL). Code d'accès à l'API : `src/api/client.ts` (fetch, renouvellement du jeton, `ApiError`),
`src/api/endpoints.ts`, `src/api/types.ts` ; session : `src/auth/auth-context.tsx`.

### Lancer l'app contre l'API locale

Prérequis : base, API et worker démarrés, comptes de démo créés (voir [Tester à la main](#tester-à-la-main), étapes 1 et 2).

```bash
cd mobile
npm install
```

L'URL de l'API (préfixe `/api` compris) vient de `EXPO_PUBLIC_API_URL`, à mettre dans `mobile/.env.local`
(modèle : `mobile/.env.example`). Elle est lue au moment du bundle : relancer avec `npx expo start -c` après un changement.

| Cible | `EXPO_PUBLIC_API_URL` |
|---|---|
| Émulateur Android | `http://10.0.2.2:8000/api` — valeur par défaut sur Android sans `.env.local` (10.0.2.2 = la machine hôte) |
| Simulateur iOS, web | `http://localhost:8000/api` — valeur par défaut ailleurs |
| Téléphone physique (Expo Go) | `http://<IP du PC sur le réseau local>:8000/api`, et l'API lancée avec `--listen-ip=0.0.0.0` |

```bash
npx expo start
```

Puis `a` (émulateur Android), `i` (simulateur iOS) ou `w` (navigateur).

**Se connecter** : saisir les 8 chiffres sans `+216` (client `50000002`, professionnels `50000011` à `50000013`), puis
le code écrit dans `backend/var/sms_outbox.log` (en développement, l'écran du code le rappelle). Le lien « Vous êtes
professionnel ? » sous le formulaire ouvre l'espace pro ; on bascule ensuite d'un espace à l'autre depuis l'onglet Profil.
Un compte sans profil professionnel arrive sur l'écran d'inscription (`POST /api/professional/profile`).

### Une demande de bout en bout

Avec deux appareils (ou un émulateur et le navigateur, voir la limite CORS plus bas) :

1. **Client** : Accueil › Maison › Plomberie, description, quartier « La Marsa » et adresse › « Rechercher un professionnel ».
2. Le worker diffuse la demande (≤ 10 s). **Pro** (`50000011`) : la carte « Nouvelle demande à proximité » apparaît sur
   l'accueil › « Proposer et réserver » (frais de déplacement modifiables à la baisse).
3. **Client** : l'écran de recherche affiche la proposition › cocher les frais › « Confirmer ce professionnel ».
4. **Pro** : Missions › la mission › « Je pars » (durée estimée), « Je suis sur place », saisie du devis ligne par ligne.
5. **Client** : Suivi › « Voir le devis » › accepter (ou demander une modification : le pro envoie une version 2, avec explication).
6. **Pro** : « Commencer les travaux » puis « Déclarer les travaux terminés ».
7. **Client** : « Confirmer la fin des travaux » (ou « Signaler un problème ») puis l'avis. **Pro** : la commission apparaît
   dans Relevés.

### Comportement

- **Session** : jeton d'accès (15 min) et jeton de rafraîchissement dans `expo-secure-store` (en mémoire sur le web).
  Au premier 401, un seul `POST /api/auth/refresh` est lancé (rotation) puis la requête est rejouée ; s'il échoue, retour
  à l'écran de connexion. « Se déconnecter » révoque le jeton de rafraîchissement (`/api/auth/logout`).
- **Mises à jour** : en attendant les notifications push, les écrans de suivi (recherche, proposition, intervention,
  sollicitations, missions) relisent l'API toutes les 5 s tant qu'ils sont affichés et que l'app est au premier plan
  (`POLL_INTERVAL_MS` dans `src/constants/config.ts`).
- **Conflits** : un 409 affiche le `detail` du serveur dans un encadré « Action impossible » (demande déjà réservée par
  un autre professionnel, réservation expirée, devis plus en attente…) et l'écran se recharge pour montrer l'état réel.
  Les erreurs de saisie (422) affichent les messages de validation.
- **Idempotence** : `POST /api/requests` envoie un `Idempotency-Key` fixé à l'ouverture du formulaire : renvoyer après
  une coupure réseau ne crée pas de doublon.

### Limites connues

- Pas de géolocalisation ni de vraie carte (`expo-location`, carte : non installés) : le client choisit un quartier
  (`src/constants/places.ts`) dont les coordonnées sont envoyées ; la carte reste une illustration SVG.
- Dépôt des justificatifs (`POST /api/professional/documents`) : depuis la version web seulement ; sur téléphone il
  faudra `expo-document-picker`.
- Version web : le backend n'autorise pas l'en-tête `Idempotency-Key` en CORS (`allow_headers` de
  `backend/config/packages/nelmio_cors.yaml`), donc la création de demande échoue dans le navigateur. Les apps natives
  ne sont pas concernées.
- Ni push, ni canal de contact avec le professionnel (§5.6), ni photos jointes à la demande (pas d'endpoint).
