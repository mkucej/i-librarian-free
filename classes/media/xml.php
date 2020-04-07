<?php

namespace Librarian\Media;

use Exception;
use SimpleXMLElement;
use SimpleXMLIterator;

final class Xml {

    public function repair($string) {

        // remove invalid XML UTF-8 characters.
        $string = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
        $string = preg_replace('/\s{2,}/ui', ' ', $string);

        return $string;
    }

    /**
     * Load XML string.
     *
     * @param $xml_str
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function loadXmlString(string $xml_str) {

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_str);

        if ($xml === false) {

            $errors = [];

            foreach(libxml_get_errors() as $error) {

                $errors[] = $error->message;
            }

            throw new Exception(join(', ', $errors), 500);
        }

        return $xml;
    }

    /**
     * Load XML string and return iterator.
     *
     * @param string $xml_str
     * @return SimpleXMLIterator
     * @throws Exception
     */
    public function iterateXml(string $xml_str) {

        libxml_use_internal_errors(true);

        $xml = new SimpleXMLIterator($xml_str);
        $xml->rewind();

        if ($xml === false) {

            $errors = [];

            foreach(libxml_get_errors() as $error) {

                $errors[] = $error->message;
            }

            throw new Exception(join(', ', $errors), 500);
        }

        return $xml;
    }
}
