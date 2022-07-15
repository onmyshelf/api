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
            '/' => 'welcome',
            '/collections' => 'collections',
            '/collections/{id}' => 'collection',
            '/collections/{id}/import' => 'collectionImport',
            '/collections/{id}/import/scan' => 'collectionImportScan',
            '/collections/{cid}/items' => 'items',
            '/collections/{cid}/properties' => 'properties',
            '/collections/{cid}/properties/{name}' => 'property',
            '/collections/{cid}/items/{id}' => 'item',
            '/collections/{cid}/items/{id}/import/data' => 'itemImportData',
            '/properties/types' => 'propertyTypes',
            '/import/modules' => 'importModules',
            '/import/search' => 'importSearch',
            '/config' => 'config',
            '/login' => 'userLogin',
            '/resetpassword' => 'userPasswordReset',
            '/token' => 'token',
            '/upload' => 'upload',
            '/users/{uid}/collections' => 'userCollections',
            '/users/{uid}/password' => 'userPassword'
        ];
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
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
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

        Logger::debug("User ".$GLOBALS['currentUserID']." connected using token: $token");
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
     * Require user name
     * @param  string $username
     * @return void
     */
    private function requireUsername($username)
    {
        if (!is_null($GLOBALS['currentUsername']) && $username == $GLOBALS['currentUsername']) {
            return;
        }

        $this->error(403);
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
                $function = $this->routes[$r];
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

        // call method with args
        try {
            $this->$function();
        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error(500);
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


    /*****************
     *  API METHODS  *
     *****************/

    /**
     * Welcome message to the main URL
     * @return void
     */
    private function welcome()
    {
        $info = [
            'name' => 'OnMyShelf',
            'media' => MEDIA_URL,
            'readonly' => READ_ONLY,
        ];

        // more information if logged in
        if (isset($GLOBALS['currentToken'])) {
            $info['version'] = VERSION;
        }

        $this->response($info);
    }


    /**
     * Config handler
     * @return void
     */
    private function config()
    {
        // requires to be the administrator
        $this->requireUsername('onmyshelf');

        switch ($this->method) {
            case 'PATCH':
                // update config

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                $success = true;

                foreach ($this->data as $param => $value) {
                    // forbidden params
                    switch ($param) {
                      case 'version':
                        // do nothing
                        break;

                      default:
                        if (!Config::set($param, $value)) {
                            $success = false;
                        }
                        break;
                    }
                }

                // update item
                $this->response(['updated' => $success]);
                break;

            default:
                // print all config
                $this->response(Config::dump());
                break;
        }
    }


    /**
     * Login user
     * @return void
     */
    private function userLogin()
    {
        // check method
        if ($this->method != 'POST') {
            $this->error(400);
        }

        // check data
        $this->requireData(['username', 'password']);

        // authentication
        $user = User::getByLogin($this->data['username'], $this->data['password']);
        if (!$user) {
            $this->error(401, 'Authentication failed');
        }

        // clean expired tokens
        (new Database())->cleanupTokens();

        // create token for user
        $token = $user->createToken();
        if (!$token) {
            $this->error(500, 'Failed to create token');
        }

        // returns token and user ID to client
        $this->response([
            'token' => $token,
            'userid' => $user->getId(),
            'readonly' => READ_ONLY,
        ]);
    }


    /**
     * Token
     * @return void
     */
    private function token()
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


    /*
     *  Collections
     */

    /**
     * Get collections
     * @return void
     */
    private function collections()
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
                    $this->error(500);
                }

                $this->response($collections);
                break;
        }
    }


    /**
     * Collection handler
     * @return void
     */
    private function collection()
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
                // update collection
                $this->response(['updated' => $collection->update($this->data)]);
                break;

            case 'DELETE':
                // delete collection

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());
                // delete collection
                $this->response(['deleted' => $collection->delete()]);
                break;

            default:
                // get collection
                $this->response($collection->dump());
                break;
        }
    }


    /**
     * Scan properties to import a collection
     * @return void
     */
    private function collectionImportScan()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->post = true;
        $this->requireData(['type', 'source']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        try {
            $fields = Collection::scanImport($this->data['type'],
                                            $this->data['source'],
                                            $this->data['options']);

            if ($fields === false) {
                $this->error(400, 'Bad type');
            }

            if (count($fields)) {
                $this->response(['fields' => $fields]);
            } else {
                $this->error(500, 'No fields detected');
            }
        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error(500);
        }
    }


    /**
     * Import collection
     * @return void
     */
    private function collectionImport()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->post = true;
        $this->requireArgs(['id']);
        $this->requireData(['type', 'source']);

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
            $result = $collection->import($this->data['type'],
            $this->data['source'],
            $this->data['options']);
        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error(500);
        }

        if ($result === false) {
            $this->error(500);
        }

        $this->response($result);
    }


    /*
     *  Items
     */

    /**
     * Create item
     * @return void
     */
    private function items()
    {
        $this->requireArgs(['cid']);

        // get collection
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }

        // forbidden if not owner
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
                $filters = [];
                $sortBy = [];

                foreach ($_GET as $key => $value) {
                    // sorting
                    if ($key == 'sort') {
                        // split fields by comma
                        $sort = explode(',', $value);

                        // security checking
                        foreach ($sort as $property) {
                            if (preg_match('/^-?\w+$/', $property)) {
                                $sortBy[] = $property;
                            }
                        }
                        continue;
                    }

                    // detect property
                    if (substr($key, 0, 2) !== 'p_') {
                        continue;
                    }

                    $property = substr($key, 2);
                    if (!preg_match('/^\w+$/', $property)) {
                        continue;
                    }

                    // security check (avoid symbols)
                    if (preg_match('/^\P{S}+$/u', $value)) {
                        $filters[$property] = $value;
                    }
                }

                $this->response($collection->dumpItems($filters, $sortBy));
                break;
        }
    }


    /**
     * Item handler
     * @return void
     */
    private function item()
    {
        $this->requireArgs(['cid','id']);

        // get collection object to check access rights
        $collection = Collection::getById($this->args['cid']);
        if (!$collection) {
            $this->error(404, 'Collection does not exists');
        }
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

                // update item
                $this->response(['updated' => $item->update($this->data)]);
                break;

            case 'DELETE':
                // delete item

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($item->getOwner());

                $this->response(['deleted' => $item->delete()]);
                break;

            default:
                // dump item
                $this->response($item->dump());
                break;
        }
    }


    /**
     * Scan properties to import an item
     * @return void
     */
    private function itemImportData()
    {
        // forbidden in read only mode
        if (READ_ONLY) {
            $this->error(403);
        }

        $this->post = true;
        $this->requireData(['type', 'source']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        try {
            $data = Item::importData($this->data['type'],
                                     $this->data['source'],
                                     $this->data['options']);

            if ($data === false) {
                $this->error(400, 'Bad type');
            }

            $this->response($data);

        } catch (Throwable $t) {
            Logger::fatal($t);
            $this->error(500);
        }
    }


    /*
     *  Properties
     */

    /**
     * Properties handler
     * @return void
     */
    private function properties()
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

                if (!$collection->addProperty($this->data['name'], $this->data)) {
                    $this->error(500);
                }
                $this->response(['updated' => true]);
                break;

            default:
                $this->error(404);
                break;
        }
    }


    /**
     * Property handler
     * @return void
     */
    private function property()
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

                if (!$property->update($this->data)) {
                    $this->error(500);
                }
                $this->response(['updated' => true]);
                break;

            case 'DELETE':
                // delete property

                // forbidden in read only mode
                if (READ_ONLY) {
                    $this->error(403);
                }

                // check ownership
                $this->requireUserID($collection->getOwner());

                if (!$property->delete()) {
                    $this->error(500);
                }
                $this->response(['deleted' => true]);
                break;

            default:
                // TODO: dump property (if useful)
                $this->error(404);
                break;
        }
    }


    /**
     * Property types
     * @return void
     */
    private function propertyTypes()
    {
        $this->response(Property::getTypes());
    }


    /*
     *  Import
     */


    /**
     * Returns available import modules
     * @return void
     */
    private function importModules()
    {
        require_once('inc/classes/Module.php');
        $this->response(Module::list('import'));
    }


    /**
     * Search items from import module
     * @return void
     */
    private function importSearch()
    {
        $this->requireParams(['module', 'source', 'search']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        // load module
        require_once('inc/classes/Module.php');
        if (!Module::load('import', $_GET['module'])) {
            $this->error();
        }

        $import = new Import($_GET['source']);
        if (!$import) {
            $this->error();
        }

        $this->response($import->search($_GET['search']));
    }


    /**
     * Upload a file
     * @return void
     */
    private function upload()
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
            $this->error(500);
        }

        $this->response(['url' => $url]);
    }


    /*
     *  User
     */

    /**
     * Get user's collections
     * @return void
     */
    private function userCollections()
    {
        $this->requireArgs(['uid']);

        // check user ID
        $this->requireUserID((int)$this->args['uid']);

        // get collections
        $collections = Collection::dumpAll($GLOBALS['currentUserID']);
        if ($collections === false) {
            $this->error(500);
        }

        $this->response($collections);
    }


    /**
     * Change user password
     * @return void
     */
    private function userPassword()
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
        $this->requireData(['password', 'newpassword']);

        // check user ID
        $this->requireUserID((int)$this->args['uid']);

        // check old password
        $user = User::getByLogin($GLOBALS['currentUsername'], $this->data['password']);
        if (!$user) {
            $this->error(401, 'Bad password');
        }

        $this->response(['changed' => $user->setPassword($this->data['newpassword'])]);
    }


    /**
     * Reset user password
     * @return void
     */
    private function userPasswordReset()
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
            $this->requireData(['username']);

            $user = User::getByName($this->data['username']);
            if (!$user) {
                Logger::warn('Reset password request for unknown username: '.$this->data['username']);

                // we send a positive feedback to avoid guessing usernames
                $this->response(['asked' => true]);
                return;
            }

            // create reset token for user
            $token = $user->createToken('resetpassword');
            if (!$token) {
                $this->error(500, 'Failed to create token');
            }

            Logger::message("*************  RESET PASSWORD REQUEST  *************");
            Logger::message("*  UserID: ".$user->getId());
            Logger::message("*  URL:    /resetpassword?token=$token");
            Logger::message("****************************************************");

            // quit
            $this->response(['asked' => true]);
            return;
        }

        // ask for password reset
        $this->requireData(['newpassword']);

        // clean expired tokens
        (new Database())->cleanupTokens();

        // check reset token
        $user = User::getByToken($this->data['resetToken'], 'resetpassword');
        if (!$user) {
            $this->error(401);
        }

        // reset password
        if (!$user->setPassword($this->data['newpassword'])) {
            $this->error(500, "Unexpected error");
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

            /****************************************/
            //       INSECURE: for dev only!
            //header("Access-Control-Allow-Origin: *");
            /****************************************/

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


    /**
     * Return a simple JSON object to tell if something exists: {"exists": true}
     * @param  boolean $exists Thing exists
     * @param  boolean $quit   Quit after response
     * @return void
     */
    private function responseExists($exists, $quit=false)
    {
        if (!is_bool($exists)) {
            $this->error(500, 'bad exists response');
        }

        $this->response(['exists' => $exists], 200, $quit);
    }
}
