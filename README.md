# HERE API PHP Client

A simple PHP client for interacting with the [HERE API](https://developer.here.com/).

## Features

- Easy integration with HERE REST APIs
- Supports authentication via API Key
- Simple, object-oriented interface

## Installation

Install via [Composer](https://getcomposer.org/):

1. Create a `composer.json` file in your project root if you don't have one:

```bash
composer init
```

2. Add the HERE API PHP client as a dependency:

```json
{
	"repositories": [
		{
			"type": "vcs",
			"url": "https://github.com/joshmckibbin/here-api-php.git"
		}
	],
	"require": {
		"joshmckibbin/here-api-php": "^1.0"
	}
}
```

## Usage

```php
require 'vendor/autoload.php';

use JMckibbin\HereApi\HereApi;

$client = new HereApi('YOUR_API_KEY','YOUR_COORDINATES');

// Example: Get traffic flow data for your coordinates
$result = $client->flow();
print_r($result);
```

## Supported APIs

- Traffic Data
- Map Image

## Documentation

See the [HERE API documentation](https://here.com/docs) for details on available endpoints and parameters.

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](LICENSE)