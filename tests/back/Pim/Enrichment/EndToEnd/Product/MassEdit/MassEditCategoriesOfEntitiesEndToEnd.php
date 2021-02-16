<?php
declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\EndToEnd\Product\MassEdit;

use Akeneo\Pim\Enrichment\Component\Product\Message\ProductModelUpdated;
use Akeneo\Pim\Enrichment\Component\Product\Message\ProductUpdated;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;

class MassEditCategoriesOfEntitiesEndToEnd extends AbstractMassEditEndToEnd
{
    public function test_adding_a_category_to_entities_produces_event(): void
    {
        $this->executeMassEdit([
            'filters' => [
                [
                    'field' => 'id',
                    'operator' => Operators::IN_LIST,
                    'value' => [
                        '1111111111', // variant product
                        'watch', // product
                        'apollon_yellow', // product model
                    ],
                    'context' => [
                        'locale' => null,
                        'scope' => null,
                    ],
                ]
            ],
            'jobInstanceCode' => 'add_to_category',
            'actions' => [
                'field' => 'categories',
                'value' => ['master_men_pants_jeans'],
            ],
            'itemsCount' => 3,
            'familyVariant' => null,
            'operation' => 'add_to_category',
        ]);

        $this->assertEventCount(2, ProductUpdated::class);
        $this->assertEventCount(1, ProductModelUpdated::class);
    }
}
