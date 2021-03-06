<?php

/**
 * Smarty Internal Plugin
 *
 * @package Smarty\Exception
 */

/**
 * Smarty compiler exception class
 *
 * @package Smarty\Exception
 */
class Smarty_Exception_Compiler extends Smarty_Exception
{

    public $no_escape = true;

    /**
     * @return string
     */
    public function __toString()
    {
        // TODO
        // NOTE: PHP does escape \n and HTML tags on return. For this reasion we echo the message.
        // This needs to be investigated later.
        echo "Compiler: {$this->message}";

        return '';
    }

}
