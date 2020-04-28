<?php

namespace Charcoal\FilePond\Service\Helper;

/**
 * Class UniqueIdDispenser
 */
class UniqueIdDispenser
{
    private static $counter = 0;
    public static function dispense()
    {
        return md5(uniqid(self::$counter++, true));
    }
}
