<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class PaynearmeCallback {
    protected static $EXCLUDED_PARAMS = ['signature', 'call', 'fp', 'print_buttons', 'type'];
    protected $secret = '';
    protected $params;
    protected $calculated_sig, $request_sig, $version, 
              $timestamp, $site_order_identifier,
              $pnm_order_identifier, $pnm_payment_identifier;

    function __construct( $secret = '', $params ) {
        $this->secret = $secret;
        $this->params = $params;
        $this->calculated_sig = $this->signature($params);
        $this->request_sig = $params['signature'];
        $this->version = $params['version'];
        $this->timestamp = $params['timestamp'];
        $this->site_order_identifier = $params['site_order_identifier'];
        $this->pnm_order_identifier = $params['pnm_order_identifier'];
        $this->pnm_payment_identifier = $params['pnm_payment_identifier'];
        $this->site_order_annotation = '';

        //  InvalidSignatureException
        //  With the exception of Invalid Signature and Internal Server errors, it is expected that the callback response
        //  be properly formatted XML per the PayNearMe specification.
        //
        //  This is a security exception and may highlight a configuration problem (wrong secret or siteIdentifier) OR it
        //  may highlight a possible payment injection from a source other than PayNearMe.  You may choose to notify your
        //  IT department when this error class is raised.  PayNearMe strongly recommends that your callback listeners be
        //  whitelisted to ONLY allow traffic from PayNearMe IP addresses.
        //
        //  When this class of error is raised in a production environment you may choose to not respond to PayNearMe, which
        //  will trigger a timeout exception, leading to PayNearMe to retry the callbacks up to 40 times.  If the error
        //  persists, callbacks will be suspended.
        //
        //  In development environment this default message will aid with debugging.
        //
        //  Warn if the sig is invalid, DEBUG will show valid outcomes.

        if ( ! $this->valid_signature() ) {
            HC_Logger::warn( 'PAYNEARME', "Invalid signature: $this->request_sig", null, $_GET['site_customer_identifier'] );
        }
    }

    abstract public function handleRequest();

    public function valid_signature() {
        return $this->calculated_sig === $this->request_sig;
    }

    // Test hackery - returns a response - if nil, handle normally
    protected function handle_special_condition( $arg ) {
        if (empty($arg)) {
            return null;
        } elseif (preg_match("/^confirm_delay_([0-9]+)/", $arg, $matches)) {
            sleep($matches[1]);
        } elseif ($arg == 'confirm_bad_xml') {
            return '<result';
        } elseif ($arg == 'confirm_blank') {
            return '';
        } elseif ($arg == 'confirm_redirect') {
            header('Location: /');
        }
        return null;
    }

    private function signature( $params ) {
        $str = '';
        ksort($params);

        foreach ($params as $key => $value) {
            if (!in_array($key, self::$EXCLUDED_PARAMS)) {
                $str .= "$key$value";
            }
        }
        return md5($str . $this->secret);
    }

}
