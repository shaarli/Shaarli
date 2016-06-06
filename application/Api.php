<?php

class Account {
	public $name = "";
	public $num = 0;
	public $version = 0;
}

class Api
{
	const LINK_DATE_DAY = 'Ymd';

	const PARAM_O = 'o';
	const PARAM_M = 'm';
	
	const O_ACCOUNT = 'account';
	const O_LINKS = 'links';
	const O_LINK = 'link';
	const O_TAGS = 'tags';
		
	const M_SEARCH = 'search';
	const M_UPDATED = 'updated';
	const M_DELETED = 'deleted';
	
	const M_SEARCH_SEARCH_TERM = 'searchterm';
	const M_SEARCH_SEARCH_TAGS = 'searchtags';
	const M_SEARCH_KEY = 'key';
	const M_SEARCH_HASH = 'hash';
	
	const PARAM_MIN_DATE='mindate';
	const PARAM_NB = 'nb';
	
	const LOGIN_ERROR_UNKNOWN = 'LOGIN_ERROR_UNKNOWN';
	const LOGIN_ERROR_DATE_NOT_FOUND = 'LOGIN_ERROR_DATE_NOT_FOUND';
	const LOGIN_ERROR_CLIENT_SERVER_TIME_SYNC = 'LOGIN_ERROR_CLIENT_SERVER_TIME_SYNC';
	const LOGIN_ERROR_TOKEN_VERIFICATION_FAILED = 'LOGIN_ERROR_TOKEN_VERIFICATION_FAILED';

	const POST_EDIT_ERROR_URL_EMPTY = 'POST_EDIT_ERROR_URL_EMPTY';
	const POST_EDIT_ERROR_LINK_NOT_FOUND = 'POST_EDIT_ERROR_LINK_NOT_FOUND';
	const POST_EDIT_ERROR_DUPLICATE_URL = 'POST_EDIT_ERROR_DUPLICATE_URL';
	
	const MAX_TIMESTAMP_CLIENT_SERVER_DIFF = 5;
	
	const VALUE_ALL = 'all';
	
	private $loggedin; // Is the used logged in ? (used to filter private links)
	private $allowed_methods = array('get', 'post', 'delete');
	private $linkdb;
	private $linkdeleteddb;
	
	public function __construct()
	{
		$this->getLoggedIn();
	
		$method = $this->getMethod();
		if ($method === null) {
			exit;
		}
		$this->linkdb = new LinkDB(
				$GLOBALS['config']['DATASTORE'],
				$this->loggedin,
				$GLOBALS['config']['HIDE_PUBLIC_LINKS'],
				$GLOBALS['redirector']
				);
		$this->linkdeleteddb = new LinkDeletedDB(
				$GLOBALS['config']['DATASTORE_DELETED'],
				$this->loggedin
				);
		$this->$method();
	}
	
	private function getMethod()
	{
		if (isset($_SERVER['REQUEST_METHOD']) === false) {
			$this->send(400);
			return null;
		}
	
		$method = strtolower($_SERVER['REQUEST_METHOD']);
	
		if (in_array($method, $this->allowed_methods) === false) {
			$this->send(405);
			return null;
		}
	
		return $method;
	}
	
	private function checkParam($name, $table = null)
	{
		if ($table === null) {
			$table = $_GET;
		}
		return (isset($table[$name]) === true
				&&
				is_string($table[$name]) === true
				&&
				empty($table[$name]) === false
				);
	}
	
