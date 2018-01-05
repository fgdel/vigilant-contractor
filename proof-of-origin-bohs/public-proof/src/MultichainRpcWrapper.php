<?php

class MultichainRpcWrapper
{
    /**
     * The JsonRPC client used to call the multichain api
     *
     */
    private $chainName;
    private $host;
    private $port;
    private $username;
    private $password;

    /**
     * Enable debug output to the php error log
     *
     * @var boolean
     */
    private $url;
    private $debug = false;
    private $multichainInfo = null;
    private $multichainLabels = null;
    private $maxDataSize = null;
    private $lastError = null;

    /**
     * Constructor
     *
     */
    public function __construct($config)
    {
        $this->chainName = $config['name'];
        $this->host = $config['rpchost'];
        $this->port = $config['rpcport'];
        $this->username = $config['rpcuser'];
        $this->password = $config['rpcpassword'];
        // initialise url with http protocol
        $this->setUrl();
        // create a log channel
        //$this->log = new Logger('multichain');
        //$this->log->pushHandler(new StreamHandler(__DIR__ . "/../rpc-calls.log", Logger::INFO));
    }

    public function setUrl($secure=false)
    {
        $this->url = ($secure ? 'https' : 'http') . '://' . $this->host . ':' . $this->port;
    }

    public function execute($method, $params=null)
    {
        $payload=json_encode(array(
            'id' => time(),
            'method' => $method,
            'params' => $params,
        ));

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: '.strlen($payload)
        ));

        $response=curl_exec($ch);

        $result=json_decode($response, true);

        if (!is_array($result)) {
            $info = curl_getinfo($ch);
            $result = array('error' => array(
                'code' => 'HTTP ' . $info['http_code'],
                'message' => strip_tags($response) . ' ' . $this->url
            ));
        }

        return $result;
    }

    public function getMultichainInfo()
    {
        if (!is_array($this->multichainInfo)) {
            $this->multichainInfo = $this->unboxResult($this->execute('getinfo'));
        }

        return $this->multichainInfo;
    }

    public function getMultichainLabels()
    {
        if (!is_array($this->multichainLabels)) {
            $items = $this->execute('liststreampublishers', array('root', '*', true, 10000));
            if (! $this->hasError($items)) {
                $this->multichainLabels = array();
                foreach ($this->unboxResult($items) as $item)
                    $this->multichainLabels[$item['publisher']]=pack('H*', $item['last']['data']);
            }
        }

        return $this->multichainLabels;
    }

    public function getMaxDataSize()
    {
        if (!isset($this->maxDataSize)) {

            $response = $this->execute('getblockchainparams');

            if (is_array($params = $this->unboxResult($response))) {
                $this->maxDataSize = min(
                    $params['maximum-block-size']-80-320,
                    $params['max-std-tx-size']-320,
                    $params['max-std-op-return-size']
                );
            }
        }

        return $this->maxDataSize;
    }

    public function hasError($data)
    {
        return is_array($data['error']);
    }

    public function unboxResult($data)
    {
        return (is_array($data['error']) ? false : $data['result']);
    }

    private function setLastError($data)
    {
        $this->lastError = $data['error'];
        return false;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getCommandResult($method, $params=null)
    {
        $args = func_get_args();

        $response = $this->execute($method, array_slice($args, 1));

        return (is_array($response['error']) ? $this->setLastError($response) : $response['result']);
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    public function stringToTxOutBin($string)
    {
        return ltrim($string, "\x00"); // ensures that first byte 0x00 means it's a file
    }

    public function fileToTxOutBin($filename, $mimetype, $content)
    {
        return "\x00" . $filename . "\x00" . $mimetype . "\x00" . $content;
    }

    public function txOutBinToFile($data)
    {
        $parts = explode("\x00", $data, 4);

        if ( (count($parts)!=4) || ($parts[0]!='') )
            return null;

        return array(
            'filename' => $parts[1],
            'mimetype' => $parts[2],
            'content' => $parts[3],
        );
    }

    function fileRefToString($vout, $filename, $mimetype, $filesize)
    {
        return "\x00" . $vout . "\x00" . $filename . "\x00" . $mimetype . "\x00" . $filesize;
    }

    function stringToFileRef($string)
    {
        $parts = explode("\x00", $string);

        if ( (count($parts)!=5) || ($parts[0]!='') )
            return null;

        return array(
            'vout' => $parts[1],
            'filename' => $parts[2],
            'mimetype' => $parts[3],
            'filesize' => $parts[4],
        );
    }

}