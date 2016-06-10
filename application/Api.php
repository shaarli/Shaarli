<?php

class Account {
    public $name = "";
    public $num = 0;
    public $version = 0;
    
    public function __construct($name,$num,$version) {
        $this->name = $name;
        $this->num = $num;
        $this->version = $version;
    }
}

class Api
{
    const LINK_DATE_DAY = 'Ymd';

    const PARAM_OBJECT = 'object';
    const PARAM_METHOD = 'method';
    
    const O_ACCOUNT = 'account';
    const O_LINKS = 'links';
    const O_LINK = 'link';
    const O_TAGS = 'tags';
    const O_HISTORY = 'history';
    
    const M_SEARCH = 'search';
    const M_UPDATED = 'updated';
    const M_DELETED = 'deleted';
    
    const M_SEARCH_SEARCH_TERM = 'searchterm';
    const M_SEARCH_SEARCH_TAGS = 'searchtags';
    const M_SEARCH_KEY = 'key';
    
    const PARAM_START_DATE='start_date';
    const PARAM_END_DATE='end_date';
    const PARAM_PER_PAGE = 'per_page';
    const PARAM_TYPE='type';
    
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
    private $linkhistorydb;
    
    /**
     * Creates a new Api
     *
     */
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
        $this->linkhistorydb = new LinkHistoryDB(
            $GLOBALS['config']['DATASTORE_HISTORY'],
            $this->loggedin
        );
        $this->$method();
    }
    
    /**
     * Identify request method
     *
     * @return method allowed (get, post, delete) or 400 or 405
     */    
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
    
    /**
     * Check if a parameter is in the request
     *
     * @param string $name parameter to check
     * @param string $table request. Optionnal : if null check get request
     *
     * @return boolean
     */
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
    
    /**
     * Check if the token provided is legitimate
     *
     * See https://www.ida.liu.se/~TDP024/labs/hmacarticle.pdf for method explanation.
     *
     * @return boolean
     */
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
    
        $calculated_hash = $_SERVER['REQUEST_METHOD']."\n".$_SERVER['CONTENT_TYPE']
            ."\n".$_SERVER['HTTP_DATE']."\n".$_SERVER['QUERY_STRING'];
    
        if (hash_hmac('sha256', $GLOBALS['api_secret_key'], $calculated_hash) 
                !== $authorization_client_hash) {
            $this->send(403, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED); 
            exit;
        }
    
        $this->loggedin = true;
    }
    
    /**
     * Router for the GET request
     *
     */
    private function get()
    {
        if ($this->checkParam(Api::PARAM_OBJECT) === true) {
            switch ($_GET[Api::PARAM_OBJECT]) {
                case Api::O_ACCOUNT:
                    $this->checkaccount();
                    break;
                case Api::O_LINKS:
                    if ($this->checkParam(Api::PARAM_METHOD) === true) {
                        switch ($_GET[Api::PARAM_METHOD]) {
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
                    if ($this->checkParam(Api::PARAM_METHOD) === true) {
                        switch ($_GET[Api::PARAM_METHOD]) {
                            case Api::M_SEARCH:
                                if ($this->checkParam(Api::M_SEARCH_KEY) === true) {
                                    $this->getLinkByKey($_GET[Api::M_SEARCH_KEY]);
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
                case Api::O_HISTORY:
                    $type='';
                    if (!empty($_GET[Api::PARAM_TYPE])) {
                        $type=$_GET[Api::PARAM_TYPE];
                    }
                    $this->getHistory($type);
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
    
    /**
     * GET Method : check is the account is legitimate
     * 
     * @url param : do=api
     * @url param : object=account
     * 
     * @return JSON Object (name = Name of the account, num = number of links saved, 
     * version = version of Shaarli)
     */
    private function checkAccount(){
        $account = new Account($GLOBALS['title'],
                count($this->linkdb), shaarli_version);
        $this->send(200, $account);
    }
    
    /**
     * GET Method : retrieve all tags
     * 
     * @url param : do=api
     * @url param : object=tags
     * 
     * @return JSON Array List of tags (title = name of the tag, count = occurences of the tag)
     *  sort by title
     */
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
        $this->send(200, $tagList);
        
    }
    
    /**
     * GET Method : get history of modification on links
     * 
     * @url param : do=api
     * @url param : object=history
     * @url param : type=DELETED or UPDATED (optionnal, default = both)
     * @url param : startdate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : enddate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     * 
     * @return JSON Array List of history (linkdate = linkdate of the link, 
     * editdate = edit date of the link, type = type of action
     * on the link (e.g. DELETED, UPDATED))
     */
    private function getHistory($type)
    {
        $filtered = $this->linkhistorydb->filterSearch($type);
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);
        $this->send(200, $filtered); 
    }
    
    /**
     * GET Method : get links updated
     * 
     * @url param : do=api
     * @url param : object=links
     * @url param : method=updated
     * @url param : startdate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : enddate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     * 
     * @return JSON Array List of links (linkdate, url, title, description, private, tags)
     */
    private function getLinksUpdated()
    {
        $filtered = $this->linkhistorydb->filterSearch('UPDATED');
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);

        $dateLastEdited='';
        $links = array();
        foreach ($filtered as $l) {
            $key = $l['linkdate'];
            $link = isset($this->linkdb[$key]) === true ? $this->linkdb[$key] : false;
            if ($link !== false) {
                if (empty($dateLastEdited)) $dateLastEdited=$l['editdate'];
                $links[]=$link;
            }
        }
        
        $headers = array();
        $headers = $this->add_header($headers, 'X-DATE-LAST-EDITED', $dateLastEdited);
       
        $this->send(200, $links, $headers);
    }
    
    
    /**
     * GET Method : get links deleted
     * 
     * @url param : do=api
     * @url param : object=links
     * @url param : method=deleted
     * @url param : startdate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : enddate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     * 
     * @return JSON Array List of deleted links (linkdate, editdate = edit date of the link)
     */
    private function getLinksDeleted(){
        $filtered = $this->linkhistorydb->filterSearch('DELETED');
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);
        foreach ($filtered as &$l) {
            unset($l['type']);
        }
        $this->send(200, $filtered);
    }
    

    /**
     * GET Method : get link deleted by linkdate
     * 
     * @url param : do=api
     * @url param : object=link
     * @url param : method=deleted
     * @url param : key. Format : YYYYMMDD_HHMMSS.
     * 
     * @return JSON Object (linkdate, editdate = edit date of the link)
     *  or a 404 error
     */
    private function getLinkDeletedByKey($key)
    {
        $link = isset($this->linkhistorydb[$key]) === true ? $this->linkhistorydb[$key] : false;
        if ($link === false) {
            $this->send(404, 'Not Found');
            return;
        }
        unset($link['type']);
        $this->send(200, $link);
    }
    
    
    /**
     * GET Method : retrieve links, optionnaly filter by term or tags 
     * 
     * @url param : do=api
     * @url param : object=links
     * @url param : method=search
     * @url param : searchterm (optionnal)
     * @url param : searchtags (optionnal)
     * @url param : startdate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : enddate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     * 
     * @return JSON Array List of links (linkdate, url, title, description, private, tags)
     */
    private function search()
    {
        $filtered = $this->linkdb->filterSearch($_GET, false, false);
        $filtered = $this->filterByDate($filtered,'linkdate');
        $filtered = $this->filterByPage($filtered);
        $this->send(200, $filtered);
    }
    
    /**
     * GET Method : Returns the list of articles for a given day.
     *
     * @url param : do=api
     * @url param : object=links
     * @url param : method=search
     * @url param : FILTER_DAY day to filter. Format: YYYYMMDD.
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     *
     * @return JSON Array List of links (linkdate, url, title, description, private, tags)
     *  or a 400 error
     */
    private function getLinksByDay($day)
    {
        try {
            $links = $this->linkdb->filterDay($day);
            $filtered = $this->filterByPage($links);
            $this->send(200, $filtered);
        } catch (Exception $e) {
            $this->send(400, 'Bad Request');
            return;
        }
    }
    
    /**
     * GET Method : get link by linkdate
     * 
     * @url param : do=api
     * @url param : object=link
     * @url param : method=search
     * @url param : key. Format : YYYYMMDD_HHMMSS.
     * 
     * @return JSON Object (linkdate, url, title, description, private, tags)
     *  or a 404 error
     */
    private function getLinkByKey($key)
    {
        $link = isset($this->linkdb[$key]) === true ? $this->linkdb[$key] : false;
        if ($link === false) {
            $this->send(404, 'Not Found');
            return;
        }
        $this->send(200, $link);
    }
    
    /**
     * filter by date
     *
     * @param string $linksToDisplay links to filter
     * @param string $field field use to filter (e.g. linkdate, date...)
     * 
     * @url param : startdate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * @url param : enddate (optionnal). Format: YYYYMMDD_HHMMSS or YYYYMMDD.
     * 
     * @return JSON Array List filtered or whole array list if no date provided
     */
    private function filterByDate($linksToDisplay,$field)
    {
        if (empty($_GET[Api::PARAM_START_DATE]) && empty($_GET[Api::PARAM_END_DATE])) {
            return $linksToDisplay;
        }  
                
        $start = null;
        if (!empty($_GET[Api::PARAM_START_DATE])) 
            $start = $_GET[Api::PARAM_START_DATE];
        $end = null;
        if (!empty($_GET[Api::PARAM_END_DATE])) 
            $end = $_GET[Api::PARAM_END_DATE];
        
        if (checkDateFormat('Ymd_His', $start)) {
            $startDate = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $start);
        } else if (checkDateFormat('Ymd', $start)) {
            $startDate = DateTime::createFromFormat(Api::LINK_DATE_DAY, $start);
            $startDate->setTime(23,59,59);
        } else {
            $startDate = null;
        }
        
        if (checkDateFormat('Ymd_His', $end)) {
            $endDate = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $end);
        } else if (checkDateFormat('Ymd', $end)) {
            $endDate = DateTime::createFromFormat(Api::LINK_DATE_DAY, $end);
            $endDate->setTime(0,0,0);
        } else {
            $endDate = null;
        }

        if (empty($startDate) && empty($endDate)) {
            return $linksToDisplay;
        }
        
        foreach ($linksToDisplay as $l => $info) {
			$dateLink = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $info[$field]);
        	$diffInSecondsStart = null;
			if (!empty($startDate)) {
			    $diffInSecondsStart = $startDate->getTimestamp() - $dateLink->getTimestamp();
			}
				
			$diffInSecondsEnd = null;
			if (!empty($endDate)) {
			    $diffInSecondsEnd = $endDate->getTimestamp() - $dateLink->getTimestamp();
			}
			
			if (!empty($startDate) && !empty($endDate)) {
			    if (!($diffInSecondsStart>0 && $diffInSecondsEnd<0)) {
			        unset($linksToDisplay[$l]);
			    }
			} else if (!empty($startDate)) {
			    if (!($diffInSecondsStart>0)) {
			        unset($linksToDisplay[$l]);
			    }
			} else if (!empty($endDate)) {
			    if (!($diffInSecondsEnd<0)) {
			        unset($linksToDisplay[$l]);
			    } 
			}
			
        }
        return $linksToDisplay;
    }
    
    /**
     * filter by page
     *
     * @param string $linksToDisplay links to filter
     * 
     * @url param : per_page. page size (optionnal, default = 2)
     * @url param : page. (optionnal, default = 1)
     * 
     * @return JSON Array List filtered 
     */
    private function filterByPage($linksToDisplay)
    {
        $nblinksToDisplay = 2;  // Number of links to display.
        // In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
        if (!empty($_GET[Api::PARAM_PER_PAGE])) {
            $nblinksToDisplay = $_GET[Api::PARAM_PER_PAGE]==Api::VALUE_ALL ? 
                count($linksToDisplay) : max(intval($_GET[Api::PARAM_PER_PAGE]), 1);
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
       
        return $links;
    }
    
    /**
     * Handle POST request
     *
     * @url param : restore (optionnal). Format: YYYYMMDD_HHMMSS. 
     *  Add a link by providing an edit date.
     * @url param : key (optionnal). Format: YYYYMMDD_HHMMSS. Edit a link 
     * 
     * Duplicate URL is not allowed at the moment... Send a 400 error.
     * 
     * @return JSON Object (linkdate, url, title, description, private, tags)
     *  or a 400, 403, 404 error
     */
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
        } else if ($this->checkParam('key') === true) {
            $key = $_GET['key'];
            if (isset($this->linkdb[$key]) === false) {
                $this->send(404, Api::POST_EDIT_ERROR_LINK_NOT_FOUND);
                return;
            }
            $link = $this->linkdb[$key];
            $is_new = false;
        }  else {
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

        if ($is_new==false) {
            $linkHistory = array(
                    'linkdate' => $link['linkdate'],
                    'editdate' => date('Ymd_His'),
                    'type' => 'UPDATED'
            );
            $this->linkhistorydb[$link['linkdate']] = $linkHistory;
            $this->linkhistorydb->savedb($GLOBALS['config']['PAGECACHE']);
        }
    
        $this->linkdb[$key] = $link;
        $this->linkdb->savedb($GLOBALS['config']['PAGECACHE']);
        $this->send($is_new ? 201 : 200, $link);
    }
    
    /**
     * Handle DELETE request
     *
     * @url param : key. Format: YYYYMMDD_HHMMSS. 
     * 
     * @return 200 or a 400, 404 error
     */
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
    
            $linkHistory = array(
                    'linkdate' => $key,
                    'editdate' => date('Ymd_His'),
                    'type' => 'DELETED'
            );
            $this->linkhistorydb[$key] = $linkHistory;
            $this->linkhistorydb->savedb($GLOBALS['config']['PAGECACHE']);
                
            unset($this->linkdb[$key]);
            $this->linkdb->savedb($GLOBALS['config']['PAGECACHE']);
            $this->send(200);
        } else {
            $this->send(400, 'key is required to delete an item');
        }
    }
    
    /**
     * Send request
     *
     * @param : status. Http status.
     * @param : message (optionnal). JSON string.
     * 
     */
    private function send($status, $message = null, $map = null)
    {
        $this->sendHttpStatusCode($status);
    
        if ($message !== null) {
            header('Content-Type: application/json');
            if (!empty($map)){
                foreach ($map as $header) {
                    header(implode(':', $header));
                }
            }
            echo json_encode($message);
        }
    }
    
    /**
     * Format header request
     *
     * @param : status. Http status.
     * @param : message (optionnal). JSON string.
     * 
     */
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
    
    /**
     * Add header
     *
     * @param : $headers. Array of headers
     * @param : $key. Key of header
     * @param : $value. Value of header
     *
     * @return array of headers
     */
    function add_header($headers, $key, $value)
    {
        $headers[] = array($key,$value);
        return $headers;
    }
    
}