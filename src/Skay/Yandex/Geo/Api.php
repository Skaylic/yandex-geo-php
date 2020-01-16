<?php

namespace Skay\Yandex\Geo;

use Curl\Curl;

/**
 * Class Api
 * @package Skay\Yandex\Geo
 * @license The MIT License (MIT)
 * @see http://api.yandex.ru/maps/doc/geocoder/desc/concepts/About.xml
 */
class Api
{
	/** Url */
	const BASE_URL = 'https://geocode-maps.yandex.ru/%s/';
	/** дом */
	const KIND_HOUSE = 'house';
	/** улица */
	const KIND_STREET = 'street';
	/** станция метро */
	const KIND_METRO = 'metro';
	/** район города */
	const KIND_DISTRICT = 'district';
	/** населенный пункт (город/поселок/деревня/село/...) */
	const KIND_LOCALITY = 'locality';
	/** русский (по умолчанию) */
	const LANG_RU = 'ru-RU';
	/** украинский */
	const LANG_UA = 'uk-UA';
	/** белорусский */
	const LANG_BY = 'be-BY';
	/** американский английский */
	const LANG_US = 'en-US';
	/** британский английский */
	const LANG_BR = 'en-BR';
	/** турецкий (только для карты Турции) */
	const LANG_TR = 'tr-TR';
	/**
	 * @var string Key
	 */
	protected $_apikey = '';
	/**
	 * @var string Версия используемого api
	 */
	protected $_version = '1.x';
	/**
	 * @var array
	 */
	protected $_filters = array();
	/**
	 * @var Skay\Yandex\Geo\Response|null
	 */
	protected $_response;

	/**
	 * @param null|string $version
	 */
	public function __construct($apikey, $version = null)
	{
		$this->_apikey = $apikey;
		if (!empty($version)) {
			$this->_version = (string)$version;
		}
		$this->clear();
	}

	/**
	 * @param array $options Curl options
	 * @return $this
	 * @throws Exception
	 * @throws Exception\CurlError
	 * @throws Exception\ServerError
	 */
	public function load(array $options = [])
	{
		self::initCurl($options);
	}

	public function initCurl(array $options = [])
	{
		$apiUrl = $this->generateUri();
		$curl = new Curl();
		$curl->setOpt(CURLOPT_RETURNTRANSFER, true);
		$curl->setOpt(CURLOPT_HTTPGET, true);
		$curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
		$curl->get($apiUrl, $this->_filters);
		if($curl->error){
			throw new \Skay\Yandex\Exception\CurlException($curl);
		}
		$data = json_decode($curl->response, true);
		if (empty($data)) {
			$msg = sprintf('Can\'t load data by url: %s', $apiUrl);
			throw new \Skay\Yandex\Exception\BaseException($msg);
		}
		if (!empty($data['error'])) {
			throw new \Skay\Yandex\Exception\ErrorException($data['message'], $data['statusCode']);
		}

		$this->_response = new \Skay\Yandex\Geo\Response($data);

		return $this;
	}

	public function generateUri()
	{
		$uri = sprintf(self::BASE_URL, $this->_version);
		return $uri;
	}
	/**
	 * @return Response
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * Очистка фильтров гео-кодирования
	 * @return self
	 */
	public function clear()
	{
		// указываем явно значения по-умолчанию
		$this
		->setApiKey($this->_apikey)
		->setLang(self::LANG_RU)
		->setXml()
		->setOffset(0)
		->setLimit(10);
		// ->useAreaLimit(false);
		$this->_response = null;
		return $this;
	}

	/**
	 * Гео-кодирование по координатам
	 * @see http://api.yandex.ru/maps/doc/geocoder/desc/concepts/input_params.xml#geocode-format
	 * @param float $longitude Долгота в градусах
	 * @param float $latitude Широта в градусах
	 * @return self
	 */
	public function setPoint($longitude, $latitude)
	{
		$longitude = (float)$longitude;
		$latitude = (float)$latitude;
		$this->_filters['geocode'] = sprintf('%F,%F', $longitude, $latitude);
		return $this;
	}

	/**
	 * Географическая область поиска объекта
	 * @param float $lengthLng Разница между максимальной и минимальной долготой в градусах
	 * @param float $lengthLat Разница между максимальной и минимальной широтой в градусах
	 * @param null|float $longitude Долгота в градусах
	 * @param null|float $latitude Широта в градусах
	 * @return self
	 */
	public function setArea($lengthLng, $lengthLat, $longitude = null, $latitude = null)
	{
		$lengthLng = (float)$lengthLng;
		$lengthLat = (float)$lengthLat;
		$this->_filters['spn'] = sprintf('%f,%f', $lengthLng, $lengthLat);
		if (!empty($longitude) && !empty($latitude)) {
			$longitude = (float)$longitude;
			$latitude = (float)$latitude;
			$this->_filters['ll'] = sprintf('%f,%f', $longitude, $latitude);
		}
		return $this;
	}

	/**
	 * Позволяет ограничить поиск объектов областью, заданной self::setArea()
	 * @param boolean $areaLimit
	 * @return self
	 */
	public function useAreaLimit($areaLimit)
	{
		$this->_filters['rspn'] = $areaLimit ? 1 : 0;
		return $this;
	}

	/**
	 * Гео-кодирование по запросу (адрес/координаты)
	 * @param string $query
	 * @return self
	 */
	public function setQuery($query)
	{
		$this->_filters['geocode'] = (string)$query;
		return $this;
	}

	/**
	 * Вид топонима (только для обратного геокодирования)
	 * @param string $kind
	 * @return self
	 */
	public function setKind($kind)
	{
		$this->_filters['kind'] = (string)$kind;
		return $this;
	}

	/**
	 * Максимальное количество возвращаемых объектов (по-умолчанию 10)
	 * @param int $limit
	 * @return self
	 */
	public function setLimit($limit)
	{
		$this->_filters['results'] = (int)$limit;
		return $this;
	}

	/**
	 * Количество объектов в ответе (начиная с первого), которое необходимо пропустить
	 * @param int $offset
	 * @return self
	 */
	public function setOffset($offset)
	{
		$this->_filters['skip'] = (int)$offset;
		return $this;
	}

	/**
	 * Предпочитаемый язык описания объектов
	 * @param string $lang
	 * @return self
	 */
	public function setLang($lang)
	{
		$this->_filters['lang'] = (string)$lang;
		return $this;
	}

	/**
	 * Ключ API Яндекс.Карт
	 * @see https://tech.yandex.ru/maps/doc/geocoder/desc/concepts/input_params-docpage
	 * @param string $token
	 * @return self
	 */
	public function setApiKey($apikey)
	{
		$this->_filters['apikey'] = (string)$apikey;
		return $this;
	}

	/**
	 * Формат ответа Яндекс.Карт
	 * @see https://tech.yandex.ru/maps/doc/geocoder/desc/concepts/input_params-docpage
	 * @param string $xml
	 * @return self
	 */
	public function setXml($xml = false)
	{
		$this->_filters['format'] = $xml?'xml':'json';
		return $this;
	}
}
