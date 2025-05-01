<!--
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
-->

# JWT Cross-Domain Authentication for Magento 2

## Overview

TheDevKitchen_JwtCrossDomainAuth is a Magento 2 module that enables seamless user authentication across multiple domains or websites within a Magento ecosystem. It uses JSON Web Tokens (JWT) for secure authentication and RabbitMQ for asynchronous processing of login events.

## Features

- Secure cross-domain authentication using JWT tokens
- Visual feedback with Magento's native loader during domain transitions
- Asynchronous processing via message broker (RabbitMQ)
- Configurable via admin interface
- Support for multiple languages (currently English and Brazilian Portuguese)
- CLI commands for JWT token generation and validation

## Requirements

- Magento 2.4.6 or higher
- PHP 8.1 or higher
- RabbitMQ server (for asynchronous processing)

## Installation

### Manual Installation

1. Create the following directory in your Magento installation: `app/code/TheDevKitchen/JwtCrossDomainAuth`
2. Copy all module files to this directory
3. Enable the module:
   ```bash
   bin/magento module:enable TheDevKitchen_JwtCrossDomainAuth
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy
   bin/magento cache:clean
   ```

## Configuration

### Step 1: Set up RabbitMQ (Message Queue)

1. Install RabbitMQ on your server if not already installed
2. Configure RabbitMQ in your `app/etc/env.php`:
   ```php
   'queue' => [
       'amqp' => [
           'host' => 'rabbitmq',     // Change to your RabbitMQ host
           'port' => '5672',         // Default RabbitMQ port
           'user' => 'guest',        // RabbitMQ username
           'password' => 'guest',    // RabbitMQ password
           'virtualhost' => '/'      // RabbitMQ virtual host
       ]
   ],
   ```

### Step 2: Enable and Configure the Module

1. In Magento admin, navigate to **Stores > Configuration > JWT Cross-Domain Auth**
2. Under **General Settings**:
   - Set "Enable Module" to "Yes"
   - Enter the target domain URL in the "Target Domain" field (e.g., `https://second-store.com`)
3. Under **Security Settings**:
   - Enter a secure JWT Secret Key (must be identical across all connected domains)
   - Use a strong, random string of at least 32 characters
4. Save configuration

> **Important**: The JWT Secret Key must be exactly the same on all connected domains.

### Step 3: Configure Multiple Domains

1. Repeat the configuration on each Magento installation that will participate in the cross-domain authentication
2. Make sure each installation has:
   - The module installed and enabled
   - The same JWT Secret Key configured
   - The correct Target Domain configured (pointing to the other domain)
   - RabbitMQ properly configured

### Step 4: Clear Cache on All Systems

After configuration on all domains, clear the cache on each system:
```bash
bin/magento cache:clean
bin/magento cache:flush
```

Or use the provided script:
```bash
./clean.sh
```

## Usage

Once configured:

1. Log in to any of your connected Magento stores
2. A "Switch Store" link will appear in the header (only for logged-in customers)
3. Clicking this link will show a loader and redirect to the configured target domain
4. Authentication will happen automatically using the JWT token

## CLI Commands

### JWT Token Generation

The module provides a command-line interface for generating JWT tokens for testing and development purposes.

**Basic Usage**:
```bash
bin/magento thedevkitchen:jwt:generate
```

This will generate a JWT token with default settings.

**Options**:

- `--subject` or `-s`: Set the subject identifier (e.g., customer ID)
  ```bash
  bin/magento thedevkitchen:jwt:generate --subject=customer_123
  ```

- `--expiration` or `-e`: Set token expiration time in seconds (default is 3600)
  ```bash
  bin/magento thedevkitchen:jwt:generate --expiration=600
  ```

- `--claim` or `-c`: Add custom claims in format name:value (can be used multiple times)
  ```bash
  bin/magento thedevkitchen:jwt:generate --claim="email:test@example.com" --claim="role:admin"
  ```

**Complex Example**:
```bash
bin/magento thedevkitchen:jwt:generate --subject=customer_123 --expiration=1800 --claim="email:john@example.com" --claim="roles:customer" --claim="store_id:1"
```

This will generate a JWT token with:
- Subject: customer_123
- Expiration: 30 minutes (1800 seconds)
- Custom claims: email, roles, and store_id

The command outputs the complete token, detailed payload information, and expiration details.

## Troubleshooting

### Link Not Appearing

- Verify you're logged in (link only shows for authenticated users)
- Check the module is enabled in admin configuration
- Clear cache and run static content deployment
- Check browser console for JavaScript errors

### Authentication Not Working

- Verify the JWT Secret Key is identical on both domains
- Check both domains have the module installed and enabled
- Ensure RabbitMQ is running and properly configured
- Check Magento logs for errors: `var/log/system.log` and `var/log/exception.log`

### JavaScript Not Loading

If the JavaScript for the module is not loading:
```bash
# Deploy static content again
bin/magento setup:static-content:deploy
# Clear cache
bin/magento cache:clean
# Remove generated static files
rm -rf pub/static/frontend/* var/view_preprocessed/*
```

## Security Considerations

- Always use HTTPS for all domains
- Regularly rotate your JWT Secret Key
- Monitor logs for any unusual authentication patterns
- The JWT token has a short expiration time (5 minutes) for security

## Advanced Configuration

### Custom Styling the Loader

To customize the loader appearance, modify:
```
view/frontend/templates/loader/styles.phtml
```

### Changing Token Expiration Time

To modify the JWT token expiration time (default 5 minutes), edit:
```
Model/TokenGenerator.php
```
And change this line:
```php
$expirationTime = $currentTime + 300; // Token valid for 5 minutes
```

## Support

For questions or support, please contact TheDevKitchen team or open an issue in the GitHub repository.