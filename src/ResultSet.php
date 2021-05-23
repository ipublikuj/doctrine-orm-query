<?php declare(strict_types = 1);

/**
 * ResultSet.php
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

use ArrayIterator;
use Countable;
use Doctrine\ORM;
use Exception;
use IteratorAggregate;
use Nette\Utils;

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
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @author         Filip Procházka <filip@prochazka.su>
 *
 * @phpstan-template    TEntityClass of object
 * @phpstan-implements  IteratorAggregate<int, TEntityClass>
 */
final class ResultSet implements Countable, IteratorAggregate
{

	/** @var int|null */
	private ?int $totalCount = null;

	/** @var ORM\AbstractQuery|ORM\Query|ORM\NativeQuery */
	private $query;

	/**
	 * @var QueryObject
	 *
	 * @phpstan-var QueryObject<TEntityClass>
	 */
	private QueryObject $queryObject;

	/**
	 * @var ORM\EntityRepository
	 *
	 * @phpstan-var ORM\EntityRepository<TEntityClass>
	 */
	private ORM\EntityRepository $repository;

	/** @var bool */
	private bool $fetchJoinCollection = true;

	/** @var bool|null */
	private ?bool $useOutputWalkers = null;

	/**
	 * @var ArrayIterator|null
	 *
	 * @phpstan-var ArrayIterator<int, TEntityClass>|null
	 */
	private ?ArrayIterator $iterator = null;

	/** @var bool */
	private bool $frozen = false;

	/**
	 * @param ORM\AbstractQuery $query
	 * @param QueryObject $queryObject
	 * @param ORM\EntityRepository $repository
	 *
	 * @phpstan-param QueryObject<TEntityClass> $queryObject
	 * @phpstan-param ORM\EntityRepository<TEntityClass> $repository
	 */
	public function __construct(
		ORM\AbstractQuery $query,
		QueryObject $queryObject,
		ORM\EntityRepository $repository
	) {
		$this->query = $query;
		$this->queryObject = $queryObject;
		$this->repository = $repository;
	}

	/**
	 * @return bool|null
	 */
	public function getUseOutputWalkers(): ?bool
	{
		return $this->useOutputWalkers;
	}

	/**
	 * @param bool|null $useOutputWalkers
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function setUseOutputWalkers(?bool $useOutputWalkers): void
	{
		$this->updating();

		$this->useOutputWalkers = $useOutputWalkers;
		$this->iterator = null;
	}

	/**
	 * @return bool
	 */
	public function getFetchJoinCollection(): bool
	{
		return $this->fetchJoinCollection;
	}

	/**
	 * @param bool $fetchJoinCollection
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function setFetchJoinCollection(bool $fetchJoinCollection): void
	{
		$this->updating();

		$this->fetchJoinCollection = $fetchJoinCollection;
		$this->iterator = null;
	}

	private function updating(): void
	{
		if ($this->frozen !== false) {
			throw new Exceptions\InvalidStateException('Cannot modify result set, that was already fetched from storage');
		}
	}

	/**
	 * Removes ORDER BY clause that is not inside sub-query
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function clearSorting(): void
	{
		$this->updating();

		if ($this->query instanceof ORM\Query) {
			$dql = $this->query->getDQL();

			if ($dql === null) {
				throw new Exceptions\InvalidStateException('DQL could not be created');
			}

			$dql = Utils\Strings::normalize($dql);

			if (preg_match('~^(.+)\\s+(ORDER BY\\s+((?!FROM|WHERE|ORDER\\s+BY|GROUP\\sBY|JOIN).)*)\\z~si', $dql, $m) !== false) {
				$dql = $m[1];
			}

			$this->query->setDQL(trim($dql));
		}
	}

	/**
	 * @param string|mixed[] $columns
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function applySorting($columns): void
	{
		$this->updating();

		$sorting = [];

		foreach (is_array($columns) ? $columns : func_get_args() as $name => $column) {
			if (!is_numeric($name)) {
				$column = $name . ' ' . $column;
			}

			if (preg_match('~\s+(DESC|ASC)\s*\z~i', $column = trim($column)) === false) {
				$column .= ' ASC';
			}

			$sorting[] = $column;
		}

		if ($sorting !== [] && $this->query instanceof ORM\Query) {
			$dql = $this->query->getDQL();

			if ($dql === null) {
				throw new Exceptions\InvalidStateException('DQL could not be created');
			}

			$dql = Utils\Strings::normalize($dql);

			if (preg_match('~^(.+)\\s+(ORDER BY\\s+((?!FROM|WHERE|ORDER\\s+BY|GROUP\\sBY|JOIN).)*)\\z~si', $dql, $m) !== false) {
				$dql .= ' ORDER BY ';

			} else {
				$dql .= ', ';
			}

			$this->query->setDQL($dql . implode(', ', $sorting));
		}

		$this->iterator = null;
	}

	/**
	 * @param Utils\Paginator $paginator
	 * @param int|null $itemsPerPage
	 *
	 * @return void
	 */
	public function applyPaginator(
		Utils\Paginator $paginator,
		?int $itemsPerPage = null
	): void {
		if ($itemsPerPage !== null) {
			$paginator->setItemsPerPage($itemsPerPage);
		}

		$paginator->setItemCount($this->getTotalCount());
		$this->applyPaging($paginator->getOffset(), $paginator->getLength());
	}

