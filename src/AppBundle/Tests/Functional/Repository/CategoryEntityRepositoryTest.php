<?php

namespace AppBundle\Tests\Functional\Repository;

use AppBundle\DataFixtures\ORM\LoadCategoriesData;
use AppBundle\DataFixtures\ORM\LoadFullTreeBookmarksData;
use AppBundle\DataFixtures\ORM\LoadFullTreeCategoriesData;
use AppBundle\DataFixtures\ORM\LoadUsersData;
use AppBundle\Entity\Category;
use AppBundle\Entity\User;
use AppBundle\Repository\CategoryEntityRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\QueryBuilder;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class CategoryEntityRepositoryTest extends WebTestCase
{
    /**
     * @var CategoryEntityRepository
     */
    protected $repository;

    /**
     * @var ReferenceRepository
     */
    protected $fixtureRepository;

    public function setUp()
    {
        $this->fixtureRepository = $this
            ->loadFixtures(array(LoadUsersData::class, LoadCategoriesData::class))
            ->getReferenceRepository();

        $this->repository = $this->getContainer()->get('doctrine')->getRepository(Category::class);
    }

    public function testGet()
    {
        $expected = new Category();
        $expected->setId(LoadCategoriesData::ROOT_ID);
        $expected->setName('root');

        $actual = $this->repository->get(LoadCategoriesData::ROOT_ID);

        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getName(), $actual->getName());
    }

    public function testFindAllOrderedByPosition()
    {
        $actual = $this->repository->findAllAsTree();

        $actualRoot = $actual[0];
        /** @var Category[] $actualChildren */
        $actualChildren = $actualRoot->getChildren();

        $expectedRoot = new Category();
        $expectedRoot->setName('root');
        $expectedRoot->setId(LoadCategoriesData::ROOT_ID);

        $this->assertEquals($expectedRoot->getId(), $actualRoot->getId());
        $this->assertEquals($expectedRoot->getName(), $actualRoot->getName());
        $this->assertCount(2, $actualRoot->getChildren());

        $this->assertCount(0, $actualChildren[0]->getChildren());
    }

    public function testSave()
    {
        $user = $this->fixtureRepository->getReference(LoadUsersData::REFERENCE_2);
        $category = new Category();
        $category->setName('other Name');
        $category->setUser($user);

        $this->repository->save($category);

        $saved = $this->repository->get($category->getId());
        $this->assertEquals($category->getId(), $saved->getId());
        $this->assertEquals($category->getName(), $saved->getName());
        $this->assertEquals($category->getUser()->getId(), $saved->getUser()->getId());
    }

    public function testGetCategoriesForUserQueryBuilder()
    {
        $user = new User();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('c.user = :userId')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('c.name', 'ASC')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())
            ->method('setParameters')
            ->with(['userId' => $user->getId()])
            ->willReturn($queryBuilder);

        /** @var CategoryEntityRepository|\PHPUnit_Framework_MockObject_MockObject $repository */
        $repository = $this->getMockBuilder(CategoryEntityRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

        $actualBuilder = $repository->getCategoriesForUserQueryBuilder($user);
        $this->assertEquals($queryBuilder, $actualBuilder);
    }
}
