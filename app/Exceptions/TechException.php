<?php namespace App\Exceptions;

class TechException extends \Exception {

    protected $xml;

    public function __construct($message = "", string $xml = '', $previous = null)
    {
        $this->xml = $xml;
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return string
     */
    public function getXml(): string
    {
        return beautifyXml($this->xml);
    }
}
