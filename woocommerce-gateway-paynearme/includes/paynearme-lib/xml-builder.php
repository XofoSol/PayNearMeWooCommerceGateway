<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class XmlBuilder {
    private $doc;
    private $root;

    public function __construct( $root, $api = '2.0' ) {
        $this->doc = new DOMDocument( '1.0', 'UTF-8' );
        $this->doc->formatOutput = true;
        $api_underscored = str_replace( '.', '_', $api );
        $this->root = $this->doc->createElementNS(
            "http://www.paynearme.com/api/pnm_xmlschema_v$api_underscored", "t:$root"
        );
        $this->doc->appendChild( $this->root );
        $this->root->setAttribute( 'version', $api );
        $this->root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            "xmlns:xsi",
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $this->root->setAttribute(
            'xsi:schemaLocation',
            'http://www.paynearme.com/api/pnm_xmlschema_v2_0 pnm_xmlschema_v2_0.xsd'
        );
    }

    public function createElement( $name, $value = '' ) {
        $element = $this->doc->createElement( "t:$name", $value );
        return $element;
    }

    public function appendChild( $node ) {
        $this->root->appendChild( $node );
    }

    public function to_xml() {
        return $this->doc->saveXML();
    }
}
