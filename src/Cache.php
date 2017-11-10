<?php
/**
 * ----------------------------------------------
 * | Author: Андрей Рыжов (Dune) <info@rznw.ru>  |
 * | Site: www.rznw.ru                           |
 * | Phone: +7 (4912) 51-10-23                   |
 * | Date: 09.11.2017                               |
 * -----------------------------------------------
 *
 */


namespace AndyDune\FileCacheLazyUpdate;
use Symfony\Component\Cache\Simple\FilesystemCache;

class Cache
{
    protected $type;

    protected $key = null;
    protected $keyReal = null;

    protected $cache;

    protected $dateTimeStored = null;

    protected $date = null;

    protected $retrieved = false;

    protected $has = false;

    protected $dataFromCache = null;

    protected $cacheDirectory;

    static public $version = 1;

    public function __construct($cacheDirectory = null)
    {
        $this->cacheDirectory =  $cacheDirectory ?? $_SERVER['DOCUMENT_ROOT'] . '/tmp/cache/';
    }


    public function init($type = 'default', $key = null)
    {
        $dir = $this->cacheDirectory . $type;
        $this->cache = new FilesystemCache('',  3600, $dir);
        $this->type = $type;
        if ($key) {
            $this->keyReal = $key;
            $this->key = md5($key);
        }
    }

    public function getCurrentCacheVersion()
    {
        return self::$version;
    }


    public function setKey($key)
    {
        $this->key = md5($key);
        return $this;
    }

    public function has()
    {
        $this->retrieve();
        return $this->has;
    }

    public function get($default = null)
    {
        $this->retrieve();
        return $this->dataFromCache ? $this->dataFromCache : $default;
    }

    public function set($value, $lifetimeHours = 1)
    {
        $lifetime = (int)($lifetimeHours * 3600) + rand(0, 1800);
        $this->retrieve();
        $data = [
            'time' => time() + $lifetime,
            'version' => $this->getCurrentCacheVersion(),
            'data' => $value
        ];
        return $this->cache->set($this->key, $data, $lifetime * 40);
    }

    public function restore()
    {
        $this->retrieve();
        if ($this->dataFromCache) {
            $this->set($this->dataFromCache, 2);
            return true;
        }
        return false;
    }

    /**
     * @return null
     */
    public function getDateTimeStored()
    {
        return $this->dateTimeStored;
    }

    protected function retrieve()
    {
        if ($this->retrieved) {
            return $this;
        }

        if (!$this->key) {
            throw new Exception('First ypu need to execute init() method.');
        }

        $this->retrieved = true;
        $this->dateTimeStored = 0;
        $this->dataFromCache = null;

        $this->has = $this->cache->has($this->key);
        if ($this->has) {
            $data = $this->cache->get($this->key);
            if (isset($data['time']) and isset($data['data'])) {
                $this->dateTimeStored = $data['time'];
                $version = isset($data['version']) ? $data['version'] : 1;
                $this->dataFromCache = $data['data'];
                if ($version < $this->getCurrentCacheVersion() or $this->dateTimeStored < time()) {
                    $this->update($this->type, $this->keyReal);
                }
            } else {
                $this->dateTimeStored = 123;
                $this->dataFromCache = $data;
            }
        }
        return $this;
    }


    public function delete()
    {
        $this->retrieved = false;
        return $this->cache->delete($this->key);
    }


}