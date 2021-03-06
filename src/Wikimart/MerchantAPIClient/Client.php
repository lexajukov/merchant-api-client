<?php

namespace Wikimart\MerchantAPIClient;

use DateTime;
use InvalidArgumentException;
use SimpleXMLElement;

class Client
{
    const API_PATH = '/api/1.0/';

    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';
    const METHOD_DELETE = 'DELETE';

    const STATUS_OPENED    = 'opened';
    const STATUS_CANCELED  = 'canceled';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_ANNULED   = 'annuled';
    const STATUS_INVALID   = 'invalid';
    const STATUS_FAKED     = 'faked';
    
    const DATA_JSON        = 'json';
    const DATA_XML         = 'xml';

    private $validStatuses = array(
        self::STATUS_OPENED,
        self::STATUS_CANCELED,
        self::STATUS_REJECTED,
        self::STATUS_CONFIRMED,
        self::STATUS_ANNULED,
        self::STATUS_INVALID,
        self::STATUS_FAKED
    );
    
    private $valideDataFormat = array(
        self::DATA_JSON,
        self::DATA_XML
    );

    const VERSION          = '1.0';

    /**
     * @var string Хост
     */
    protected $host;

    /**
     * @var string Идентификатор доступа
     */
    protected $accessId;

    /**
     * @var string Секретный ключ
     */
    protected $secretKey;
    
    /**
    * @var string Тип отправляемых и получаемых данных (json,xml)
    */
    protected $dataType;

    /**
     * @param $host      Хост Wikimart merchant API
     * @param $appID     Идентификатор доступа
     * @param $appSecret Секретный ключ
     * @param string $dataType Тип данных
     * @throws InvalidArgumentException
     */
    public function __construct( $host, $appID, $appSecret, $dataType = self::DATA_JSON )
    {
        $this->host      = $host;
        $this->accessId  = $appID;
        $this->secretKey = $appSecret;
        
        if ( !in_array( $dataType, $this->valideDataFormat ) ) {
            throw new InvalidArgumentException( 'Valid values for data type is: ' . implode( ', ', $this->valideDataFormat ) );
        }
        
        $this->dataType  = $dataType;
    }

    /**
     * Возвращает хост Wikimart merchant API
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getAccessId()
    {
        return $this->accessId;
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }
    
    /**
     * @return string
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * @param string $URI
     * @param string $method Метод HTTP запроса. Может принимать значения: 'GET', 'POST', 'PUT', 'DELETE'.
     * @param string $body
     *
     * @return \Wikimart\MerchantAPIClient\Response
     * @throws MerchantAPIException
     * @throws InvalidArgumentException
     */
    public function api( $URI, $method, $body = null )
    {
        if ( !is_string( $URI ) ) {
            throw new InvalidArgumentException( 'Argument \'$URI\' must be string' );
        }

        if ( !is_string( $method ) ) {
            throw new InvalidArgumentException( 'Argument \'$method\' must be string' );
        }

        $validMethod = array( self::METHOD_GET, self::METHOD_POST, self::METHOD_PUT, self::METHOD_DELETE );
        if ( !in_array( $method, $validMethod ) ) {
            throw new InvalidArgumentException( 'Valid values for argument \'$method\' is: ' . implode( ', ', $validMethod ) );
        }

        if ( $body !== null && !is_string( $body ) ) {
            throw new InvalidArgumentException( 'Argument \'$body\' must be string' );
        }

        $date = new DateTime();

        $curl = curl_init( $this->host . $URI );
        $headers = array(
            'User-agent: Mozilla/5.0 (compatible; Wikimart-MerchantAPIClient/'. self::VERSION .'; +vitaliy.tolmachev@wikimart.ru)',
            'Accept: application/' . $this->getDataType(),
            'X-WM-Date: ' . $date->format( DATE_RFC2822 ),
            'X-WM-Authentication: ' . $this->getAccessId() . ':' . $this->generateSignature( $URI, $method, $body, $date )
        );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        switch ( $method ) {
            case self::METHOD_GET:
                break;
            case self::METHOD_POST:
                $headers[] = 'Content-Type: application/' . $this->getDataType();
                curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
                break;
            case self::METHOD_PUT:
                $headers[] = 'Content-Type: application/' . $this->getDataType();
                curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
                break;
            case self::METHOD_DELETE:
                curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'DELETE' );
                break;
        }
        curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
        $httpResponse = curl_exec( $curl );
        if ( $httpResponse === false ) {
            throw new MerchantAPIException( 'Can`t get response' );
        }
        $httpCode     = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
        curl_close( $curl );

        $data = $httpResponse;
        if ( $decoded = json_decode( $httpResponse ) ) {
            $data = $decoded;
        }

