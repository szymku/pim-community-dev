<?php
declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\EndToEnd\Product\MassEdit;

use Akeneo\Test\IntegrationTestsBundle\Launcher\JobLauncher;
use Akeneo\Test\IntegrationTestsBundle\Messenger\AssertEventCountTrait;
use Akeneo\Tool\Component\Batch\Job\BatchStatus;
use AkeneoTest\Pim\Enrichment\EndToEnd\InternalApiTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractMassEditEndToEnd extends InternalApiTestCase
{
    use AssertEventCountTrait;

    protected JobLauncher $jobLauncher;
    protected Connection $dbalConnection;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->jobLauncher = $this->get('akeneo_integration_tests.launcher.job_launcher');
        $this->dbalConnection = $this->get('database_connection');
        $this->authenticate($this->getAdminUser());
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguration()
    {
        return $this->catalog->useFunctionalCatalog('catalog_modeling');
    }

    protected function getAdminUser(): UserInterface
    {
        return self::$container->get('pim_user.repository.user')->findOneByIdentifier('admin');
    }

    protected function executeMassEdit(array $data): Response
    {
        $this->client->request(
            'POST',
            '/rest/mass_edit/',
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
            json_encode($data)
        );
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->launchAndWaitForJob($data['jobInstanceCode']);

        return $response;
    }

    protected function launchAndWaitForJob(string $jobInstanceCode): void
    {
        $this->jobLauncher->launchConsumerOnce();

        $query = <<<SQL
SELECT exec.status, exec.id
FROM akeneo_batch_job_execution as exec
INNER JOIN akeneo_batch_job_instance as instance ON exec.job_instance_id = instance.id AND instance.code = :instance_code   
ORDER BY exec.id DESC
LIMIT 1;
SQL;
        $timeout = 0;
        $isCompleted = false;

        $stmt = $this->dbalConnection->prepare($query);

        while (!$isCompleted) {
            if ($timeout > 30) {
                throw new \RuntimeException(
                    sprintf(
                        'Timeout: last job execution from "%s" job instance is not complete.',
                        $jobInstanceCode
                    )
                );
            }
            $stmt->bindValue('instance_code', $jobInstanceCode);
            $stmt->execute();
            $result = $stmt->fetch();

            $isCompleted = isset($result['status']) && BatchStatus::COMPLETED === (int) $result['status'];

            $timeout++;

            sleep(1);
        }
    }
}
