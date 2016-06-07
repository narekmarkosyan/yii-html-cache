<?php

/**
 * HTML caching extension for Yii
 * Developed by: Narek Markosyan narek.markosyan@yahoo.com
 */
class HtmlCache extends CApplicationComponent{
	/**
	 * Folder in assets
	 *
	 * @var string
	 */
	public $folder = 'html_cache';

	/**
	 * Lifetime of cache
	 *
	 * @var int
	 */
	public $lifeTime = 51300; // one day

	/**
	 * Extra params for cache.
	 * For different set of extra params will be generated different cache file
	 *
	 * @var array
	 */
	public $extra_params = array();

	/**
	 * Is cache is disabled
	 *
	 * @var bool
	 */
	public $disabled = false;

	/**
	 * List of excluded actions
	 *
	 * @var array
	 */
	public $excluded_actions = array();

	/**
	 * List of excluded params
	 *
	 * @var array
	 */
	public $excluded_params = array();

	/**
	 * Asset URL
	 *
	 * @var null|string
	 */
	private $_assetsUrl = null;

	/**
	 * Controller
	 *
	 * @var null|CController
	 */
	private $_controller = null;

	/**
	 * Action
	 *
	 * @var null|CInlineAction
	 */
	private $_action = null;

	/**
	 * Direct replace in HTML
	 *
	 * @var null|string
	 */
	private $_direct_replace = array();

	public function init(){
	}

	/**
	 * Load from cache
	 *
	 * @param CController $controller
	 * @param CInlineAction $action
	 */
	public function loadFromCache($controller, $action){

		$this->saveInputParams(array('controller' => $controller, 'action' => $action));

		if(!$this->needCache()){
			return false;
		}

		$path = Yii::getPathOfAlias('webroot') . $this->getFilePath();

		if(!file_exists($path)){
			return false;
		}

		$lastModified = filemtime($path);

		// File exists but it older than lifetime of cache
		if(time() - $lastModified > $this->lifeTime){
			return false;
		}

		$this->directReplace('CSRF_TOKEN', Yii::app()->request->getCsrfToken());

		$output = file_get_contents($path);

		$replaced = $this->makeReplace($output);


		echo $replaced;
		echo '<!--GENERATED WITH YII HTML CACHE-->';
		Yii::app()->end();
	}

	/**
	 * Save to cache
	 *
	 * @param CController $controller
	 * @param CInlineAction $action
	 * @param string $output
	 */
	public function saveToCache($controller, $action, $output){

		if(!$this->needCache()){

			$replaced = $this->makeReplace($output);

			return $replaced;
		}

		$name = $this->generateFileName($controller, $action);

		$path = Yii::getPathOfAlias('webroot') . $this->getAssetsUrl() . DIRECTORY_SEPARATOR . $this->folder;
		$this->checkPermissionsOrCreate($path);

		$file = $path . DIRECTORY_SEPARATOR . $name;

		$fp = fopen($file, 'w');

		$output = $controller->processOutput($output);

		$replaced = $this->makeReplace($output);

		$output = str_replace(Yii::app()->request->getCsrfToken(), '{CSRF_TOKEN}', $output);

		Yii::app()->clientScript->reset();

		if(!$fp){
			throw new Exception('Directory ' . $file . ' does not exists or does not have write permission');
		}

		fwrite($fp, $output);
		fclose($fp);

		Yii::app()->assetManager->publish(__DIR__ . DIRECTORY_SEPARATOR, true, 1);

		return $replaced;
	}

	/**
	 * Get cache file name for this controller, action and set of params
	 *
	 * @param CController $controller
	 * @param CInlineAction $action
	 */
	private function getFilePath($controller = false, $action = false){
		$controller = $controller ?: $this->_controller;
		$action = $action ?: $this->_action;

		$cacheFileName = $this->generateFileName($controller, $action);

		$path = $this->getAssetsUrl() . DIRECTORY_SEPARATOR . $this->folder . DIRECTORY_SEPARATOR . $cacheFileName;

		return $path;
	}