	private function getLoggedIn()
	{
		$this->loggedin = false;
	
		$date_request = null;
		$authorization_client = null;
		$android_version = null;
	
		$headers = apache_request_headers();
		foreach ($headers as $header => $value) {
			if ($header==='Authorization') $authorization_client=$value;
			if ($header==='Date') $date_request=strtotime($value);
			if ($header==='X-SHAARLI-ANDROID-VERSION') $android_version=$value;
		}
	
		if (empty($date_request) && empty($_SERVER['HTTP_DATE'])) {
			$this->send(403, Api::LOGIN_ERROR_DATE_NOT_FOUND); 
			exit;
		} elseif (empty($date_request)) {
			$date_request = strtotime($_SERVER['HTTP_DATE']);
		}

		if (empty($authorization_client) && empty($_SERVER['REMOTE_USER'])) {
			$this->send(403, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED); 
			exit;
		} elseif (empty($authorization_client)) {
			$authorization_client = $_SERVER['REMOTE_USER'];
		}
		
		if (empty($android_version) && isset($_SERVER['X-SHAARLI-ANDROID-VERSION'])) {
			$android_version = $_SERVER['X-SHAARLI-ANDROID-VERSION'];
		} 
	  
		$date_server = time();
		$seconds = $date_server - $date_request;
	
		if ($seconds > Api::MAX_TIMESTAMP_CLIENT_SERVER_DIFF) {
			$this->send(403, Api::LOGIN_ERROR_CLIENT_SERVER_TIME_SYNC);
			exit;
		}
		
		if (empty($authorization_client)) {
			$this->send(403, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED);
			exit;
		}
				
		$authorization_client_array = explode(":", $authorization_client);
		$authorization_client_name = $authorization_client_array[0];
		$authorization_client_hash = $authorization_client_array[1];
	
		$calculated_hash = $_SERVER['REQUEST_METHOD']."\n".$_SERVER['CONTENT_TYPE']."\n".$_SERVER['HTTP_DATE']."\n".$_SERVER['QUERY_STRING'];
	
		if (hash_hmac('sha256', $GLOBALS['api_secret_key'], $calculated_hash) !== $authorization_client_hash) {
			$this->send(403, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED); 
			exit;
		}
	
		$this->loggedin = true;
	}
	
	
	private function get()
	{
		if ($this->checkParam(Api::PARAM_O) === true) {
			switch ($_GET[Api::PARAM_O]) {
				case Api::O_ACCOUNT:
					$this->checkaccount();
					break;
				case Api::O_LINKS:
					if ($this->checkParam(Api::PARAM_M) === true) {
						switch ($_GET[Api::PARAM_M]) {
							case Api::M_SEARCH:
								if ($this->checkParam(LinkFilter::$FILTER_DAY) === true) {
									$this->getLinksByDay($_GET[LinkFilter::$FILTER_DAY]);
								} else {
									$this->search();
								}
								break;
							case Api::M_UPDATED:
								$this->getLinksUpdated();
								break;
							case Api::M_DELETED:
								$this->getLinksDeleted();
								break;
							default:
								$this->send(400, 'Bad Request');
								return;
						}
					} else {
						$this->send(400, 'Bad Request');
						return;
					}
					break;
				case Api::O_TAGS:
					$this->getTags();
					break;
				case Api::O_LINK:
					if ($this->checkParam(Api::PARAM_M) === true) {
						switch ($_GET[Api::PARAM_M]) {
							case Api::M_SEARCH:
								if ($this->checkParam(Api::M_SEARCH_KEY) === true) {
									$this->getLinkByKey($_GET[Api::M_SEARCH_KEY]);
								} elseif ($this->checkParam(Api::M_SEARCH_HASH) === true) {
									$this->getLinkByHash($_GET[Api::M_SEARCH_HASH]);
								} else {
									$this->send(400, 'Bad Request');
									return;
								}
								break;
							case Api::M_DELETED:
								if ($this->checkParam(Api::M_SEARCH_KEY) === true) {
									$this->getLinkDeletedByKey($_GET[Api::M_SEARCH_KEY]);
								} else {
									$this->send(400, 'Bad Request');
									return;
								}
								break;
						}
					} else {
						$this->send(400, 'Bad Request');
						return;
					}
					break;
				default:
					$this->send(400, 'Bad Request');
					return;
			}
		} else {
			$this->send(400, 'Bad Request');
			return;
		}
		
	}
	
	//?do=api&o=account
	private function checkAccount(){
		$account = new Account();
		$account->name = $GLOBALS['title'];
		$account->num  = count($this->linkdb);
		$account->version = shaarli_version;
		$this->send(200, $account);
	}
	
	//?do=api&o=tags
	private function getTags()
	{
		$tags= $this->linkdb->allTags();
		$maxcount=0; foreach($tags as $key=>$value) $maxcount=max($maxcount,$value);
		ksort($tags);
		$tagList=array();
		foreach($tags as $key=>$value)
		{
			$tag = array(
					'title' => $key,
					'count' => $value
			);
			$tagList[] = $tag;
		}
		$this->send(200, array_values($tagList));
	}
	
	//?do=api&o=links&m=updated
	private function getLinksUpdated()
	{
		$filtered = array();
		foreach ($this->linkdb as $l) {
			if (!empty($l['editdate'])) {
				$filtered[$l['editdate']] = $l;
			}
		}
		$filtered = $this->filterByDate($filtered,'editdate');
		krsort($filtered);
		$this->filterByPage($filtered);
	}
	
	//?do=api&o=links&m=deleted
	private function getLinksDeleted(){
		$filtered = $this->linkdeleteddb;
		$filtered = $this->filterByDate($filtered,'deletedate');
		$this->filterByPage($filtered);
	}
	

