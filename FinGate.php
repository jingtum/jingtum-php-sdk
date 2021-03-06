<?php
/**
 * PHP SDK for Jingtum network; FinGate 银关类
 * @version 1.0.0
 * Copyright (C) 2016 by Jingtum Inc.
 * or its affiliates. All rights reserved.
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with
 * the License. A copy of the License is located at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * FunctionName	Descriptions
 * getActivateAmount	获取当前激活SWT数量
 * getApiVersion	获得API版本
 * getPathRate		获取当前设定的路径费用
 * getPrefix		获得前缀
 * getServerInfo	获得当前服务器信息
 * getTrustLimit	获得信用上限
 *
 * setActivateAmount	设定激活的SWT数量
 * setApiVersion	设定使用API的版本
 * setConfig		设定银关的配置
 * setFinGate		设置商户银关账号
 * setPathRate		设置支付路径的可承受比例
 * setPrefix		设置交易流水号前缀
 * setServerInfo	设置api服务器
 * setMode  		设置测试模式
 * setTrustLimit	设置银关默认的信任额度
 * setWebSocketServer	设置Websocket服务器
 * 
 */
namespace JingtumSDK;


use JingtumSDK\lib\SnsNetwork;
use JingtumSDK\lib\ECDSA;
use JingtumSDK\AccountClass;
//use JingtumSDK\Wallet;
use JingtumSDK\APIServer;
use JingtumSDK\TumServer;
use WebSocket\Client;

require_once 'vendor/autoload.php';
require_once './lib/ECDSA.php';
require_once './lib/SnsNetwork.php';
require_once './lib/ConfigUtil.php';
require_once './lib/Constants.php';
require_once './lib/DataCheck.php';
require_once 'AccountClass.php';
require_once 'Server.php';

/**
 * require PHP install the cURL extension.
 */
if (! function_exists('curl_init')) {
    throw new Exception('JingtumSDK needs the cURL PHP extension.');
}

/**
 * Make sure the PHP version supports JSON,
 * otherwise need to upgrade to PHP 5.2.x.
 */
if (! function_exists('json_decode')) {
    throw new Exception('JingtumSDK needs the JSON PHP extension.');
}

//class FinGate extends AccountClass 
class FinGate extends AccountClass
{
    //internal prefix to create transaction ID
    private $prefix = 'prefix';

    //internal counter to generate transaction ID
    private $uuid = 0;

    //The amount of SWT to active one Jingtum account
    private $activation_amount = MIN_ACT_AMOUNT;

    //api and tum_server
    private $tum_server = NULL;
    private $api_server = NULL;
    private $websocket_server = NULL;

    //Variables used to issue custom Tum
    private $token = '';

    private $sign_key = '';

    //Internal wallet object used to active 
    //0 - production mode
    //1 - develop mode
    //2 - other mode, reserved

    const DEVELOPMENT = 1;

    const PRODUCTION = 0;


    /**
     * 静态成品变量 保存全局实例
     */
    private static  $_instance = NULL;

    //use Singleton mode
    //The constructor __construct() is declared as protected to prevent creating 
    //a new instance outside of the class via the new operator.
    protected function __construct()//$secret，$address = NULL)
    {
      parent::__construct($secret=NULL, $address=NULL);

      //Restart the UUID 
      $this->uuid = 0;

      //Set default value
      $this->prefix = 'prefix'; 

      //set Tum server
      $this->tum_server = TumServer::getInstance();
      $this->api_server = APIServer::getInstance();
      $this->websocket_server = WebSocketServer::getInstance();

    }

