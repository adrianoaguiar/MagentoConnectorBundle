<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Pim\Bundle\CatalogBundle\Entity\Category;
use Doctrine\ORM\EntityRepository;
use Pim\Bundle\MagentoConnectorBundle\Entity\MagentoCategoryMapping;
use Pim\Bundle\MagentoConnectorBundle\Mapper\MappingCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CategoryMappingManagerSpec extends ObjectBehavior
{
    function let(ObjectManager $objectManager, EntityRepository $entityRepository, MappingCollection $mappingCollection)
    {
        $this->beConstructedWith($objectManager, 'Pim\Bundle\MagentoConnectorBundle\Entity\MagentoCategoryMapping');
        $objectManager->getRepository('Pim\Bundle\MagentoConnectorBundle\Entity\MagentoCategoryMapping')
            ->willReturn($entityRepository);

        $mappingCollection->getTarget('default')->willReturn(12);
    }

    function it_gets_id_from_root_category_mapping(Category $category, $mappingCollection)
    {
        $category->getCode()->willReturn('default');

        $this->getIdFromCategory($category, '', $mappingCollection)->shouldReturn(12);
    }

    function it_gets_id_from_category_mapping_stored_in_database(Category $category, $entityRepository, MagentoCategoryMapping $categoryMapping, $mappingCollection)
    {
        $entityRepository->findOneBy(
            array(
                'category'   => $category,
                'magentoUrl' => ''
            )
        )->willReturn($categoryMapping);

        $categoryMapping->getMagentoCategoryId()->willReturn(13);

        $mappingCollection->getTarget('colors')->willReturn('colors');

        $category->getCode()->willReturn('colors');

        $this->getIdFromCategory($category, '', $mappingCollection)->shouldReturn(13);
    }

    function it_returns_null_if_category_is_not_found(Category $category, $entityRepository, $mappingCollection)
    {
        $entityRepository->findOneBy(
            array(
                'category'   => $category,
                'magentoUrl' => ''
            )
        )->willReturn(null);

        $mappingCollection->getTarget('colors')->willReturn('colors');

        $category->getCode()->willReturn('colors');

        $this->getIdFromCategory($category, '', $mappingCollection)->shouldReturn(null);
    }
}
