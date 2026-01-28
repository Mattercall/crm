<?php
/**
 * User: Damian Zamojski (br33f)
 * Date: 25.06.2021
 * Time: 13:33
 */

namespace PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Event;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Parameter\AbstractParameter;

/**
 * Class SelectItemEvent
 * @package PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Event
 * @method string getItemListId()
 * @method SelectItemEvent setItemListId(string $itemListId)
 * @method string getItemListName()
 * @method SelectItemEvent setItemListName(string $itemListName)
 */
class SelectItemEvent extends ItemBaseEvent
{
    private $eventName = 'select_item';

    /**
     * SelectItemEvent constructor.
     * @param AbstractParameter[] $paramList
     */
    public function __construct(array $paramList = [])
    {
        parent::__construct($this->eventName, $paramList);
    }
}
