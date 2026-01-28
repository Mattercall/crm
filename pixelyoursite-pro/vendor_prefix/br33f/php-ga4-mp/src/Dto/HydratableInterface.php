<?php
/**
 * User: Damian Zamojski (br33f)
 * Date: 22.06.2021
 * Time: 13:42
 */

namespace PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Exception\HydrationException;
use PYS_PRO_GLOBAL\Psr\Http\Message\ResponseInterface;

interface HydratableInterface
{
    /**
     * Method hydrates DTO with data from blueprint
     * @param ResponseInterface|array $blueprint
     * @throws HydrationException
     */
    public function hydrate($blueprint);
}
