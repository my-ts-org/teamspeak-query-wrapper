# TeamSpeak Query Wrapper

A secure PHP wrapper for TeamSpeak server owners to safely gather server information and send it to authorized applications.

## Features

- Secure connection to TeamSpeak server
- Minimal required permissions
- Data filtering capabilities
- Debug logging
- Easy to integrate with web applications
- Secure configuration management
- Protected sensitive data
- Automatic API key generation
- Secure API authentication
- Multiple authentication methods

## Directory Structure

```
teamspeak-query-wrapper/
├── config/                 # Configuration files (protected)
│   └── config.php         # Main configuration file
├── logs/                  # Log directory (protected)
├── public/                # Public web root
│   └── api.php           # Public API endpoint
├── src/                   # Source code
│   └── TeamSpeakQueryWrapper.php
├── .gitignore
└── README.md
```

## Required Permissions

The wrapper requires the following TeamSpeak server permissions:

- `b_virtualserver_info_view`
- `b_virtualserver_channel_list`
- `b_virtualserver_client_list`
- `b_client_remoteaddress_view`
- `b_serverquery_login`
- `b_client_create_modify_serverquery_login`

You may use your existing serveradmin query account for authentication. Since the wrapper runs on your own server, your credentials remain secure and are never exposed externally.

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/my-ts-org/teamspeak-query-wrapper.git
   cd teamspeak-query-wrapper
   ```

2. Configure your web server:

   - Set the document root to the `public` directory
   - Ensure the `config` and `logs` directories are not web-accessible
   - Configure proper permissions for the `logs` directory
   - Ensure the web server has write permissions to the `config` directory

3. Create configuration:

   - Copy `config/config.example.php` to `config/config.php`
   - Edit `config/config.php` with your settings:
     ```php
     return [
         'teamspeak' => [
             'host' => 'localhost',
             'queryPort' => 10011,
             'username' => 'your_username',
             'password' => 'your_password'
         ],
         'webapp' => [
             'endpoint' => 'https://your-webapp.com/api',
             'apiKey' => ''  // Leave empty for automatic generation
         ],
         'debug' => [
             'enabled' => false,  // Set to true only during testing
             'logFile' => __DIR__ . '/../logs/ts_wrapper.log'
         ]
     ];
     ```

4. Configure your TeamSpeak server:
   - Create a server query login
   - Assign the required permissions
   - Note down the username and password

You may use your existing serveradmin query account for authentication. Since the wrapper runs on your own server, your credentials remain secure and are never exposed externally.

## Usage

Make a POST request to the API endpoint using one of the following authentication methods:

### 1. Using JSON Body (Recommended)

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d "{\"api_key\":\"your-api-key\"}" \
  http://your-server.com/api.php
```

### 2. Using Form Data

```bash
curl -X POST \
  -F "api_key=your-api-key" \
  http://your-server.com/api.php
```

### 3. Using Custom Header

```bash
curl -X POST \
  -H "X-API-Key: your-api-key" \
  http://your-server.com/api.php
```

The API will return a JSON response containing:

- Server information
- Channel list
- Client list

### API Authentication

The API endpoint requires authentication using one of the following methods:

1. JSON body with `api_key` field
2. Form data with `api_key` field
3. Custom header `X-API-Key`

The API key must match the one stored in your configuration file.

### Debug Mode

⚠️ **IMPORTANT SECURITY NOTICE**: Debug mode should only be enabled during testing and development. When enabled, it:

- Exposes sensitive information in error responses
- Logs detailed information about requests
- May reveal API keys and other sensitive data

To disable debug mode in production:

1. Open `config/config.php`
2. Set `'enabled' => false` in the debug section
3. Ensure the log file is not web-accessible

### Automatic API Key Generation

The wrapper will automatically generate a secure API key when:

1. The API key is empty in the configuration
2. The API key is set to the default value ('1234567890')

The generated key will be:

- 64 characters long
- Cryptographically secure
- Automatically saved to the configuration file

You can use this API key to authenticate requests to your web application.

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

- Configuration files are protected outside the web root
- Log files are stored in a protected directory
- Source code is not directly accessible
- Only the public API endpoint is exposed
- Minimal required permissions for TeamSpeak server
- All data can be filtered before sending
- Debug mode helps identify issues (disable in production)
- Connection timeouts prevent hanging
- Error handling for failed connections
- Secure API key generation and management
- POST-only API endpoint
- API key authentication required
- JSON request body validation
- Multiple authentication methods supported
- Debug information available when enabled (disable in production)

## Web Server Configuration

### Apache

Add this to your `.htaccess` in the root directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>
```

### Nginx

Update your server configuration:

```nginx
server {
    root /path/to/teamspeak-query-wrapper/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
}
```

## License

MIT License - Feel free to use and modify for your needs.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