    /**
     * static，返还此类的唯一实例
     */
    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new FinGate();
        }
 
        return self::$_instance;
    }

    /**
     * 防止用户克隆实例，使用private
     */
    private function __clone(){
        die('Clone is not allowed.' . E_USER_ERROR);
    }

    /**
     * 防止unserializing of an instance of the class via the global function unserialize() .使用private
     */
    private function __wakeup(){
        die('unserialize is not allowed.' . E_USER_ERROR);
    }


    //Setup an account for FinGate
    public function setAccount($in_secret, $in_address=null)
    {
       parent::__construct($in_secret, $in_address);

    }


    /**
     * This value is set in the configure file.
     * Should return the websocket server.
     * @return the websocket server instance
     * 
     */
    public function getWebsocketServer()
    {
        return $this->websocket_server;
    }
    
    /**
     * This value is set in the configure file.
     * Should return the API server.
     * @return the API server instance
     * 
     */
    public function getAPIServer()
    {
        return $this->api_server;
    }

    /**
     * This value is set in the configure file.
     * Should not less than 20 SWT.
     * @return the ActivateAmount
     * 
     */
    public function getActivateAmount()
    {
        return $this->activation_amount;
    }

    //Return a uuid from the API SERVER
    //Change it to use prefix and UNIX time
    //Format as the follows:
    //prefix.yyyymmddHHMMss.000000
    //
    public function getClientResourceID()
    {
      //API /v1/uuid，GET method
      //Increase the internal counter by 1
      $id = sprintf("%06d",++$this->uuid);
      //keep it between 1 and 999999
      if ( $this->uuid > 999999 )
        $this->uuid = 0;

      return $this->prefix.time().$id;
    }

    /**
     *
     * @return the $token
     * getCustom() -> getToken
     */
    public function getToken()
    {
        return $this->token;
    }

     /**
     *
     * @return the $getTrustLimit
     */
    public function getTrustLimit()
    {
        return $this->trust_limit;
    }

    /**
     * Return the signKey for the FinGate
     * @return the $sign_key
     * getCustomSecret() -> getKey
     */
    public function getKey()
    {
        return $this->sign_key;
    }

    /**
     * 获得前缀
     * @return the prefix
     */
    public function getPrefix()
    {
        return $this->prefix;
    }
    
    /**
     * 获取当前设定的路径费用
     * @return the path rate
     */
     public function getPathRate()
    {
        return $this->path_rate;
    }
    
     /**
     * 设定当前的路径费用
     * @return path rate of FinGate 
     */
    public function setPathRate($in_rate)
    {
        $this->path_rate = $in_rate;
        return true;
    }
    
    /**
     * 设定前缀
     * @return the Transaction prefix
     */
    public function setPrefix($in_prefix)
    {
        $this->prefix = $in_prefix;
    }
    
    
    /**
     * Custom ID
     * @param string $token 
     * setCustom -> setToken
     */
    public function setToken($in_token)
    {
        $this->token = $in_token;
    }
    
     /**
     * 设置测试模式
     * Set the API/Tum Server to 
     * the correct settings
     */
    public function setMode($in_mode)
    {
        //default mode is production = 0
        $this->api_server->setMode($in_mode);
        $this->tum_server->setMode($in_mode);
        $this->websocket_server->setMode($in_mode);
   
    }
    
    /**
     *
     * @param string $in_limit 
     * setTrustLimit 
     */
    public function setTrustLimit($in_limit)
    {
        $this->trust_limit = $in_limit;
    }
    /**
     *
     * @param string $sign_key
     * setCustomSecret -> setSignKey
     */
    public function setKey($sign_key)
    {
        $this->sign_key = $sign_key;
    }

    /**
     *
     * set the ActivateAmount from the input
     * 
     */
    public function setActivateAmount($in_amount)
    {
      if ( is_numeric($in_amount) && $in_amount >= MIN_ACT_AMOUNT )
        $this->activation_amount = $in_amount;
      else
        throw new Exception('Invalid activation acmount');
    }
    
     /**
      * Use the input info to setup the finGate configurations.
      * @param token, 商户标识
      * sign_key，商户签名
      *  
     */
    public function setConfig($in_token, $in_sign_key)
    {
        $this->token = $in_token;
        $this->sign_key = $in_sign_key;
    }
    


    /**
     *
     * @param $uuid: an unique ID for the transaction to identify 
     *          can use get_uuid function to generate this number.
     *        This number will be used to find the Issue information
     * about the Tum issued.
     * @param $currency ID, 40 characters 
     * @param unknown $amount, string, 
     * @param unknown $address            
     * @return boolean
     * issueCustom --> issueCustomTum
     * 01/17/2017
     * Added an input address to receive the issuing tum.
     */
    public function issueCustomTum($currency, $amount, $uuid, $in_address)
    {
        //Need to convert the input into a float with two decimal
        $formatted_amount = sprintf("%01.2f", $amount);
       
        $params['cmd'] = ISSUE_TUM;
        $params['custom'] = $this->token;
        $params['order'] = $uuid;
        $params['currency'] = $currency;
        $params['amount'] = $formatted_amount;
        $params['account'] = $in_address;
 
        $hmac = $params['cmd'].$params['custom']. $uuid . $currency . $formatted_amount.$in_address;

        $params['hmac'] = hash_hmac('md5', $hmac, $this->sign_key);
        
        $cmd['method'] = 'POST';
        $cmd['params'] = $params;
        $cmd['url'] = '/v1/business/node';        

        return $this->tum_server->submitRequest($cmd);

    }

    /**
     * QueryIssue 
     * @param unknown $uuid            
     * @return multitype:
     */
    public function queryIssue($uuid)
    {
        $params['cmd'] = QUERY_ISSUE;
        $params['custom'] = $this->token;
        $params['order'] = $uuid;

        //Generate the hmac using the info and 
        //the custom sign key
        $hmac = QUERY_ISSUE . $this->token . $uuid;
        $params['hmac'] = hash_hmac('md5', $hmac, $this->sign_key);
        
        
        $cmd['method'] = 'POST'; 
        $cmd['params'] = $params;
        $cmd['url'] = '/v1/business/node';//QUERY_ISSUE_API; 

        return $this->tum_server->submitRequest($cmd);
    }

    /**
     * Return the custom Tum information
     * at the present time.
     * @param Input Tum code $currency            
     * @return array with the following format:
     * 
     */
    public function queryCustomTum($in_tum)
    {
        //Get the current UNIX time in seconds.
        $cur_time = time();

        $params['cmd'] = QUERY_TUM;
        $params['custom'] = $this->token;
        $params['currency'] = $in_tum;
        $params['date'] = $cur_time;

        //Generate the hmac using the info and
        //the custom sign key
        $hmac = QUERY_TUM. $this->token.$in_tum.$cur_time;
        $params['hmac'] = hash_hmac('md5', $hmac, $this->sign_key);

        //Setup the command
        $cmd['method'] = 'POST';
        $cmd['params'] = $params;
        $cmd['url'] = '/v1/business/node';
 
        return $this->tum_server->submitRequest($cmd);
    }

    /**
     * activeWallet 
     * @param $dest_address wallet address to active            
     * @return multitype:
    */
    public function activeWallet($dest_address, $call_back_func = NULL)
    {
        if ( empty($this->secret) )
            throw new Exception("Need to set FinGate account");
            
        $amount['currency'] = 'SWT';
        $amount['value'] = strval($this->activation_amount);
        $amount['issuer'] = '';

        //No path needed for the SWT payment
        $payment['destination_amount'] = $amount;
        $payment['source_account'] = $this->address;
        $payment['destination_account'] = $dest_address;
        $payment['payment_paths'] = '';

        
        //parameter info to submit, use internal client resource id
        $params['secret'] = $this->secret;
        $params['client_resource_id'] = $this->getClientResourceID();
        $params['payment'] = $payment;
        
        //Setupt the URL and parameters, only used asyn for this
        //operation
        $cmd['method'] = 'POST';
        $cmd['params'] = $params;
        $cmd['url'] = str_replace("{0}", $this->address, PAYMENTS) . '?validated=true';
         if ($call_back_func)
        {
          $call_back_func($this->api_server->submitRequest($cmd, $this->address, $this->secret));
        }
        else
          return $this->api_server->submitRequest($cmd, $this->address, $this->secret);
    }
 
    /**
     * Generate a pair of wallet locally
     * using the ECDSA lib functions.
     * This should replace the API function
     * to get the wallet in paire. 
     * @return an object with two attributes.
     *
    */
    public function createWallet()
    {
        $ecdsa = new ECDSA();
        $ecdsa->generateRandomPrivateKey();

        $secret = $ecdsa->getWif();
        $address = $ecdsa->getAddress();

        $ret = new Wallet($secret, $address);

        return $ret;

      //API /v1/uuid，GET method
        $ret = $this->api_server->getNewWalletFromServer();
        if ( $ret['success'] == true ){
        $wt = new Wallet($ret['wallet']['secret'], $ret['wallet']['address']);
        return $wt;
        }
        else{
          throw new Exception('Error in creating the new Wallet');
          
        }
    }

    /**
     * getOrderBook 
     * Change the return to
     * @param $dest_address wallet address to active            
     * @return multitype:
    */
    public function getOrderBook($in_str, $call_back_func = NULL)
    {
        //decode the in_pair to base and counter
        try {
          if ( is_string($in_str)) {
          $pair = explode('/', $in_str);

          if (count($pair) != 2)
            throw new Exception("Input should have a pair", 1);
          
            //echo "set taker pays $pair[0]\n";

            $base = getTumfromPair($pair[0]);
            $counter = getTumfromPair($pair[1]);
          $str = str_replace(':', '+',$in_str);


          $cmd['method'] = 'GET';
          $cmd['url'] = str_replace("{0}",$this->address, ORDER_BOOK).$str;
          //.$base['currency']. '+'.
          //$base['counterparty'].'/'. $counter['currency']. '+'.$counter['counterparty'];
          $cmd['params'] = '';
        
          if ( is_object($this->api_server))
          {

            //use call back function if any
            if ($call_back_func)
            {
              $call_back_func(convertOrderBook($this->api_server->submitRequest($cmd, $this->address, $this->secret)));
            }
            else
              return convertOrderBook(convertOrderBook($this->api_server->submitRequest($cmd, $this->address, $this->secret)));//$this->api_server->submitRequest($cmd, $this->address, $this->secret);
          }
          else
            throw new Exception('API Server is not ready!');
          }else
            throw new Exception('Input is not a String!');
        } catch (Exception $e) {
            print "Cannot get the order book:".$e->getMessage();
        }

    }
}
?>
