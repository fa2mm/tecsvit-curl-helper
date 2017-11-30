<?php

namespace \tecsvit;

/**
 * Class CurlHelper
 *
 * @author fa2m
 * Date: 22.04.17
 *
 * @property integer|null   $httpCode
 * @static   integer|array  $sleep
 * @static   bool           $autoClose
 * @property resource       $ch
 * @property bool           $curlClose
 * @property array          $options
 *
 * @use ObjectHelper
 * @use PublicErrors
 */
class CurlHelper
{
    // requests types
    const R_GET                             = 'GET';
    const R_POST                            = 'POST';
    const R_OPTIONS                         = 'OPTIONS';

    public $httpCode                        = null;

    public static $sleep;
    public static $autoClose                = true;

    private $ch                             = null;
    private $curlClose                      = true;

    /**
     * Example Options:
     * verbose: true/false
     * returntransfer: true/false
     * customrequest: R_GET, R_POST, R_OPTIONS
     * fields: mix
     * etc
     * @var array
     */
    private $options                       = [];

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->curlClose();
    }

    /**
     * @param $option
     * @param $value
     * @return $this
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * @param array $optionsArray
     * @return $this
     */
    public function setOptions(array $optionsArray)
    {
        foreach ($optionsArray as $optionName => $optionValue) {
            $this->setOption($optionName, $optionValue);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param null   $default
     * @return mixed|null
     */
    public function getOption($name, $default = null)
    {
        return ObjectHelper::getAttribute($this->options, $name, $default);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $name
     * @return void
     */
    public function unsetOption($name)
    {
        unset($this->options[$name]);
    }

    /**
     * @param array $optionsArray
     * @return void
     */
    public function unsetOptions(array $optionsArray)
    {
        foreach ($optionsArray as $optionName) {
            $this->unsetOption($optionName);
        }
    }

    /**
     * @return void
     */
    public function clearOptions()
    {
        $this->options = [];
    }

    public static $errorInstance;

    /**
     * @return void
     */
    public static function initErrors()
    {
        if (null === self::$errorInstance) {
            self::$errorInstance = new PublicErrors();
        }
    }

    /**
     * @return PublicErrors
     */
    public static function errorInstance()
    {
        self::initErrors();
        return self::$errorInstance;
    }

    /**
     * @param string    $content
     * @param string    $pattern
     * @param string    $version
     * @param boolean   $errors
     * @return \DOMNodeList
     */
    public function domXPath($content, $pattern, $version = '1.0', $errors = true)
    {
        if (empty($content)) {
            return new \DOMNodeList;
        }

        $doc    = $this->dom($content, $version, $errors);
        $xpath  = new \DOMXpath($doc);
        return $xpath->query($pattern);
    }

    /**
     * @param string  $content
     * @param string  $version
     * @param boolean $errors
     * @return \DOMDocument
     */
    public function dom($content, $version = '1.0', $errors = true)
    {
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $doc = new \DOMDocument($version);
        $doc->loadHtml($content);

        return $doc;
    }

    /**
     * @param string $url
     * @param null   $default
     * @return mixed|null
     */
    public function curl($url, $default = null)
    {
        $this->sleep();

        try {
            if (true === $this->curlClose) {
                $this->ch           = curl_init();
                $this->curlClose    = false;
            }
        } catch (\Exception $e) {
            self::errorInstance()->addError($e->getMessage());

            return $default;
        }

        if (self::R_POST == $this->getOption('customrequest')) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->getOption('fields'));
        } else {
            if (!empty($this->getOption('fields'))) {
                $url .= '?' . http_build_query($this->getOption('fields'));
            }

            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        if ($this->hasOption('interface') && $interface = $this->getInterface()) {
            curl_setopt($this->ch, CURLOPT_INTERFACE, $interface);
        }

        if ($this->hasOption('cookiejar')) {
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->getCookiesFile());
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->getCookiesFile());
        }

        curl_setopt($this->ch, CURLOPT_URL, str_replace(' ', '+', $url));

        $this->setCurlOption(CURLOPT_RETURNTRANSFER, 'returntransfer');
        $this->setCurlOption(CURLOPT_FOLLOWLOCATION, 'followlocation');
        $this->setCurlOption(CURLOPT_HTTPHEADER, 'headers');
        $this->setCurlOption(CURLOPT_VERBOSE, 'verbose');
        $this->setCurlOption(CURLOPT_USERAGENT, 'useragent');
        $this->setCurlOption(CURLOPT_SSL_VERIFYPEER, 'ssl_verifypeer');
        $this->setCurlOption(CURLOPT_SSL_VERIFYHOST, 'ssl_verifyhost');

        $response = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            self::errorInstance()->addError('Curl error: ' . curl_error($this->ch) . '. Url: ' . $url);
            return $default;
        } else {
            $this->httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        }

        if (true === self::$autoClose) {
            $this->curlClose();
        }

        return $response;
    }

    /**
     * @return void
     */
    public function sleep()
    {
        if (is_array(self::$sleep)) {
            $min    = ObjectHelper::getAttribute(self::$sleep, 0, 2);
            $max    = ObjectHelper::getAttribute(self::$sleep, 1, 2);
            $sec    = mt_rand($min, $max);
        } else {
            $sec    = self::$sleep;
        }

        sleep($sec);
    }

    /**
     * @return void
     */
    public function curlClose()
    {
        if (is_resource($this->ch) && false === $this->curlClose) {
            curl_close($this->ch);
            $this->curlClose = true;
        }
    }

    /**
     * @param $curlOptionName
     * @param $optionName
     * @return void
     */
    private function setCurlOption($curlOptionName, $optionName)
    {
        if ($this->hasOption($optionName)) {
            curl_setopt($this->ch, $curlOptionName, $this->getOption($optionName));
        }
    }

    /**
     * @return bool|mixed
     */
    private function getInterface()
    {
        $interfaceOption = $this->getOption('interface');

        if (is_array($interfaceOption) && !empty($interfaceOption)) {
            $count      = count($interfaceOption);
            $key        = mt_rand(0, $count - 1);

            $interface  = ObjectHelper::getAttribute($interfaceOption, $key);

            if ($this->hasOption('cookiejar')) {
                $this->setOption('cookiejar', $this->getCookiesFile() . $interface);
            }

            return $interface;
        }

        return false;
    }

    /**
     * @return string
     */
    private function getCookiesFile()
    {
        return $this->getOption('cookiejar');
    }

    /**
     * @param $name
     * @return bool
     */
    private function hasOption($name)
    {
        return isset($this->options[$name]);
    }
}

