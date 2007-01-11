<?php
include_once(DEVBLOCKS_PATH . "pear/i18n/I18N_UnicodeString.php");
include_once(APP_PATH . "/languages/".DEVBLOCKS_LANGUAGE."/strings.php");

define('PLATFORM_BUILD',13);

/**
 *  @defgroup core Devblocks Framework Core
 *  Core data structures of the framework
 */

/**
 *  @defgroup plugin Devblocks Framework Plugins
 *  Components for plugin/extensions
 */

/**
 *  @defgroup services Devblocks Framework Services
 *  Services provided by the framework
 */

/**
 * A platform container for plugin/extension registries.
 *
 * @static 
 * @ingroup core
 * @author Jeff Standen <jeff@webgroupmedia.com>
 */
class DevblocksPlatform {
	private static $request = null;
	private static $response = null;
	
	/**
	 * @private
	 */
	private function DevblocksPlatform() {}
	
	static function importGPC($var) {
		return get_magic_quotes_gpc() ? stripslashes($var) : $var;
	}
	
	/**
	 * Returns the list of extensions on a given extension point.
	 *
	 * @static
	 * @param string $point
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensions($point) {
		$results = array();
		$extensions = DevblocksPlatform::getExtensionRegistry();
		
		if(is_array($extensions))
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
			if(0 == strcasecmp($extension->point,$point)) {
				$results[] = $extension;
			}
		}
		return $results;
	}
	
	/**
	 * Returns the manifest of a given extension ID.
	 *
	 * @static
	 * @param string $extension_id
	 * @return DevblocksExtensionManifest
	 */
	static function getExtension($extension_id) {
		$result = null;
		$extensions = DevblocksPlatform::getExtensionRegistry();
		
		if(is_array($extensions))
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
			if(0 == strcasecmp($extension->id,$extension_id)) {
				$result = $extension;
			}
		}		
		