	//?do=api&o=link&m=deleted&key=20160519_064930
	private function getLinkDeletedByKey($key)
	{
		$link = isset($this->linkdeleteddb[$key]) === true ? $this->linkdeleteddb[$key] : false;
		if ($link === false) {
			$this->send(404, 'Not Found');
			return;
		}
		$this->send(200, $link);
	}
	
	
	//?do=api&o=links&m=search&searchterm=shaarli&searchtags=todo
	//?do=api&o=links&m=search&searchterm=shaarli
	//?do=api&o=links&m=search&searchtags=todo
	private function search()
	{
		$filtered = $this->linkdb->filterSearch($_GET, false, false);
		$filtered = $this->filterByDate($filtered,'linkdate');
		$this->filterByPage($filtered);
	}
	
	//?do=api&o=links&m=search&FILTER_DAY=20160127
	private function getLinksByDay($day)
	{
		try {
			$links = $this->linkdb->filterDay($day);
			$this->filterByPage($links);
		} catch (Exception $e) {
			$this->send(400, 'Bad Request');
			return;
		}
	}
	
	//?do=api&o=link&m=search&key=20160519_064930
	private function getLinkByKey($key)
	{
		$link = isset($this->linkdb[$key]) === true ? $this->linkdb[$key] : false;
		if ($link === false) {
			$this->send(404, 'Not Found');
			return;
		}
		$this->send(200, $link);
	}
	
	//?do=api&o=link&m=search&hash=rJKFTg
	private function getLinkByHash($hash)
	{
		try {
			$link = $this->linkdb->filterHash($hash);
			if (count($link) === 0) {
				$this->send(404, 'Not Found');
				return;
			}
			$this->send(200, current($link));
		} catch (LinkNotFoundException $lnfe) {
			$this->send(404, 'Not Found');
			return;
		}
	}
	
	//?do=api&xxx&mindate=20160127
	private function filterByDate($linksToDisplay,$field)
	{
		if (empty($_GET[Api::PARAM_MIN_DATE])) {
			return $linksToDisplay;
		}  
				
		$date = $_GET[Api::PARAM_MIN_DATE];
		
		if (checkDateFormat('Ymd_His', $date)) {
			$dateMax = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $date);
		} else if (checkDateFormat('Ymd', $date)) {
			$dateMax = DateTime::createFromFormat(Api::LINK_DATE_DAY, $date);
			$dateMax->setTime(0,0,0);
		} else {
			return $linksToDisplay;
		}
		
