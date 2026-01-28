<?php
/**
 * User: Damian Zamojski (br33f)
 * Date: 25.06.2021
 * Time: 13:33
 */

namespace PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Event;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Parameter\AbstractParameter;

/**
 * Class ViewItemListEvent
 * @package PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Event
 * @method string getItemListId()
 * @method ViewItemListEvent setItemListId(string $itemListId)
 * @method string getItemListName()
 * @method ViewItemListEvent setItemListName(string $itemListName)
 */
class ViewItemListEvent extends ItemBaseEvent
{
    private $eventName = 'view_item_list';

    /**
     * ViewItemListEvent constructor.
     * @param AbstractParameter[] $paramList
     */
    public function __construct(array $paramList = [])
    {
        parent::__construct($this->eventName, $paramList);
    }
}
