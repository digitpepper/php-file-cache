<?php

declare(strict_types = 1);

namespace DP;

class FileCache
{
	const FORMAT_PHP = 'php';
	const FORMAT_JSON = 'json';

	/**
	 * @var bool
	 */
	public static $is_constructed = false;

	/**
	 * @var string
	 */
	public static $cache_dir;

	public static function construct(): void
	{
		if (self::$is_constructed) {
			return;
		}
		self::$cache_dir = \dirname($_SERVER['SCRIPT_FILENAME']) . '/cache';
		self::$is_constructed = true;
	}

	/**
	 * @param string $name
	 * @param mixed $data
	 * @param int $ttl
	 * @param string $format
	 * @throws \Exception
	 */
	public static function set(string $name, $data, int $ttl = 3600, string $format = self::FORMAT_PHP): void
	{
		self::construct();
		if ($ttl <= 0) {
			return;
		}
		self::create_cache_dir();
		$cache_dir = self::$cache_dir;
		$random_bytes = \bin2hex(\random_bytes(16));
		$filename_random = "$cache_dir/_{$name}_$random_bytes.$format";
		$filename = "$cache_dir/$name.$format";
		if ($format === self::FORMAT_PHP) {
			$content = \var_export($data, true);
			$data = "<?php\nreturn $content;";
		} else if ($format === self::FORMAT_JSON) {
			$data = \json_encode($data);
		} else {
			throw new \Exception("Format $format is not supported.");

		}
		$bytes = \file_put_contents($filename_random, $data);
		if ($bytes === false) {
			throw new \Exception("Failed to create cache $filename_random");
		}
		if (!\touch($filename_random, \time() + $ttl)) {
			throw new \Exception("Failed to update cache ttl $filename_random");
		};
		if (\rename($filename_random, $filename) === false) {
			throw new \Exception("Failed to move $filename_random to $filename");
		}
	}

	/**
	 * @param string $name
	 * @param string $format
	 * @return mixed|void
	 * @throws \Exception
	 */
	public static function get(string $name, string $format = self::FORMAT_PHP)
	{
		self::construct();
		$cache_dir = self::$cache_dir;
		$filename = "$cache_dir/$name.$format";
		if (!\is_file($filename) || \filemtime($filename) <= \time()) {
			return;
		}
		if ($format === self::FORMAT_PHP) {
			$content = require $filename;
		} else if ($format === self::FORMAT_JSON) {
			$content = \file_get_contents($filename);
			if ($content === false) {
				throw new \Exception('Cannot read cache file');
			}
			$content = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		} else {
			throw new \Exception("Format $format is not supported.");
		}
		return $content;
	}

	/**
	 * @throws \Exception
	 */
	public static function create_cache_dir(): void
	{
		self::construct();
		$cache_dir = self::$cache_dir;
		$index = "$cache_dir/index.html";
		if (!\is_dir($cache_dir)) {
			if (!\mkdir($cache_dir, 0755)) {
				throw new \Exception("Failed to create directory $cache_dir");
			}
		}
		if (!\is_file($index)) {
			if (\file_put_contents($index, '') === false) {
				throw new \Exception("Failed to create file $index");
			}
		}
	}
}