		return $result;
	}
	
	/**
	 * Returns an array of all contributed extension manifests.
	 *
	 * @static 
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensionRegistry() {
		static $extensions = array();
		
		if(!empty($extensions))
			return $extensions;
		
		$db = DevblocksPlatform::getDatabaseService();
		$plugins = DevblocksPlatform::getPluginRegistry();
		
		$sql = sprintf("SELECT e.id , e.plugin_id, e.point, e.pos, e.name , e.file , e.class, e.params ".
			"FROM extension e ".
			"ORDER BY e.plugin_id ASC, e.pos ASC"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$extension = new DevblocksExtensionManifest();
			$extension->id = $rs->fields['id'];
			$extension->plugin_id = $rs->fields['plugin_id'];
			$extension->point = $rs->fields['point'];
			$extension->name = $rs->fields['name'];
			$extension->file = $rs->fields['file'];
			$extension->class = $rs->fields['class'];
			$extension->params = @unserialize($rs->fields['params']);
			
			if(empty($extension->params))
				$extension->params = array();
			
			@$plugin = $plugins[$extension->plugin_id]; /* @var $plugin DevblocksPluginManifest */
			if(!empty($plugin)) {
				$extensions[$extension->id] = $extension;
			}
			
			$rs->MoveNext();
		}
		
		return $extensions;
	}
	
	/**
	 * Returns an array of all contributed plugin manifests.
	 *
	 * @static
	 * @return DevblocksPluginManifest[]
	 */
	static function getPluginRegistry() {
		static $plugins = array();
		
		if(!empty($plugins))
			return $plugins;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT p.id , p.enabled , p.name, p.author, p.dir ".
			"FROM plugin p"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$plugin = new DevblocksPluginManifest();
			$plugin->id = $rs->fields['id'];
//			$plugin->enabled = intval($rs->fields['enabled']);
			$plugin->name = $rs->fields['name'];
			$plugin->author = $rs->fields['author'];
			$plugin->dir = $rs->fields['dir'];
			
			if(file_exists(DEVBLOCKS_PLUGIN_PATH . $plugin->dir)) {
				$plugins[$plugin->id] = $plugin;
			}
			
			$rs->MoveNext();
		}
		
		return $plugins;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param string $id
	 * @return DevblocksPluginManifest
	 */
	static function getPlugin($id) {
		$plugins = DevblocksPlatform::getPluginRegistry();
		
		if(isset($plugins[$id]))
			return $plugins[$id];
			
		return null;
	}
	
	static function getMappingRegistry() {
		static $maps = array();
		
		if(!empty($maps))
			return $maps;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT uri,extension_id ".
			"FROM uri"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$uri = $rs->fields['uri'];
			$extension_id = $rs->fields['extension_id'];
			$maps[$uri] = $extension_id;
			
			$rs->MoveNext();
		}
		
		return $maps;
	}
	
	/**
	 * Reads and caches manifests from the plugin directory.
	 *
	 * @static 
	 * @return DevblocksPluginManifest[]
	 */
	static function readPlugins() {
		$dir = DEVBLOCKS_PLUGIN_PATH;
		$plugins = array();
		
		if (is_dir($dir)) {
		    if ($dh = opendir($dir)) {
		        while (($file = readdir($dh)) !== false) {
		        	if($file=="." || $file == ".." || 0 == strcasecmp($file,"CVS"))
		        		continue;
		        		
		        	$path = $dir . '/' . $file;
		        	if(is_dir($path) && file_exists($path.'/plugin.xml')) {
		        		$manifest = DevblocksPlatform::_readPluginManifest($file);
		        		if(null != $manifest) {
//							print_r($manifest);
							$plugins[] = $manifest;
		        		}
		        	}
		        }
		        closedir($dh);
		    }
		}
		
		return $plugins; // [TODO] Move this to the DB
	}
	
	/**
	 * Reads and caches a single manifest from a given plugin directory.
	 * 
	 * @static 
	 * @private
	 * @param string $file
	 * @return DevblocksPluginManifest
	 */
	static private function _readPluginManifest($dir) {
		if(!file_exists(DEVBLOCKS_PLUGIN_PATH.$dir.'/plugin.xml'))
			return NULL;
			
		include_once(DEVBLOCKS_PATH . 'domit/xml_domit_include.php');
		$rssRoot =& new DOMIT_Document();
		$success = $rssRoot->loadXML(DEVBLOCKS_PLUGIN_PATH.$dir.'/plugin.xml', false);
		$doc =& $rssRoot->documentElement; /* @var $doc DOMIT_Node */
		
		$eId = $doc->getElementsByPath("id",1);
		$eName = $doc->getElementsByPath("name",1);
		$eAuthor = $doc->getElementsByPath("author",1);
			
		$manifest = new DevblocksPluginManifest();
		$manifest->id = $eId->getText();
		$manifest->dir = $dir;
		$manifest->author = $eAuthor->getText();
		$manifest->name = $eName->getText();
		
		$db = DevblocksPlatform::getDatabaseService();

		$db->Replace(
			'plugin',
			array(
				'id' => $db->qstr($manifest->id),
				'name' => $db->qstr($manifest->name),
				'author' => $db->qstr($manifest->author),
				'dir' => $db->qstr($manifest->dir),
				'enabled' => 1
			),
			array('id'),
			false
		);
		
		// [JAS]: URI Mapping
		$db->Execute("DELETE FROM uri WHERE plugin_id = %s",
			$db->qstr($manifest->id)
		);
		
		$eUris =& $doc->getElementsByPath("mapping/uri"); /* @var $eUris DOMIT_NodeList */
		if(is_array($eUris->arNodeList))
		foreach($eUris->arNodeList as $eUri) { /* @var $eUri DOMIT_Node */
			$sUri = $eUri->getAttribute('value');
			$sExtensionId = $eUri->getAttribute('extension_id');
			
			$db->Replace(
				'uri',
				array(
					'uri' => $db->qstr($sUri),
					'plugin_id' => $db->qstr($manifest->id),
					'extension_id' => $db->qstr($sExtensionId)
				),
				array('uri'),
				false
			);
		};
				
		$eExtensions =& $doc->getElementsByPath("extensions/extension"); /* @var $eExtensions DOMIT_NodeList */
		if(is_array($eExtensions->arNodeList))
		foreach($eExtensions->arNodeList as $eExtension) { /* @var $eExtension DOMIT_Node */
			
			$sPoint = $eExtension->getAttribute('point');
			$eId = $eExtension->getElementsByPath('id',1);
			$eName = $eExtension->getElementsByPath('name',1);
			$eClassName = $eExtension->getElementsByPath('class/name',1);
			$eClassFile = $eExtension->getElementsByPath('class/file',1);
			$params = $eExtension->getElementsByPath('params/param');
			$extension = new DevblocksExtensionManifest();

			if(empty($eId) || empty($eName))
				continue;

			$extension->id = $eId->getText();
			$extension->plugin_id = $manifest->id;
			$extension->point = $sPoint;
			$extension->name = $eName->getText();
			$extension->file = $eClassFile->getText();
			$extension->class = $eClassName->getText();
				
			if(null != $params && !empty($params->arNodeList)) {
				foreach($params->arNodeList as $pnode) {
					$sKey = $pnode->getAttribute('key');
					$sValue = $pnode->getAttribute('value');
					$extension->params[$sKey] = $sValue;
				}
			}
				
			$manifest->extensions[] = $extension;
		}
		
		// [JAS]: Extension caching
		if(is_array($manifest->extensions))
		foreach($manifest->extensions as $pos => $extension) { /* @var $extension DevblocksExtensionManifest */
			$db->Replace(
				'extension',
				array(
					'id' => $db->qstr($extension->id),
					'plugin_id' => $db->qstr($extension->plugin_id),
					'point' => $db->qstr($extension->point),
					'pos' => $pos,
					'name' => $db->qstr($extension->name),
					'file' => $db->qstr($extension->file),
					'class' => $db->qstr($extension->class),
					'params' => $db->qstr(serialize($extension->params))
				),
				array('id'),
				false
			);
		}

		return $manifest;
	}

	
	/**
	 * Enter description here...
	 *
	 * @return ADOConnection
	 */
	static function getDatabaseService() {
		return _DevblocksDatabaseManager::getInstance();
	}
	
	/**
	 * @return _DevblocksEmailManager
	 */
	static function getMailService() {
		return _DevblocksEmailManager::getInstance();
	}
	
	/**
	 * @return _DevblocksSessionManager
	 */
	static function getSessionService() {
		return _DevblocksSessionManager::getInstance();
	}
	
	/**
	 * @return Smarty
	 */
	static function getTemplateService() {
		return _DevblocksTemplateManager::getInstance();
	}
	
	/**
	 * @return _DevblocksTranslationManager
	 */
	static function getTranslationService() {
		return _DevblocksTranslationManager::getInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return DevblocksHttpRequest
	 */
	static function getHttpRequest() {
		return self::$request;
	}
	
	/**
	 * @param DevblocksHttpRequest $request
	 */
	static function setHttpRequest($request) {
		if(!is_a($request,'DevblocksHttpRequest')) return null;
		self::$request = $request;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return DevblocksHttpRequest
	 */
	static function getHttpResponse() {
		return self::$response;
	}
	
	/**
	 * @param DevblocksHttpResponse $$response
	 */
	static function setHttpResponse($response) {
		if(!is_a($response,'DevblocksHttpResponse')) return null;
		self::$response = $response;
	}
	
	/**
	 * @return DevblocksHttpRequest
	 */
	static function readRequest() {
		$url = URL::getInstance();
		
		if(DEVBLOCKS_REWRITE) {
			$parts = $url->parseURL($_SERVER['REQUEST_URI']);
			$query = $_SERVER['QUERY_STRING'];
			
		} else {
			$argc = $url->parseQueryString($_SERVER['QUERY_STRING']);
			$parts = array_values($argc);
			$query = '';
		}

		if(empty($parts)) {
			// Overrides (Form POST, etc.)
			@$uri = DevblocksPlatform::importGPC($_REQUEST['c']); // extension
			if(!empty($uri)) $parts[] = $uri;

			@$listener = DevblocksPlatform::importGPC($_REQUEST['a']); // listener
			if(!empty($listener)) $parts[] = $listener;
			
			// Use our default URI if we didn't have an override
			if(empty($parts)) $parts[] = APP_DEFAULT_URI;
		}
		
		$request = new DevblocksHttpRequest($parts,$query); 
		DevblocksPlatform::setHttpRequest($request);
		
		return $request;
	}
	
	/**
	 * @param DevblocksHttpRequest $request
	 */
	static function processRequest($request,$is_ajax=false) {
		if(!is_a($request,'DevblocksHttpRequest')) return null;
		
		$path = $request->path;
		$command = array_shift($path);
		
		// [JAS]: Offer the platform a chance to intercept.
		switch($command) {

			// [JAS]: Resource proxy URI
			case 'resource':
				$plugin_id = $path[0];
				$file = $path[1];
				
				$plugin = DevblocksPlatform::getPlugin($plugin_id);
				if(null == $plugin) continue;
				
				// [JAS]: [TODO] Run through an audit to make sure this isn't abusable (../plugin.xml, etc.)
				$dir = DEVBLOCKS_PLUGIN_PATH . $plugin->dir . '/resources/'.$file;
				
				if(file_exists($dir)) {
					echo file_get_contents($dir,false);
				} else {
					echo "Requested resource not found!";
				}
								
				break;
				
			// [JAS]: Plugin-supplied URIs
			default:
				$mapping = DevblocksPlatform::getMappingRegistry();
				
				/*
				 * [JAS]: Try to find our command in the URI lookup first, and if we
				 * fail then fall back to raw extension ids.
				 */
				if(null == ($extension_id = $mapping[$command])) {
					$extension_id = $command;
				}
				
				if(null != ($manifest = DevblocksPlatform::getExtension($extension_id))) {
					$inst = $manifest->createInstance(); /* @var $inst DevblocksHttpRequestHandler */
					
					if($inst instanceof DevblocksHttpRequestHandler) {
						$inst->handleRequest($request);
						
						// [JAS]: If we didn't write a new response, repeat the request
						if(null == ($response = DevblocksPlatform::getHttpResponse())) {
							$response = new DevblocksHttpResponse($request->path);
							DevblocksPlatform::setHttpResponse($response);
						}
						
						// [JAS]: An Ajax request doesn't need the full Http cycle
						if(!$is_ajax) {
							$inst->writeResponse($response);
						}
					}
					
				} else {
					// [JAS]: [TODO] This would be a good point for a 404 URI Platform option		
					echo "No request handler was found for this URI.";
				}
				break;
		}
		
		return;
	}
	
	/**
	 * Initializes the plugin platform (paths, etc).
	 *
	 * @static 
	 * @return void
	 */
	static function init() {
		// [JAS] [MDF]: Automatically determine the relative webpath to Devblocks files
		if(!defined('DEVBLOCKS_WEBPATH')) {
			$php_self = $_SERVER["PHP_SELF"];
			$pos = strrpos($php_self,'/');
			$php_self = substr($php_self,0,$pos) . '/';
			@define('DEVBLOCKS_WEBPATH',$php_self);
		}
	}
	
	/**
	 * Prints out the Platform Javascript Library for use by Application.
	 * This library provides the ability to rewrite URLs in Javascript for 
	 * Ajax functionality, etc.
	 * 
	 * @example
	 * <script language="javascript" type="text/javascript">{php}DevblocksPlatform::printJavascriptLibrary();{/php}</script>
	 */
	static function printJavascriptLibrary() {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(__FILE__);
		$tpl->caching = 2;
		$tpl->cache_lifetime = 3600*24; // 1day
		$tpl->display("file:$path/devblocks.tpl.js",APP_BUILD);
		$tpl->caching = 0;
	}
	
	/*
	 * [JAS]: [TODO] This should move into a DatabaseUpdateService area later 
	 * (where plugins can also contribute their patches)
	 */
	static function getDatabaseSchema() {
		$tables = array();
		
		$tables['extension'] = "
			id C(128) DEFAULT '' NOTNULL PRIMARY,
			plugin_id C(128) DEFAULT 0 NOTNULL,
			point C(128) DEFAULT '' NOTNULL,
			pos I2 DEFAULT 0 NOTNULL,
			name C(128) DEFAULT '' NOTNULL,
			file C(128) DEFAULT '' NOTNULL,
			class C(128) DEFAULT '' NOTNULL,
			params B DEFAULT '' NOTNULL
		";
		
		$tables['plugin'] = "
			id C(128) PRIMARY,
			enabled I1 DEFAULT 0 NOTNULL,
			name C(128) DEFAULT '' NOTNULL,
			author C(64) DEFAULT '' NOTNULL,
			dir C(128) DEFAULT '' NOTNULL
		";
		
		$tables['property_store'] = "
			extension_id C(128) DEFAULT '' NOTNULL PRIMARY,
			instance_id I DEFAULT 0 NOTNULL PRIMARY,
			property C(128) DEFAULT '' NOTNULL PRIMARY,
			value C(255) DEFAULT '' NOTNULL
		";
		
		$tables['session'] = "
			sesskey C(64) PRIMARY,
			expiry T,
			expireref C(250),
			created T NOTNULL,
			modified T NOTNULL,
			sessdata B
		";
		
		$tables['login'] = "
			id I PRIMARY,
			login C(32) NOTNULL,
			password C(32) NOTNULL,
			admin I1 DEFAULT 0 NOTNULL
		";
		
		$tables['uri'] = "
			uri C(32) PRIMARY,
			plugin_id C(128) NOTNULL,
			extension_id C(128) NOTNULL
		";

		return $tables;
	}
	
};

/**
 * Manifest information for plugin.
 * @ingroup plugin
 */
class DevblocksPluginManifest {
	var $id = '';
	var $name = '';
	var $author = '';
	var $dir = '';
	var $extensions = array();
};

/**
 * Manifest information for a plugin's extension.
 * @ingroup plugin
 */
class DevblocksExtensionManifest {
	var $id = '';
	var $plugin_id ='';
	var $point = '';
	var $name = '';
	var $file = '';
	var $class = '';
	var $params = array();

	function DevblocksExtensionManifest() {}
	
	/**
	 * Creates and loads a usable extension from a manifest record.  The object returned 
	 * will be of type $class defined by the manifest.  $instance_id is passed as an 
	 * argument to uniquely identify multiple instances of an extension.
	 *
	 * @param integer $instance_id
	 * @return object
	 */
	function createInstance($instance_id=1) {
		if(empty($this->id) || empty($this->plugin_id)) // empty($instance_id) || 
			return null;

//		$plugins = DevblocksPlatform::getPluginRegistry();
//		
//		if(!isset($plugins[$this->plugin_id]))
//			return null;
//		
//		$plugin = $plugins[$this->plugin_id]; /* @var $plugin DevblocksPluginManifest */

		$plugin = DevblocksPlatform::getPlugin($this->plugin_id);
			
		$class_file = DEVBLOCKS_PLUGIN_PATH . $plugin->dir . '/' . $this->file;
		$class_name = $this->class;

		if(!file_exists($class_file))
			return null;
			
		include_once($class_file);
		if(!class_exists($class_name)) {
			return null;
		}
			
		$instance = new $class_name($this,$instance_id);
		return $instance;
	}
}

/**
 * The superclass of instanced extensions.
 *
 * @abstract 
 * @ingroup plugin
 */
class DevblocksExtension {
	var $manifest = null;
	var $instance_id = 1;
	var $id  = '';
	var $params = array();
	
	/**
	 * Constructor
	 *
	 * @private
	 * @param DevblocksExtensionManifest $manifest
	 * @param int $instance_id
	 * @return DevblocksExtension
	 */
	function DevblocksExtension($manifest,$instance_id=1) { /* @var $manifest DevblocksExtensionManifest */
		$this->manifest = $manifest;
		$this->id = $manifest->id;
		$this->instance_id = $instance_id;
		$this->params = $this->_getParams();
	}
	
	/**
	 * Loads parameters unique to this extension instance.  Returns an 
	 * associative array indexed by parameter key.
	 *
	 * @private
	 * @return array
	 */
	function _getParams() {
//		static $params = null;
		
		if(empty($this->id) || empty($this->instance_id))
			return null;
		
//		if(null != $params)
//			return $params;
		
		$params = $this->manifest->params;
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT property,value ".
			"FROM property_store ".
			"WHERE extension_id=%s AND instance_id='%d' ",
			$db->qstr($this->id),
			$this->instance_id
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$params[$rs->fields['property']] = $rs->fields['value'];
			$rs->MoveNext();
		}
		
		return $params;
	}
	
	/**
	 * Persists any changed instanced extension parameters.
	 *
	 * @return void
	 */
	function saveParams() {
		if(empty($this->instance_id) || empty($this->id))
			return FALSE;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(is_array($this->params))
		foreach($this->params as $k => $v) {
			$db->Replace(
				'property_store',
				array('extension_id'=>$this->id,'instance_id'=>$this->instance_id,'property'=>$db->qstr($k),'value'=>$db->qstr($v)),
				array('extension_id','instance_id','property'),
				true
			);
		}
	}
	
};

