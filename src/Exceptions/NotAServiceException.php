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
// Date:     13/03/2018
// Time:     10:24
// Project:  lib-servicemanager
//
declare(strict_types = 1);
namespace CodeInc\ServiceManager\Exceptions;
use CodeInc\ServiceManager\ServiceInterface;
use CodeInc\ServiceManager\ServiceManager;
use Throwable;


/**
 * Class NotAServiceException
 *
 * @package CodeInc\ServiceManager\Exceptions
 * @author Joan Fabrégat <joan@codeinc.fr>
 */
class NotAServiceException extends ServiceManagerException
{
    /**
     * NotAServiceException constructor.
     *
     * @param string $class
     * @param ServiceManager $instantiator
     * @param int|null $code
     * @param null|Throwable $previous
     */
    public function __construct(string $class, ServiceManager $instantiator,
        ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf("The class %s is not a servce. All services must implement %s.",
                $class, ServiceInterface::class),
            $instantiator, $code, $previous);
    }
}