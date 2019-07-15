<?php

namespace TANIOS\Airtable;

class Request implements \ArrayAccess
{
    private $airtable;
    private $curl;
    private $table;
    private $data = [];
    private $method = 'get';
    private $relations;
    private $options;

    public function __construct(
        Airtable $airtable,
        string $table,
        array $data = [],
        string $method = 'get',
        bool $relations = false,
        array $options = []
    ) {
        $this->airtable = $airtable;
        $this->table = $table;
        $this->data = $data;
        $this->method = $method;
        $this->relations = $relations;
        $this->options = $options;
    }

    private function init() : void
    {
        $headers = [
            'Content-Type: application/json',
            sprintf('Authorization: Bearer %s', $this->airtable->getKey())
        ];

        $request = $this->table;

        if (!$this->method) {
            if (!empty($this->data)) {
                $data = http_build_query($this->data);
                $request .= "?" . $data;
            }
        }

        $curl = curl_init($this->airtable->getApiUrl($request));

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);

        if (isset($this->options['timeout'])) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        }

        if ($this->method) {
            if (strtolower($this->method) === 'patch') {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            } elseif (strtolower($this->method) === 'delete') {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            curl_setopt($curl, CURLOPT_POST, count($this->data));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->data));
        }

        $this->curl = $curl;
    }

    public function getResponse() : Response
    {
        $this->init();

        $responseString = curl_exec($this->curl);
        $response = new Response($this->airtable, $this, $responseString, $this->relations);

        return $response;
    }

    public function __set(string $key, string $value) : void
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }

        $this->data[$key] = $value;
    }

    public function offsetExists($offset)
    {
        return is_array($this->data) && isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return is_array($this->data) && isset($this->data[$offset])
            ? $this->data[$offset]
            : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        if (is_array($this->data) && isset($this->data[$offset])) {
            unset($this->data[$offset]);
        }
    }
}
