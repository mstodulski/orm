<?php
namespace test\orm\helpers;

class ProductEventService
{
    public static function postLoadEvent(Product $product)
    {
        //dump('PRODUKT postLoadEvent');
    }

    public static function preRemoveEvent(Product $product)
    {
        //dump('PRODUKT preRemoveEvent');
    }

    public static function postRemoveEvent(Product $product)
    {
        //dump('PRODUKT postRemoveEvent');
    }

    public static function preUpdateEvent(Product $product)
    {
        //dump('PRODUKT preUpdateEvent');
    }

    public static function postUpdateEvent(Product $product)
    {
        //dump('PRODUKT postUpdateEvent');
    }

    public static function postCreateEvent(Product $product)
    {
        //dump('PRODUKT postCreateEvent');
    }

    public static function postCreateEventProductFeature(Feature $productFeature)
    {
        //dump('CECHA PRODUKTU postCreateEvent');
    }

    public static function postLoadEventProductFeature(Feature $productFeature)
    {
        //dump('CECHA PRODUKTU postLoadEvent');
    }
}