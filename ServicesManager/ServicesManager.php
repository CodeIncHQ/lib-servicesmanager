<?php
//
// +---------------------------------------------------------------------+
// | CODE INC. SOURCE CODE                                               |
// +---------------------------------------------------------------------+
// | Copyright (c) 2017 - Code Inc. SAS - All Rights Reserved.           |
// | Visit https://www.codeinc.fr for more information about licensing.  |
// +---------------------------------------------------------------------+
// | NOTICE:  All information contained herein is, and remains the       |
// | property of Code Inc. SAS. The intellectual and technical concepts  |
// | contained herein are proprietary to Code Inc. SAS are protected by  |
// | trade secret or copyright law. Dissemination of this information or |
// | reproduction of this material  is strictly forbidden unless prior   |
// | written permission is obtained from Code Inc. SAS.                  |
// +---------------------------------------------------------------------+
//
// Author:   Joan Fabrégat <joan@codeinc.fr>
// Date:     12/03/2018
// Time:     10:33
// Project:  lib-servicesmanager
//
declare(strict_types = 1);
namespace CodeInc\ServicesManager;
use CodeInc\ServicesManager\Exceptions\InterfaceWithoutAliasException;
use CodeInc\ServicesManager\Exceptions\NewInstanceException;
use CodeInc\ServicesManager\Exceptions\NotAnObjectException;
use CodeInc\ServicesManager\Exceptions\NotAServiceException;
use CodeInc\ServicesManager\Exceptions\ParamValueException;
use CodeInc\ServicesManager\Exceptions\ServicesManagerException;
use CodeInc\ServicesManager\Exceptions\ClassNotFoundException;


/**
 * Class ServicesManager
 *
 * @package CodeInc\ServicesManager
 * @author Joan Fabrégat <joan@codeinc.fr>
 * @todo add object type hint when min compatibility >= 7.2
 */
class ServicesManager implements ServiceInterface
{
    /**
     * Instantiated objects stack.
     *
     * @see ServicesManager::addService()
     * @see ServicesManager::getService()
     * @var object[]
     */
    private $services = [];

    /**
     * Services aliases (other classes or interfaces)
     *
     * @see ServicesManager::addAlias()
     * @var string[]
     */
    private $aliases = [];

    /**
     * ServicesManager constructor.
     *
     * @throws ServicesManagerException
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->addService($this);
    }

    /**
     * Adds an instance of an object. This can be usefull when you need to make
     * available instances which requires a specific configuration like the
     * ServerRequest object or Doctrine's EntityManager.
     *
     * @param object $instance
     * @throws NotAnObjectException
     * @throws \ReflectionException
     */
    public function addService($instance):void
    {
        // checks the serivce
        if (!is_object($instance)) {
            throw new NotAnObjectException(gettype($instance), $this);
        }

        // adds the instance
        $reflectionClass = new \ReflectionClass($instance);
        $this->services[$reflectionClass->getName()] = $instance;

        // maps the instance interfaces
        foreach ($reflectionClass->getInterfaces() as $interface) {
            $this->addAlias(
                $reflectionClass->getName(),
                $interface->getName(),
                false
            );
        }

        // maps the instance parent classes
        $parent = $reflectionClass;
        while ($parent = $parent->getParentClass()) {
            $this->addAlias(
                $reflectionClass->getName(),
                $parent->getName(),
                false
            );
        }
    }

    /**
     * Adds an alias for a class of an interface. This is the way to tell the
     * service manager to use a specific class when it encounters an interface
     * as a type hint.
     *
     * @param string $class Class or interface name
     * @param string $alias
     * @param bool $replace
     * @return bool
     */
    public function addAlias(string $class, string $alias,
        bool $replace = true):bool
    {
        if ($replace === true || !isset($this->aliases[$alias])) {
            $this->aliases[$alias] = $class;
        }
        return false;
    }

    /**
     * Verifies if an alias exist for a class / interface.
     *
     * @param string $class
     * @return bool
     */
    public function hasAlias(string $class):bool
    {
        return isset($this->aliases[$class]);
    }

