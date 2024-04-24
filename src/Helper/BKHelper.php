<?php

namespace ElasticExportGoogleShopping\Helper;

class BKHelper
{

    public function getPropertyValue($properties, $propertyId)
    {
        foreach($properties as $property)
        {
            if($property['property']['id'] != $propertyId) 
                continue;

            return $property['values']['value'] ?? "";
        }
        return "";
    }
}