<?php

namespace Charcoal\FilePond\Service\Helper;

/**
 * Trait FileHelperTrait
 * @package Charcoal\FilePond\Service\Helper
 */
trait FileHelperTrait
{
    /**
     * @param array $arr The array to validate.
     * @return boolean
     */
    protected function isAssociativeArray(array $arr)
    {
        return array_keys($arr) !== range(0, (count($arr) - 1));
    }

    /**
     * @param mixed $value Value to convert to array.
     * @return array|array[]
     */
    protected function toArray($value)
    {
        if (is_array($value) && !$this->isAssociativeArray($value)) {
            return $value;
        }
        return isset($value) ? [$value] : [];
    }

    /**
     * @param mixed $value Value to convert.
     * @return array
     */
    protected function toArrayOfFiles($value)
    {
        if (is_array($value['tmp_name'])) {
            $results = [];
            foreach ($value['tmp_name'] as $index => $tmpName) {
                $file = [
                    'tmp_name' => $value['tmp_name'][$index],
                    'name' => $value['name'][$index],
                    'size' => $value['size'][$index],
                    'error' => $value['error'][$index],
                    'type' => $value['type'][$index]
                ];
                array_push($results, $file);
            }
            return $results;
        }
        return $this->toArray($value);
    }

    /**
     * @param string $value The value to decode.
     * @return boolean
     */
    protected function isEncodedFile($value)
    {
        $data = @json_decode($value);
        return is_object($data);
    }
}
