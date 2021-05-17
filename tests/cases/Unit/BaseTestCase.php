<?php declare(strict_types = 1);

namespace Tests\Cases;

use Doctrine\DBAL;
use Doctrine\ORM;
use InvalidArgumentException;
use Nette;
use Nette\DI;
use Nettrine;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use RuntimeException;

abstract class BaseTestCase extends BaseMockeryTestCase
{

	/** @var string[] */
	protected array $additionalConfigs = [];

	/** @var DI\Container */
	protected DI\Container $container;

	/** @var ORM\EntityManagerInterface|null */
	private ?ORM\EntityManagerInterface $em = null;

	/** @var bool */
	private bool $isDatabaseSetUp = false;

	/** @var string[] */
	private array $sqlFiles = [];

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->createContainer($this->additionalConfigs);
	}

	/**
	 * @return ORM\EntityManagerInterface
	 */
	protected function getEntityManager(): ORM\EntityManagerInterface
	{
		if ($this->em === null) {
			/** @var ORM\EntityManagerInterface $em */
			$em = $this->container->getByType(Nettrine\ORM\EntityManagerDecorator::class);

			$this->em = $em;
		}

		return $this->em;
	}

	/**
	 * @return void
	 *
	 * @throws ORM\Tools\ToolsException
	 */
	protected function generateDbSchema(): void
	{
		$schema = new ORM\Tools\SchemaTool($this->getEntityManager());
		$schema->createSchema($this->getEntityManager()->getMetadataFactory()
			->getAllMetadata());
	}

	/**
	 * @param string $file
	 */
	protected function registerDatabaseSchemaFile(string $file): void
	{
		if (!in_array($file, $this->sqlFiles, true)) {
			$this->sqlFiles[] = $file;
		}
	}

	/**
	 * @return void
	 */
	private function setupDatabase(): void
	{
		if (!$this->isDatabaseSetUp) {
			/** @var DBAL\Connection $service */
			$db = $this->container->getByType(DBAL\Connection::class);

			$metadatas = $this->getEntityManager()->getMetadataFactory()->getAllMetadata();
			$schemaTool = new ORM\Tools\SchemaTool($this->getEntityManager());

			$schemas = $schemaTool->getCreateSchemaSql($metadatas);

			foreach ($schemas as $sql) {
				try {
					$db->executeStatement($sql);

				} catch (DBAL\Exception $ex) {
					throw new RuntimeException('Database schema could not be created');
				}
			}

			foreach (array_reverse($this->sqlFiles) as $file) {
				$this->loadSqlFromFile($db, $file);
			}

			$this->isDatabaseSetUp = true;
		}
	}

	/**
	 * @param string[] $additionalConfigs
	 */
	protected function createContainer(array $additionalConfigs = []): void
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../common.neon');

		foreach ($additionalConfigs as $additionalConfig) {
			if ($additionalConfig && file_exists($additionalConfig)) {
				$config->addConfig($additionalConfig);
			}
		}

		$this->container = $config->createContainer();

		$this->setupDatabase();
	}

	/**
	 * @param string $serviceType
	 * @param object $serviceMock
	 *
	 * @return void
	 */
	protected function mockContainerService(
		string $serviceType,
		object $serviceMock
	): void {
		$foundServiceNames = $this->container->findByType($serviceType);

		foreach ($foundServiceNames as $serviceName) {
			$this->replaceContainerService($serviceName, $serviceMock);
		}
	}

	/**
	 * @param string $serviceName
	 * @param object $service
	 *
	 * @return void
	 */
	private function replaceContainerService(string $serviceName, object $service): void
	{
		$this->container->removeService($serviceName);
		$this->container->addService($serviceName, $service);
	}

	/**
	 * @param DBAL\Connection $db
	 * @param string $file
	 *
	 * @return int
	 */
	private function loadSqlFromFile(DBAL\Connection $db, string $file): int
	{
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file, 'r'); // intentionally @

		if ($handle === false) {
			throw new InvalidArgumentException(sprintf('Cannot open file "%s".', $file));
		}

		$count = 0;
		$delimiter = ';';
		$sql = '';

		while (!feof($handle)) {
			$content = fgets($handle);

			if ($content !== false) {
				$s = rtrim($content);

				if (substr($s, 0, 10) === 'DELIMITER ') {
					$delimiter = substr($s, 10);

				} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
					$sql .= substr($s, 0, -strlen($delimiter));

					try {
						$db->executeQuery($sql);
						$sql = '';
						$count++;

					} catch (DBAL\Exception $ex) {
						// File could not be loaded
					}

				} else {
					$sql .= $s . "\n";
				}
			}
		}

		if (trim($sql) !== '') {
			try {
				$db->executeQuery($sql);
				$count++;

			} catch (DBAL\Exception $ex) {
				// File could not be loaded
			}
		}

		fclose($handle);

		return $count;
	}

}
