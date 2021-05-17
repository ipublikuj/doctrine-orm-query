<?php declare(strict_types = 1);

namespace Tests\Cases\Queries;

use Closure;
use Doctrine\Common;
use Doctrine\ORM;
use Doctrine\Persistence;
use InvalidArgumentException;
use IPub\DoctrineOrmQuery;

/**
 * @phpstan-template T of Models\ArticleEntity
 * @phpstan-extends  DoctrineOrmQuery\QueryObject<T>
 */
class FindArticleQuery extends DoctrineOrmQuery\QueryObject
{

	/** @var Closure[] */
	private array $filter = [];

	/** @var Closure[] */
	private array $select = [];

	/**
	 * @param int $id
	 *
	 * @return void
	 */
	public function byId(int $id): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb) use ($id): void {
			$qb->andWhere('a.id = :id')->setParameter('id', $id);
		};
	}

	/**
	 * @param string $title
	 *
	 * @return void
	 */
	public function byTitle(string $title): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb) use ($title): void {
			$qb->andWhere('a.title = :title')->setParameter('title', $title);
		};
	}

	/**
	 * @return void
	 */
	public function onlyEnabled(): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb): void {
			$qb->andWhere('a.enabled = :enabled')->setParameter('enabled', true);
		};
	}

	/**
	 * @param string $sortBy
	 * @param string $sortDir
	 *
	 * @return void
	 */
	public function sortBy(string $sortBy, string $sortDir = Common\Collections\Criteria::ASC): void
	{
		if (!in_array($sortDir, [Common\Collections\Criteria::ASC, Common\Collections\Criteria::DESC], true)) {
			throw new InvalidArgumentException('Provided sortDir value is not valid.');
		}

		$this->filter[] = function (ORM\QueryBuilder $qb) use ($sortBy, $sortDir): void {
			$qb->addOrderBy($sortBy, $sortDir);
		};
	}

	/**
	 * @param Persistence\ObjectRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param Persistence\ObjectRepository<T> $repository
	 */
	protected function doCreateQuery(Persistence\ObjectRepository $repository): ORM\QueryBuilder
	{
		$qb = $this->createBasicDql($repository);

		foreach ($this->select as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

	/**
	 * @param Persistence\ObjectRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param Persistence\ObjectRepository<T> $repository
	 */
	private function createBasicDql(Persistence\ObjectRepository $repository): ORM\QueryBuilder
	{
		$qb = $repository->createQueryBuilder('a');

		foreach ($this->filter as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

	/**
	 * @param Persistence\ObjectRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param Persistence\ObjectRepository<T> $repository
	 */
	protected function doCreateCountQuery(Persistence\ObjectRepository $repository): ORM\QueryBuilder
	{
		$qb = $this->createBasicDql($repository)->select('COUNT(a.id)');

		foreach ($this->select as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

}
