FMHTTP
======

These are basic php classes for Making / Parsing HTTP Reqeusts / Responses.

It's incomplete and ugly and uses curl.

No documentation right now, basic usage;

```php
$request = new FMHTTP\Request('https://api.iflyer.tv/v1.6/events');
$request->setHeader('Content-Type, 'application/json);
// $request->method = 'POST';
// $request->body = json_encode(array(
//	'id' => 5482,
//	'name' => 'Test Name'
// ));

$request->timeout = 60.0;
$response = $request->send();
if (!$response) {
	echo "Request Failed, Probably timed out, since there is no response.\n";
}
else {
	echo "Got a response {$response->statusCode} {$response->statusMessage}!\n";
	echo "Dumping Response Body:\n";
	echo $response->body;
}
```
