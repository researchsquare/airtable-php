<?php

namespace TANIOS\Airtable;

/**
 * Airtable API Class
 *
 * @author Sleiman Tanios
 * @copyright Sleiman Tanios - TANIOS 2017
 * @version 1.0
 */
class Airtable
{
    const API_URL = "https://api.airtable.com/v0/";

    private $key;
    private $base;
    private $requestOptions;

    public function __construct($config)
    {
        if (is_array($config)) {
            $this->setKey($config['api_key']);
            $this->setBase($config['base']);
            $this->setRequestOptions($config['request_options']);
        } else {
            echo 'Error: __construct() - Configuration data is missing.';
        }
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setBase($base)
    {
        $this->base = $base;
    }

    public function getBase()
    {
        return $this->base;
    }

    public function getApiUrl($request)
    {
        $request = str_replace(' ', '%20', $request);
        $url = self::API_URL.$this->getBase().'/'.$request;

        return $url;
    }

    public function setRequestOptions(array $requestOptions)
    {
        $this->requestOptions = $requestOptions;
    }

    public function getContent(string $table, array $data = [], $relations = false)
    {
        return new Request($this, $table, $data, false, $relations, $this->requestOptions);
    }

    public function saveContent(string $table, array $fields)
    {
        $fields = ['fields' => $fields];
        $request = new Request($this, $table, $fields, true, $this->requestOptions);

        return $request->getResponse();
    }

    public function updateContent(string $table, array $fields)
    {
        $fields = ['fields' => $fields];
        $request = new Request($this, $table, $fields, 'patch', $this->requestOptions);

        return $request->getResponse();
    }

    public function deleteContent(string $table)
    {
        $request = new Request($this, $table, [], 'delete', $this->requestOptions);

        return $request->getResponse();
    }

    public function quickCheck(string $table, string $field = '', string $value = '')
    {
        $params = '';
        if (!empty($field)&& !empty($value)) {
            $params = array(
                "filterByFormula" => "AND({{$field}} = '$value')",
            );
        }

        $request = new Request($this, $table, $params, false, $this->requestOptions);
        $response = $request->getResponse();

        $results['count'] = count($response->records);
        $results['records'] = $response->records;

        return (object)$results;
    }
}
