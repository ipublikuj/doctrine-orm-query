<?php
/**
 * QueryObject.php
 *
 * @copyright      More in license.md
 * @license        https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 * @since          1.0.0
 *
 * @date           10.11.19
 */

declare(strict_types = 1);

namespace IPub\DoctrineOrmQuery;

use Exception;

use Doctrine;
use Doctrine\ORM;

use IPub\DoctrineOrmQuery;
use IPub\DoctrineOrmQuery\Exceptions;

use Nette;

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
 *
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @author         Filip Procházka <filip@prochazka.su>
 */
abstract class QueryObject
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * @var array
	 */
	public $onPostFetch = [];

	/**
	 * @var ORM\Query|NULL
	 */
	private $lastQuery;

	/**
	 * @var DoctrineOrmQuery\ResultSet
	 */
	private $lastResult;

	/**
	 * @param ORM\EntityRepository $repository
	 * @param DoctrineOrmQuery\ResultSet $resultSet
	 * @param ORM\Tools\Pagination\Paginator $paginatedQuery
	 *
	 * @return int
	 *
	 * @throws ORM\NoResultException
	 * @throws ORM\NonUniqueResultException
	 */
	public function count(
		ORM\EntityRepository $repository,
		DoctrineOrmQuery\ResultSet $resultSet = NULL,
		ORM\Tools\Pagination\Paginator $paginatedQuery = NULL
	) : int {
		if ($query = $this->doCreateCountQuery($repository)) {
			return $this->toQuery($query)->getSingleScalarResult();
		}

		if ($paginatedQuery !== NULL) {
			return $paginatedQuery->count();
		}

		$query = $this->getQuery($repository)
			->setFirstResult(NULL)
			->setMaxResults(NULL);

		$paginatedQuery = new ORM\Tools\Pagination\Paginator($query, ($resultSet !== NULL) ? $resultSet->getFetchJoinCollection() : TRUE);
		$paginatedQuery->setUseOutputWalkers(($resultSet !== NULL) ? $resultSet->getUseOutputWalkers() : NULL);

		return $paginatedQuery->count();
	}

	/**
	 * @param ORM\EntityRepository $repository
	 * @param int $hydrationMode
	 *
	 * @return DoctrineOrmQuery\ResultSet|array
	 *
	 * @throws Exceptions\QueryException
	 */
	public function fetch(
		ORM\EntityRepository $repository,
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	) {
		try {
			$query = $this->getQuery($repository)
				->setFirstResult(NULL)
				->setMaxResults(NULL);

			return $hydrationMode !== ORM\AbstractQuery::HYDRATE_OBJECT
				? $query->execute(NULL, $hydrationMode)
				: $this->lastResult;

		} catch (Exception $ex) {
			throw new Exceptions\QueryException(
				$ex,
				$this->getLastQuery(),
				'[' . get_class($this->getLastQuery()) . '] ' . $ex->getMessage()
			);
		}
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return object|NULL
	 *
	 * @throws Exceptions\InvalidStateException
	 * @throws Exceptions\QueryException
	 */
	public function fetchOne(
		ORM\EntityRepository $repository
	) {
		try {
			$query = $this->getQuery($repository)
				->setFirstResult(NULL)
				->setMaxResults(1);

			// getResult has to be called to have consistent result for the postFetch
			// this is the only way to main the INDEX BY value
			$singleResult = $query->getResult();

			if (!$singleResult) {
				return NULL;
			}

		} catch (ORM\NonUniqueResultException $ex) { // this should never happen!
			throw new Exceptions\InvalidStateException("You have to setup your query calling ->setMaxResult(1).", 0, $ex);

		} catch (Exception $ex) {
			throw new Exceptions\QueryException(
				$ex,
				$this->getLastQuery(),
				'[' . get_class($this->getLastQuery()) . '] ' . $ex->getMessage()
			);
		}

		return array_shift($singleResult);
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\Query|ORM\QueryBuilder
	 */
	protected abstract function doCreateQuery(ORM\EntityRepository $repository);

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\Query|ORM\QueryBuilder
	 */
	protected function doCreateCountQuery(
		ORM\EntityRepository $repository
	) {
		throw new Exceptions\InvalidStateException('Method doCreateCountQuery is not defined!');
	}

	/**
	 * @return ORM\Query|NULL
	 *
	 * @internal For Debugging purposes only!
	 */
	public function getLastQuery() : ?ORM\Query
	{
		return $this->lastQuery;
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\Query
	 */
	private function getQuery(ORM\EntityRepository $repository) : ORM\Query
	{
		$query = $this->toQuery($this->doCreateQuery($repository));

		if (
			$this->lastQuery instanceof Doctrine\ORM\Query
			&& $query instanceof Doctrine\ORM\Query
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
	 * @param ORM\QueryBuilder $query
	 *
	 * @return ORM\Query
	 */
	private function toQuery(
		ORM\QueryBuilder $query
	) : ORM\Query {
		return $query->getQuery();
	}
}
