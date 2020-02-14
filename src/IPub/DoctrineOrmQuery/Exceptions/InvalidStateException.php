<?php
/**
 * InvalidStateException.php
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

use RuntimeException;

class InvalidStateException extends RuntimeException implements IException
{
}
