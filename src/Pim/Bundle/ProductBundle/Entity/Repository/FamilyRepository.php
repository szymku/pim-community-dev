<?php

namespace Pim\Bundle\ProductBundle\Entity\Repository;

use Pim\Bundle\TranslationBundle\Entity\TranslatableInterface;

use Pim\Bundle\ProductBundle\Doctrine\EntityRepository;

/**
 * Repository
 *
 * @author    Gildas Quemener <gildas.quemener@gmail.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FamilyRepository extends EntityRepository
{
    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function buildAllOrderedByLabel()
    {
        $locale = TranslatableInterface::FALLBACK_LOCALE;
        $build = $this->build()
            ->addSelect('translations')
            ->leftJoin('family.translations', 'translations')
            ->leftJoin('family.translations', 'translationOrder', 'with', 'translationOrder.locale = :locale')
            ->setParameter('locale', $locale)
            ->addOrderBy('translationOrder.label');

        return $build;
    }

    /**
     * @param integer $id
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function buildOneWithAttributes($id)
    {
        return $this
            ->buildOne($id)
            ->addSelect('attribute')
            ->leftJoin('family.attributes', 'attribute')
            ->leftJoin('attribute.group', 'group')
            ->addOrderBy('group.sortOrder', 'ASC')
            ->addOrderBy('attribute.sortOrder', 'ASC');
    }
}