/**
 * Session Management Singleton
 *
 * @static 
 * @ingroup services
 */
class _DevblocksSessionManager {
	var $visit = null;
	
	/**
	 * @private
	 */
	private function _DevblocksSessionManager() {}
	
	/**
	 * Returns an instance of the session manager
	 *
	 * @static
	 * @return _DevblocksSessionManager
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$db = DevblocksPlatform::getDatabaseService();
			if(!$db->IsConnected()) return null;
			
			include_once(DEVBLOCKS_PATH . "adodb/session/adodb-session2.php");
			$options = array();
			$options['table'] = 'session';
			ADOdb_Session::config(APP_DB_DRIVER, APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_DATABASE, $options);
			ADOdb_session::Persist($connectMode=false);
			ADOdb_session::lifetime($lifetime=86400);
			
			//session_name("cerb4");
			session_set_cookie_params(0);
			session_start();
			$instance = new _DevblocksSessionManager();
			$instance->visit = $_SESSION['um_visit']; /* @var $visit DevblocksSession */
		}
		
		return $instance;
	}
	
	/**
	 * Returns the current session or NULL if no session exists.
	 *
	 * @return DevblocksSession
	 */
	function getVisit() {
		return $this->visit;
	}
	
	/**
	 * Attempts to create a session by login/password.  On success a DevblocksSession 
	 * is returned.  On failure NULL is returned.
	 *
	 * @param string $login
	 * @param string $password
	 * @return DevblocksSession
	 */
	function login($login,$password) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT id,login,admin ".
			"FROM login ".
			"WHERE login = %s ".
			"AND password = MD5(%s)",
				$db->qstr($login),
				$db->qstr($password)
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if($rs->NumRows()) {
			$visit = new DevblocksSession();
				$visit->id = intval($rs->fields['id']);
				$visit->login = $rs->fields['login'];
				$visit->admin = intval($rs->fields['admin']);
			$this->visit = $visit;
			$_SESSION['um_visit'] = $visit;
			return $this->visit;
		}
		
		$_SESSION['um_visit'] = null;
		return null;
	}
	
	/**
	 * Kills the current session.
	 *
	 */
	function logout() {
		$this->visit = null;
		unset($_SESSION['um_visit']);
	}
}

