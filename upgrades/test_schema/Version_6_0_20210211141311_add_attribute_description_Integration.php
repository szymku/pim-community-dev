<?php
declare(strict_types=1);

namespace Pim\Upgrade\Schema\Tests;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Assert;

final class Version_6_0_20210211141311_add_attribute_description_Integration extends TestCase
{
    use ExecuteMigrationTrait;

    private const MIGRATION_LABEL = '_6_0_20210211141311_add_attribute_description';

    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useMinimalCatalog();
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_updates_the_attribute_table(): void
    {
        $connection = $this->get('database_connection');
        $connection->executeQuery('ALTER TABLE pim_catalog_attribute DROP description;');

        $this->reExecuteMigration(self::MIGRATION_LABEL);

        $this->assertTableHasColumns('pim_catalog_attribute', ['description' => 'string']);
    }

    private function assertTableHasColumns(string $tableName, array $expectedColumnsAndTypes): void
    {
        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = $this->get('database_connection')->getSchemaManager();
        $tableColumns = $schemaManager->listTableColumns($tableName);
        foreach ($tableColumns as $actualColumn) {
            $actualColumnsAndTypes[$actualColumn->getName()] = $actualColumn->getType()->getName();
        }

        Assert::assertEquals(array_merge($actualColumnsAndTypes, $expectedColumnsAndTypes), $actualColumnsAndTypes);
    }
}