    /**
     * Returns an alias target or null if not set.
     *
     * @param string $class
     * @return null|string
     */
    public function getAlias(string $class):?string
    {
        return $this->aliases[$class] ?? null;
    }

    /**
     * Returns an instance. If the instance was used previously, returns
     * the previous one. If the instance was added using addInstance(),
     * returns the added instance.
     *
     * @param string $class
     * @param array|null $dependencies
     * @return mixed
     * @throws ClassNotFoundException
     * @throws InterfaceWithoutAliasException
     * @throws NotAnObjectException
     * @throws ServicesManagerException
     * @throws \ReflectionException
     */
    public function getService(string $class, ?array $dependencies = null)
    {
        // if the class is an alias
        if ($alias = $this->getAlias($class)) {
            $class = $alias;
        }

        // if there is an instance already available
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }

        // checks if the class is an interface
        if (interface_exists($class)) {
            throw new InterfaceWithoutAliasException($class, $this);
        }

        // checks if the class exists
        if (!class_exists($class)) {
            throw new ClassNotFoundException($class, $this);
        }

        // checks if the class is a service
        if (is_subclass_of($class, ServiceInterface::class)) {
            throw new NotAServiceException($class, $this);
        }

        // if the class was never added or instantiated, we instantiate it
        $service = $this->instantiate($class, $dependencies);
        $this->addService($service);
        return $service;
    }

    /**
     * Alias of getService()
     *
     * @uses ServicesManager::getService()
     * @param string $class
     * @return object
     * @throws ServicesManagerException
     * @throws \ReflectionException
     */
    public function __invoke(string $class)
    {
        return $this->getService($class);
    }

    /**
     * Verifies the manager has an instance of an object.
     *
     * @param string|object $class
     * @return bool
     */
    public function hasServiceInstance($class):bool
    {
        // if the class is an object
        if (is_object($class)) {
            $class = get_class($class);
        }

        // if the class is an alias
        if ($alias = $this->getAlias($class)) {
            $class = $alias;
        }

        return isset($this->services[$class]);
    }

    /**
     * Instantiate any class which requires services.
     *
     * @param string $class
     * @param object[] $dependencies
     * @return object
     * @throws NewInstanceException
     * @throws ClassNotFoundException
     */
    public function instantiate(string $class, ?array $dependencies = null)
    {
        // checks if the class exists
        if (!class_exists($class)) {
            throw new ClassNotFoundException($class, $this);
        }

        // preparing the dependencies array
        if ($dependencies) {
            foreach ($dependencies as $key => $object) {
                unset($dependencies[$key]);
                $dependencies[get_class($object)] = $object;
            }
        }

        // instantiate the class
        try {
            $class = new \ReflectionClass($class);
            if ($class->hasMethod("__construct")) {
                $args = [];
                foreach ($class->getMethod("__construct")->getParameters() as $number => $param) {
                    // if the param is required
                    if (!$param->isOptional()) {

                        // if the param type is not set
                        if (!$param->hasType()) {
                            throw new ParamValueException(
                                $class->getName(), $param->getName(), $number + 1,
                                "the parameter does not have a type hint", $this
                            );
                        }

                        // if the param type is not a class
                        $paramClass = $param->getType()->getName();
                        if ($param->getType()->isBuiltin()) {
                            throw new ParamValueException(
                                $class->getName(), $param->getName(), $number + 1,
                                sprintf("the parameter type is not a class or an interface (type: %s)",
                                    $paramClass), $this
                            );
                        }

                        // if the parameter value is available among the local dependencies
                        if (isset($dependencies[$paramClass])) {
                            return $dependencies[$paramClass];
                        }

                        // else instantiating the class
                        return $this->getService($paramClass);
                    }

                    // optionnal param
                    else {
                        // if the param is optionnal returning it's default value
                        return $param->getDefaultValue();
                    }
                }
                return $class->newInstanceArgs($args);
            }
            else {
                return $class->newInstance();
            }
        }
        catch (\Throwable $exception) {
            throw new NewInstanceException(
                $class->getName(),
                $this,
                null, $exception
            );
        }
    }
}