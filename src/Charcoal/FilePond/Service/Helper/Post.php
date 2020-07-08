<?php

namespace Charcoal\FilePond\Service\Helper;

/**
 * FilePond Post
 */
class Post
{
    use FileHelperTrait;

    /**
     * @var string
     */
    private $format;

    /**
     * @var array|null
     */
    private $values;

    /**
     * Post constructor.
     * @param string $entry The entry.
     */
    public function __construct($entry)
    {
        if (isset($_FILES[$entry])) {
            $this->values = $this->toArrayOfFiles($_FILES[$entry]);
            $this->format = 'FILE_OBJECTS';
        }
        if (isset($_POST[$entry])) {
            $this->values = $this->toArray($_POST[$entry]);
            if ($this->isEncodedFile($this->values[0])) {
                $this->format = 'BASE64_ENCODED_FILE_OBJECTS';
            } else {
                $this->format = 'TRANSFER_IDS';
            }
        }
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return array|array[]|null
     */
    public function getValues()
    {
        return $this->values;
    }
}
