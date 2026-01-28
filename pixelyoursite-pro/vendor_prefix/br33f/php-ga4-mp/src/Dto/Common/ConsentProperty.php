<?php
/**
 * User: Alexis POUPELIN (AlexisPPLIN)
 * Date: 08.04.2024
 * Time: 11:00
 */

namespace PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\ExportableInterface;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Enum\ConsentCode;

class ConsentProperty implements ExportableInterface
{
    /**
     * @var string
     */
    protected $ad_user_data;

    /**
     * @var string
     */
    protected $ad_personalization;

    /**
     * ConsentProperty constructor
     * @param string|null $ad_user_data
     * @param string|null $ad_personalization
     */
    public function __construct(?string $ad_user_data = null, ?string $ad_personalization = null)
    {
        $this->ad_user_data = $ad_user_data;
        $this->ad_personalization = $ad_personalization;
    }

    public function export() : array
    {
        $result = [];

        if (isset($this->ad_user_data)) {
            $result['ad_user_data'] = $this->ad_user_data;
        }

        if (isset($this->ad_personalization)) {
            $result['ad_personalization'] = $this->ad_personalization;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getAdUserData() : ?string
    {
        return $this->ad_user_data;
    }

    /**
     * @param string|null $ad_user_data
     */
    public function setAdUserData(?string $ad_user_data) : void
    {
        $this->ad_user_data = $ad_user_data;
    }

    /**
     * @return string|null
     */
    public function getAdPersonalization() : ?string
    {
        return $this->ad_personalization;
    }
    /**
     * @param string|null $ad_personalization
     */
    public function setAdPersonalization(?string $ad_personalization) : void
    {
        $this->ad_personalization = $ad_personalization;
    }
}
