<?php
/**
 * ResultSet.php
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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Exception;

use Doctrine\ORM;

use Nette\Utils;

use IPub\DoctrineOrmQuery;
use IPub\DoctrineOrmQuery\Exceptions;

/**
 * ResultSet accepts a Query that it can then paginate and count the results for you
 *
 * <code>
 * public function renderDefault()
 * {
 *    $articlesQuery = new ArticlesQuery();
 *    $articles = $articlesQuery->fetch($this->articlesRepository));
 *    $articles->applyPaginator($this['vp']->paginator);
 *    $this->template->articles = $articles;
 * }
 *
 * protected function createComponentVp()
 * {
 *    return new VisualPaginator;
 * }
 * </code>.
 *
 * It automatically counts the query, passes the count of results to paginator
 * and then reads the offset from paginator and applies it to the query so you get the correct results.
 *
 *
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @author         Filip Procházka <filip@prochazka.su>
 */
final class ResultSet implements Countable, IteratorAggregate
{
	/**
	 * @var int|NULL
	 */
	private $totalCount;

	/**
	 * @var ORM\AbstractQuery|ORM\Query|ORM\NativeQuery
	 */
	private $query;

	/**
	 * @var DoctrineOrmQuery\QueryObject
	 */
	private $queryObject;

	/**
	 * @var ORM\EntityRepository
	 */
	private $repository;

	/**
	 * @var bool
	 */
	private $fetchJoinCollection = TRUE;

	/**
	 * @var bool|NULL
	 */
	private $useOutputWalkers;

	/**
	 * @var ArrayIterator|NULL
	 */
	private $iterator;

	/**
	 * @var bool
	 */
	private $frozen = FALSE;

	/**
	 * @param ORM\AbstractQuery $query
	 * @param QueryObject $queryObject
	 * @param ORM\EntityRepository $repository
	 */
	public function __construct(
		ORM\AbstractQuery $query,
		DoctrineOrmQuery\QueryObject $queryObject,
		ORM\EntityRepository $repository
	) {
		$this->query = $query;
		$this->queryObject = $queryObject;
		$this->repository = $repository;
	}

	/**
	 * @param bool $fetchJoinCollection
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function setFetchJoinCollection($fetchJoinCollection)
	{
		$this->updating();

		$this->fetchJoinCollection = !is_bool($fetchJoinCollection) ? (bool) $fetchJoinCollection : $fetchJoinCollection;
		$this->iterator = NULL;
	}

	/**
	 * @param bool|null $useOutputWalkers
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function setUseOutputWalkers($useOutputWalkers)
	{
		$this->updating();

		$this->useOutputWalkers = $useOutputWalkers;
		$this->iterator = NULL;
	}

	/**
	 * @return bool|NULL
	 */
	public function getUseOutputWalkers() : ?bool
	{
		return $this->useOutputWalkers;
	}

	/**
	 * @return boolean
	 */
	public function getFetchJoinCollection() : bool
	{
		return $this->fetchJoinCollection;
	}

	/**
	 * Removes ORDER BY clause that is not inside subquery.
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function clearSorting()
	{
		$this->updating();

		if ($this->query instanceof ORM\Query) {
			$dql = Utils\Strings::normalize($this->query->getDQL());

			if (preg_match('~^(.+)\\s+(ORDER BY\\s+((?!FROM|WHERE|ORDER\\s+BY|GROUP\\sBY|JOIN).)*)\\z~si', $dql, $m)) {
				$dql = $m[1];
			}

			$this->query->setDQL(trim($dql));
		}
	}

	/**
	 * @param string|array $columns
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function applySorting($columns)
	{
		$this->updating();

		$sorting = [];

		foreach (is_array($columns) ? $columns : func_get_args() as $name => $column) {
			if (!is_numeric($name)) {
				$column = $name . ' ' . $column;
			}

			if (!preg_match('~\s+(DESC|ASC)\s*\z~i', $column = trim($column))) {
				$column .= ' ASC';
			}

			$sorting[] = $column;
		}

		if ($sorting && $this->query instanceof ORM\Query) {
			$dql = Utils\Strings::normalize($this->query->getDQL());

			if (!preg_match('~^(.+)\\s+(ORDER BY\\s+((?!FROM|WHERE|ORDER\\s+BY|GROUP\\sBY|JOIN).)*)\\z~si', $dql, $m)) {
				$dql .= ' ORDER BY ';

			} else {
				$dql .= ', ';
			}

			$this->query->setDQL($dql . implode(', ', $sorting));
		}

		$this->iterator = NULL;
	}

	/**
	 * @param int|NULL $offset
	 * @param int|NULL $limit
	 *
	 * @return void
	 */
	public function applyPaging(int $offset = NULL, int $limit = NULL)
	{
		if (
			$this->query instanceof ORM\Query
			&& (
				$this->query->getFirstResult() != $offset
				|| $this->query->getMaxResults() != $limit
			)
		) {
			$this->query->setFirstResult($offset);
			$this->query->setMaxResults($limit);

			$this->iterator = NULL;
		}
	}