		$filtered = array();
		foreach ($linksToDisplay as $l) {
			$dateLink = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $l[$field]);
			$diffInSeconds = $dateLink->getTimestamp() - $dateMax->getTimestamp();
			if ($diffInSeconds>=0) {
				if (empty($l['editdate'])) {
					$filtered[$l['linkdate']] = $l;
				} else {
					$filtered[$l['editdate']] = $l;
				}
			}
		}
		krsort($filtered);
		return $filtered;
	}
	
	//?do=api&xxx&nb=20
	private function filterByPage($linksToDisplay)
	{
		$nblinksToDisplay = 2;  // Number of links to display.
		// In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
		if (!empty($_GET[Api::PARAM_NB])) {
			$nblinksToDisplay = $_GET[Api::PARAM_NB]==Api::VALUE_ALL ? count($linksToDisplay) : max(intval($_GET[Api::PARAM_NB]), 1);
		}
		$i=0;
		//$linksToDisplay = $this->linkdb;
		$keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }
	
		$pagecount = ceil(count($keys) / $nblinksToDisplay);
		$pagecount = $pagecount == 0 ? 1 : $pagecount;
	
		$page= empty($_GET['page']) ? 1 : intval($_GET['page']);
		$page = $page < 1 ? 1 : $page;
		$page = $page > $pagecount ? $pagecount : $page;
		// Start index.
		$i = ($page-1) * $nblinksToDisplay;
		$end = $i + $nblinksToDisplay;
	
		$links = array();
		while ($i<$end && $i<count($keys))
		{
			$links[] = $linksToDisplay[$keys[$i]];
			$i++;
		}
	
		$this->send(200, $links);
	}
	
	private function post()
	{
		if ($this->loggedin === false) {
			$this->send(403, Api::LOGIN_ERROR_UNKNOWN);
			return;
		}
		if ($this->checkParam('restore') === true) {
			$key = $_GET['restore'];
			$link = array(
					'title' => null,
					'url' => null,
					'description' => '',
					'private' => 0,
					'linkdate' => $key,
					'tags' => '',
			);
			$is_new = false;
			
			unset($this->linkdeleteddb[$key]);
			$this->linkdeleteddb->savedb($GLOBALS['config']['PAGECACHE']);
			
		} else if ($this->checkParam('key') === true) {
			$key = $_GET['key'];
			if (isset($this->linkdb[$key]) === false) {
				$this->send(404, Api::POST_EDIT_ERROR_LINK_NOT_FOUND);
				return;
			}
			$link = $this->linkdb[$key];
			$is_new = false;
		} else if ($this->checkParam('hash') === true) {
			$hash = $_GET['hash'];
			$link = $this->linkdb->filterSmallHash($hash);
			if (count($link) === 0) {
				$this->send(404, Api::POST_EDIT_ERROR_LINK_NOT_FOUND);
				return;
			}
			$key = key($link);
			$is_new = false;
		} else {
			$key = date('Ymd_His');
			$link = array(
					'title' => null,
					'url' => null,
					'description' => '',
					'private' => 0,
					'linkdate' => $key,
					'tags' => '',
			);
			$is_new = true;
		}
	
		$post = array_map('trim', $_POST);
	
		foreach (array('title', 'url', 'description', 'linkdate', 'tags') as $data) {
			if ($this->checkParam($data, $post) === true) {
				$link[$data] = $post[$data];
			}
		}
	
		if (empty($post['title']) === true)  {
			$link['title'] = "";
		}
	
		if (empty($post['description']) === true)  {
			$link['description'] = "";
		}
	
		if (empty($post['tags']) === true)  {
			$link['tags'] = "";
		}
	
		if (empty($link['url']) === true) {
			$this->send(400, Api::POST_EDIT_ERROR_URL_EMPTY);
			return;
		}
		
		if ($is_new && $this->linkdb->getLinkFromUrl($link['url'])) {
			$this->send(400, Api::POST_EDIT_ERROR_DUPLICATE_URL);
			return;
		}
		
	
		if (isset($post['private']) === true && $post['private'] !== '') {
			$link['private'] = $post['private'];
		}
	
		if (empty($link['title']) === true) {
			$link['title'] = $link['url'];
		}
	
		$link['editdate'] = date('Ymd_His');
	
		$this->linkdb[$key] = $link;
		$this->linkdb->savedb($GLOBALS['config']['PAGECACHE']);
		$this->send($is_new ? 201 : 200, $link);
	}
	
	private function delete()
	{
		if ($this->loggedin === false) {
			$this->send(403);
			return;
		}
	
		if ($this->checkParam('key') === true) {
			$key = $_GET['key'];
			if (isset($this->linkdb[$key]) === false) {
				$this->send(404);
				return;
			}
	
			$linkdeleted = array(
					'linkdate' => $key,
					'deletedate' => date('Ymd_His')
			);
			$this->linkdeleteddb[$key] = $linkdeleted;
			$this->linkdeleteddb->savedb($GLOBALS['config']['PAGECACHE']);
	
			unset($this->linkdb[$key]);
			$this->linkdb->savedb($GLOBALS['config']['PAGECACHE']);
			$this->send(200);
		} else if ($this->checkParam('hash') === true) {
			$hash = $_GET['hash'];
			$link = $this->linkdb->filterSmallHash($hash);
			if (count($link) === 0) {
				$this->send(404);
				return;
			}
	
			$linkdeleted = array(
					'linkdate' => $key,
					'deletedate' => date('Ymd_His')
			);
			$this->linkdeleteddb[$key] = $linkdeleted;
			$this->linkdeleteddb->savedb($GLOBALS['config']['PAGECACHE']);
	
			unset($this->linkdb[key($link)]);
			$this->linkdb->savedb($GLOBALS['config']['PAGECACHE']);
			$this->send(200);
		} else {
			$this->send(400, 'key or hash is required to delete an item');
		}
	}
	
	private function send($status, $message = null)
	{
		$this->sendHttpStatusCode($status);
	
		if ($message !== null) {
			header('Content-Type: application/json');
			echo json_encode($message);
		}
	}
	
	private function sendHttpStatusCode($status)
	{
		$status_text = array(
				200 => 'OK',
				201 => 'Created',
				204 => 'No Content',
				400 => 'Bad Request',
				403 => 'Forbidden',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				500 => 'Internal Server Error',
		);
	
		if (isset($status_text[$status]) === true) {
			$header = 'HTTP/1.0 '.$status.' '.$status_text[$status];
		} else {
			$header = 'HTTP/1.0 500 '.$status_text[500];
		}
	
		header($header);
	}
	
}