	/**
	 * @return int
	 *
	 * @throws Exceptions\QueryException
	 */
	public function getTotalCount(): int
	{
		if ($this->totalCount !== null) {
			return $this->totalCount;
		}

		try {
			$paginatedQuery = $this->createPaginatedQuery($this->query);

			$totalCount = $this->queryObject->count($this->repository, $this, $paginatedQuery);

			$this->frozen = true;

			$this->totalCount = $totalCount;

			return $this->totalCount;

		} catch (ORM\ORMException $e) {
			throw new Exceptions\QueryException($e, $this->query, $e->getMessage());
		}
	}

	/**
	 * @param ORM\AbstractQuery $query
	 *
	 * @return ORM\Tools\Pagination\Paginator
	 *
	 * @phpstan-return ORM\Tools\Pagination\Paginator<TEntityClass>
	 */
	private function createPaginatedQuery(
		ORM\AbstractQuery $query
	): ORM\Tools\Pagination\Paginator {
		if (!$query instanceof ORM\Query) {
			throw new Exceptions\InvalidArgumentException(sprintf('QueryObject pagination only works with %s', ORM\Query::class));
		}

		$paginated = new ORM\Tools\Pagination\Paginator($query, $this->fetchJoinCollection);
		$paginated->setUseOutputWalkers($this->useOutputWalkers);

		return $paginated;
	}

	/**
	 * @param int|null $offset
	 * @param int|null $limit
	 *
	 * @return void
	 */
	public function applyPaging(?int $offset = null, ?int $limit = null): void
	{
		if (
			$this->query instanceof ORM\Query
			&& (
				$this->query->getFirstResult() !== $offset
				|| $this->query->getMaxResults() !== $limit
			)
		) {
			$this->query->setFirstResult($offset);
			$this->query->setMaxResults($limit);

			$this->iterator = null;
		}
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		$count = $this->getTotalCount();
		$offset = $this->query instanceof ORM\Query ? $this->query->getFirstResult() : 0;

		return $count <= $offset;
	}

	/**
	 * @param int $hydrationMode
	 *
	 * @return mixed[]
	 *
	 * @throws Exception
	 *
	 * @phpstan-return Array<TEntityClass>|mixed[]
	 */
	public function toArray(
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	): array {
		return iterator_to_array(clone $this->getIterator($hydrationMode), true);
	}

	/**
	 * @param int $hydrationMode
	 *
	 * @return ArrayIterator
	 *
	 * @throws Exception
	 *
	 * @phpstan-return ArrayIterator<int, TEntityClass>
	 */
	public function getIterator(
		int $hydrationMode = ORM\AbstractQuery::HYDRATE_OBJECT
	): ArrayIterator {
		if ($this->iterator !== null) {
			return $this->iterator;
		}

		$this->query->setHydrationMode($hydrationMode);

		try {
			if ($this->fetchJoinCollection && $this->query instanceof ORM\Query && ($this->query->getMaxResults() > 0 || $this->query->getFirstResult() > 0)) {
				$iterator = $this->createPaginatedQuery($this->query)->getIterator();

			} else {
				$iterator = new ArrayIterator($this->query->getResult());
			}

			$this->frozen = true;

			return $this->iterator = $iterator;

		} catch (ORM\ORMException $e) {
			throw new Exceptions\QueryException($e, $this->query, $e->getMessage());
		}
	}

	/**
	 * @return int
	 *
	 * @throws Exception
	 */
	public function count(): int
	{
		return $this->getIterator()->count();
	}

}
