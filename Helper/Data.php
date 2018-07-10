<?php
/**
 * NDP_Monetico extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License ("OSL") v. 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category       NDP
 * @package        NDP_Monetico
 * @copyright      Copyright (c) 2017
 * @author         NDP Systèmes
 * @license        Open Software License ("OSL") v. 3.0
 */

namespace NDP\Monetico\Helper;

class Data {

    protected $ressourceConnection;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $ressourceConnection
    )
    {
        $this->ressourceConnection = $ressourceConnection;
    }


    public function setCustomerCcSaved($customerId, $value)
    {
        $query = "UPDATE customer_entity SET saved_cc = $value WHERE entity_id = $customerId";
        $this->ressourceConnection->getConnection()->query($query);
        return;
    }

    public function isCustomerCcSaved($customerId)
    {
        $query = "SELECT saved_cc FROM customer_entity WHERE entity_id = $customerId";
        $isSavedCc = $this->ressourceConnection->getConnection()->fetchOne($query);
        return $isSavedCc == "1" ? true : false;
    }

    /**
     *  Return response for Monetico payment API
     *
     *  @return	string Response string
     */
    public function getApiResponse($success)
    {
        $response = array('version=2','cdr=' . ($success ? '0' : '1'));
        echo (implode("\n", $response) . "\n");
    }

    /**
     * Encode special characters under HTML format
     *
     * @param $data String to encode
     * @return string Encoded string
     */
    public function htmlEncode($data)
    {
        $SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
        $result = "";
        for ($i = 0; $i < strlen($data); $i++) {
            if (strchr($SAFE_OUT_CHARS, $data{$i})) {
                $result .= $data{$i};
            } else if (($var = bin2hex(substr($data, $i, 1))) <= "7F") {
                $result .= "&#x" . $var . ";";
            } else
                $result .= $data{$i};
        }
        return $result;
    }

    /**
     *  Return the HMAC for a data string
     *
     * @param $data
     * @param $key
     * @return string
     */
    public function computeHmac($data, $key) {
        return strtolower(hash_hmac("sha1", $data, $this->_getUsableKey($key)));
    }

    /**
     * Return the key to be used in the HMAC function
     *
     * @param $key
     * @return string
     */
    private function _getUsableKey($key){

        $hexStrKey  = substr($key, 0, 38);
        $hexFinal   = "" . substr($key, 38, 2) . "00";

        $cca0=ord($hexFinal);

        if ($cca0>70 && $cca0<97)
            $hexStrKey .= chr($cca0-23) . substr($hexFinal, 1, 1);
        else {
            if (substr($hexFinal, 1, 1)=="M")
                $hexStrKey .= substr($hexFinal, 0, 1) . "0";
            else
                $hexStrKey .= substr($hexFinal, 0, 2);
        }

        return pack("H*", $hexStrKey);
    }
}