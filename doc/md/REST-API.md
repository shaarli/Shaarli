# REST API

## Server requirements

See the **[REST API documentation](https://shaarli.github.io/api-documentation/)** for a list of available endpoints and parameters.

Please ensure that your server meets the requirements and is properly [configured](Server-configuration.md):

- URL rewriting is enabled (see specific Apache and Nginx sections)
- the server's timezone is properly defined
- the server's clock is synchronized with [NTP](https://en.wikipedia.org/wiki/Network_Time_Protocol)

The host where the API client is invoked should also be synchronized with NTP, see _payload/token expiration_


## Clients and examples

- **[python-shaarli-client](https://github.com/shaarli/python-shaarli-client)** - the reference API client ([Documentation](https://python-shaarli-client.readthedocs.io/en/latest/))
- [shaarli-client](https://www.npmjs.com/package/shaarli-client) - NodeJs client ([source code](https://github.com/laBecasse/shaarli-client)) by [laBecasse](https://github.com/laBecasse)
- [Android client example with Kotlin](https://gitlab.com/-/snippets/1665808) by [Braincoke](https://github.com/Braincoke)


This example uses the [PHP cURL](https://www.php.net/manual/en/book.curl.php) library.

```php
<?php
$baseUrl = 'https://shaarli.mydomain.net';
$secret = 'thats_my_api_secret';

function base64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateToken($secret) {
    $header = base64url_encode('{
        "typ": "JWT",
        "alg": "HS512"
    }');
    $payload = base64url_encode('{
        "iat": '. time() .'
    }');
    $signature = base64url_encode(hash_hmac('sha512', $header .'.'. $payload , $secret, true));
    return $header . '.' . $payload . '.' . $signature;
}


function getInfo($baseUrl, $secret) {
    $token = generateToken($secret);
    $endpoint = rtrim($baseUrl, '/') . '/api/v1/info';

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Authorization: Bearer ' . $token,
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

var_dump(getInfo($baseUrl, $secret));
```

## Implementation

### Authentication

- All requests to Shaarli's API must include a **JWT token** to verify their authenticity.
- This token must be included as an HTTP header called `Authorization: Bearer <jwt token>`.
- JWT tokens are composed by three parts, separated by a dot `.` and encoded in base64:

```
[header].[payload].[signature]
```

##### Header

Shaarli only allow one hash algorithm, so the header will always be the same:

```json
{
    "typ": "JWT",
    "alg": "HS512"
}
```

Encoded in base64, it gives:

```
ewogICAgICAgICJ0eXAiOiAiSldUIiwKICAgICAgICAiYWxnIjogIkhTNTEyIgogICAgfQ==
```

##### Payload

Token expiration: To avoid infinite token validity, JWT tokens must include their creation date in UNIX timestamp format (timezone independent - UTC) under the key `iat` (issued at) field ([1](https://datatracker.ietf.org/doc/html/rfc7519)). This token will be valid during **9 minutes**.

```json
{
    "iat": 1468663519
}
```

##### Signature

The signature authenticates the token validity. It contains the base64 of the header and the body, separated by a dot `.`, hashed in SHA512 with the API secret available in Shaarli administration page.

Example signature with PHP:

```php
$content = base64_encode($header) . '.' . base64_encode($payload);
$signature = hash_hmac('sha512', $content, $secret);
```



## Troubleshooting

### Debug mode

> This should never be used in a production environment.

For security reasons, authentication issues will always return an `HTTP 401` error code without any detail.

It is possible to enable the debug mode in `config.json.php` 
to get the actual error message in the HTTP response body with:

```json
{
  "dev": {
    "debug": true
  }
}
```

## References

- [jwt.io](https://jwt.io) (including a list of client per language).
- [RFC - JSON Web Token (JWT)](https://datatracker.ietf.org/doc/html/rfc7519)
- [JSON Web Tokens (JWT) vs Sessions](https://float-middle.com/json-web-tokens-jwt-vs-sessions/), [HackerNews thread](https://news.ycombinator.com/item?id=11929267)




