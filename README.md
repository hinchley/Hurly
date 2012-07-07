Hurly
=====
Hurly is a general purpose class for efficiently and asynchronously requesting multiple URLs in PHP.

Why
---
Here are some reasons to use Hurly:

1. **Parallel requests.** Even though PHP is a single-threaded programming language, with Hurly you can request multiple URLs in parallel.
2. **Non-blocking.** As soon as any request completes, another is started. The code won't block waiting for the slowest of the parallel executing tasks to end.
3. **Throttling**. Hurly is respectful, and will introduce a configurable delay when requesting multiple URLs from the same domain.
4. **Custom callbacks.** Use custom callback functions to process response data. You can even access performance metrics associated with a request (e.g. the time to establish a connection).
5. **Rich method support.** Hurly is not restricted to just GET requests. You can use any HTTP method. This includes the submission of POST data. You can even set custom HTTP headers.
6. **Bandwidth optimisations.** If your callback function doesn't require access to page content, you can save time and bandwidth by using the HEAD method instead of GET. If using HEAD results in a "method not accepted" response, Hurly will try again with a GET request. And when using GET, you can also take advantage of content compression (e.g. gzip).
7. **Highly configurable.** You can modify global request settings using the Hurly constructor, or via fluid methods calls.  And when necessary, settings can be uniquely tailored to an individual URL.
8. **Great documentation.** No groping about in the dark. No need to wade through mountains of code to understand how it works. The simple interface and easy to read documentation will see you up and running in a flash.
9. **Total freedom.** Hurly is released under the "Do What the Fuck You Want To" license agreement, offering you the freedom to use and change the code as you please.
10. **Nuff said.** I think you get the point. Hurly rocks.

Requirements
------------
Hurly requires PHP 5.4 compiled with support for cURL. 

An Example
----------
The default behaviour of Hurly is to operate as a link validation tool. In this mode, it's assumed you will want to verify a large number of URLs. As this will often be a time consuming activity, the default callback function included with Hurly is optimised for displaying output in a shell window (not a web browser).

To see the library in action, let's create a file named 'validator.php', include 'hurly.php', instantiate an instance of the Hurly class, and call the ``run`` method, passing the array of URLs to be requested. Like this:

    include 'hurly.php';
    $urls = ['http://google.com', 'http://amazon.com'];
    $hurly = new Hurly();
    $hurly->run($urls);

Now, from a shell prompt, run:

    php validator.php

You should see output similar to:

    Success: http://google.com [http://www.google.com.au/]
    Success: http://amazon.com [http://www.amazon.com/]

The output consists of three parts: the status of the request (i.e. did the request sequence culminate in a HTTP 200 response code), the originally requested URL, and the URL (in brackets) requested after all redirects (if any) were followed.

Configuration
-------------
### Overview ###
There are two ways to configure Hurly. You can pass an array of configuration options into the class constructor, or you can use fluid method calls.

The following example uses the class constructor to create an instance of Hurly that will support up to 5 parallel requests with a minimum 10 second delay between requests to the same domain.

    $hurly = new Hurly(['parallel' => 5, 'delay' => 10]);
    $hurly->run($urls);

This example uses fluid method calls to achieve the same result:

    $hurly = new Hurly();
    $hurly->parallel(5)->delay(5)->run($urls);

### Configuration Options ###
The following options can be used to configure Hurly.

##### Delay #####
The number of seconds between requests to the *same domain*. The default value is 5.

    // Set a 10 second delay.
    $hurly->delay(10);

##### Parallel #####
The maximum number of parallel requests. The default value is 10, and the configured value must be greater than 1.

    // Set a limit of 5 parallel requests.
    $hurly->parallel(5);

##### Method #####
The default method used when making requests. Refer to the section 'Specifying URLs' for instructions on overriding the method for specific URLs. The default value is HEAD, but you can set any valid HTTP verb.

    // Use GET as the default request method.
    $hurly->method('GET');

Note: When HEAD is used, the $response variable provided to the callback function will be empty.

##### Retry #####
As some sites do not support the HEAD method (e.g. Amazon), if a HEAD request fails with a 405 response code (method not accepted), Hurly, by default, will retry with a GET request. Use the ``retry`` setting to control this behaviour.

    // Do not fallback to GET if HEAD fails.
    $hurly->retry(false);

