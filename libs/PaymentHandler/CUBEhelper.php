<?php

namespace F3CMS\PaymentHandler;

use F3CMS\Contracts\PaymentHandlerInterface;
use F3CMS\Helper;

/**
 * Helper for Cathay United Bank EPOS payment flow.
 */
class CUBEhelper extends Helper implements PaymentHandlerInterface
{
    private $_store_id = '';
    private $_cub_key = '';
    private $_gateway_url = 'https://sslpayment.uwccb.com.tw/EPOSService/Payment/OrderInitial.aspx';
    private $_soap_url = 'https://sslpayment.uwccb.com.tw/EPOSService/CRDOrderService.asmx';
    private $_logger;
    private static $debug = false;

    private $_commands = [
        'init'   => ['action' => 'Payment/OrderInitial.aspx', 'method' => 'POST'],
        'query'  => ['action' => 'CRDOrderService.asmx',       'method' => 'POST'],
        'ack'    => ['action' => 'merchant/ack',               'method' => 'POST'],
        'verify' => ['action' => 'merchant/verify',            'method' => 'POST'],
    ];

    public function __construct($storeId = null, $cubKey = null, $gatewayUrl = null)
    {
        parent::__construct();

        $this->_store_id = $storeId ?: (string)f3()->get('cube.store_id');
        $this->_cub_key  = $cubKey  ?: (string)f3()->get('cube.cub_key');

        if (!empty($gatewayUrl)) {
            $this->_gateway_url = $gatewayUrl;
        } elseif (!empty(f3()->get('cube.gateway_url'))) {
            $this->_gateway_url = (string)f3()->get('cube.gateway_url');
        }

        if (empty($this->_store_id) || empty($this->_cub_key)) {
            throw new \InvalidArgumentException('CUBE helper requires store id and CUBKEY.');
        }

        $this->_logger = new \Log('cube.log');
        $this->setDebugMode(f3()->get('DEBUG') >= 1);
    }

    public function supportsCommand($command)
    {
        return isset($this->_commands[$command]);
    }

    public function setDebugMode($enabled)
    {
        self::$debug = (bool)$enabled;
    }

    public function isDebugModeEnabled()
    {
        return (bool)self::$debug;
    }

