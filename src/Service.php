<?php
/**
 * @link https://reviewthree.com/ru-ru/installation
 */
namespace ReviewThree;

class Service {

    const METHOD_MPN = 'mpn';
    const METHOD_BARCODE = 'barcode';
    const METHOD_YMID = 'ymid';
    const METHOD_NAME = 'name';

    const RESPONSE_TYPE_JSON = 'json';
    const RESPONSE_TYPE_XML = 'xml';

    private $url = '://api.reviewthree.com/products/ai/';

    private $webstore;

    private $secure = true;

    private $responseType;

    private $useragent = 'Review3 CURL Client';

    private $logPath;

    private $cache;

    private $cachePrefix = 'review3.';

    private $cacheLifeTime = 60 * 60;

    public function __construct(string $webstore = '')
    {
        $this->webstore = $webstore;
    }

    public function setCache($cache) : Service
    {
        if (!method_exists($cache, 'set') || !method_exists($cache, 'get')) {
            throw new \Exception('Cache must support set and get methods');
        }

        $this->cache = $cache;

        return $this;
    }

    public function setCachePrefix(string $cachePrefix) : Service
    {
        $this->cachePrefix = $cachePrefix;

        return $this;
    }

    public function getCachePrefix() : string
    {
        return $this->cachePrefix;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function setCacheLifetime(int $cacheLifetime) : Service
    {
        $this->cacheLifeTime = $cacheLifetime;

        return $this;
    }

    public function getCacheLifetime() : int
    {
        return $this->cacheLifeTime;
    }

    public function getUrl() : string
    {
        return $this->url;
    }

    public function setLogPath(string $logPath) : Service
    {
        if (!is_dir($logPath)) {
            throw new \Exception('Log path not exist: ' . $logPath);
        }

        $this->logPath = $logPath;

        return $this;
    }

    public function getLogPath() : string
    {
        return $this->logPath ?? '';
    }

    public function setWebstore(string $webstore) : Service
    {
        $this->webstore = $webstore;

        return $this;
    }

    public function getWebstore() : string
    {
        if (!$this->webstore) {
            throw new \Exception('Set webstore name firstly');
        }

        return $this->webstore;
    }

    public function setSecure(bool $secure) : Service
    {
        $this->secure = $secure;

        return $this;
    }

    public function getSecure() : bool
    {
        return $this->secure;
    }

    public function setUseragent(string $useragent) : Service
    {
        $this->useragent = $useragent;

        return $this;
    }

    public function getUseragent() : string
    {
        return $this->useragent;
    }

    public function getMethods() : array
    {
        # Методы поиска
        return [
            self::METHOD_MPN,     # По артикулу производителя
            self::METHOD_BARCODE, # По штрих-коду
            self::METHOD_YMID,    # По идентификатору товара в Яндекс.Маркет
            self::METHOD_NAME,    # По имени
            $this->getWebstore(), # По id товара магазина (имя магазина является методом поиска
                                  # среди привязанных товаров этого магазина)
        ];
    }

    public function search($search, string $method = '') : int
    {
        $id = 0;

        $cacheKey = "{$this->getCachePrefix()}{$search}";

        if ($cache = $this->getCache()) {

            if ($id = $cache->get($cacheKey)) {
                return (int) $id;
            }
        }

        try {
            $response = $this->request(urlencode($search), $this->getResponseType(), $method);

            switch ($this->getResponseType()) {
                case self::RESPONSE_TYPE_XML:
                    /*
                    <Products xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
                        <Product>
                            <Category>
                                <Id>3</Id>
                                <Name>Ноутбуки</Name>
                                <IsPro>true</IsPro>
                            </Category>
                            <Id>7519</Id>
                            <Name>HP 250 G3 Black</Name>
                            <Vendor>
                                <Id>22</Id>
                                <Name>HP</Name>
                            </Vendor>
                        </Product>
                    </Products>
                     */
                    if (isset($response->Product->Id)) {
                        $id = $response->Product->Id;
                    }
                    break;

                case self::RESPONSE_TYPE_JSON:
                    /*
                    object(stdClass)#2 (8) {
                      ["id"]=>
                      int(11638)
                      ["name"]=>
                      string(27) "Sony Cyber-shot DSC-RX10 IV"
                      ["vendor"]=>
                      object(stdClass)#4 (2) {
                        ["id"]=>
                        int(11)
                        ["name"]=>
                        string(4) "Sony"
                      }
                      ["category"]=>
                      object(stdClass)#5 (3) {
                        ["isPro"]=>
                        bool(false)
                        ["id"]=>
                        int(2)
                        ["name"]=>
                        string(12) "Камеры"
                      }
                      ["rating"]=>
                      int(0)
                      ["markets"]=>
                      array(0) {
                      }
                      ["productReleaseDate"]=>
                      string(7) "2017-10"
                      ["review3ReleaseDate"]=>
                      string(10) "2017-11-20"
                    }
                     */
                    if (isset($response->id)) {
                        $id = $response->id;
                    }
                    break;
            }
        }
        catch (\Exception $e) {
            return $id;
        }

        # 0 or real id
        $cache->set($cacheKey, $id, $this->getCacheLifetime());

        return (int) $id;
    }

    public function setResponseType(string $responseType)
    {
        if (!in_array($responseType, $this->getResponseTypes())) {
            throw new \Exception('Wrong response type: ' . $responseType);
        }

        $this->responseType = $responseType;

        return $this;
    }

    public function getResponseTypes()
    {
        return [
            self::RESPONSE_TYPE_JSON,
            self::RESPONSE_TYPE_XML
        ];
    }

    public function getResponseType()
    {
        return $this->responseType ?? self::RESPONSE_TYPE_JSON;
    }

    /**
     * @param string $search
     * @param string $responseType
     * @param string $method
     *
     * @return array|\SimpleXMLElement
     *
     * @throws \Exception
     */
    private function request(string $search, string $responseType, string $method = '')
    {
        if (!$method) {
            $method = $this->getWebstore();
        }

        if (!in_array($method, $this->getMethods())) {
            throw new \Exception('Method not allowed: ' . $method);
        }

        $url = ($this->getSecure() ? 'https' : 'http') . $this->getUrl() . $method . '/' . $search;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->getUseragent());

        if ($responseType === self::RESPONSE_TYPE_JSON) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        }

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);

        if (200 !== $info['http_code']) {
            if ($this->getLogPath()) {
                $this->log('fail', [
                    'request' => $url,
                    'response' => $response,
                    'info' => $info
                ]);
            }

            throw new \Exception("HTTP {$info['http_code']} Error URL {$url}");
        }
        else {
            if ($this->getLogPath()) {
                $this->log('success', [
                    'request' => $url,
                    'response' => $response,
                ]);
            }
        }

        switch ($responseType) {
            case self::RESPONSE_TYPE_JSON:
                $array = json_decode($response);

                if (is_null($array)) {
                    throw new \Exception('Bad response json data');
                }

                return !empty($array) ? current($array) : $array;

            case self::RESPONSE_TYPE_XML:
                $xml = simplexml_load_string($response);

                if (false === $xml) {
                    throw new \Exception('Bad response xml data');
                }

                return $xml;
        }
    }

    private function log(string $type, array $data) : void
    {
        $filename = date('Y-m-d') . '_' . $type . '.json';
        $json = json_encode($data);
        $file = $this->getLogPath() . DIRECTORY_SEPARATOR . $filename;
        $flag = file_exists($file) ? FILE_APPEND : FILE_BINARY;

        $string = date('H:i:s') . ' ' . $json . PHP_EOL;

        file_put_contents($file, $string, $flag);
    }
}