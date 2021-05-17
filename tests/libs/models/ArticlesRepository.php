<?php declare(strict_types = 1);

namespace Tests\Cases\Models;

use Doctrine\Persistence;
use IPub\DoctrineOrmQuery;
use Nette;
use RuntimeException;
use Tests\Cases\Queries;
use Throwable;

/**
 * @phpstan-template T of ArticleEntity
 */
class ArticlesRepository
{

	use Nette\SmartObject;

	/** @var Persistence\ObjectRepository<T>|null */
	public ?Persistence\ObjectRepository $repository = null;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	public function __construct(Persistence\ManagerRegistry $managerRegistry)
	{
		$this->managerRegistry = $managerRegistry;
	}

	/**
	 * {@inheritDoc}
	 */
	public function findOneBy(Queries\FindArticleQuery $queryObject): ?ArticleEntity
	{
		/** @var ArticleEntity|null $article */
		$article = $queryObject->fetchOne($this->getRepository());

		return $article;
	}

	/**
	 * @return Persistence\ObjectRepository<T>
	 */
	private function getRepository(): Persistence\ObjectRepository
	{
		if ($this->repository === null) {
			$this->repository = $this->managerRegistry->getRepository(ArticleEntity::class);
		}

		return $this->repository;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public function findAllBy(Queries\FindArticleQuery $queryObject): array
	{
		$result = $queryObject->fetch($this->getRepository());

		return is_array($result) ? $result : $result->toArray();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public function getResultSet(
		Queries\FindArticleQuery $queryObject
	): DoctrineOrmQuery\ResultSet {
		$result = $queryObject->fetch($this->getRepository());

		if (!$result instanceof DoctrineOrmQuery\ResultSet) {
			throw new RuntimeException('Result set for given query could not be loaded.');
		}

		return $result;
	}

}
