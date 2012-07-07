<?php
/**
 * @author  Peter Hinchley
 * @license http://sam.zoy.org/wtfpl
 */

/**
 * Hurly is a general purpose class for efficiently and
 * asynchronously requesting multiple URLs.
 */
class Hurly {
  /**
   * Number of seconds between requests to the same domain.
   *
   * @var int
   */
  protected $delay = 5;

  /**
   * Maximum number of parallel requests. Must be greater than 1.
   *
   * @var int
   */
  protected $parallel = 10;

  /**
   * Timestamps of recent domain requests.
   *
   * @var array
   */
  protected $recent;

  /**
   * A map of requests to handles.
   *
   * @var array
   */
  private $map;

  /**
   * URLs to be requested.
   *
   * @var array
   */
  protected $requests;

  /**
   * Request method.
   *
   * @var string
   */
  protected $method = 'GET';

  /**
   * Retry with GET if HEAD fails.
   *
   * @var bool
   */
  protected $retry = true;

  /**
   * POST data.
   *
   * @var array
   */
  protected $data = [];

  /**
   * HTTP headers.
   *
   * @var array
   */
  protected $headers = [];

  /**
   * cURL options.
   *
   * @var array
   */
  protected $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_ENCODING  => 'gzip',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; hurlybot/1.0)'
  ];

  /**
   * Class constructor with optional configuration settings.
   *
   * @param  array $config Configuration options.
   * @return this
   */
  public function __construct($config = []) {
    foreach ($config as $key => $value) {
      $this->set($key, $value);
    }
  }

  /**
   * Magic method for setting object properties.
   *
   * @param  string $key Property name.
   * @param  mixed  $value Property value.
   * @return this
   */
  public function __call($key, $value) {
    $this->set($key, $value[0]);
    return $this;
  }

  /**
   * Set object properties.
   *
   * @param  string $key Property name.
   * @param  mixed  $value Property value.
   * @return void
   */
  protected function set($key, $value) {
    if ($key == 'headers' || $key == 'options') {
      $this->$key = $value + $this->$key;
    } elseif (property_exists($this, $key)) {
      $this->$key = $value;
    }
  }

  /**
   * Default callback function.
   *
   * @param  array  $info Result returned from curl_getinfo.
   * @param  string $response Body of response.
   * @param  string $request Requested URL.
   * @return void
   */
  protected function callback($info, $request, $response) {
    echo $info['http_code'] == 200 ?
      "Success: {$request['url']} [{$info['url']}]\n" :
      "Failure: {$request['url']}\n";
  }

  /**
   * Create a cURL handle and add it to the cURL multi handle.
   *
   * @param  resource $mh cURL multi handle.
   * @param  array    $request Request array.
   * @return void
   */
  protected function dispatch($mh, $request) {
    $ch = curl_init($request['url']);
    $this->map[(string) $ch] = $request;

    curl_setopt_array($ch, $request['options']);
    curl_multi_add_handle($mh, $ch);
  }

  /**
   * Find the URL of a domain that has not been recently requested.
   *
   * The method returns false if all URLs are exhausted and null if
   * the only URLs available are from recently requested domains.
   *
   * @return mixed
   */
  protected function request() {
    if (empty($this->requests)) return false;

    $now = time();

    foreach ($this->requests as $index => $request) {
      $url  = $request['url'];
      $host = parse_url($url)['host'];

      if (!isset($this->recent[$host]) ||
        $now - $this->recent[$host] > $this->delay) {
        $this->recent[$host] = $now;
        array_splice($this->requests, $index, 1);
        return $request;
      }
    }

    return null;
  }

  /**
   * Request multiple URLs and apply callback function to response.
   *
   * @param  array    $urls URLs to request.
   * @param  function $callback Function applied to response.
   * @return void
   */
  public function run($urls, $callback = false) {
    $mh = curl_multi_init();

    $callback = $callback ?: [$this, 'callback'];

    if ($this->parallel < 2) {
      $error = 'Must support at least two parallel requests.';
      throw new OutOfBoundsException($error);
    }

    $parallel = min(sizeof($urls), $this->parallel);

    $keys = ['method', 'options', 'headers', 'data'];
    foreach ($keys as $key) {
      $init[$key] = $this->$key;
    }

    foreach ($urls as $index => $request) {
      $this->requests[$index] = is_array($request) ?
        $request + $init : ['url' => $request] + $init;

      $headers = [];
      foreach ($this->requests[$index]['headers'] as $k => $v) {
        $headers[] = "$k: $v";
      }

      $options[CURLOPT_HTTPHEADER] = $headers;

      $options[CURLOPT_NOBODY] = false;
      $options[CURLOPT_POST]   = false;

      $method = strtoupper($this->requests[$index]['method']);

      if ($method == 'HEAD') {
        $options[CURLOPT_NOBODY] = true;
      }

      if ($method == 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] =
          http_build_query($this->requests[$index]['data']);
      }

      $this->requests[$index]['options'] =
        $options + $this->requests[$index]['options'];
    }

    for ($i = 0; $i <= $parallel &&
      $request = $this->request(); $i++) {
      $this->dispatch($mh, $request);
    }

    do {
      $exec = curl_multi_exec($mh, $active);
      $msgs = curl_multi_info_read($mh);

      if ($msgs !== false) {
        $ch = $msgs['handle'];

        $info = curl_getinfo($ch);
        $request = $this->map[(string) $ch];

        if ($info['http_code'] == 405 &&
          $request['method'] == 'HEAD' && $this->retry) {
          $request['options'] = array(CURLOPT_NOBODY => false)
            + $request['options'];
          $this->dispatch($mh, $request);
        } else {
          $response = curl_multi_getcontent($msgs['handle']);
          $params = [$info, $this->map[(string) $ch], $response];
          call_user_func_array($callback, $params);

          while (($request = $this->request()) === null) sleep(1);
          if ($request) $this->dispatch($mh, $request);
        }

        $active = true;
        curl_multi_remove_handle($mh, $ch);
      }

      //if ($active) curl_multi_select($mh, $this->timeout);
    } while ($exec === CURLM_CALL_MULTI_PERFORM || $active);
  
    curl_multi_close($mh);
  }
} 