    public function call($command, array $requestData = [], $method = 'POST')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        switch ($command) {
            case 'init':
                $order   = $requestData['order'] ?? $requestData;
                $options = $requestData['options'] ?? [];
                return $this->generatePaymentForm($order, $options);
            case 'query':
                return $this->buildOrderQueryXml(
                    $requestData['order_no'] ?? ($requestData['ORDERNUMBER'] ?? ''),
                    $requestData['amount'] ?? ($requestData['AMOUNT'] ?? null)
                );
            case 'ack':
                return $this->buildAckResponse($requestData['ret_url'] ?? $requestData['RETURL'] ?? '');
            case 'verify':
                return $this->verifyCallback(
                    $requestData['payload'] ?? $requestData['strRsXML'] ?? '',
                    $requestData['url_encoded'] ?? ($requestData['is_url_encoded'] ?? true)
                );
            default:
                return false;
        }
    }

    public function getURL($command, array $requestData, $return = 'string')
    {
        if (!$this->supportsCommand($command)) {
            return false;
        }

        $uri = $this->_gateway_url;

        if ('query' === $command) {
            $uri = $this->_soap_url;
        } elseif (('ack' === $command) || ('verify' === $command)) {
            $uri = 'merchant://' . $command;
        }

        if ('string' === $return) {
            return $uri;
        }

        return [$uri, $requestData];
    }

    /**
     * Normalize the raw order entity (like the provided JSON) into payment payload fields.
     */
    public function preparePaymentPayload(array $order, array $overrides = [])
    {
        $base = [
            'order_no'      => $order['order_no'] ?? $order['orderNo'] ?? null,
            'amount'        => $order['amount'] ?? $order['total'] ?? null,
            'language'      => $order['language'] ?? null,
            'msg_id'        => $order['msg_id'] ?? null,
            'period_number' => $order['period_number'] ?? null,
        ];

        if (empty($base['order_no']) && isset($order['id'])) {
            $base['order_no'] = sprintf('ORD%s', str_pad((string)$order['id'], 8, '0', STR_PAD_LEFT));
        }

        if (isset($order['installment']['period']) && empty($base['period_number'])) {
            $base['period_number'] = (int)$order['installment']['period'];
        }

        $payload    = array_merge($base, $overrides);
        $normalized = $this->normalizePaymentData($payload);

        $normalized['context'] = [
            'order_id'    => $order['id'] ?? null,
            'member_id'   => $order['member_id'] ?? null,
            'buyer_name'  => $order['buyer']['name'] ?? null,
            'buyer_email' => $order['buyer']['email'] ?? null,
        ];

        return $normalized;
    }

    /**
     * Build auto submit form for TRS0004/TRS0005 order initialization.
     */
    public function generatePaymentForm(array $orderData, array $options = [])
    {
        $payload = $this->normalizePaymentData($orderData, $options);
        $xml = $this->buildPaymentXml(
            $payload['msg_id'],
            $payload['order_no'],
            $payload['amount'],
            $payload['language'],
            $payload['period_number'] ?? null
        );

        return $this->renderHiddenForm($xml, $options['form_id'] ?? 'cub_epos_form');
    }

    /**
     * Convenience wrapper that takes the raw order JSON structure.
     */
    public function generatePaymentFormFromOrder(array $order, array $overrides = [])
    {
        $payload = $this->preparePaymentPayload($order, $overrides);

        return $this->generatePaymentForm($payload, $overrides);
    }

    /**
     * Expose the TRS payload body (strRqXML) for logging or debugging.
     */
    public function buildPaymentXmlBody(array $orderData, array $options = [])
    {
        $payload = $this->normalizePaymentData($orderData, $options);

        return $this->buildPaymentXml(
            $payload['msg_id'],
            $payload['order_no'],
            $payload['amount'],
            $payload['language'],
            $payload['period_number'] ?? null
        );
    }

    /**
     * Build ORD0001 order inquiry XML payload.
     */
    public function buildOrderQueryXml($orderNo, $amount)
    {
        $orderNo = $this->sanitizeOrderNumber($orderNo);
        $amount  = $this->sanitizeAmount($amount);

        $xml = new \SimpleXMLElement('<MERCHANTXML />');
        $xml->addChild('MSGID', 'ORD0001');
        $xml->addChild('CAVALUE', $this->hashFields([
            $this->_store_id,
            $orderNo,
            $amount,
        ]));

        $orderInfo = $xml->addChild('ORDERINFO');
        $orderInfo->addChild('STOREID', $this->_store_id);
        $orderInfo->addChild('ORDERNUMBER', $orderNo);
        $orderInfo->addChild('AMOUNT', $amount);

        return $xml->asXML();
    }

    /**
     * Verify callback XML (strRsXML) and return structured data or false.
     */
    public function verifyCallback($rawXml, $isUrlEncoded = true)
    {
        $payload = $isUrlEncoded ? urldecode($rawXml) : $rawXml;
        $xml = @simplexml_load_string($payload);
        if (false === $xml) {
            $this->_logger->write('CUBE verifyCallback: invalid XML');
            return false;
        }

        if (!isset($xml->ORDERINFO, $xml->AUTHINFO)) {
            $this->_logger->write('CUBE verifyCallback: missing ORDERINFO or AUTHINFO');
            return false;
        }

        if ((string)$xml->ORDERINFO->STOREID !== $this->_store_id) {
            $this->_logger->write('CUBE verifyCallback: store id mismatch');
            return false;
        }

        $expected = $this->hashFields([
            (string)$xml->ORDERINFO->STOREID,
            (string)$xml->ORDERINFO->ORDERNUMBER,
            (string)$xml->ORDERINFO->AMOUNT,
            (string)$xml->AUTHINFO->AUTHSTATUS,
            (string)$xml->AUTHINFO->AUTHCODE,
        ]);

        if (0 !== strcasecmp($expected, (string)$xml->CAVALUE)) {
            $this->_logger->write('CUBE verifyCallback: signature mismatch');
            return false;
        }

        return [
            'order_no'    => (string)$xml->ORDERINFO->ORDERNUMBER,
            'amount'      => (int)$xml->ORDERINFO->AMOUNT,
            'auth_code'   => (string)$xml->AUTHINFO->AUTHCODE,
            'auth_status' => (string)$xml->AUTHINFO->AUTHSTATUS,
            'xml'         => $payload,
        ];
    }

    /**
     * Build the XML body required by the bank once callback is received.
     */
    public function buildAckResponse($retUrl)
    {
        if (false !== strpos($retUrl, '&')) {
            throw new \InvalidArgumentException('RETURL must not contain ampersand.');
        }

        $domain = parse_url($retUrl, PHP_URL_HOST);
        if (empty($domain)) {
            throw new \InvalidArgumentException('RETURL requires a valid domain.');
        }

        $xml = new \SimpleXMLElement('<MERCHANTXML />');
        $xml->addChild('CAVALUE', $this->hashFields([$domain]));
        $xml->addChild('RETURL', $retUrl);

        return $xml->asXML();
    }

    private function buildPaymentXml($msgId, $orderNo, $amount, $language, $periodNumber = null)
    {
        $stack = [$this->_store_id, $orderNo, $amount];
        if ('TRS0005' === $msgId) {
            $stack[] = str_pad((string)$periodNumber, 2, '0', STR_PAD_LEFT);
        }
        $stack[] = $language;

        $xml = new \SimpleXMLElement('<MERCHANTXML />');
        $xml->addChild('CAVALUE', $this->hashFields($stack));
        $xml->addChild('MSGID', $msgId);

        $orderInfo = $xml->addChild('ORDERINFO');
        $orderInfo->addChild('STOREID', $this->_store_id);
        $orderInfo->addChild('ORDERNUMBER', $orderNo);
        $orderInfo->addChild('AMOUNT', $amount);
        $orderInfo->addChild('LANGUAGE', $language);

        if ('TRS0005' === $msgId && null !== $periodNumber) {
            $orderInfo->addChild('PERIODNUMBER', str_pad((string)$periodNumber, 2, '0', STR_PAD_LEFT));
        }

        return $xml->asXML();
    }

    private function renderHiddenForm($xml, $formId)
    {
        $action = htmlspecialchars($this->_gateway_url, ENT_QUOTES, 'UTF-8');
        $id     = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');
        $value  = htmlspecialchars($xml, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<form id="{$id}" action="{$action}" method="post">
    <input type="hidden" name="strRqXML" value="{$value}">
</form>
<script>document.getElementById('{$id}').submit();</script>
HTML;
    }

    private function normalizePaymentData(array $orderData, array $options = [])
    {
        $merged   = array_merge($orderData, $options);
        $msgId    = strtoupper($merged['msg_id'] ?? 'TRS0004');
        $language = strtoupper($merged['language'] ?? 'ZH-TW');
        $orderNo  = $this->sanitizeOrderNumber($merged['order_no'] ?? '');
        $amount   = $this->sanitizeAmount($merged['amount'] ?? null);

        $payload = [
            'msg_id'   => $msgId,
            'order_no' => $orderNo,
            'amount'   => $amount,
            'language' => $language,
        ];

        if ('TRS0005' === $msgId) {
            $periodNumber = (int)($merged['period_number'] ?? 0);
            if ($periodNumber < 2) {
                throw new \InvalidArgumentException('Installment transaction requires period_number >= 2.');
            }
            $payload['period_number'] = $periodNumber;
        }

        return $payload;
    }

    private function sanitizeOrderNumber($orderNo)
    {
        $orderNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$orderNo));
        if ('' === $orderNo) {
            throw new \InvalidArgumentException('Order number is required.');
        }

        return substr($orderNo, 0, 20);
    }

    private function sanitizeAmount($amount)
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Amount must be numeric.');
        }

        $amount = (int)round($amount);
        if ($amount <= 0 || $amount > 99999999) {
            throw new \InvalidArgumentException('Amount must be between 1 and 99,999,999.');
        }

        return (string)$amount;
    }

    private function hashFields(array $fields)
    {
        $fields[] = $this->_cub_key;
        $fields = array_map('strval', $fields);

        return md5(implode('', $fields));
    }
}
