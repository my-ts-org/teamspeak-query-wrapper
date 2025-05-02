# TeamSpeak Query Wrapper

A secure PHP wrapper for TeamSpeak server owners to safely gather server information and send it to authorized applications.

## Features

- Secure connection to TeamSpeak server
- Minimal required permissions
- Data filtering capabilities
- Debug logging
- Easy to integrate with web applications

## Required Permissions

The wrapper requires the following TeamSpeak server permissions:

- `b_virtualserver_info_view`
- `b_virtualserver_channel_list`
- `b_virtualserver_client_list`
- `b_client_remoteaddress_view`
- `b_serverquery_login`
- `b_client_create_modify_serverquery_login`

## Installation

1. Download the files:

   - `TeamSpeakQueryWrapper.php`
   - `api.php`

2. Configure your TeamSpeak server:

   - Create a server query login
   - Assign the required permissions
   - Note down the username and password

3. Configure the wrapper:
   - Edit `api.php` with your server details
   - Set up your webapp endpoint and API key

## Usage

```php
require_once 'TeamSpeakQueryWrapper.php';
use TeamSpeakWrapper\TSQueryWrapper;

$ts = new TSQueryWrapper(
    'localhost',             // TeamSpeak server host
    10011,                   // TeamSpeak query port
    'serveradmin',           // TeamSpeak query username
    'password',              // TeamSpeak query password
    'https://your-webapp.com/api', // Your webapp endpoint
    'your-api-key'           // Your API key
);

// Enable debug mode if needed
$ts->setDebugMode(true, 'ts_wrapper.log');

// Connect to the server
if ($ts->connect()) {
    // Get server information
    $serverInfo = $ts->getServerInfo();
    $channels = $ts->getChannelList();
    $clients = $ts->getClientList();

    // Send data to your webapp
    $ts->sendDataToWebapp();

    // Disconnect
    $ts->disconnect();
}
```

## Data Filtering

You can set up a custom filter to modify the data before it's sent to your webapp:

```php
$ts->setDataFilter(function($data) {
    // Example: Remove client IP addresses
    foreach ($data['clients'] as &$client) {
        unset($client['client_ip']);
    }
    return $data;
});
```

## Security

- The wrapper uses minimal required permissions
- All data can be filtered before sending
- Debug mode helps identify issues
- Connection timeouts prevent hanging
- Error handling for failed connections

## License

MIT License - Feel free to use and modify for your needs.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