/**
 * Email Management Singleton
 *
 * @static 
 * @ingroup services
 */
class _DevblocksEmailManager {
	/**
	 * @private
	 */
	private function _DevblocksEmailManager() {}
	
	public function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksEmailManager();
		}
		return $instance;
	}
	
	static function send($server, $sRCPT, $headers, $body) {
		// mailer setup
		require_once(DEVBLOCKS_PATH . 'pear/Mail.php');
		$mail_params = array();
		$mail_params['host'] = $server;
		$mailer =& Mail::factory("smtp", $mail_params);

		$result = $mailer->send($sRCPT, $headers, $body);
		return $result;
	}
	
	static function getMessages($server, $port, $service, $username, $password) {
		if (!extension_loaded("imap")) die("IMAP Extension not loaded!");
		require_once(DEVBLOCKS_PATH . 'pear/mimeDecode.php');
		
		$mailbox = imap_open("{".$server.":".$port."/service=".$service."/notls}INBOX",
							 !empty($username)?$username:"superuser",
							 !empty($password)?$password:"superuser")
			or die("Failed with error: ".imap_last_error());
		$check = imap_check($mailbox);
		
		$messages = array();
		$params = array();
		$params['include_bodies']	= true;
		$params['decode_bodies']	= true;
		$params['decode_headers']	= true;
		$params['crlf']				= "\r\n";
	
		for ($i=1; $i<=$check->Nmsgs; $i++) {
			$headers = imap_fetchheader($mailbox, $i);
			$body = imap_body($mailbox, $i);
			$params['input'] = $headers . "\r\n\r\n" . $body;
			$structure = Mail_mimeDecode::decode($params);
			$messages[] = $structure;
		}
		
		imap_close($mailbox);
		return $messages;
	}
}

