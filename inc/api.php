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
            '/collections/{id}/import/scan' => 'collectionImportScan',
            '/collections/{id}/import' => 'collectionImport',
            '/collections/{cid}/items' => 'items',
            '/collections/{cid}/properties' => 'properties',
            '/collections/{cid}/properties/{name}' => 'property',
            '/collections/{cid}/items/{id}' => 'item',
            '/properties/types' => 'propertyTypes',
            '/import/modules' => 'importModules',
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
     * Check token
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
        $userData = (new Database())->getUserFromToken($token);
        if (!$userData) {
            Logger::debug("Bad token: $token");
            $this->error(401, "Invalid token");
        }

        $GLOBALS['accessRights'] = 1;
        $GLOBALS['currentToken'] = $token;
        $GLOBALS['currentUserID'] = $userData['id'];
        $GLOBALS['currentUsername'] = $userData['username'];

        Logger::debug("User ".$userData['id']." connected using token: $token");
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
                Logger::fatal('API cannot decode JSON:');
                Logger::var_dump($body);
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
        $this->response([
            'name' => 'OnMyShelf',
            'version' => VERSION,
            'media' => MEDIA_URL,
            'url' => 'https://onmyshelf.cm',
            'email' => 'contact@onmyshelf.cm',
        ]);
    }


    /**
     * Login user
     * @return void
     */
    private function userLogin()
    {
        // read only mode: disable authentication
        if (READONLY) {
            $this->error(403);
        }

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

        // delete expired tokens
        // TODO: move this to another place
        $db = new Database();
        $db->cleanupTokens();

        // create token that expires in 10 hours
        $token = Token::generate();
        $expiration = time()+36000;
        Logger::debug("New token for user ".$this->data['username'].": $token, expires: $expiration");

        // TODO: check if behind reverse proxy; add trusted proxies config
        $ipOrigin = (string)$_SERVER['REMOTE_ADDR'];

        // add token in database
        if (!$db->createToken($token, $this->data['username'], $expiration, $ipOrigin)) {
            $this->error(500, 'Failed to create token');
        }

        // returns token and user ID to client
        $this->response([
            'token' => $token,
            'userid' => $user->getId()
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

                // check ownership
                $this->requireUserID($collection->getOwner());
                // update collection
                $this->response(['updated' => $collection->update($this->data)]);
                break;

            case 'DELETE':
                // delete collection

                // check ownership
                $this->requireUserID($collection->getOwner());
                // delete collection
                $this->response(['deleted' => $collection->delete()]);
                break;

            default:
                // get collection

                // filters
                $filters = [];

                // security check
                if (isset($_GET['filterBy']) && isset($_GET['filterValue'])) {
                    if (preg_match('/^\w+$/', $_GET['filterBy'])) {
                        $filter = $_GET['filterBy'];
                        if (preg_match('/^\w+$/', $_GET['filterValue'])) {
                            $filters[$filter] = $_GET['filterValue'];
                        }
                    }
                }
                $this->response($collection->dump($filters));
                break;
        }
    }


    /**
     * Scan properties to import a collection
     * @return void
     */
    private function collectionImportScan()
    {
        $this->post = true;
        $this->requireData(['type', 'source']);

        // default options
        if (!isset($this->data['options'])) {
            $this->data['options'] = [];
        }

        try {
            $properties = Collection::scanImport($this->data['type'],
            $this->data['source'],
            $this->data['options']);

            if ($properties === false) {
                $this->error(400, 'Bad type');
            }

            if (count($properties)) {
                $this->response(['properties' => $properties]);
            } else {
                $this->error(500, 'No properties detected');
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

        $this->compareUserID($collection->getOwner());

        switch ($this->method) {
            case 'POST':
                // create a new item

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

                // filters
                $filters = [];
                // security check
                if (isset($_GET['filterBy']) && isset($_GET['filterValue'])) {
                    if (preg_match('/^\w+$/', $_GET['filterBy'])) {
                        $filter = $_GET['filterBy'];
                        if (preg_match('/^\w+$/', $_GET['filterValue'])) {
                            $filters[$filter] = $_GET['filterValue'];
                        }
                    }
                }

                // sorting
                $sortBy = [];
                if (isset($_GET['sort'])) {
                    // split fields by comma
                    $sort = explode(',', $_GET['sort']);

                    // security checking
                    foreach ($sort as $field) {
                      if (preg_match('/^-?\w+$/', $field)) {
                          $sortBy[] = $field;
                      }
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

                // check ownership
                $this->requireUserID($collection->getOwner());

                // update item
                $this->response(['updated' => $item->update($this->data)]);
                break;

            case 'DELETE':
                // delete item

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

                // check ownership
                $this->requireUserID($collection->getOwner());

                if (!$property->update($this->data)) {
                    $this->error(500);
                }
                $this->response(['updated' => true]);
                break;

            case 'DELETE':
                // delete property

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
        $core = glob('inc/import/*.php');
        $addons = glob('inc/modules/import/*.php');
        $modules = [];

        // get import modules
        foreach (array_merge($core, $addons) as $path) {
            $module = basename($path, '.php');

            switch ($module) {
                case 'example':
                case 'import':
                    // ignore
                    break;

                default:
                    $modules[] = $module;
                    break;
            }
        }

        $this->response($modules);
    }


    /**
     * Upload a file
     * @return void
     */
    private function upload()
    {
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
        // check method
        if ($this->method != 'POST') {
            $this->error(400);
        }

        // ask for a reset token
        if (!isset($this->data['resetToken'])) {
            $this->requireData(['username']);

            $user = User::getByName($this->data['username']);
            if (!$user) {
                $this->error(401);
            }

            // generate reset token
            $resetToken = Token::generate(20);
            $user->setResetToken($resetToken);

            Logger::message("*****  PASSWORD RESET ASK  *****");
            Logger::message("* User: ".$user->getId());
            Logger::message("* Token: $resetToken");
            Logger::message("********************************");

            // quit
            $this->response(['asked' => true]);
            return;
        }

        // ask for password reset
        $this->requireData(['newpassword']);

        // check reset token
        $user = User::getByResetToken($this->data['resetToken']);
        if (!$user) {
            $this->error(401);
        }

        // reset password
        if (!$user->setPassword($this->data['newpassword'])) {
            $this->error(500, "Unexpected error");
        }

        // delete reset token
        $user->setResetToken('');

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
                return false;
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
                Logger::error("API call: ".$this->route." missing field: ".$field);
                $this->error(400, "Missing field: ".$field);
                return false;
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
