<?php

/**
 * characteristics of the Shaarli Account
 * @property String $name Name of the account
 * @property int $num Total number of links
 * @property String $foo Version of Shaarli
 *
 */
class Account {
    public $name;
    public $num;
    public $version;
    
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
    
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    
    private $loggedIn; // Is the used logged in ? (used to filter private links)
    private $allowedMethods = array('get', 'post', 'delete');
    private $linkDb;
    private $linkHistoryDb;
    
    private $server;
    private $get;
    
    /**
     * Creates a new Api
     *
     */
    public function __construct($server, $get)
    {
        $this->server = $server;
        $this->get = $get;
        
        $this->getLoggedIn();
    
        $method = $this->getMethod();
        if ($method === null) {
            exit;
        }
        $this->linkDb = new LinkDB(
            $GLOBALS['config']['DATASTORE'],
            $this->loggedIn,
            $GLOBALS['config']['HIDE_PUBLIC_LINKS'],
            $GLOBALS['redirector']
        );
        $this->linkHistoryDb = new LinkHistoryDB(
            $GLOBALS['config']['DATASTORE_HISTORY'],
            $this->loggedIn
        );
        $this->$method();
    }
    
    /**
     * Identify request method
     *
     * @return method allowed (get, post, delete) or HTTP_BAD_REQUEST or HTTP_METHOD_NOT_ALLOWED
     */    
    private function getMethod()
    {
        if (isset($this->server['REQUEST_METHOD']) === false) {
            $this->send(Api::HTTP_BAD_REQUEST);
            return null;
        }
    
        $method = strtolower($this->server['REQUEST_METHOD']);
    
        if (in_array($method, $this->allowedMethods) === false) {
            $this->send(Api::HTTP_METHOD_NOT_ALLOWED);
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
            $table = $this->get;
        }
        return (!empty($table[$name]) === true
                &&
                is_string($table[$name]) === true);
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
        $this->loggedIn = false;
    
        $dateRequest = null;
        $authorizationClient = null;
        $clientVersion = null;
    
        $headers = apache_request_headers();
        foreach ($headers as $header => $value) {
            if ($header==='Authorization') $authorizationClient=$value;
            if ($header==='Date') $dateRequest=strtotime($value);
            if ($header==='X-SHAARLI-CLIENT-VERSION') $clientVersion=$value;
        }
    
        if (empty($dateRequest) && empty($this->server['HTTP_DATE'])) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_DATE_NOT_FOUND); 
            exit;
        } elseif (empty($dateRequest)) {
            $dateRequest = strtotime($this->server['HTTP_DATE']);
        }

        if (empty($authorizationClient) && empty($this->server['REMOTE_USER'])) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED); 
            exit;
        } elseif (empty($authorizationClient)) {
            $authorizationClient = $this->server['REMOTE_USER'];
        }
        
        if (empty($clientVersion) && isset($this->server['X-SHAARLI-ANDROID-VERSION'])) {
            $clientVersion = $this->server['X-SHAARLI-ANDROID-VERSION'];
        } 
      
        $date_server = time();
        $seconds = $date_server - $dateRequest;
    
        if ($seconds > Api::MAX_TIMESTAMP_CLIENT_SERVER_DIFF) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_CLIENT_SERVER_TIME_SYNC);
            exit;
        }
        
        if (empty($authorizationClient)) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED);
            exit;
        }
                
        $authorizationClientArray = explode(":", $authorizationClient);
        $authorizationClientName = "";
        $authorizationClientHash = "";
        if (!empty($authorizationClientArray) && sizeof($authorizationClientArray)==2) {
            $authorizationClientName = $authorizationClientArray[0];
            $authorizationClientHash = $authorizationClientArray[1];
        }
    
        $calculated_hash = $this->server['REQUEST_METHOD']."\n".$this->server['CONTENT_TYPE']
            ."\n".$this->server['HTTP_DATE']."\n".$this->server['QUERY_STRING'];
    
        if (hash_hmac('sha256', $GLOBALS['api_secret_key'], $calculated_hash) 
                !== $authorizationClientHash) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_TOKEN_VERIFICATION_FAILED); 
            exit;
        }
    
        $this->loggedIn = true;
    }
    
    /**
     * Router for the GET request
     *
     */
    private function get()
    {
        if ($this->checkParam(Api::PARAM_OBJECT) === true) {
            switch ($this->get[Api::PARAM_OBJECT]) {
                case Api::O_ACCOUNT:
                    $this->checkaccount();
                    break;
                case Api::O_LINKS:
                    if ($this->checkParam(Api::PARAM_METHOD) === true) {
                        switch ($this->get[Api::PARAM_METHOD]) {
                            case Api::M_SEARCH:
                                if ($this->checkParam(LinkFilter::$FILTER_DAY) === true) {
                                    $this->getLinksByDay($this->get[LinkFilter::$FILTER_DAY]);
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
                                $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                                return;
                        }
                    } else {
                        $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                        return;
                    }
                    break;
                case Api::O_TAGS:
                    $this->getTags();
                    break;
                case Api::O_LINK:
                    if ($this->checkParam(Api::PARAM_METHOD) === true) {
                        switch ($this->get[Api::PARAM_METHOD]) {
                            case Api::M_SEARCH:
                                if ($this->checkParam(Api::M_SEARCH_KEY) === true) {
                                    $this->getLinkByKey($this->get[Api::M_SEARCH_KEY]);
                                } else {
                                    $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                                    return;
                                }
                                break;
                            case Api::M_DELETED:
                                if ($this->checkParam(Api::M_SEARCH_KEY) === true) {
                                    $this->getLinkDeletedByKey($this->get[Api::M_SEARCH_KEY]);
                                } else {
                                    $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                                    return;
                                }
                                break;
                        }
                    } else {
                        $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                        return;
                    }
                    break;
                case Api::O_HISTORY:
                    $type='';
                    if (!empty($this->get[Api::PARAM_TYPE])) {
                        $type=$this->get[Api::PARAM_TYPE];
                    }
                    $this->getHistory($type);
                    break;
                default:
                    $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
                    return;
            }
        } else {
            $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
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
                count($this->linkDb), shaarli_version);
        $this->send(Api::HTTP_OK, $account);
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
        $tags= $this->linkDb->allTags();
        ksort($tags);
        $tagList=array();
        foreach($tags as $key=>$value)
        {
            $tagList[] = array(
                    'title' => $key,
                    'count' => $value
            );
        }
        $this->send(Api::HTTP_OK, $tagList);
        
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
        $filtered = $this->linkHistoryDb->filterSearch($type);
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);
        $this->send(Api::HTTP_OK, $filtered); 
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
        $filtered = $this->linkHistoryDb->filterSearch('UPDATED');
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);

        $dateLastEdited='';
        $links = array();
        foreach ($filtered as $l) {
            $key = $l['linkdate'];
            $link = isset($this->linkDb[$key]) === true ? $this->linkDb[$key] : false;
            if ($link !== false) {
                if (empty($dateLastEdited)) $dateLastEdited=$l['editdate'];
                $links[]=$link;
            }
        }
        
        $headers = array();
        $headers = $this->add_header($headers, 'X-DATE-LAST-EDITED', $dateLastEdited);
       
        $this->send(Api::HTTP_OK, $links, $headers);
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
        $filtered = $this->linkHistoryDb->filterSearch('DELETED');
        $filtered = $this->filterByDate($filtered,'editdate');
        $filtered = $this->filterByPage($filtered);
        foreach ($filtered as &$l) {
            unset($l['type']);
        }
        $this->send(Api::HTTP_OK, $filtered);
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
     *  or a HTTP_NOT_FOUND error
     */
    private function getLinkDeletedByKey($key)
    {
        $link = isset($this->linkHistoryDb[$key]) === true ? $this->linkHistoryDb[$key] : false;
        if ($link === false) {
            $this->send(Api::HTTP_NOT_FOUND, 'Not Found');
            return;
        }
        unset($link['type']);
        $this->send(Api::HTTP_OK, $link);
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
        $filtered = $this->linkDb->filterSearch($this->get, false, false);
        $filtered = $this->filterByDate($filtered,'linkdate');
        $filtered = $this->filterByPage($filtered);
        $this->send(Api::HTTP_OK, $filtered);
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
     *  or a HTTP_BAD_REQUEST error
     */
    private function getLinksByDay($day)
    {
        try {
            $links = $this->linkDb->filterDay($day);
            $filtered = $this->filterByPage($links);
            $this->send(Api::HTTP_OK, $filtered);
        } catch (Exception $e) {
            $this->send(Api::HTTP_BAD_REQUEST, 'Bad Request');
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
     *  or a HTTP_NOT_FOUND error
     */
    private function getLinkByKey($key)
    {
        $link = isset($this->linkDb[$key]) === true ? $this->linkDb[$key] : false;
        if ($link === false) {
            $this->send(Api::HTTP_NOT_FOUND, 'Not Found');
            return;
        }
        $this->send(Api::HTTP_OK, $link);
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
        if (empty($this->get[Api::PARAM_START_DATE]) && empty($this->get[Api::PARAM_END_DATE])) {
            return $linksToDisplay;
        }  
                
        $start = null;
        if (!empty($this->get[Api::PARAM_START_DATE])) {
            $start = $this->get[Api::PARAM_START_DATE];
        }
        $end = null;
        if (!empty($this->get[Api::PARAM_END_DATE])) {
            $end = $this->get[Api::PARAM_END_DATE];
        }
        
        $startDate = $this->get_date($start,23,59,59);
        $endDate = $this->get_date($end);
        
        if (empty($startDate) && empty($endDate)) {
            return $linksToDisplay;
        }
        
        $links = array();
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
			    if ($diffInSecondsStart>=0 && $diffInSecondsEnd<=0) {
			        $links[]=$linksToDisplay[$l];
			    }
			} else if (!empty($startDate)) {
			    if ($diffInSecondsStart>=0) {
			        $links[]=$linksToDisplay[$l];
			    }
			} else if (!empty($endDate)) {
			    if ($diffInSecondsEnd<=0) {
			        $links[]=$linksToDisplay[$l];
			    }
			}
        }
        return $links;
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
        if (!empty($this->get[Api::PARAM_PER_PAGE])) {
            $nblinksToDisplay = $this->get[Api::PARAM_PER_PAGE]==Api::VALUE_ALL ? 
                count($linksToDisplay) : max(intval($this->get[Api::PARAM_PER_PAGE]), 1);
        }
        $i=0;
        //$linksToDisplay = $this->linkdb;
        $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }
    
        $pagecount = ceil(count($keys) / $nblinksToDisplay);
        $pagecount = $pagecount == 0 ? 1 : $pagecount;
    
        $page= empty($this->get['page']) ? 1 : intval($this->get['page']);
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
     * Duplicate URL is not allowed at the moment... Send a HTTP_BAD_REQUEST error.
     * 
     * @return JSON Object (linkdate, url, title, description, private, tags)
     *  or a HTTP_BAD_REQUEST, HTTP_FORBIDDEN, HTTP_NOT_FOUND error
     */
    private function post()
    {
        if ($this->loggedIn === false) {
            $this->send(Api::HTTP_FORBIDDEN, Api::LOGIN_ERROR_UNKNOWN);
            return;
        }
        if ($this->checkParam('restore') === true) {
            $key = $this->get['restore'];
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
            $key = $this->get['key'];
            if (isset($this->linkDb[$key]) === false) {
                $this->send(Api::HTTP_NOT_FOUND, Api::POST_EDIT_ERROR_LINK_NOT_FOUND);
                return;
            }
            $link = $this->linkDb[$key];
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
            $this->send(Api::HTTP_BAD_REQUEST, Api::POST_EDIT_ERROR_URL_EMPTY);
            return;
        }
        
        if ($is_new && $this->linkDb->getLinkFromUrl($link['url'])) {
            $this->send(Api::HTTP_BAD_REQUEST, Api::POST_EDIT_ERROR_DUPLICATE_URL);
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
            $this->linkHistoryDb[$link['linkdate']] = $linkHistory;
            $this->linkHistoryDb->savedb($GLOBALS['config']['PAGECACHE']);
        }
    
        $this->linkDb[$key] = $link;
        $this->linkDb->savedb($GLOBALS['config']['PAGECACHE']);
        $this->send($is_new ? Api::HTTP_CREATED : Api::HTTP_OK, $link);
    }
    
    /**
     * Handle DELETE request
     *
     * @url param : key. Format: YYYYMMDD_HHMMSS. 
     * 
     * @return HTTP_OK or a HTTP_BAD_REQUEST, HTTP_NOT_FOUND error
     */
    private function delete()
    {
        if ($this->loggedIn === false) {
            $this->send(Api::HTTP_FORBIDDEN);
            return;
        }
    
        if ($this->checkParam('key') === true) {
            $key = $this->get['key'];
            if (isset($this->linkDb[$key]) === false) {
                $this->send(Api::HTTP_NOT_FOUND);
                return;
            }
    
            $linkHistory = array(
                    'linkdate' => $key,
                    'editdate' => date('Ymd_His'),
                    'type' => 'DELETED'
            );
            $this->linkHistoryDb[$key] = $linkHistory;
            $this->linkHistoryDb->savedb($GLOBALS['config']['PAGECACHE']);
                
            unset($this->linkDb[$key]);
            $this->linkDb->savedb($GLOBALS['config']['PAGECACHE']);
            $this->send(Api::HTTP_OK);
        } else {
            $this->send(Api::HTTP_BAD_REQUEST, 'key is required to delete an item');
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
                Api::HTTP_OK => 'OK',
                Api::HTTP_CREATED => 'Created',
                Api::HTTP_BAD_REQUEST => 'Bad Request',
                Api::HTTP_FORBIDDEN => 'Forbidden',
                Api::HTTP_NOT_FOUND => 'Not Found',
                Api::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
                Api::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
        );
    
        if (isset($status_text[$status]) === true) {
            $header = 'HTTP/1.0 '.$status.' '.$status_text[$status];
        } else {
            $header = 'HTTP/1.0 '.Api::HTTP_INTERNAL_SERVER_ERROR.' '.$status_text[500];
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
    
    /**
     * Convert a string (Format: YYYYMMDD_HHMMSS or YYYYMMDD) to a date
     * Additionaly add hour, minute, seconds if in format YYYYMMDD  
     *
     * @param : $date. String
     * @param : $hour. Int
     * @param : $minute. Int
     * @param : $second. Int
     *
     * @return array of headers
     */
    function get_date($dateString, $hour = 0, $minute = 0, $second = 0)
    {
        $date = null;
        if (checkDateFormat('Ymd_His', $dateString)) {
            $date = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $dateString);
        } else if (checkDateFormat('Ymd', $dateString)) {
            $date = DateTime::createFromFormat(Api::LINK_DATE_DAY, $dateString);
            $date->setTime($hour,$minute,$second);
        }
        return $date;
    }
    
}