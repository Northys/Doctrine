<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Doctrine;

use Doctrine;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RepositoryFactory implements Doctrine\ORM\Repository\RepositoryFactory
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * The list of EntityRepository instances.
	 *
	 * @var \Doctrine\Common\Persistence\ObjectRepository[]
	 */
	private $repositoryList = [];

	/**
	 * @var array
	 */
	private $repositoryServicesMap = [];

	/**
	 * @var string
	 */
	private $defaultRepositoryFactory;



	public function __construct(Nette\DI\Container $serviceLocator)
	{
		$this->serviceLocator = $serviceLocator;
	}



	/**
	 * @param array $repositoryServicesMap [RepositoryType => repositoryServiceId]
	 * @param string $defaultRepositoryFactory
	 */
	public function setServiceIdsMap(array $repositoryServicesMap, $defaultRepositoryFactory)
	{
		$this->repositoryServicesMap = $repositoryServicesMap;
		$this->defaultRepositoryFactory = $defaultRepositoryFactory;
	}



	/**
	 * @param EntityManagerInterface|EntityManager $entityManager
	 * @param string $entityName
	 * @return EntityRepository
	 */
	public function getRepository(EntityManagerInterface $entityManager, $entityName)
	{
		if (is_object($entityName)) {
			$entityName = Doctrine\Common\Util\ClassUtils::getRealClass(get_class($entityName));
		}

		$entityName = ltrim($entityName, '\\');

		if (isset($this->repositoryList[$emId = spl_object_hash($entityManager)][$entityName])) {
			return $this->repositoryList[$emId][$entityName];
		}

		/** @var Doctrine\ORM\Mapping\ClassMetadata $metadata */
		$metadata = $entityManager->getClassMetadata($entityName);
		$repository = $this->createRepository($entityManager, $metadata);

		return $this->repositoryList[$emId][$entityName] = $repository;
	}



	/**
	 * Create a new repository instance for an entity class.
	 *
	 * @param \Doctrine\ORM\EntityManagerInterface $entityManager The EntityManager instance.
	 * @param Doctrine\ORM\Mapping\ClassMetadata $metadata
	 * @return Doctrine\Common\Persistence\ObjectRepository
	 */
	private function createRepository(EntityManagerInterface $entityManager, Doctrine\ORM\Mapping\ClassMetadata $metadata)
	{
		$defaultClass = $entityManager->getConfiguration()->getDefaultRepositoryClassName();
		$customClass = ltrim($metadata->customRepositoryClassName, '\\');

		if (empty($customClass) || $customClass === $defaultClass) {
			$factory = $this->getRepositoryFactory($this->defaultRepositoryFactory);

		} elseif (isset($this->repositoryServicesMap[$customClass])) {
			$factory = $this->getRepositoryFactory($this->repositoryServicesMap[$customClass]);

		} else {
			return new $customClass($entityManager, $metadata);
		}

		return $factory->create($entityManager, $metadata);
	}



	/**
	 * @param string $serviceName
	 * @return Kdyby\Doctrine\DI\IRepositoryFactory
	 */
	protected function getRepositoryFactory($serviceName)
	{
		return $this->serviceLocator->getService($serviceName);
	}

}
