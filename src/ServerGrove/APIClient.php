<?php

/*
 * This file is part of sgcli.
 *
 * (c) ServerGrove
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ServerGrove;

class APIClient
{
    const FORMAT_JSON = 'json';
    const FORMAT_ARRAY = 'array';
    const FORMAT_RAW = 'raw';

    protected $url;
    protected $format = self::FORMAT_JSON;
    protected $args = array();
    protected $call;
    protected $response;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function getFullUrl($call, array $args = array())
    {
        return $this->url . '/api/' . $call . '.' . $this->format .(count($args) ? '?' . http_build_query($args) : '');
    }

    /**
     * Executes API Call. Returns true|false depending on the api response. use getResponse() to retrieve response.
     * @param $call
     * @param  array      $args
     * @return bool
     * @throws \Exception
     */
    public function call($call, array $args = array())
    {
        if (!function_exists('curl_init')) {
            throw new \Exception("curl support not found. please install the curl extension.");
        }

        $args = array_merge($this->args, $args);

        $post_data = http_build_query($args);

        $url = $this->getFullUrl($call);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $this->response = curl_exec($ch);
        curl_close($ch);

        return $this->isSuccess($this->response);
    }

    public function getResponse($format = null)
    {
        if (!$format) {
            $format = $this->format;
        }
        switch ($format) {
            case 'json':
                return json_decode($this->response);
                break;
            case 'array':
                return json_decode($this->response, true);
                break;
            default:
                return $this->response;
        }
    }

    public function getRawResponse()
    {
        return $this->response;
    }

    public function isSuccess($result=null)
    {
        if (null === $result) {
            $result = $this->getResponse();
        }

        if (is_string($result)) {
            $json = json_decode($result);
            if ($json) {
                $result = $json;
            } else {
                return $result == true;
            }
        } elseif (is_array($result)) {
            return $result['result'] == true;
        }

        return $result && $result->result == true;
    }

    public function getError($result=null)
    {
        if (null === $result) {
            $result = $this->getResponse();
        }

        if (is_string($result)) {
            $json = json_decode($result);
            if ($json) {
                $result = $json;
            } else {
                return $result;
            }
        } elseif (is_array($result)) {
            return $result['msg'];
        }

        return $result && $result->msg ? $result->msg : 'Unknown error';
    }

    public function setFormat($format)
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setArgs($args)
    {
        $this->args = $args;

        return $this;
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function getArg($id)
    {
        return isset($this->args[$id]) ? $this->args[$id] : null;
    }

    public function setArg($name, $value)
    {
        $this->args[$name] = $value;

        return $this;
    }

    public function setApiKey($value)
    {
        return $this->setArg('apiKey', $value);
    }

    public function getApiKey()
    {
        return $this->getArg('apiKey');
    }

    public function setApiSecret($value)
    {
        return $this->setArg('apiSecret', $value);
    }

    public function getApiSecret()
    {
        return $this->getArg('apiSecret');
    }

    public function dryRun($value = 1)
    {
        return $this->setArg('dryRun', $value);
    }

    public function debug($value = 1)
    {
        return $this->setArg('debug', $value);
    }

}
