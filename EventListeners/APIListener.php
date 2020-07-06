<?php


namespace ColissimoHomeDelivery\EventListeners;


use ColissimoHomeDelivery\ColissimoHomeDelivery;
use OpenApi\Events\DeliveryModuleOptionEvent;
use OpenApi\Events\OpenApiEvents;
use OpenApi\Model\Api\DeliveryModuleOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryArea;
use Thelia\Module\Exception\DeliveryException;

class APIListener implements EventSubscriberInterface
{
    public function getDeliveryModuleOptions(DeliveryModuleOptionEvent $deliveryModuleOptionEvent)
    {
        $isValid = true;
        $postage = null;
        $postageTax = null;

        try {
            $module = new ColissimoHomeDelivery();
            $country = $deliveryModuleOptionEvent->getCountry();

            if (empty($module->getAllAreasForCountry($country))) {
                throw new DeliveryException(Translator::getInstance()->trans("Your delivery country is not covered by Colissimo"));
            }

            $countryAreas = $country->getCountryAreas();
            $areasArray = [];

            /** @var CountryArea $countryArea */
            foreach ($countryAreas as $countryArea) {
                $areasArray[] = $countryArea->getAreaId();
            }

            $postage = $module->getMinPostage(
                $areasArray,
                $deliveryModuleOptionEvent->getCart()->getWeight(),
                $deliveryModuleOptionEvent->getCart()->getTaxedAmount($country)
            );

            $postageTax = 0; //TODO
        } catch (\Exception $exception) {
            $isValid = false;
        }

        $minimumDeliveryDate = ''; // TODO (calculate delivery date from day of order)
        $maximumDeliveryDate = ''; // TODO (calculate delivery date from day of order

        $deliveryModuleOptionEvent->setDeliveryModuleOptions(
            (new DeliveryModuleOption())
                ->setCode('ColissimoHomeDelivery')
                ->setValid($isValid)
                ->setTitle('Colissimo Home Delivery')
                ->setImage('')
                ->setMinimumDeliveryDate($minimumDeliveryDate)
                ->setMaximumDeliveryDate($maximumDeliveryDate)
                ->setPostage($postage)
                ->setPostageTax($postageTax)
                ->setPostageUntaxed($postage - $postageTax)
        );
    }

    public static function getSubscribedEvents()
    {
        $listenedEvents = [];

        /** Check for old versions of Thelia where the events used by the API didn't exists */
        if (class_exists(DeliveryModuleOptionEvent::class)) {
            $listenedEvents[OpenApiEvents::MODULE_DELIVERY_GET_OPTIONS] = array("getDeliveryModuleOptions", 128);
        }

        return $listenedEvents;
    }
}