        $error = null;
        if ( $httpCode !== 200 ) {
            if ( is_object( $data ) && property_exists( $data, 'message' ) ) {
                $error = $data->message;
            }
        }
        $response = new Response( $data, $httpCode, $error );
        return $response;
    }

    /**
     * @param string    $URI
     * @param string    $method
     * @param string    $body
     * @param DateTime $date
     *
     * @return string
     */
    protected function generateSignature( $URI, $method, $body, DateTime $date )
    {
        $stringToHash = $method . "\n"
            . md5( $body ) . "\n"
            . $date->format( DATE_RFC2822 ) . "\n"
            . $URI;
        return hash_hmac( 'sha1', $stringToHash, $this->getSecretKey() );
    }

    /**
     * Получение информации о заказе
     *
     * @param integer $orderID Идентификатор заказа
     *
     * @return \Wikimart\MerchantAPIClient\Response
     * @throws InvalidArgumentException
     */
    public function methodGetOrder( $orderID )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        return $this->api( self::API_PATH . 'orders/' . $orderID, self::METHOD_GET );
    }

    /**
     * Получение списка заказов
     *
     * @param integer          $pageSize           Колличество возвращаемых заказов на "странице"
     * @param integer          $page               Порядковый номер "страницы" (начиная с 1)
     * @param null|string      $status             Фильтр по статусам. Может принимать значения: opened (Новые),
     *                                             canceled (Отменённые), rejected (Не принятые), confirmed (Принятые),
     *                                             annuled (Аннулированные), invalid (Ошибки Викимарта), faked (Фейковые)
     * @param null|DateTime   $transitionDateFrom Начало диапазона времени изменения статуса заказа
     * @param null|DateTime   $transitionDateTo   Конец диапозона времени изменения статуса заказа
     * @param null|string      $transitionStatus
     *
     * @return \Wikimart\MerchantAPIClient\Response
     * @throws InvalidArgumentException
     */
    public function methodGetOrderList( $pageSize, $page, $status = null, DateTime $transitionDateFrom = null, DateTime $transitionDateTo = null, $transitionStatus = null )
    {
        $params = array();

        if ( !is_integer( $pageSize ) ) {
            throw new InvalidArgumentException( 'Argument \'$pageSize\' must be integer' );
        } else {
            $params['pageSize'] = $pageSize;
        }

        if ( !is_integer( $page ) ) {
            throw new InvalidArgumentException( 'Argument \'$page\' must be integer' );
        } else {
            $params['page'] = $page;
        }

        if ( !is_null( $status ) ) {
            if ( !in_array( $status, $this->validStatuses ) ) {
                throw new InvalidArgumentException( 'Valid values for argument \'$status\' is: ' . implode( ', ', $this->validStatuses ) );
            } else {
                $params['status'] = $status;
            }
        }

        if ( !is_null( $transitionDateFrom ) ) {
            $params['transitionDateFrom'] = $transitionDateFrom->format( 'c' );
        }

        if ( !is_null( $transitionDateTo ) ) {
            $params['transitionDateTo'] = $transitionDateTo->format( 'c' );
        }

        if ( !is_null( $transitionStatus ) ) {
            if ( !in_array( $transitionStatus, $this->validStatuses ) ) {
                throw new InvalidArgumentException( 'Valid values for argument \'$transitionStatus\' is: ' . implode( ', ', $this->validStatuses ) );
            } else {
                $params['transitionStatus'] = $transitionStatus;
            }
        }
        return $this->api( self::API_PATH . 'orders?' . http_build_query( $params ), self::METHOD_GET );
    }

    /**
     * Получение списка причин для смены статуса
     *
     * @param $orderID Идентификатор заказа
     *
     * @return \Wikimart\MerchantAPIClient\Response
     * @throws InvalidArgumentException
     */
    public function methodGetOrderStatusReasons( $orderID )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        return $this->api( "/api/1.0/orders/$orderID/transitions", self::METHOD_GET );
    }

    public function methodSetOrderStatus( $orderID, $status, $reasonID, $comment )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }

        if ( !in_array( $status, $this->validStatuses ) ) {
            throw new InvalidArgumentException( 'Valid values for argument \'$status\' is: ' . implode( ', ', $this->validStatuses ) );
        }

        if ( !is_integer( $reasonID ) ) {
            throw new InvalidArgumentException( 'Argument \'$reasonID\' must be integer' );
        }

        if ( !is_string( $comment ) ) {
            throw new InvalidArgumentException( 'Argument \'$comment\' must be string' );
        }

        $putBody = array(
            'status' => $status,
            'reasonId' => $reasonID,
            'comment' => $comment,
        );
        return $this->api( self::API_PATH . "orders/$orderID/status", self::METHOD_PUT, json_encode( $putBody ) );
    }

    /**
     * Получение истории смены статусов заказа
     *
     * @param $orderID
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    public function methodGetOrderStatusHistory( $orderID )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        return $this->api( self::API_PATH . "orders/$orderID/statuses", self::METHOD_GET );
    }
    
    /**
     * Добавление комментария к заказу
     * 
     * @param $orderID
     * @param $comment
     * 
     * @return Response
     * @throws InvalidArgumentException
     */
    public function methodOrderAddComment( $orderID, $comment )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        
        if ( !is_string( $comment ) ) {
            throw new InvalidArgumentException( 'Argument \'$comment\' must be string' );
        }
        
        $postBody = '';
        
        if( $this->getDataType() == self::DATA_JSON ) {
            $postBody = json_encode( array( 'text' => $comment ) );
        } else {
            $postBody = '<?xml version="1.0" encoding="UTF-8"?>
                             <request>
                                 <text>
                                	 <![CDATA[' . $comment . ']]>
                                 </text>
                        	 </request>';
        }
        
        return $this->api( self::API_PATH . "orders/$orderID/comments", self::METHOD_POST, $postBody );
                
    }
    
    /**
     * Получение комментариев заказа
     * 
     * @param $orderID
     * 
     * @return Response
     * @throws InvalidArgumentException
     */
    public function methodOrderGetComments( $orderID )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }

        return $this->api( self::API_PATH . "orders/$orderID/comments", self::METHOD_GET );
    }

    /**
     * Регистрация почтового отправления
     *
     * @param int                  $orderID
     * @param Entities\PostPackage $package
     *
     * @return Response
     *
     * @throws InvalidArgumentException
     */
    public function methodRegisterPostPackage( $orderID, Entities\PostPackage $package )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        $postBody = null;
        if ( $this->getDataType() == static::DATA_JSON ) {
            $postBody = json_encode( $package->getAttributes() );
        } else {
            $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><request></request>' );
            $xml->addChild( 'service', $package->getService() );
            $xml->addChild( 'packageId', $package->getPackageId() );
            $items = $xml->addChild( 'items' );
            foreach( $package->getItems() as $postPackageItem ) {
                $item = $items->addChild( 'item' );
                $item->addChild( 'name', $postPackageItem->getName() );
                $item->addChild( 'quantity', $postPackageItem->getQuantity() );
            }
            $postBody = $xml->asXML();
        }

        return $this->api( self::API_PATH . "orders/$orderID/packages", static::METHOD_POST, $postBody );
    }

    /**
     * Изменение статуса доставки заказа
     *
     * @param int      $orderID
     * @param string   $state
     * @param DateTime $datetime
     *
     * @return Response
     *
     * @throws InvalidArgumentException
     */
    public function methodSetOrderDeliveryState( $orderID, $state, DateTime $datetime = null )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        if ( !is_string( $state ) || mb_strlen( $state ) > 50 ) {
            throw new InvalidArgumentException( 'Argument \'$state\' must be string. Max length is 50 characters');
        }
        if ( !( $datetime instanceof DateTime ) ) {
            $datetime = new DateTime();
        }
        $putBody = $this->getBodyForStateUpdate( $state, $datetime );
        return $this->api( self::API_PATH . "orders/$orderID/deliverystatus", static::METHOD_PUT, $putBody );
    }

    /**
     * Получение списка отправлений по заказу
     *
     * @param int $orderID
     *
     * @return Response
     *
     * @throws InvalidArgumentException
     */
    public function methodGetOrderPackages( $orderID )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        return $this->api( self::API_PATH . "orders/$orderID/packages", static::METHOD_GET );
    }

    /**
     * Обновление статуса отправления
     *
     * @param int      $orderID
     * @param int      $packageID
     * @param string   $state
     * @param DateTime $datetime
     *
     * @return \Wikimart\MerchantAPIClient\Response
     *
     * @throws \InvalidArgumentException
     */
    public function methodSetOrderPackageState( $orderID, $packageID, $state, DateTime $datetime = null )
    {
        if ( !is_integer( $orderID ) ) {
            throw new InvalidArgumentException( 'Argument \'$orderID\' must be integer' );
        }
        if ( !is_integer( $packageID ) ) {
            throw new InvalidArgumentException( 'Argument \'$packageID\' must be integer' );
        }
        if ( !is_string( $state ) ) {
            throw new InvalidArgumentException( 'Argument \'$state\' must be integer' );
        }
        if ( !( $datetime instanceof DateTime ) ) {
            $datetime = new DateTime();
        }
        $putBody = $this->getBodyForStateUpdate( $state, $datetime );
        return $this->api( self::API_PATH . "orders/$orderID/packages/$packageID/states", static::METHOD_PUT, $putBody );
    }

    /**
     * @param string   $state
     * @param DateTime $datetime
     *
     * @return string
     */
    protected function getBodyForStateUpdate( $state, DateTime $datetime )
    {
        $body = null;
        if ( $this->getDataType() == static::DATA_JSON ) {
            $body = json_encode( array(
                'state'      => $state,
                'updateTime' => $datetime->format( DATE_W3C )
            ) );
        } else {
            $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><request></request>' );
            $xml->addChild( 'state', $state );
            $xml->addChild( 'updateTime', $datetime->format( DATE_W3C ) );
            $body = $xml->asXML();
        }
        return $body;
    }
}