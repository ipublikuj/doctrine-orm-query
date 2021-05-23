<?php declare(strict_types = 1);

/**
 * QueryObject.php
 *
 * @copyright      More in LICENSE.md
 * @license        https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 * @since          0.0.1
 *
 * @date           10.11.19
 */

namespace IPub\DoctrineOrmQuery;

use Closure;
use Doctrine;
use Doctrine\ORM;
use Nette;
use Throwable;

/**
 * Purpose of this class is to be inherited and have implemented doCreateQuery() method,
 * which constructs DQL from your constraints and filters.
 *
 * QueryObject inheritors are great when you're printing a data to the user,
 * they may be used in service layer but that's not really suggested.
 *
 * Don't be afraid to use them in presenters
 *
 * <code>
 * $articlesQuery = new ArticlesQuery();
 * $this->template->articles = $articlesQuery->fetch($this->articlesRepository));
 * </code>
 *
 * or in more complex ways
 *
 * <code>
 * $productsQuery = new ProductsQuery;
 * $productsQuery
 *    ->setColor('green')
 *    ->setMaxDeliveryPrice(100)
 *    ->setMaxDeliveryMinutes(75);
 *
 * $productsQuery->size = 'big';
 *
 * $this->template->products = $productsQuery->fetch($this->productsRepository);
 * </code>
 *
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @author         Filip Procházka <filip@prochazka.su>
 *
 * @phpstan-template TEntityClass of object
 */
abstract class QueryObject
{

	use Nette\SmartObject;

	/** @var Closure[] */
	public array $onPostFetch = [];

	/** @var ORM\Query|null */
	private ?ORM\Query $lastQuery = null;

	/**
	 * @var ResultSet
	 *
	 * @phpstan-var ResultSet<TEntityClass>
	 */
	private ResultSet $lastResult;

	/**
	 * @param ORM\EntityRepository $repository
	 * @param ResultSet|null $resultSet
	 * @param ORM\Tools\Pagination\Paginator|null $paginatedQuery
	 *
	 * @return int
	 *
	 * @throws ORM\NoResultException
	 * @throws ORM\NonUniqueResultException
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 * @phpstan-param ResultSet<TEntityClass>|null $resultSet
	 * @phpstan-param ORM\Tools\Pagination\Paginator<TEntityClass>|null $paginatedQuery
	 */
	public function count(
		ORM\EntityRepository $repository,
		?ResultSet $resultSet = null,
		?ORM\Tools\Pagination\Paginator $paginatedQuery = null
	): int {
		try {
			$query = $this->doCreateCountQuery($repository);

			return (int) $this->toQuery($query)->getSingleScalarResult();

		} catch (Exceptions\NotImplementedException $ex) {
			// Nothing to do here
		}

		if ($paginatedQuery !== null) {
			return $paginatedQuery->count();
		}

		$query = $this->getQuery($repository)
			->setFirstResult(null)
			->setMaxResults(null);

		$paginatedQuery = new ORM\Tools\Pagination\Paginator($query, ($resultSet !== null) ? $resultSet->getFetchJoinCollection() : true);
		$paginatedQuery->setUseOutputWalkers(($resultSet !== null) ? $resultSet->getUseOutputWalkers() : null);

		return $paginatedQuery->count();
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.InvalidNoReturn
	protected function doCreateCountQuery(
		ORM\EntityRepository $repository
	): ORM\QueryBuilder {
		throw new Exceptions\NotImplementedException('Method doCreateCountQuery is not implemented');
	}

	/**
	 * @param ORM\QueryBuilder $query
	 *
	 * @return ORM\Query
	 */
	private function toQuery(
		ORM\QueryBuilder $query
	): ORM\Query {
		return $query->getQuery();
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\Query
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 */
	private function getQuery(ORM\EntityRepository $repository): ORM\Query
	{
		$query = $this->toQuery($this->doCreateQuery($repository));

		if (
			$this->lastQuery instanceof Doctrine\ORM\Query
			&& $this->lastQuery->getDQL() === $query->getDQL()
		) {
			$query = $this->lastQuery;
		}

		if ($this->lastQuery !== $query) {
			$this->lastResult = new ResultSet($query, $this, $repository);
		}

		return $this->lastQuery = $query;
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 */
	abstract protected function doCreateQuery(ORM\EntityRepository $repository): ORM\QueryBuilder;

	/**
	 * @param ORM\EntityRepository $repository
	 * @param int $hydrationMode
	 *
	 * @return ResultSet|mixed[]
	 *
	 * @throws Exceptions\QueryException
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 *
	 * @phpstan-return ResultSet<TEntityClass>|mixed[]
	 */
	public function fetch(
		ORM\EntityRepository $repository,
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	) {
		try {
			$query = $this->getQuery($repository)
				->setFirstResult(null)
				->setMaxResults(null);

			return $hydrationMode !== ORM\AbstractQuery::HYDRATE_OBJECT
				? $query->execute(null, $hydrationMode)
				: $this->lastResult;

		} catch (Throwable $ex) {
			throw new Exceptions\QueryException(
				$ex,
				$this->getLastQuery(),
				'[' . ($this->getLastQuery() === null ? 'unknown' : get_class($this->getLastQuery())) . '] ' . $ex->getMessage()
			);
		}
	}

	/**
	 * @return ORM\Query|null
	 *
	 * @internal For Debugging purposes only!
	 */
	public function getLastQuery(): ?ORM\Query
	{
		return $this->lastQuery;
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return object|null
	 *
	 * @throws Exceptions\InvalidStateException
	 * @throws Exceptions\QueryException
	 *
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 *
	 * @phpstan-return  TEntityClass
	 */
	public function fetchOne(
		ORM\EntityRepository $repository
	): ?object {
		try {
			$query = $this->getQuery($repository)
				->setFirstResult(null)
				->setMaxResults(1);

			// getResult has to be called to have consistent result for the postFetch
			// this is the only way to main the INDEX BY value
			$singleResult = $query->getResult();

			if ($singleResult === null) {
				return null;
			}

		} catch (ORM\NonUniqueResultException $ex) { // this should never happen!
			throw new Exceptions\InvalidStateException('You have to setup your query calling ->setMaxResult(1).', 0, $ex);

		} catch (Throwable $ex) {
			throw new Exceptions\QueryException(
				$ex,
				$this->getLastQuery(),
				'[' . ($this->getLastQuery() === null ? 'unknown' : get_class($this->getLastQuery())) . '] ' . $ex->getMessage()
			);
		}

		return array_shift($singleResult);
	}

}