/**
 * A single session instance
 *
 * @ingroup core
 */
class DevblocksSession {
	var $id = 0;
	var $login = '';
	var $admin = 0;
	
	/**
	 * Returns TRUE if the current session has administrative privileges, or FALSE otherwise.
	 *
	 * @return boolean
	 */
	function isAdmin() {
		return $this->admin != 0;
	}
};

/**
 * Smarty Template Manager Singleton
 *
 * @ingroup services
 */
class _DevblocksTemplateManager {
	/**
	 * Constructor
	 * 
	 * @private
	 */
	private function _DevblocksTemplateManager() {}
	/**
	 * Returns an instance of the Smarty Template Engine
	 * 
	 * @static 
	 * @return Smarty
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			require(DEVBLOCKS_PATH . 'smarty/Smarty.class.php');
			$instance = new Smarty();
			$instance->template_dir = APP_PATH . '/templates';
			$instance->compile_dir = DEVBLOCKS_PATH . 'templates_c';
			$instance->cache_dir = DEVBLOCKS_PATH . 'cache';
			
			//$smarty->config_dir = DEVBLOCKS_PATH. 'configs';
			$instance->caching = 0;
			$instance->cache_lifetime = 0;
		}
		return $instance;
	}
};

/**
 * ADODB Database Singleton
 *
 * @ingroup services
 */
class _DevblocksDatabaseManager {
	
