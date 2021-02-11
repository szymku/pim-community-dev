<?php declare(strict_types=1);

namespace Pim\Upgrade\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the "description" column on the attribute table.
 */
final class Version_6_0_20210211141311_add_attribute_description extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE pim_catalog_attribute ADD description VARCHAR(255) NOT NULL DEFAULT '';");
        $this->addSql("ALTER TABLE pim_catalog_attribute ALTER COLUMN description DROP DEFAULT;");
    }

    public function down(Schema $schema) : void
    {
        $this->throwIrreversibleMigrationException();
    }
}
