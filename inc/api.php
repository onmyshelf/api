<?php
require_once('classes/Token.php');

class Api
{
    private $headers;
    private $post;
    private $method;
    private $args;
    private $data;
    private $route;
    private $routes;

    public function __construct()
    {
        $GLOBALS['currentUserID'] = null;
        $GLOBALS['accessRights'] = 0;
        $this->headers = false;
        $this->post = false;
        $this->args = [];
        $this->data = [];

        // get request method
        $this->method = $_SERVER['REQUEST_METHOD'];

        // routes definition (see methods below)
        $this->routes = [
            '/' => 'Home',
            '/borrowers' => 'Borrowers',
            '/borrowers/{id}' => 'BorrowersId',
            '/collections' => 'Collections',
            '/collections/{id}' => 'CollectionsId',
            '/collections/{id}/export' => 'CollectionsIdExport',
            '/collections/{id}/import' => 'CollectionsIdImport',
            '/collections/{id}/import/search' => 'CollectionsIdImportSearch',
            '/collections/{id}/import/data' => 'CollectionsIdImportData',
            '/collections/{cid}/items' => 'CollectionsIdItems',
            '/collections/{cid}/properties' => 'CollectionsIdProperties',
            '/collections/{cid}/properties/{name}' => 'CollectionsIdPropertiesName',
            '/collections/{cid}/items/{id}' => 'CollectionsIdItemsId',
            '/collections/{cid}/items/{id}/borrow' => 'CollectionsIdItemsIdBorrow',
            '/collections/{cid}/items/{id}/loans' => 'CollectionsIdItemsIdLoans',
            '/collections/{cid}/items/{iid}/loans/{id}' => 'CollectionsIdItemsIdLoansId',
            '/collectiontemplates' => 'CollectionTemplates',
            '/config' => 'Config',
            '/config/email' => 'ConfigEmail',
            '/config/email/test' => 'ConfigEmailTest',
            '/media/download' => 'MediaDownload',
            '/media/upload' => 'MediaUpload',
            '/modules/import' => 'ModulesImport',
            '/modules/upgrade' => 'ModulesUpgrade',
            '/login' => 'Login',
            '/properties/types' => 'PropertiesTypes',
            '/resetpassword' => 'Resetpassword',
            '/token' => 'Token',
            '/users' => 'Users',
            '/users/{uid}' => 'UsersId',
            '/users/{uid}/collections' => 'UsersIdCollections',
            '/users/{uid}/password' => 'UsersIdPassword',
        ];
    }


    /**
     * Get a header of the current request
     *
     * @param string $name
     * @return void
     */
    private function getRequestHeaders($name)
    {
        $headers = null;

        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

            if (isset($requestHeaders[$name])) {
                $headers = trim($requestHeaders[$name]);
            }
        }

