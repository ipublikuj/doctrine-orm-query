<?php declare(strict_types = 1);

/**
 * QueryException.php
 *
 * @copyright      More in LICENSE.md
 * @license        https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     Exceptions
 * @since          0.0.1
 *
 * @date           10.11.19
 */

namespace IPub\DoctrineOrmQuery\Exceptions;

use Doctrine\ORM;
use RuntimeException;
use Throwable;

class QueryException extends RuntimeException implements IException
{

	/** @var ORM\AbstractQuery|null */
	public ?ORM\AbstractQuery $query;

	/**
	 * @param Throwable $previous
	 * @param ORM\AbstractQuery|null $query
	 * @param string|null $message
	 */
	public function __construct(Throwable $previous, ?ORM\AbstractQuery $query = null, ?string $message = null)
	{
		parent::__construct($message ?? $previous->getMessage(), 0, $previous);

		$this->query = $query;
	}

}