	/**
	 * Constructor 
	 * 
	 * @private
	 */
	private function _DevblocksDatabaseManager() {}
	
	/**
	 * Returns an ADODB database resource
	 *
	 * @static 
	 * @return ADOConnection
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			include_once(DEVBLOCKS_PATH . "adodb/adodb.inc.php");
			$ADODB_CACHE_DIR = APP_PATH . "/cache";
			@$instance =& ADONewConnection(APP_DB_DRIVER); /* @var $instance ADOConnection */
			@$instance->Connect(APP_DB_HOST,APP_DB_USER,APP_DB_PASS,APP_DB_DATABASE);
			@$instance->SetFetchMode(ADODB_FETCH_ASSOC);
		}
		return $instance;
	}
	
};

/**
 * Unicode Translation Singleton
 *
 * @ingroup services
 */
class _DevblocksTranslationManager {
	/**
	 * Constructor
	 * 
	 * @private
	 */
	private function _DevblocksTranslationManager() {}
	
	/**
	 * Returns an instance of the translation singleton.
	 *
	 * @static 
	 * @return DevblocksTranslationManager
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksTranslationManager();
		}
		return $instance;
	}

	/**
	 * Translate an externalized string token into a Unicode string in the 
	 * current language.  The $vars argument provides a list of substitutions 
	 * similar to sprintf().
	 *
	 * @param string $token The externalized string token to replace
	 * @param array $vars A list of substitutions
	 * @return string A string with the Unicode values encoded in UTF-8
	 */
	function say($token,$vars=array()) {
		global $language;
		
		if(!isset($language[$token]))
			return "[#".$token."#]";
		
		if(!empty($vars)) {
			$u = new I18N_UnicodeString(vsprintf($language[$token],$vars),'UTF8');
		} else {
			$u = new I18N_UnicodeString($language[$token],'UTF8');
		}
		return $u->toUtf8String();
	}

}

