# Quickstart

Doctrine2 query object is here to help you create custom Doctrine queries. You can use it to filter data, join multiple entities or paginate data.

## Installation

The best way to install **ipub/doctrine-orm-query** is using [Composer](http://getcomposer.org/):

```sh
composer require ipub/doctrine-orm-query
```

## Create query object

This Doctrine ORM query is easy to use. Just create you own fetch class which is extending query object class and use it with your repository.

```php
use Closure;
use Doctrine\ORM;
use Doctrine\Persistence;
use IPub\DoctrineOrmQuery;

class FindArticleQuery extends DoctrineOrmQuery\QueryObject
{

	/** @var Closure[] */
	private array $filter = [];

	public function byId(int $id): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb) use ($id): void {
			$qb->andWhere('a.id = :id')->setParameter('id', $id);
		};
	}

	protected function doCreateQuery(Persistence\ObjectRepository $repository): ORM\QueryBuilder
	{
		/** @var ORM\QueryBuilder $qb */
		$qb = $repository->createQueryBuilder('a');

		foreach ($this->filter as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

}
```

And now this query object could be used with repository:

```php
use Doctrine\Persistence;

/** Persistence\ObjectRepository $repository */
$repository = $someDIService->getArticleRepository();

$queryObject = new FindArticleQuery();
$queryObject->byId($articleId);

$article = $queryObject->fetchOne($repository);
```

And if you want to fetch more records, use paging or sorting:

```php
use Doctrine\Persistence;

/** Persistence\ObjectRepository $repository */
$repository = $someDIService->getArticleRepository();

$queryObject = new FindArticleQuery();

$resultSet = $queryObject->fetch($repository);

$resultSet->applyPaging($pageOffset, $pageLimit);
$resultSet->applySorting(['id' => 'DESC']);

$articles = $resultSet->toArray(); // Will return array of selected entities
```
