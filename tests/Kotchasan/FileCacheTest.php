<?php

namespace Kotchasan\Cache;

use Kotchasan\Config;
use Kotchasan\File;
use PHPUnit_Framework_TestCase;
use Psr\Cache\CacheItemInterface;

class FileCacheTest extends PHPUnit_Framework_TestCase
{
    private $cache;
    private $cache_dir = ROOT_PATH . 'datas/cache/';
    private $itemKey = 'test_item';
    private $itemValue = ['data' => 'sample data', 'number' => 123];

    public static function setUpBeforeClass()
    {
        // Ensure ROOT_PATH is defined for standalone test execution
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(dirname(dirname(__FILE__))) . '/');
        }
    }

    protected function setUp()
    {
        // Mock Config
        $config = $this->getMockBuilder(Config::class)->getMock();
        $config->method('get')->willReturnMap([
            ['cache_expire', 0, 3600], // Default cache_expire to 1 hour for tests
        ]);

        // Replace the static::$cfg with the mock
        $reflection = new \ReflectionClass(Cache::class);
        $cfgProperty = $reflection->getProperty('cfg');
        $cfgProperty->setAccessible(true);
        $cfgProperty->setValue(null, $config);

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0777, true);
        }
        // Create index.php for daily cleanup check
        if (!is_file($this->cache_dir.'index.php')) {
            @file_put_contents($this->cache_dir.'index.php', date('d'));
        }

        $this->cache = new FileCache();
    }

    protected function tearDown()
    {
        // Clear the cache directory after each test
        $this->cache->clear();
        // Remove the datas/cache directory and its contents if it was created by tests
        if (is_dir(ROOT_PATH . 'datas')) {
            $this->removeDirectory(ROOT_PATH . 'datas');
        }
    }

    private function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    private function getCacheFilePath($key)
    {
        $reflection = new \ReflectionClass(FileCache::class);
        $method = $reflection->getMethod('fetchStreamUri');
        $method->setAccessible(true);
        return $method->invoke($this->cache, $key);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(FileCache::class, $this->cache);
        $this->assertAttributeEquals($this->cache_dir, 'cache_dir', $this->cache);
        $this->assertAttributeEquals(3600, 'cache_expire', $this->cache); // From mock
    }

    public function testSaveAndHasItemAndGetItem()
    {
        $item = new CacheItem($this->itemKey);
        $item->set($this->itemValue);
        $item->expiresAfter(3600); // Explicitly set expiry for the item

        // Save item
        $this->assertTrue($this->cache->save($item));
        $cacheFilePath = $this->getCacheFilePath($this->itemKey);
        $this->assertFileExists($cacheFilePath);

        // Check if item exists
        $this->assertTrue($this->cache->hasItem($this->itemKey));

        // Get item
        $retrievedItem = $this->cache->getItem($this->itemKey);
        $this->assertInstanceOf(CacheItemInterface::class, $retrievedItem);
        $this->assertTrue($retrievedItem->isHit());
        $this->assertEquals($this->itemKey, $retrievedItem->getKey());
        $this->assertEquals($this->itemValue, $retrievedItem->get());

        // Test non-existent item
        $this->assertFalse($this->cache->hasItem('non_existent_key'));
        $nonExistentItem = $this->cache->getItem('non_existent_key');
        $this->assertInstanceOf(CacheItemInterface::class, $nonExistentItem);
        $this->assertFalse($nonExistentItem->isHit()); // Should be a miss
    }

    public function testGetItems()
    {
        $keys = ['item1', 'item2', 'non_existent'];
        $item1_data = ['data' => 'value1'];
        $item2_data = ['data' => 'value2'];

        $item1 = new CacheItem($keys[0]);
        $item1->set($item1_data);
        $this->cache->save($item1);

        $item2 = new CacheItem($keys[1]);
        $item2->set($item2_data);
        $this->cache->save($item2);

        $retrievedItems = $this->cache->getItems($keys);

        $this->assertCount(2, $retrievedItems); // non_existent shouldn't be there
        $this->assertArrayHasKey($keys[0], $retrievedItems);
        $this->assertArrayHasKey($keys[1], $retrievedItems);

        $this->assertEquals($item1_data, $retrievedItems[$keys[0]]->get());
        $this->assertEquals($item2_data, $retrievedItems[$keys[1]]->get());
    }


    public function testDeleteItem()
    {
        $item = new CacheItem($this->itemKey);
        $item->set($this->itemValue);
        $this->cache->save($item);

        $this->assertTrue($this->cache->hasItem($this->itemKey));
        $this->assertTrue($this->cache->deleteItem($this->itemKey));
        $this->assertFalse($this->cache->hasItem($this->itemKey));
        $this->assertFileNotExists($this->getCacheFilePath($this->itemKey));

        // Test deleting non-existent item
        $this->assertTrue($this->cache->deleteItem('non_existent_key'));
    }

    public function testDeleteItems()
    {
        $keys = ['del_item1', 'del_item2', 'del_item3'];
        foreach ($keys as $key) {
            $item = new CacheItem($key);
            $item->set(['data' => $key.'_value']);
            $this->cache->save($item);
            $this->assertTrue($this->cache->hasItem($key));
        }

        $this->assertTrue($this->cache->deleteItems([$keys[0], $keys[2], 'non_existent_del']));
        $this->assertFalse($this->cache->hasItem($keys[0]));
        $this->assertTrue($this->cache->hasItem($keys[1])); // Should still exist
        $this->assertFalse($this->cache->hasItem($keys[2]));
    }

    public function testClear()
    {
        $item1 = new CacheItem('clear_item1');
        $item1->set('data1');
        $this->cache->save($item1);

        $item2 = new CacheItem('clear_item2');
        $item2->set('data2');
        $this->cache->save($item2);

        // Create a dummy file that should not be deleted by clear (index.php)
        file_put_contents($this->cache_dir.'index.php', date('d'));
        // Create a dummy subdirectory
        mkdir($this->cache_dir.'subdir');
        file_put_contents($this->cache_dir.'subdir/dummy.txt', 'test');


        $this->assertTrue($this->cache->clear());
        $this->assertFileExists($this->cache_dir.'index.php'); // index.php should remain
        $this->assertFileNotExists($this->getCacheFilePath('clear_item1'));
        $this->assertFileNotExists($this->getCacheFilePath('clear_item2'));
        $this->assertFileNotExists($this->cache_dir.'subdir/dummy.txt'); // sub-directories and files should be cleared
        $this->assertFalse(is_dir($this->cache_dir.'subdir')); // sub-directories themselves should be cleared
    }

    public function testCacheExpiration()
    {
        $key = 'expired_item';
        $value = 'this will expire';
        $item = new CacheItem($key);
        $item->set($value);
        $this->cache->save($item);

        $cacheFilePath = $this->getCacheFilePath($key);
        $this->assertTrue($this->cache->hasItem($key));

        // Manually set the file modification time to be older than cache_expire
        // cache_expire is 3600 seconds (1 hour)
        $oldTime = time() - 4000; // More than 1 hour ago
        touch($cacheFilePath, $oldTime);
        clearstatcache(); // Clear file status cache

        $this->assertFalse($this->cache->hasItem($key)); // Should be expired

        $retrievedItem = $this->cache->getItem($key);
        $this->assertFalse($retrievedItem->isHit()); // Should be a miss due to expiration
    }

    public function testCacheDisabled()
    {
        // Mock Config to disable cache
        $config = $this->getMockBuilder(Config::class)->getMock();
        $config->method('get')->willReturnMap([
            ['cache_expire', 0, 0], // cache_expire = 0 to disable
        ]);
        $reflection = new \ReflectionClass(Cache::class);
        $cfgProperty = $reflection->getProperty('cfg');
        $cfgProperty->setAccessible(true);
        $cfgProperty->setValue(null, $config);

        $disabledCache = new FileCache(); // Re-instantiate with new config

        $item = new CacheItem('disabled_cache_item');
        $item->set('some data');

        $this->assertFalse($disabledCache->save($item)); // Save should fail or do nothing
        $this->assertFileNotExists($this->getCacheFilePath('disabled_cache_item'));
        $this->assertFalse($disabledCache->hasItem('disabled_cache_item'));

        $retrieved = $disabledCache->getItem('disabled_cache_item');
        $this->assertFalse($retrieved->isHit());

        $this->assertTrue($disabledCache->clear()); // Clear should still return true
        $this->assertTrue($disabledCache->deleteItem('disabled_cache_item')); // Delete should still return true
    }
}