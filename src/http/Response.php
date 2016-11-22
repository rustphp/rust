<?php
namespace rust\http;
/**
 * Class Response
 *
 * @package rust\http
 */
class Response {
    /**
     * @var int HTTP status
     */
    protected $status = 200;

    /**
     * @var array HTTP headers
     */
    protected $headers = [];

    /**
     * @var string HTTP response body
     */
    protected $body;

    /**
     * @var array HTTP status codes
     */
    public static $codes = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    ];

    /**
     * Response constructor.
     */
    public function __construct() {
    }


    /**
     * Sets the HTTP status of the response.
     *
     * @param int $code HTTP status code.
     * @return $this
     * @throws \Exception If invalid status code
     */
    public function status($code) {
        if (array_key_exists($code, self::$codes)) {
            $this->status = $code;
        } else {
            throw new \Exception('Invalid status code.');
        }
        return $this;
    }

    /**
     * Adds a header to the response.
     *
     * @param string|array $name Header name or array of names and values
     * @param string $value Header value
     * @return object Self reference
     */
    public function header($name, $value = NULL) {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->headers[$k] = $v;
            }
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Writes content to the response body.
     *
     * @param string $str Response content
     * @return object Self reference
     */
    public function write($str) {
        $this->body .= $str;

        return $this;
    }


    /**
     * Sets caching headers for the response.
     *
     * @param int|string $expires Expiration time
     * @return object Self reference
     */
    public function cache($expires) {
        if ($expires === FALSE) {
            $this->headers['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
            $this->headers['Cache-Control'] = [
                'no-store, no-cache, must-revalidate',
                'post-check=0, pre-check=0',
                'max-age=0'
            ];
            $this->headers['Pragma'] = 'no-cache';
        } else {
            $expires = is_int($expires) ? $expires : strtotime($expires);
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
            $this->headers['Cache-Control'] = 'max-age=' . ($expires - time());
        }

        return $this;
    }

    /**
     * Clears the response.
     *
     * @return object Self reference
     */
    public function clear() {
        $this->status = 200;
        $this->headers = [];
        $this->body = '';

        return $this;
    }

    /**
     * Has content to response?
     * @return bool
     */
    public function hasContent() {
        return empty($this->body);
    }

    /**
     * Sends the response.
     */
    public function send() {
        if (ob_get_length() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            // Send status code header
            if (strpos(php_sapi_name(), 'cgi') !== FALSE) {
                header(vsprintf('Status: %d %s', [$this->status, self::$codes[$this->status]]), TRUE);
            } else {
                header(vsprintf('%s %d %s', [
                    getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1',
                    $this->status,
                    self::$codes[$this->status]
                ]), TRUE, $this->status);
            }

            // Send other headers
            foreach ($this->headers as $field => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        header($field . ': ' . $v, FALSE);
                    }
                } else {
                    header($field . ': ' . $value);
                }
            }
        }

        echo($this->body);
    }

    /**
     * 页面转向
     *
     * @param $url
     *
     * @throws \Exception
     */
    public function redirect($url) {
        $this->header('Location', $url);
        $this->status(302);
        $this->send();
    }
    /*
    
        private function _outputHeader($format){
            switch($format){
                case 'json' :
                    header('Content-Type: application/json');
                    break;
                case 'jsonp' :
                    header('Content-Type: application/javascript');
                    break;
                default :
                    break;
            }
        }
     */
}