/*
 * Platform Extensions
 */

abstract class DevblocksApplication {
	
}

interface DevblocksHttpRequestHandler {
	/**
	 * @param DevblocksHttpRequest
	 * @return DevblocksHttpResponse
	 */
	public function handleRequest($request);
	public function writeResponse($response);
}

class DevblocksHttpRequest extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path) {
		parent::__construct($path);
	}
}

class DevblocksHttpResponse extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path) {
		parent::__construct($path);
	}
}

abstract class DevblocksHttpIO {
	public $path = array();
//	public $query = null;
	
	/**
	 * Enter description here...
	 *
	 * @param array $path
	 */
	function __construct($path) {
		$this->path = $path;
//		$this->query = $query;
	}
}

/*
 * [JAS]: [TODO] Truly move into framework
 */
class URL {
	private function URL() {}
	
	/**
	 * @return URL
	 */
	public static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new URL();
		}
		return $instance;
	}
	
	function parseQueryString($args) {
		$argc = array();
		if(empty($args)) return $argc;
		
		$query = explode('&', $args);
		if(is_array($query))
		foreach($query as $q) {
			if(empty($q)) continue;
			$v = explode('=',$q);
			if(empty($v)) continue;
			$argc[$v[0]] = $v[1];
		}
		
		return $argc;
	}
	
	function parseURL($url) {
		// [JAS]: Use the index.php page as a reference to deconstruct the URI
		$pos = stripos($_SERVER['PHP_SELF'],'index.php',0);
		if($pos === FALSE) return null;
		
		// [JAS]: Extract the basedir of the path
		$basedir = substr($url,0,$pos);
		
		// [JAS]: Remove query string
		$pos = stripos($url,'?',0);
		if($pos !== FALSE) {
			$url = substr($url,0,$pos);
		}
		
		$request = substr($url,strlen($basedir));
		if(empty($request)) return array();
		
		$parts = split('/', $request);
		
		return $parts;
	}
	
	function write($sQuery='') {
		$args = URL::parseQueryString($sQuery);
		$c = @$args['c'];
		
		// [JAS]: Internal non-component URL (images/css/js/etc)
		if(empty($c)) {
			$contents = sprintf("%s%s",
				DEVBLOCKS_WEBPATH,
				$sQuery
			);
			
		// [JAS]: Component URL
		} else {
			if(DEVBLOCKS_REWRITE) {
				$contents = sprintf("%s%s",
					DEVBLOCKS_WEBPATH,
					(!empty($args) ? implode('/',array_values($args)) : '')
				);
				
			} else {
				$contents = sprintf("%sindex.php?",
					DEVBLOCKS_WEBPATH,
					(!empty($args) ? $args : '')
				);
			}
		}
		
		return $contents;
	}
}


?>