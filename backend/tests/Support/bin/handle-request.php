<?php

/*
 * Traite UNE requête HTTP avec un noyau Symfony indépendant (env test), donc sa propre connexion PostgreSQL.
 * Utilisé par OfferAcceptRaceTest pour lancer de vraies acceptations concurrentes.
 *
 * Usage : php handle-request.php METHOD URI JWT [CORPS_JSON]
 * Sortie : {"status": 200, "body": {...}}
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

// La sortie standard ne doit contenir que le JSON du résultat.
ini_set('display_errors', 'stderr');

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv($root.'/.env');

[, $method, $uri, $token] = $argv;
$request = Request::create($uri, $method, server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
], content: $argv[4] ?? '{}');

$kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
$response = $kernel->handle($request);
echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
$kernel->terminate($request, $response);
