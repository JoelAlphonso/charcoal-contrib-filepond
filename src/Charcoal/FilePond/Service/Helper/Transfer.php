<?php

namespace Charcoal\FilePond\Service\Helper;

/**
 * Class Transfer
 */
class Transfer
{
    use FileHelperTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $file;

    /**
     * @var array
     */
    private $variants = [];

    /**
     * @var array
     */
    private $metadata = [];

    /**
     * Transfer constructor.
     * @param boolean $id The transfer id.
     */
    public function __construct($id = false)
    {
        $this->id = ($id) ?: UniqueIdDispenser::dispense();
    }

    /**
     * @param string $file     File.
     * @param array  $variants Variants.
     * @param array  $metadata Metadata.
     * @return void
     */
    public function restore($file, array $variants = [], array $metadata = [])
    {
        $this->file     = $file;
        $this->variants = $variants;
        $this->metadata = $metadata;
    }

    /**
     * @param string $entry The entry ident.
     * @return void
     */
    public function populate($entry)
    {
        $files    = $this->toArrayOfFiles($_FILES[$entry]);
        $metadata = isset($_POST[$entry]) ? $this->toArray($_POST[$entry]) : [];
        // parse metadata
        if (count($metadata)) {
            $this->metadata = @json_decode($metadata[0]);
        }
        // files should always be available, first file is always the main file
        $this->file = $files[0];

        // if variants submitted, set to variants array
        $this->variants = array_slice($files, 1);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param callable|null $mutator Callback mutator.
     * @return array|mixed
     */
    public function getFiles($mutator = null)
    {
        $files = array_merge(isset($this->file) ? [$this->file] : [], $this->variants);
        return ($mutator === null) ? $files : call_user_func($mutator, $files, $this->metadata);
    }
}
