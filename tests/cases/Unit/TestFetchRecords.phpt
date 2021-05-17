<?php declare(strict_types = 1);

namespace Tests\Cases;

use Doctrine\Common\Collections\Criteria;
use Tester\Assert;
use Tests\Cases\Models\ArticleEntity;
use Tests\Cases\Queries\FindArticleQuery;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/BaseTestCase.php';

require_once __DIR__ . '/../../libs/models/ArticleEntity.php';
require_once __DIR__ . '/../../libs/models/ArticlesRepository.php';
require_once __DIR__ . '/../../libs/queries/FindArticleQuery.php';

/**
 * @testCase
 */
class TestFetchRecords extends BaseTestCase
{

	/** @var Models\ArticlesRepository<Models\ArticleEntity> */
	private Models\ArticlesRepository $repository;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void
	{
		$this->registerDatabaseSchemaFile(__DIR__ . '/../../sql/dummy.data.sql');

		parent::setUp();

		/** @var Models\ArticlesRepository $repository */
		$repository = $this->container->getByType(Models\ArticlesRepository::class);

		$this->repository = $repository;
	}

	public function testFindOneEntity(): void
	{
		$findArticle = new FindArticleQuery();
		$findArticle->byId(1);

		$article = $this->repository->findOneBy($findArticle);

		Assert::notNull($article);
		Assert::same(1, $article->getId());
	}

	public function testFindMoreEntity(): void
	{
		$findArticle = new FindArticleQuery();
		$findArticle->onlyEnabled();

		$articles = $this->repository->findAllBy($findArticle);

		Assert::count(8, $articles);
	}

	public function testPaging(): void
	{
		$findArticle = new FindArticleQuery();

		$resultSet = $this->repository->getResultSet($findArticle);

		$resultSet->applyPaging(0, 4);

		Assert::same(10, $resultSet->getTotalCount());
		Assert::count(4, $resultSet);
	}

	public function testSorting(): void
	{
		$findArticle = new FindArticleQuery();

		$resultSet = $this->repository->getResultSet($findArticle);

		$resultSet->applySorting(['a.id' => Criteria::DESC]);

		$ids = array_map(function (ArticleEntity $article): int {
			return $article->getId();
		}, $resultSet->toArray());

		Assert::same([10, 9, 8, 7, 6, 5, 4, 3, 2, 1], $ids);

		$resultSet = $this->repository->getResultSet($findArticle);

		$resultSet->applySorting(['a.id' => Criteria::DESC]);
		$resultSet->clearSorting();

		$ids = array_map(function (ArticleEntity $article): int {
			return $article->getId();
		}, $resultSet->toArray());

		Assert::same([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $ids);
	}

}

$test_case = new TestFetchRecords();
$test_case->run();