##### Options #####
As Hurly uses the cURL interface for making HTTP requests, the behaviour of the class can be customised using cURL configuration options. These are set using the ``options`` method. The following example sets a limit on the maximum number of HTTP redirects followed when requesting a URL.

    // Follow a maximum of 3 redirects.
    $hurly->options([CURLOPT_MAXREDIRS => 3]);

And to set the timeout for individual URL requests (the default is 30 seconds):

    // Timeout URL request after 10 seconds.
    $hurly->options([CURLOPT_TIMEOUT => 10]);

Refer to the official PHP web site for information on [cURL options](http://php.net/manual/en/function.curl-setopt.php "Documentation for curl_setopt").

##### Headers #####
To set custom HTTP headers when making a request, pass an array of key-value pairs to the ``headers`` method. For example, to send the header 'X-Foo: Bar' with each request.

    // Set the HTTP header X-Foo to Bar.
    $hurly->headers(['X-Foo' => 'Bar']);

##### Post Data #####
Although you will typically want to submit different POST data for different URLs, you can use the ``data`` method to set global POST data that will be submitted with every request (unless overriden). Just call the method with an array of key-value pairs. In this example, a POST request will be made to all URLs with the data 'userid = fred'.

    $hurly->method('POST')->data(['userid' => 'fred']);

### Custom Callback Functions ###

##### Overview #####
A callback function is executed following the completion of each URL request. This occurs irrespective of the HTTP response code (except when redirects are still being followed, or when resubmitting a failed HEAD request).

Hurly ships with a default callback function which can be overriden by passing a custom function to the ``run`` method, or by extending the Hurly class and overriding the ``callback`` method.

##### Callback Syntax #####
All custom callback functions must accept 3 parameters: $info (an array of useful data returned from 'curl_getinfo'), $request (an array describing the original request), and $response (a string variable containing the body of the response).

Refer to the official PHP web site for information on the data that can be retrieved from [curl_getinfo](http://www.php.net/manual/en/function.curl-getinfo.php "Documentation for curl_getinfo") (and hence, from the $info variable). As an example:

    // The last HTTP response code.
    echo $info['http_code'];

    // The final URL (after redirects have been followed).
    echo $info['url'];

The $request array, which contains information about the original request, supports the following properties: ``url`` (originally requested URL), ``method`` (the request method), ``data`` (an array of the POST data - if any - submitted with the request), ``headers`` (an array of the HTTP headers sent with the request), and ``options`` (an array of the cURL options used in making the request).

As an example, to display the originally requested URL (which may be different from the target URL after all redirects are followed):

    // The originally requested URL.
    echo $request['url'];

##### Callback Examples #####
Let's say you want to display the title of all requested web pages. You might write a custom callback like this:

    // Display page titles. For example:
    // http://php.net > PHP: Hypertext Preprocessor
    $callback = function($info, $request, $response) {
	  if (preg_match("#<title>(.*?)</title>#", $response, $m)) {
		$title = $m[1];
	  }
	  echo "{$request['url']} > $title\n";
	};

To use your custom callback, simply pass the function as the second argument of the ``run`` method:

    $hurly = new Hurly();
    $hurly->run($urls, $callback);

Alternatively, you can extend the Hurly class, and override the ``callback`` method:

    class Shurly extends Hurly {
      protected function callback($info, $request, $response) {
        // Code goes here…
      }
    }

In this example, to use the custom callback, just create an instance of the new parent class. The following code snippet is functionally equivalent to the previous example:

    $shurly = new Shurly();
    $shurly->run($urls);

### Specifying URLs ###
The simplest way to call Hurly is with a one-dimensional array of URLs. In this approach, the same settings (e.g. method, cURL options, headers, etc) are used to make each request. In the following example, a GET request will be made against each of the URLs in the $urls array:

    $urls = ['http://google.com', 'http://twitter.com',
	  'http://facebook.com', 'http://amazon.com'];
    $hurly = new Hurly(['method' => 'GET']);
    $hurly->run($urls);

You can also configure URL-specific customisations using a multi-dimensional array. For example, the following code will initiate a simple GET request for Google, but a POST request for Facebook:

    $urls = [
      'http://google.com',
      ['url'    => 'http://facebook.com/login.php',
       'method' => 'POST',
       'data'   => ['email' => 'you@gmail.com']]
    ];
    $hurly = new Hurly(['method' => 'GET']);
    $hurly->run($urls);

When using the above approach, the 'url' property is mandatory for each sub-array, whilst 'method', 'data', 'headers' and 'options' (all described under the section on 'Callback Syntax') are optional.
