<?php
/**
 * Give access to helper functions for building signed urls for interacting with the PayNearMe API
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Helper class for building signed URL's for performing paynearme api requests */
class PaynearmeRequest {
    private $site_identifier;
    private $secret;
    private $version;
    private $timestamp;
    private $params;

    public function __construct( $site_identifier, $secret, $version = '2.0', $timestamp = null ) {
        $this->site_identifier  = $site_identifier;
        $this->secret           = $secret;
        $this->version          = $version;
        $this->timestamp        = $timestamp;

        $this->params = array();
    }

    public function addParam( $param, $value ) {
        $this->params[$param] = $value;
        return $this;
    }

    public function signedParams() {
        $this->params['site_identifier'] = $this->site_identifier;
        $this->params['version'] = $this->version;

        if ( ! array_key_exists( 'timestamp', $this->params ) ) {
            $this->params['times'] = $this->timestamp;
        }
        $this->params['signature'] = $this->signature();

        return $this->params;
    }

    public function queryString() {
        $pairs = array();

        foreach( $this->signedParams() as $key => $value ) {
            $pairs[] = "$key=$value";
        }

        return implode( '&', $pairs );
    }

    public function signature() {
        $str = '';
        ksort( $this->params );

        foreach( $this->params as $key => $value ) {
            if ( ! in_array( $key, ['signature', 'datafile'] ) ) {
                $str .= "$key$value";
            }
        }
        return md5( $str . $this->secret );
    }
}
