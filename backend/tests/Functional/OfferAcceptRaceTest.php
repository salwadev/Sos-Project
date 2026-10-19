<?php

namespace App\Tests\Functional;

use App\Entity\RequestOffer;
use App\Entity\ServiceRequest;
use App\Enum\OfferStatus;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * §6.5, §16.3 : deux professionnels acceptent la même demande au même instant, depuis deux processus PHP
 * (donc deux connexions PostgreSQL). Exactement un obtient la réservation ; l'autre reçoit un 409 explicite.
 *
 * Pour rendre la course déterministe, le test verrouille d'abord la ligne de la demande depuis une troisième
 * connexion, attend que les deux requêtes soient bloquées sur ce verrou, puis le relâche : elles se disputent
 * alors réellement le SELECT … FOR UPDATE de RequestLock.
 */
final class OfferAcceptRaceTest extends ApiTestCase
{
    use DrivesMatching;

    private const WAIT_TIMEOUT_SECONDS = 60;

    public function testOnlyOneOfTwoSimultaneousAcceptancesWins(): void
    {
        $em = $this->em();
        $plumbing = Factory::service($em, Factory::category($em, 'home'), 'plumbing');
        $area = Factory::laMarsa($em);
        $first = Factory::availableProfessional($em, $plumbing, $area, name: 'First');
        $second = Factory::availableProfessional($em, $plumbing, $area, name: 'Second');
        $request = $this->submittedRequest($plumbing, $area);
        $this->dispatch($request);
        $offers = $this->offersOf($request);
        self::assertCount(2, $offers);

        $calls = [];
        foreach ($offers as $offer) {
            $calls[] = [
                'POST',
                '/api/offers/'.$offer->getId()->toRfc4122().'/accept',
                $this->tokenFor($offer->getProfessional() === $first ? $first->getUser() : $second->getUser()),
            ];
        }

        // Les deux processus ne doivent pas écrire en même temps les mêmes proxies Doctrine dans var/cache.
        $em->getProxyFactory()->generateProxyClasses($em->getMetadataFactory()->getAllMetadata());

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->executeQuery('SELECT id FROM service_request WHERE id = ? FOR UPDATE', [$request->getId()->toRfc4122()]);

        $processes = array_map(fn (array $call) => $this->startRequest(...$call), $calls);
        try {
            $this->waitUntilBlockedOnLock($processes, 2);
        } finally {
            $holder->rollBack();
            $holder->close();
        }
        $results = array_map(fn (array $process) => $this->finish($process), $processes);

        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame([200, 409], $statuses, 'Une seule réservation : '.json_encode($results, \JSON_UNESCAPED_UNICODE));

        $loser = 409 === $results[0]['status'] ? $results[0] : $results[1];
        self::assertSame('Demande déjà réservée par un autre professionnel.', $loser['body']['detail']);

        $em->clear();
        self::assertSame('OFFERED', $em->find(ServiceRequest::class, $request->getId())->getStatus());
        $reserved = array_filter(
            $em->getRepository(RequestOffer::class)->findBy(['request' => $request]),
            static fn (RequestOffer $o) => OfferStatus::Reserved === $o->getStatus(),
        );
        self::assertCount(1, $reserved);
        self::assertSame(1, array_count_values($this->history($request))['SEARCHING→OFFERED'] ?? 0, 'Une seule transition receive_offer.');
    }

    private function secondConnection(): Connection
    {
        return DriverManager::getConnection($this->em()->getConnection()->getParams());
    }

    /** @return array{handle: resource, out: string, err: string} */
    private function startRequest(string $method, string $uri, string $token): array
    {
        $out = tempnam(sys_get_temp_dir(), 'race-out-');
        $err = tempnam(sys_get_temp_dir(), 'race-err-');
        $handle = proc_open(
            [\PHP_BINARY, \dirname(__DIR__).'/Support/bin/handle-request.php', $method, $uri, $token],
            [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']],
            $pipes,
            \dirname(__DIR__, 2),
            [...getenv(), 'APP_ENV' => 'test'],
        );
        self::assertIsResource($handle);
        fclose($pipes[0]);

        return ['handle' => $handle, 'out' => $out, 'err' => $err];
    }

    /** @param list<array{handle: resource, out: string, err: string}> $processes */
    private function waitUntilBlockedOnLock(array $processes, int $expected): void
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;
        do {
            $waiting = (int) $this->em()->getConnection()->fetchOne(
                "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock'",
            );
            if ($waiting >= $expected) {
                return;
            }
            foreach ($processes as $process) {
                if (!proc_get_status($process['handle'])['running']) {
                    self::fail('Un processus s\'est terminé sans attendre le verrou : '.file_get_contents($process['out']).file_get_contents($process['err']));
                }
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail(\sprintf('Seulement %d/%d requête(s) bloquée(s) sur le verrou après %d s.', $waiting, $expected, self::WAIT_TIMEOUT_SECONDS));
    }

    /**
     * @param array{handle: resource, out: string, err: string} $process
     *
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function finish(array $process): array
    {
        $exitCode = proc_close($process['handle']);
        $output = (string) file_get_contents($process['out']);
        $errors = (string) file_get_contents($process['err']);
        @unlink($process['out']);
        @unlink($process['err']);
        self::assertSame(0, $exitCode, $errors.$output);
        $result = json_decode($output, true);
        self::assertIsArray($result, 'Sortie inattendue du processus : '.$errors.$output);

        return $result;
    }
}
