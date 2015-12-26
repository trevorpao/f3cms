<?php
namespace F3CMS;

/**
 * Screenshotmachine for PHP - small and easy-to-use library for works with https://screenshotmachine.com
 *
 * @author     Ondřej Kubíček
 * @copyright  Copyright (c) 2015 Ondřej Kubíček
 * @license    New BSD License
 * @link       http://www.kubon.cz
 * @version    1.0
 */
class Screenshot
{

	private $data = [
		'key' => NULL,
		'url' => NULL,
		'size' => self::SIZE_T,
		'format' => self::JPEG,
		'cacheLimit' => 0,
		'timeout' => 200
	];

	private static $imageType = [
		self::JPEG => IMAGETYPE_JPEG,
		self::PNG => IMAGETYPE_PNG,
		self::GIF => IMAGETYPE_GIF,
	];

	const API_URL = 'http://api.screenshotmachine.com/';

	const SIZE_T = 'T',
		SIZE_S = 'S',
		SIZE_E = 'E',
		SIZE_N = 'N',
		SIZE_M = 'M',
		SIZE_L = 'L',
		SIZE_X = 'X',
		SIZE_F = 'F';

	const JPEG = 'JPG',
		PNG = 'PNG',
		GIF = 'GIF';


	/**
	 * Create object
	 * @param array parameters
	 * @throws ScreenshotmachineException
	 */
	public function __construct(array $data)
	{
		if (!extension_loaded('curl')) {
			throw new ScreenshotmachineException('PHP extension CURL is not loaded.');
		}

		foreach ($data as $key => $value) {
			if (!array_key_exists($key, $this->data)){
				throw new ScreenshotmachineException('Invalid key: ' . $key);
			}
		}

		$this->data = array_merge($this->data, $data);
	}


	/**
	 * @return image to output
	 */
	public function getScreen()
	{
		header('Content-Type: ' . $this->getImageType());
		echo $this->request();
	}


	/**
	 * @return image to output
	 */
	public function saveScreen($filePath)
	{
		echo $this->request($filePath);
	}


	private function getImageType()
	{
		return self::$imageType[$this->data['format']];
	}


	private function request($filePath = '')
	{
		$options = [
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_HTTPHEADER => ['Expect:'],
			CURLOPT_USERAGENT => 'Screenshotmachine for PHP',
			CURLOPT_URL => $this->buildUrl()
		];


		$curl = curl_init();
		curl_setopt_array($curl, $options);

		if ($filePath != '') {
			curl_setopt($curl, CURLOPT_FILE, $filePath);
		}

		$result = curl_exec($curl);

		if (curl_errno($curl)) {
			throw new ScreenshotmachineException('Server error: ' . curl_error($curl));
		}

		curl_close($curl);

		return $result;
	}


	private function buildUrl()
	{
		return self::API_URL . '?' . http_build_query($this->data);
	}

}

class ScreenshotmachineException extends Exception {

}
