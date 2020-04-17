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
    private $redis;
    private $redisKey;

    public function __construct($config)
    {
        if (is_array($config)) {
            $this->setKey($config['api_key']);
            $this->setBase($config['base']);
            $this->setRequestOptions($config['request_options'] ?? []);
            $this->setRedisLock($config['redisLock'] ?? []);
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

    public function setRedisLock(array $redisOptions)
    {
        if (
            isset($redisOptions['client'])
            && isset($redisOptions['key'])
        ) {
            $this->redis = $redisOptions['client'];
            $this->redisKey = $redisOptions['key'];
        }
    }

    public function getContent($table, $data = [], $relations = false)
    {
        $this->acquireLock();

        $res = new Request($this, $table, $data, false, $relations, $this->requestOptions);

        $this->releaseLock();

        return $res;
    }

    public function saveContent($table, $fields)
    {
        $this->acquireLock();

        $fields = ['fields' => $fields];
        $request = new Request($this, $table, $fields, true, $this->requestOptions);

        $this->releaseLock();

        return $request->getResponse();
    }

    public function updateContent($table, $fields)
    {
        $this->acquireLock();

        $fields = ['fields' => $fields];
        $request = new Request($this, $table, $fields, 'patch', $this->requestOptions);

        $this->releaseLock();

        return $request->getResponse();
    }

    public function deleteContent($table)
    {
        $this->acquireLock();

        $request = new Request($this, $table, [], 'delete', $this->requestOptions);

        $this->releaseLock();

        return $request->getResponse();
    }

    public function quickCheck($table, $field = '', $value = '')
    {
        $this->acquireLock();

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

        $this->releaseLock();

        return (object)$results;
    }

    private function useRedisLock()
    {
        return !is_null($this->redis) && is_string($this->redisKey);
    }

    private function acquireLock()
    {
        if (!$this->useRedisLock()) {
            return;
        }

        $lockAttempts = 0;
        while ($this->redis->exists($this->redisKey) && $lockAttempts < 30) {
            sleep(2);
            $lockAttempts++;
        }
        // Acquire redis lock, expires in 60 seconds
        $this->redis->set($this->redisKey, 1, 'EX', 60);
    }

    private function releaseLock()
    {
        if (!$this->useRedisLock()) {
            return;
        }

        $this->redis->del([$this->redisKey]);
    }
}