	/**
	 * @param Utils\Paginator $paginator
	 * @param int|NULL $itemsPerPage
	 *
	 * @return void
	 */
	public function applyPaginator(
		Utils\Paginator $paginator,
		int $itemsPerPage = NULL
	) {
		if ($itemsPerPage !== NULL) {
			$paginator->setItemsPerPage($itemsPerPage);
		}

		$paginator->setItemCount($this->getTotalCount());
		$this->applyPaging($paginator->getOffset(), $paginator->getLength());
	}

	/**
	 * @return bool
	 */
	public function isEmpty() : bool
	{
		$count = $this->getTotalCount();
		$offset = $this->query instanceof ORM\Query ? $this->query->getFirstResult() : 0;

		return $count <= $offset;
	}

	/**
	 * @return int
	 *
	 * @throws Exceptions\QueryException
	 */
	public function getTotalCount() : int
	{
		if ($this->totalCount !== NULL) {
			return $this->totalCount;
		}

		try {
			$paginatedQuery = $this->createPaginatedQuery($this->query);

			if ($this->queryObject !== NULL && $this->repository !== NULL) {
				$totalCount = $this->queryObject->count($this->repository, $this, $paginatedQuery);

			} else {
				$totalCount = $paginatedQuery->count();
			}

			$this->frozen = TRUE;
			return $this->totalCount = $totalCount;

		} catch (ORM\ORMException $e) {
			throw new Exceptions\QueryException($e, $this->query, $e->getMessage());
		}
	}

	/**
	 * @param int $hydrationMode
	 *
	 * @return ArrayIterator
	 *
	 * @throws Exception
	 */
	public function getIterator(
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	) : ArrayIterator {
		if ($this->iterator !== NULL) {
			return $this->iterator;
		}

		$this->query->setHydrationMode($hydrationMode);

		try {
			if ($this->fetchJoinCollection && $this->query instanceof ORM\Query && ($this->query->getMaxResults() > 0 || $this->query->getFirstResult() > 0)) {
				$iterator = $this->createPaginatedQuery($this->query)->getIterator();

			} else {
				$iterator = new ArrayIterator($this->query->getResult());
			}

			$this->frozen = TRUE;
			return $this->iterator = $iterator;

		} catch (ORM\ORMException $e) {
			throw new Exceptions\QueryException($e, $this->query, $e->getMessage());
		}
	}

	/**
	 * @param int $hydrationMode
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function toArray(
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	) : array {
		return iterator_to_array(clone $this->getIterator($hydrationMode), TRUE);
	}

	/**
	 * @return int
	 *
	 * @throws Exception
	 */
	public function count() : int
	{
		return $this->getIterator()->count();
	}

	/**
	 * @param ORM\AbstractQuery $query
	 *
	 * @return ORM\Tools\Pagination\Paginator
	 */
	private function createPaginatedQuery(
		ORM\AbstractQuery $query
	) : ORM\Tools\Pagination\Paginator {
		if (!$query instanceof ORM\Query) {
			throw new Exceptions\InvalidArgumentException(sprintf('QueryObject pagination only works with %s', ORM\Query::class));
		}

		$paginated = new ORM\Tools\Pagination\Paginator($query, $this->fetchJoinCollection);
		$paginated->setUseOutputWalkers($this->useOutputWalkers);

		return $paginated;
	}

	private function updating()
	{
		if ($this->frozen !== FALSE) {
			throw new Exceptions\InvalidStateException("Cannot modify result set, that was already fetched from storage.");
		}
	}

}