	/**
	 * Generate file name for cache
	 *
	 * @param CController $controller
	 * @param CInlineAction $action
	 */
	private function generateFileName($controller, $action){
		$extra_params = "";

		foreach($this->extra_params as $extra_param){
			if(isset($controller->$extra_param)){
				$extra_params .= $controller->$extra_param . "_";
			}
		}

		$name = $controller->getId() . '_' . $action->getId() . '_' . $extra_params . md5(json_encode($controller->getActionParams())) . '.html';

		return $name;
	}

	/**
	 * Return asset directory
	 *
	 * @return string
	 */
	public function getAssetsUrl(){
		if($this->_assetsUrl !== null){
			return $this->_assetsUrl;
		}

		$this->_assetsUrl = Yii::app()->assetManager->publish(__DIR__, true, 1);

		return $this->_assetsUrl;
	}

	/**
	 * Checks if cache needed
	 *
	 * @return bool
	 */
	public function needCache(){
		// disabled from configs
		if($this->disabled){
			return false;
		}

		// is post request
		if(Yii::app()->request->isPostRequest){
			return false;
		}

		// has request parameter `disallowHtmlCache`
		if(Yii::app()->request->getParam('disallowHtmlCache')){
			return false;
		}

		// checking for excluded actions
		$excluded_actions = isset($this->excluded_actions[$this->_controller->id]) ? $this->excluded_actions[$this->_controller->id] : array();
		if($excluded_actions && in_array($this->_action->getId(), $excluded_actions)){
			return false;
		}

		// Checking for excluded params
		$excluded_params = isset($this->excluded_params[$this->_controller->id]) ? $this->excluded_params[$this->_controller->id] : array();

		foreach($excluded_params as $param => $value){
			if(is_numeric($param)){
				$param = $value;
				$value = null;
			}
			elseif(!is_array($value)){
				$value = array($value);
			}

			if(!isset($this->_controller->{$param})){
				continue;
			}

			if(is_null($value) && $this->_controller->{$param}){
				return false;
			}
			elseif(!is_null($value) && in_array($this->_controller->{$param}, $value)){
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear cache by removing all cache files from assets.
	 * Also you can just clear assets
	 *
	 * @return $this
	 */
	public function clearCache(){
		$path = Yii::getPathOfAlias('webroot') . $this->getAssetsUrl() . DIRECTORY_SEPARATOR . $this->folder . DIRECTORY_SEPARATOR . '*';

		$files = glob($path);

		foreach($files as $file){
			if(is_file($file)){
				unlink($file);
			}
		}

		return $this;
	}

	/**
	 * Check if folder exists or have writable permissions. If not it will create and change permissions
	 *
	 * @param string $folder
	 * @param int $mode
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function checkPermissionsOrCreate($folder, $mode = 0755){
		if(!file_exists($folder)){
			mkdir($folder, $mode, true);
			return chmod($folder, $mode);
		}

		if(is_dir($folder)){
			if(!is_writable($folder)){
				return chmod($folder, $mode);
			}

			return true;
		}

		throw new Exception("You have no permission to write in folder: {$folder}");
	}

	/**
	 * Saving initial params.
	 *
	 * @param array $params
	 * @return $this
	 */
	private function saveInputParams($params){
		foreach($params as $param => $value){
			$var = "_" . $param;

			if(!isset($this->{$var}) || !is_null($this->{$var})){
				$this->{$var} = $value;
			}
		}

		return $this;
	}

	/**
	 * Adding $key=>$value pairs to direct replace
	 *
	 * Method works with $key, $value params
	 * OR
	 * with array($key=>$value, $key=>$value) in first param
	 *
	 * @param mixed
	 * @return $this
	 */
	public function directReplace(){
		$args = func_get_args();

		$replace = is_array($args[0]) ? $args[0] : array($args[0] => $args[1]);

		foreach($replace as $key => $val){
			$this->_direct_replace[$key] = $val;
		}

		return $this;
	}

	/**
	 * Replace prepared direct replace pairs
	 *
	 * @param string $output
	 * @return mixed
	 */
	private function makeReplace($output){
		$replaced = $output;

		foreach($this->_direct_replace as $key => $val){
			$needle = '{' . strtoupper(trim($key, '{} ')) . '}';

			$replaced = str_replace($needle, $val, $replaced);
		}

		return $replaced;
	}

	/**
	 * Excluding action from caching
	 *
	 * @param CController $controller
	 * @param string|array $action
	 * @return $this
	 */
	public function excludeActions($controller, $action){
		$cid = $controller->id;
		$action = is_array($action) ? $action : array($action);

		if(!isset($this->excluded_actions[$cid])){
			$this->excluded_actions[$cid] = array();
		}

		foreach($action as $a){
			if(in_array($a, $this->excluded_actions[$cid])){
				continue;
			}

			$this->excluded_actions[$cid][] = $a;
		}

		return $this;
	}

	/**
	 * Allowing actions to be cached if before they was excluded from caching actions
	 *
	 * @param CController $controller
	 * @param string $action
	 * @return $this
	 */
	public function allowActions($controller, $action){
		$cid = $controller->id;

		// If NULL remove all excluded actions
		if(is_null($action)){
			$this->excluded_actions[$cid] = array();
			return $this;
		}

		$action = is_array($action) ? $action : array($action);

		if(!isset($this->excluded_actions[$cid])){
			$this->excluded_actions[$cid] = array();
		}

		$this->excluded_actions[$cid] = array_diff($this->excluded_actions[$cid], $action);

		return $this;
	}

	/**
	 * Excluding params from caching
	 *
	 * @param CController $controller
	 * @param string $params
	 * @return $this
	 */
	public function excludeParams($controller, $params){
		$cid = $controller->id;
		$params = is_array($params) ? $params : array($params);

		if(!isset($this->excluded_params[$cid])){
			$this->excluded_params[$cid] = array();
		}

		foreach($params as $p => $v){
			if(is_numeric($p)){
				$p = $v;
				$v = null;
			}

			foreach($this->excluded_params[$cid] as $param => $value){
				if(is_numeric($param)){
					$param = $value;
					$value = null;
				}

				if($p == $param){
					if(is_null($v)){
						$this->excluded_params[$cid][$param] = null;
						continue 2;
					}

					$value = is_array($value) ? $value : array($value);
					$v = is_array($v) ? $value : array($v);

					$value = array_merge($value, $v);

					$this->excluded_params[$cid][$param] = $value;
					continue 2;
				}
			}

			$this->excluded_params[$cid][$p] = $v;
		}

		return $this;
	}

	/**
	 * Allow params to be cached if before they was excluded from caching params
	 *
	 * @param CController $controller
	 * @param array|string $params
	 * @return $this
	 */
	public function allowParams($controller, $params){
		$cid = $controller->id;

		// If NULL remove all excluded actions
		if(is_null($params)){
			$this->excluded_params[$cid] = array();
			return $this;
		}

		$params = is_array($params) ? $params : array($params);

		if(!isset($this->excluded_params[$cid])){
			$this->excluded_params[$cid] = array();
		}

		foreach($params as $p => $v){
			if(is_numeric($p)){
				$p = $v;
				$v = null;
			}

			foreach($this->excluded_params[$cid] as $param => $value){
				if(is_numeric($param)){
					$param = $value;
					$value = null;
				}

				if($p == $param){
					if(is_null($v)){
						unset($this->excluded_params[$cid][$param]);
						continue 2;
					}

					$value = is_array($value) ? $value : array($value);
					$v = is_array($v) ? $value : array($v);

					$value = array_diff($value, $v);

					$this->excluded_params[$cid][$param] = $value;
					continue 2;
				}
			}
		}

		return $this;
	}
}