        return $headers;
    }


    /**
     * Get Authorization header
     * Source: https://gist.github.com/wildiney/b0be69ff9960642b4f7d3ec2ff3ffb0b
     * @return string
     */
    private function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else {
            $headers = $this->getRequestHeaders('Authorization');
        }
        return $headers;
    }


    /**
     * Get Bearer Token from headers
     * Source: https://gist.github.com/wildiney/b0be69ff9960642b4f7d3ec2ff3ffb0b
     * @return string Token, FALSE if not defined
     */
    private function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return false;
    }


    /**
     * Get Bearer Token from headers
     * Source: https://gist.github.com/wildiney/b0be69ff9960642b4f7d3ec2ff3ffb0b
     * @return string Token, FALSE if not defined
     */
    private function getClientLanguage()
    {
        $lang = $this->getRequestHeaders('Content-Language');
        if (!$lang) {
            $lang = 'en_US';
        }

        $GLOBALS['currentLanguage'] = $lang;
        return true;
    }


    /**
     * Check if token is set and save current user information
     * @return void
     */
    private function checkToken()
    {
        // check if Bearer Token exists
        $token = $this->getBearerToken();
        if (!$token) {
            return;
        }

        // check if token is valid
        $user = User::getByToken($token);
        if (!$user) {
            Logger::debug("Bad token: $token");
            $this->error(401, "Invalid token");
        }

        $GLOBALS['accessRights'] = 1;
        $GLOBALS['currentToken'] = $token;
        $GLOBALS['currentUserID'] = $user->getId();
        $GLOBALS['currentUsername'] = $user->getUsername();
        $GLOBALS['currentEmail'] = $user->getEmail();

        Logger::debug("User ".$GLOBALS['currentUserID']." connected using token: $token");
    }


    /**
     * Login as user
     *
     * @param string  $user
     * @param string  $password
     * @return object User object
     */
    private function login($user, $password)
    {
        if (!is_null($password)) {
            $user = User::getByLogin($user, $password);
            if ($user) {
                return $user;
            }
        }

        $this->error(401, 'Authentication failed');
    }


    /**
     * Check if user is authenticated
     * @return void
     */
    private function requireAuthentication()
    {
        if (!isset($GLOBALS['currentToken'])) {
            $this->error(403);
        }
    }


    /**
     * Compare user ID to current user
     * @param  int  $id
     * @return bool User IDs are identical
     */
    private function compareUserID($id)
    {
        if (!is_null($GLOBALS['currentUserID']) && $id == $GLOBALS['currentUserID']) {
            $GLOBALS['accessRights'] = 3;
            return true;
        }
        return false;
    }


    /**
     * Require user ID
     * @param  int $id User ID
     * @return void
     */
    private function requireUserID($id)
    {
        if (!is_integer($id)) {
            $this->error(403);
        }

        if (!$this->compareUserID($id)) {
            $this->error(403);
        }
    }


    /**
     * Test if user is administrator
     * @return void
     */
    private function userIsAdmin()
    {
        if (isset($GLOBALS['currentUsername']) && !is_null($GLOBALS['currentUsername']) && $GLOBALS['currentUsername'] == 'onmyshelf') {
            return true;
        }

        return false;
    }


    /**
     * Calls a route
     * @return void
     */
    public function route()
    {
        // remove URL prefix
        $route = substr(htmlspecialchars($_SERVER['REQUEST_URI']), strlen(parse_url(API_URL, PHP_URL_PATH)));

        // remove GET parameters
        $route = preg_replace('/\?.*/', '', $route);

        // replace keys like {id} and escape / chars
        $patterns = ['/\{\w+\}/', '/\//'];
        $replaces = ['\w+', '\/'];

        // parse available routes
        $function = '';
        foreach (array_keys($this->routes) as $r) {
            // compare routes
            $regex = '/^'.preg_replace($patterns, $replaces, $r).'\/?$/';
            if (preg_match($regex, $route)) {
                $function = 'route'.$this->routes[$r];
                break;
            }
        }

        // route not found: return 404
        if ($function == '') {
            Logger::error("Bad call: $this->method $route");
            $this->error(404);
        }

        // extract keys from URL like {id}
        preg_match_all('/\{\w+\}/', $r, $keys);

        // parse found keys
        $replaces = ['(\w+)', '\w+', '\/'];
        foreach ($keys[0] as $key) {
            // removes {}
            $key = substr($key, 1, -1);
            // replaces key by regex to extract, then replace other keys to ignore them
            // then replace / chars
            $patterns = ["/\{$key\}/", '/\{\w+\}/', '/\//'];
            $regex = '/^'.preg_replace($patterns, $replaces, $r).'\/?$/';

            // extract key value
            $this->args[$key] = preg_replace($regex, '\1', $route);
        }

        Logger::debug("API call: $this->method $route");
        $this->route = $route;

        $body = file_get_contents("php://input");

        if ($body !== '') {
            // convert JSON to object
            $this->data = json_decode($body, true);

            if (is_null($this->data)) {
                Logger::fatal('API cannot decode JSON');
                $this->error(400);
            }
        }

        $this->checkToken();
        $this->getClientLanguage();

        // call method with args
        try {
            $this->$function();
        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error();
        }
    }


    /**
     * Returns available routes
     * @return array Array of routes
     */
    public function getRoutes()
    {
        return array_keys($this->routes);
    }


    /************************
     *  API ROUTE HANDLERS  *
     ************************/

    private function routeHome()
    {
        $info = [
            'name' => 'OnMyShelf',
            'media' => MEDIA_URL,
            'readonly' => READ_ONLY,
        ];

        // more information if logged in
        if (isset($GLOBALS['currentUsername']) && $GLOBALS['currentUsername'] == 'onmyshelf') {
            $info['version'] = VERSION;
        }

        $this->response($info);
    }


    private function routeConfig()
    {
        // requires to be administrator
        if (!$this->userIsAdmin()) {
            $this->error(403);
        }

        switch ($this->method) {
            case 'PATCH':
                // update config

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $success = true;
                $details = '';
                foreach ($this->data as $param => $value) {
                    if (!Config::set($param, $value)) {
                        $success = false;
                        $details = "Failed to set parameter '$param'";
                        Logger::warn("User tried to change a locked config: $param to $value");
                    }
                }

                $this->responseOperation('updated', $success, $details);
                break;

            default:
                // print all config
                $this->response(Config::dump());
                break;
        }
    }


    private function routeConfigEmail()
    {
        // requires to be administrator
        if (!$this->userIsAdmin()) {
            $this->error(403);
        }

        $this->response(Mailer::getConfig());
    }


    private function routeConfigEmailTest()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        // requires to be administrator
        if (!$this->userIsAdmin()) {
            $this->error(403);
        }

        $this->responseOperation('sent', Mailer::send($GLOBALS['currentEmail'], "Email test", "If you received this email, it works."));
    }


    private function routeLogin()
    {
        // check method
        if ($this->method != 'POST') {
            $this->error(400);
        }

        // check data
        $this->requireData(['login', 'password']);

        // authentication
        $user = $this->login($this->data['login'], $this->data['password']);

        // clean expired tokens
        Token::cleanup();

        // create token for user
        $token = $user->createToken();
        if (!$token) {
            $this->error(500, 'Failed to create token');
        }

        // returns token and user ID to client
        $this->response([
            'token' => $token,
            'userid' => $user->getId(),
            'username' => $user->getUsername(),
            'readonly' => READ_ONLY,
        ]);
    }


    private function routeToken()
    {
        // check method
        if ($this->method != 'DELETE') {
            $this->error(400);
        }

        // check token
        if (!$GLOBALS['currentToken']) {
            $this->error(404);
        }

        // delete token
        $this->response([
            'disconnected' => Token::revoke($GLOBALS['currentToken'])
        ]);
    }


    private function routeBorrowers()
    {
        // forbidden if not logged in
        $this->requireAuthentication();

        switch ($this->method) {
            case 'POST':
                // create new borrower

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $this->post = true;
                $this->requireData(['firstname']);

                // force owner to current user
                $this->data['owner'] = $GLOBALS['currentUserID'];

                $id = Borrower::create($this->data);
                if (!$id) {
                    $this->error();
                }

                $this->response(['id' => $id]);
                break;

            default:
                // get borrowers
                $borrowers = Borrower::dumpAll();
                if ($borrowers === false) {
                    $this->error();
                }

                $this->response($borrowers);
                break;
        }
    }


    private function routeBorrowersId()
    {
        $this->requireArgs(['id']);

        $borrower = Borrower::getById($this->args['id']);
        if (!$borrower) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // update borrower

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($borrower->getOwner());

                // cannot change owner
                unset($this->data['owner']);

                $this->responseOperation('updated', $borrower->update($this->data));
                break;

            case 'DELETE':
                // delete borrower

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($borrower->getOwner());

                $this->responseOperation('deleted', $borrower->delete());
                break;

            default:
                // get borrower
                $this->response($borrower->dump());
                break;
        }
    }

    
    private function routeCollections()
    {
        switch ($this->method) {
            case 'POST':
                // create new collection

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // forbidden if not logged in
                $this->requireAuthentication();

                $this->post = true;
                $this->requireData(['name']);

                // force owner to current user
                $this->data['owner'] = $GLOBALS['currentUserID'];

                $collectionId = Collection::create($this->data);
                if (!$collectionId) {
                    $this->error();
                }

                $this->response(['id' => $collectionId]);
                break;

            default:
                // get collections
                $collections = Collection::dumpAll();
                if ($collections === false) {
                    $this->error();
                }

                $this->response($collections);
                break;
        }
    }


    private function routeCollectionsId()
    {
        $this->requireArgs(['id']);

        $collection = Collection::getById($this->args['id']);
        if (!$collection) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // update collection

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                $this->responseOperation('updated', $collection->update($this->data));
                break;

            case 'DELETE':
                // delete collection

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                $this->responseOperation('deleted', $collection->delete());
                break;

            default:
                // get collection
                $this->response($collection->dump());
                break;
        }
    }


    private function routeCollectionsIdExport()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->post = true;
        $this->requireArgs(['id']);

        $collection = Collection::getById($this->args['id']);
        if ($collection === false) {
            Logger::error("collection not found: ".$this->args['id']);
            $this->error(404, 'Collection not found');
        }

        // only current user can export collection
        $this->requireUserID($collection->getOwner());

        $this->response($collection->export());
    }


    private function routeCollectionsIdImport()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->post = true;
        $this->requireArgs(['id']);
        $this->requireData(['module', 'source']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        $collection = Collection::getById($this->args['id']);
        if ($collection === false) {
            Logger::error("collection not found: ".$this->args['id']);
            $this->error(404, 'Collection not found');
        }

        // check ownership
        $this->requireUserID($collection->getOwner());

        try {
            $result = $collection->import($this->data['module'],
                                          $this->data['source'],
                                          $this->data['options']);
        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error();
        }

        if ($result === false) {
            $this->error();
        }

        $this->response($result);
    }


    private function routeCollectionsIdImportSearch()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->requireParams(['module', 'source', 'search']);

        // default options
        if (!isset($_GET['options'])) {
            $_GET['options'] = [];
        }

        // load module
        if (!Module::load('import', $_GET['module'])) {
            $this->error(500, "Error while loading import module");
        }

        $import = new Import($_GET['source'], $_GET['options']);
        if (!$import) {
            $this->error(500, "Error while loading import module");
        }

        // load source to import
        if (!$import->load()) {
            $this->error(500, "Error while opening import source");
        }

        $results = $import->search($_GET['search']);
        if (!is_array($results)) {
            $this->error(500, "Error while searching");
        }

        $this->response($results);
    }


    private function routeCollectionsIdImportData()
    {
        $this->requireParams(['module', 'source']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        // load module
        if (!Module::load('import', $_GET['module'])) {
            $this->error(500, "Error while loading import module");
        }

        $import = new Import($_GET['source'], $this->data['options']);
        if (!$import) {
            $this->error(500, "Error while loading import module");
        }

        // load source to import
        if (!$import->load()) {
            $this->error(500, "Error while opening import source");
        }

        $this->response($import->getData($_GET['source']));
    }


    private function routeCollectionsIdItems()
    {
        $this->requireArgs(['cid']);

        // get collection
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // get access rights
        $this->compareUserID($collection->getOwner());

        switch ($this->method) {
            case 'POST':
                // create a new item

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                // create item object
                $item = $collection->addItem($this->data);
                if (!$item) {
                    $this->error(500, 'Failed to create item');
                }

                $id = $item->getId();

                $this->headers(["Location: ".API_URL."/collections/".$this->args['cid']."/items/".$id]);
                $this->response(['created' => true, 'id' => $id], 201);
                break;

            default:
                // get items of collection

                // get filters and sorting
                $search = '';
                $filters = [];
                $sortBy = [];
                $limit = 0;
                $offset = 0;

                foreach ($_GET as $key => $value) {
                    switch ($key) {
                        case 'search':
                            // limit value to 255 chars (to avoid malicious script)
                            $search = substr(urldecode($value), 0, 255);
                            break;

                        // sorting
                        case 'sort':
                            // split fields by comma
                            $sort = explode(',', $value);

                            // security checking
                            foreach ($sort as $property) {
                                if (preg_match('/^-?\w+$/', $property)) {
                                    $sortBy[] = $property;
                                }
                            }
                            break;

                        case 'limit':
                            $limit = intval($value);
                            break;

                        case 'offset':
                            $offset = intval($value);
                            break;

                        default:
                            // filters: detect property
                            if (substr($key, 0, 2) == 'p_') {
                                // get property name (without p_ prefix; must have 1 char at least)
                                $property = substr($key, 2);
                                if (preg_match('/^\w+$/', $property)) {
                                    // limit value to 255 chars (to avoid malicious script)
                                    $filters[$property] = substr($value, 0, 255);
                                }
                            }
                            break;
                    }
                }

                $this->response($collection->dumpItems($filters, $sortBy, $search, $limit, $offset));
                break;
        }
    }


    private function routeCollectionsIdItemsId()
    {
        $this->requireArgs(['cid','id']);

        // get collection object to check access rights
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // get access rights
        $this->compareUserID($collection->getOwner());

        // get item object
        $item = Item::getById($this->args['id'], $this->args['cid']);
        if (!$item) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // update item

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                $this->responseOperation('updated', $item->update($this->data));
                break;

            case 'DELETE':
                // delete item

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($item->getOwner());

                $this->responseOperation('deleted', $item->delete());
                break;

            default:
                // dump item
                $this->response($item->dump());
                break;
        }
    }


    private function routeCollectionsIdItemsIdBorrow()
    {
        $this->requireArgs(['cid','id']);

        // get collection object to check access rights
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // get item object
        $item = Item::getById($this->args['id'], $this->args['cid']);
        if (!$item) {
            $this->error(404, 'Item does not exists');
        }

        // get access rights
        $this->compareUserID($collection->getOwner());

        switch ($this->method) {
            case 'POST':
                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check access rights
                if ($GLOBALS['accessRights'] < $item->getBorrowableLevel()) {
                    $this->error(403);
                }

                if (is_null($GLOBALS['currentUserID'])) {
                    // public mode
                    $this->requireData(['firstname', 'lastname', 'email']);
                } else {
                    // append user ID
                    $this->data['userId'] = $GLOBALS['currentUserID'];
                }

                $this->responseOperation('sent', $item->askToBorrow($this->data));
                break;

            default:
                $this->error(404);
                break;
        }
    }


    private function routeCollectionsIdItemsIdLoans()
    {
        $this->requireArgs(['cid','id']);

        // get collection object to check access rights
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // only collection owner can continue
        $this->requireUserID($collection->getOwner());

        // get item object
        $item = Item::getById($this->args['id'], $this->args['cid']);
        if (!$item) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'POST':
                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $this->response(['created' => Loan::create($item->getId(), $this->data)]);
                break;

            default:
                $this->response($item->getLoans());
                break;
        }
    }


    private function routeCollectionsIdItemsIdLoansId()
    {
        $this->requireArgs(['cid', 'iid', 'id']);

        // get collection object to check access rights
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // only collection owner can continue
        $this->requireUserID($collection->getOwner());

        // get item object
        $item = Item::getById($this->args['iid'], $this->args['cid']);
        if (!$item) {
            $this->error(404);
        }

        // get loan object
        $loan = Loan::getById($this->args['id']);
        if (!$loan) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $this->responseOperation('updated', $loan->update($this->data));
                break;

            case 'DELETE':
                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $this->responseOperation('deleted', $loan->delete());
                break;

            default:
                // return loan details
                $this->response($loan->dump());
                break;
        }
    }


    private function routeCollectionTemplates()
    {
        // forbidden if not logged in
        $this->requireAuthentication();

        switch ($this->method) {
            // TODO
            // case 'POST':
            //     // create new collection template
            //     break;

            default:
                // get collection templates
                $templates = CollectionTemplate::dumpAll();
                if ($templates === false) {
                    $this->error();
                }

                $this->response($templates);
                break;
        }
    }


    private function routeCollectionsIdProperties()
    {
        $this->requireArgs(['cid']);

        // get collection object
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'POST':
                // create property

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check collection owner
                $this->requireUserID($collection->getOwner());

                $this->requireData(['name']);
                $this->responseOperation('updated', $collection->addProperty($this->data['name'], $this->data));
                break;

            default:
                $this->error(404);
                break;
        }
    }


    private function routeCollectionsIdPropertiesName()
    {
        $this->requireArgs(['cid','name']);

        // get collection object
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404);
        }

        // get property object
        $property = Property::getByName($this->args['cid'], $this->args['name']);
        if (!$property) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // update property

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                $this->responseOperation('updated', $property->update($this->data));
                break;

            case 'DELETE':
                // delete property

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                $this->responseOperation('deleted', $property->delete());
                break;

            default:
                // TODO: dump property (if useful)
                $this->error(404);
                break;
        }
    }


    private function routePropertiesTypes()
    {
        $this->response(Property::getTypes());
    }


    private function routeModulesImport()
    {
        $this->response(Module::list('import'));
    }


    private function routeModulesUpgrade()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        // requires to be administrator
        if (!$this->userIsAdmin()) {
            $this->error(403);
        }

        $this->responseOperation('upgraded', Module::upgrade('import'));
    }


    private function routeMediaDownload()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        // forbidden if not logged in
        $this->requireAuthentication();

        // quit if missing URL
        $this->requireData(['url']);

        $url = Storage::download($this->data['url']);
        if (!$url) {
            Logger::error("API call: ".$this->route." error when downloading file");
            $this->error();
        }

        $this->response(['url' => $url]);
    }


    private function routeMediaUpload()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        // forbidden if not logged in
        $this->requireAuthentication();

        if (!isset($_FILES['file'])) {
            Logger::error("API call: ".$this->route." missing file");
            $this->error(400, 'Missing file');
        }

        $url = Storage::moveUploadedFile();
        if (!$url) {
            Logger::error("API call: ".$this->route." error when storing file");
            $this->error();
        }

        $this->response(['url' => $url]);
    }


    private function routeUsers()
    {
        // requires to be administrator
        if (!$this->userIsAdmin()) {
            $this->error(403);
        }

        switch ($this->method) {
            case 'POST':
                // create user

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // get new user password
                $this->data['password'] = $this->data['newPassword'];
                unset($this->data['newPassword']);

                $this->responseOperation('created', User::create($this->data));
                break;

            default:
                // get users
                $this->response(User::dumpAll());
                break;
        }
    }


    private function routeUsersId()
    {
        $this->requireArgs(['uid']);

        $this->requireAuthentication();

        // requires user to be himself or administrator
        if (!$this->compareUserID($this->args['uid'])) {
            if (!$this->userIsAdmin()) {
                $this->error(403);
            }
        }

        $user = User::getById($this->args['uid']);
        if (!$user) {
            $this->error(404);
        }

        switch ($this->method) {
            case 'PATCH':
                // update user

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // if new password set, change field name
                if (isset($this->data['newPassword'])) {
                    $this->data['password'] = $this->data['newPassword'];
                    unset($this->data['newPassword']);
                }

                // secure fields only administrator can change
                if (!$this->userIsAdmin()) {
                    unset($this->data['role']);
                    unset($this->data['username']);
                }

                // prevent user from disabling himself
                if ($this->compareUserID($this->args['uid'])) {
                    unset($this->data['enabled']);
                }

                $this->responseOperation('updated', $user->update($this->data));
                break;

            case 'DELETE':
                // delete user

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // user cannot delete himself
                if ($this->compareUserID($this->args['uid'])) {
                    $this->error(403, "You cannot delete your own account!");
                }

                $this->responseOperation('deleted', $user->delete());
                break;

            default:
                // get user profile
                $response = $user->dump();
                if ($user === false) {
                    $this->error();
                }

                $this->response($response);
                break;
        }
    }


    private function routeUsersIdCollections()
    {
        $this->requireArgs(['uid']);

        // check user ID
        $this->requireUserID((int)$this->args['uid']);

        // get collections
        $collections = Collection::dumpAll($GLOBALS['currentUserID']);
        if ($collections === false) {
            $this->error();
        }

        $this->response($collections);
    }


    private function routeUsersIdPassword()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->requireArgs(['uid']);

        // check method
        if ($this->method != 'POST') {
            $this->error(400);
        }

        // check data
        $this->requireData(['password', 'newPassword']);

        // check user ID
        $this->requireUserID((int)$this->args['uid']);

        // check old password
        $user = $this->login($GLOBALS['currentUsername'], $this->data['password']);

        // change password and return result
        $this->responseOperation('changed', $user->setPassword($this->data['newPassword']));
    }


    private function routeResetpassword()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        // check method
        if ($this->method != 'POST') {
            $this->error(400);
        }

        // ask for a reset token
        if (!isset($this->data['resetToken'])) {
            $this->requireData(['login']);

            $user = User::getByLogin($this->data['login']);
            if (!$user) {
                Logger::warn('Reset password request for unknown login: '.$this->data['login']);

                // we send a positive feedback to avoid malicious guessing logins
                $this->response(['asked' => true]);
                return;
            }

            // create reset token for user
            if (!$user->resetPassword()) {
                $this->error(500, 'Failed to create reset token');
            }

            $this->response(['asked' => true]);
            return;
        }

        // ask for password reset
        $this->requireData(['newPassword']);

        // clean expired tokens
        Token::cleanup();

        // check reset token
        $user = User::getByToken($this->data['resetToken'], 'resetpassword');
        if (!$user) {
            $this->error(401, "Invalid token");
        }

        // reset password
        if (!$user->setPassword($this->data['newPassword'])) {
            $this->error();
        }

        // delete reset token
        Token::revoke($this->data['resetToken']);

        $this->response(['reset' => true]);
    }


    /*********************
     *  OTHER FUNCTIONS  *
     *********************/

    /**
     * Set headers
     * @param array $headers Specify headers to add
     * @return void
     */
    private function headers(array $headers=[])
    {
        if (!$this->headers) {
            // add headers

            // TODO: secure this!!!
            header("Access-Control-Allow-Methods: *");
            header("Access-Control-Allow-Headers: *");
            header("Content-Type: application/json; charset=UTF-8");

            // cross-origin requests: not recommended in production!
            if (DEV_MODE) {
                header("Access-Control-Allow-Origin: *");
            }

            $this->headers = true;
        }

        // add specified headers
        foreach ($headers as $header) {
            header($header);
        }

        // if preflight, return OK
        if ($this->method == 'OPTIONS') {
            exit();
        }
    }


    /**
     * Print a response to the client
     * @param  object  $data Object to return (will be converted to JSON)
     * @param  integer $code Exit code (200, 400, 500, ...)
     * @param  boolean $quit Quit after return
     * @return void
     */
    private function response($data, $code=200, $quit=false)
    {
        // add headers if not already done
        $this->headers();

        // add headers
        switch ($code) {
            case 200:
                $exitcode = 0;
                break;

            case 201:
                header('HTTP/1.1 201 Created');
                $exitcode = 0;
                break;

            case 400:
                header('HTTP/1.0 400 Bad request');
                $exitcode = 1;
                break;

            case 401:
            header('HTTP/1.0 401 Unauthorized');
                $exitcode = 3;
                break;

            case 403:
                header('HTTP/1.0 403 Forbidden');
                $exitcode = 3;
                break;

            case 404:
                header('HTTP/1.0 404 Not Found');
                $exitcode = 4;
                break;

            default:
                header('HTTP/1.0 500 Internal Server Error');
                $exitcode = 2;
                break;
        }

        // print response
        echo json_encode($data);

        // quit
        if ($quit) {
            exit($exitcode);
        }
    }


    /**
     * Returns an error and quit
     * @param  int     $code   Error code (400, 403, 404, 500, ...)
     * @param  string $details Optionnal description of the error
     * @return void
     */
    private function error(int $code=500, string $details='')
    {
        switch ($code) {
            case 400:
                if ($details == '') {
                    $details = 'Bad request';
                }
                $exitcode = 1;
                break;

            case 403:
                if ($details == '') {
                    $details = 'Forbidden access';
                }
                $exitcode = 3;
                break;

            case 404:
                if ($details == '') {
                    $details = 'Not Found';
                }
                $exitcode = 4;
                break;

            default:
                if ($details == '') {
                    $details = 'Internal Server Error';
                }
                $exitcode = 2;
                break;
        }

        // return error
        $this->response(['error' => $details], $code);

        Logger::debug('Exit code '.$code.': '.$details);

        exit($exitcode);
    }


    /**
     * Returns a short response for operations
     *
     * @param string  $tag
     * @param bool    $success
     * @param string  $details  Optional details
     * @return void
     */
    private function responseOperation($tag, $success, $details='')
    {
        if (!$success) {
            $this->error(500, $details);
        }

        $this->response([$tag => true, 'details' => $details]);
    }


    /**
     * Check if mandatory arguments are provided and quit if not
     * @param  array $args Array of keys to check
     * @return bool        Check OK
     */
    private function requireArgs($args)
    {
        foreach ($args as $arg) {
            // if field is missing, return error and quit
            if (!isset($this->args[$arg])) {
                Logger::error("API call: ".$this->route." missing argument: ".$arg);
                $this->error(400, 'Missing arguments');
            }
        }

        return true;
    }


    /**
     * Check if mandatory GET parameters are provided and quit if not
     * @param  array $params Array of parameters names to check
     * @return void
     */
    private function requireParams($params)
    {
        foreach ($params as $param) {
            // if param is missing, return error and quit
            if (!isset($_GET[$param])) {
                Logger::error("API call: ".$this->route." missing param: ".$param);
                $this->error(400, "Missing param: ".$param);
            }
        }

        return true;
    }


    /**
     * Check if mandatory POST data are provided and quit if not
     * @param  array $mandatory Array of keys to check
     * @return void
     */
    private function requireData($fields)
    {
        foreach ($fields as $field) {
            // if field is missing, return error and quit
            if (!isset($this->data[$field])) {
                Logger::error("API call: ".$this->route." missing data field: ".$field);
                $this->error(400, "Missing data field: ".$field);
            }
        }

        return true;
    }
}
