<?php

namespace Paymark\PaymarkClick\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Laminas\Http\Request;
use Laminas\Http\Client;
use Laminas\Http\Exception\RuntimeException;

class PaymarkAPI
{

    /**
     * @var \Paymark\PaymarkClick\Helper\Helper
     */
    private $_helper;

    /**
     * @var boolean
     */
    private $_prod = false;

    /**
     * @var string
     */
    private $_prodUrl = 'https://secure.paymarkclick.co.nz/api/';

    /**
     * @var string
     */
    private $_devUrl = 'https://uat.paymarkclick.co.nz/api/';

    /**
     * @var Client
     */
    private $_client;

    /**
     * @var string
     */
    private $_username;

    /**
     * @var string
     */
    private $_accountId;

    /**
     * @var string
     */
    private $_password;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var int
     */
    private $_statusCode;

    const CMD_CLICK = '_xclick';

    /**
     * ApiHelper constructor.
     *
     * @param Client $requestClient
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Client $requestClient,
        EncryptorInterface $encryptor
    )
    {
        $this->_encryptor = $encryptor;

        $this->_client = $requestClient;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->_helper = $objectManager->create("\Paymark\PaymarkClick\Helper\Helper");

        $this->_prod = $this->_helper->getConfig('debug') == 0 ? true : false;

        $this->_username = $this->_helper->getConfig('user');

        $this->_password = $this->_encryptor->decrypt($this->_helper->getConfig('password'));

        $this->_accountId = $this->_helper->getConfig('account');
    }

    /**
     * Generate payment URL for redirect
     *
     * @param $value
     * @param $return
     * @param string $reference
     * @param string $particular
     * @param boolean $auth
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function createTransaction(
        $value,
        $return,
        $reference = 'Magento Payment',
        $particular = 'Magento Payment',
        $auth = false)
    {
        return $this->call(Request::METHOD_POST, 'webpayments/paymentservice/rest/WPRequest', [
            'type' => $auth ? 'authorisation' : 'purchase',
            'amount' => $value,
            'reference' => $reference,
            'particular' => $particular,
            'return_url' => $return
        ]);
    }

    /**
     * Get transaction details from transaction id
     *
     * @param $transactionId
     * @return mixed
     */
    public function getTransaction($transactionId)
    {
        return $this->call(Request::METHOD_GET, 'transaction/search/' . $transactionId, [], true);
    }

    /**
     * Search for transactions using transaction id
     *
     * @param $transactionId
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    public function searchTransaction($startDate, $endDate, $transactionId)
    {
        return $this->call(Request::METHOD_GET, 'transaction/search/', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'particular' => $transactionId
        ], true);
    }

    /**
     * Call remote API
     *
     * @param $method
     * @param null $uri
     * @param array $params
     * @param bool $authorise
     * @return mixed
     * @throws \Exception
     */
    public function call($method, $uri = null, array $params = [], $authorise = false)
    {
        $this->_client->reset();

        if($authorise) {
            $this->doAuthorise();
        } else {
            $params = $this->getOptions($params);
        }

        $baseUrl = $this->_prod ? $this->_prodUrl : $this->_devUrl;

        try {

            $this->_client->setUri($baseUrl . $uri);

            $this->_client->setMethod($method);

            if($method == Request::METHOD_GET) {
                $this->_client->setParameterGet($params);
            } elseif($method == Request::METHOD_POST) {
                $this->_client->setParameterPost($params);
            }

            $this->_client->send();

            $response = $this->_client->getResponse();

        } catch (RuntimeException $e) {
            $this->_helper->log('Laminas client error: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            $this->_helper->log('Laminas client error: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        // parse body
        $responseData = $this->parseBody($response->getBody());

        // http error code
        $this->setStatusCode($response->getStatusCode());

        switch ($response->getStatusCode()) {
            case 200:
            case 201:
            case 204:
                return $responseData;

            default:
                $message = '[' . $responseData->errornumber . '] ' . $responseData->errormessage;
                throw new \Exception($message, $response->getStatusCode());
        }
    }

    /**
     * Apply auth header to Client
     */
    private function doAuthorise()
    {
        $this->_client->setAuth($this->_username, $this->_password);
    }

    /**
     * Convert response body to JSON or XML
     *
     * @param $body
     * @return mixed
     */
    private function parseBody($body)
    {
        $result = json_decode($body);
        if ($result == NULL) {
            //couldn't decode as json, try xml

            try {
                $result = new \SimpleXMLElement((string)$body);
            } catch (\Exception $e) {
                $result = '';
            }
        }

        return $result;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private function getOptions(array $params = [])
    {
        $params = array_merge([
            'cmd' => self::CMD_CLICK,
            'username' => $this->_username,
            'account_id' => $this->_accountId,
            'password' => $this->_password
        ], $params);

        return $params;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->_statusCode;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode($statusCode)
    {
        $this->_statusCode = $statusCode;
    }


}
