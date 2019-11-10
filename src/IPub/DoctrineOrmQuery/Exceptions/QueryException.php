<?php
/**
 * QueryException.php
 *
 * @copyright      More in license.md
 * @license        https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrineOrmQuery!
 * @subpackage     Exceptions
 * @since          1.0.0
 *
 * @date           10.11.19
 */

declare(strict_types = 1);

namespace IPub\DoctrineOrmQuery\Exceptions;

use Exception;
use Throwable;

use Doctrine\ORM;

class QueryException extends \RuntimeException implements IException
{
	/**
	 * @var ORM\AbstractQuery|NULL
	 */
	public $query;

	/**
	 * @param Exception|Throwable $previous
	 * @param ORM\AbstractQuery|NULL $query
	 * @param string|NULL $message
	 */
	public function __construct($previous, ORM\AbstractQuery $query = NULL, $message = NULL)
	{
		parent::__construct($message ?: $previous->getMessage(), 0, $previous);

		$this->query = $query;
	}
}
