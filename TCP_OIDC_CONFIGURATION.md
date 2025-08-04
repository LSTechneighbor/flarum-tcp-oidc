# TCP OIDC Configuration Guide

## Overview

This guide explains how to configure TCP as an OpenID Connect (OIDC) provider for Flarum SSO integration.

## TCP Server Configuration

### Required Environment Variables

Add these to your TCP server's `.env` file:

```env
# OIDC Configuration
OIDC_ENABLED=true
OIDC_CLIENT_ID=your_client_id_here
OIDC_CLIENT_SECRET=your_client_secret_here
OIDC_REDIRECT_URI=https://your-flarum-site.com/auth/linkedin
```

### What Each Variable Does

- **`OIDC_ENABLED`**: Enables the OIDC endpoints on your TCP server
- **`OIDC_CLIENT_ID`**: A unique identifier for your Flarum application (you can choose any string)
- **`OIDC_CLIENT_SECRET`**: A secret key for secure communication (generate a random string)
- **`OIDC_REDIRECT_URI`**: The callback URL where TCP will redirect after authentication

## Flarum Configuration

### Required Settings

In your Flarum admin panel, configure the "TCP" provider with:

1. **TCP Server URL**: `https://your-tcp-server.com`
   - This is your TCP server's base URL
   - Example: `https://tcp.yourdomain.com`

2. **Client ID**: `your_client_id_here`
   - Must match the `OIDC_CLIENT_ID` in your TCP `.env` file
   - Example: `flarum-tcp-oidc`

3. **Client Secret**: `your_client_secret_here`
   - Must match the `OIDC_CLIENT_SECRET` in your TCP `.env` file
   - Example: `your-super-secret-key-123`

### What These Settings Do

- **Client ID**: Identifies your Flarum site to TCP
- **Client Secret**: Proves your Flarum site is authorized to use TCP's OIDC service
- **TCP Server URL**: Tells Flarum where to find TCP's OIDC endpoints

## Step-by-Step Setup

### 1. Configure TCP Server

1. **Add to your TCP `.env` file**:
   ```env
   OIDC_ENABLED=true
   OIDC_CLIENT_ID=flarum-tcp-oidc
   OIDC_CLIENT_SECRET=your-secret-key-here
   OIDC_REDIRECT_URI=https://your-flarum-site.com/auth/linkedin
   ```

2. **Restart your TCP server** to load the new environment variables

### 2. Configure Flarum

1. **Go to Admin → Settings**
2. **Find "TCP" in the OAuth providers section**
3. **Enable TCP**
4. **Enter the configuration**:
   - **TCP Server URL**: `https://your-tcp-server.com`
   - **Client ID**: `flarum-tcp-oidc`
   - **Client Secret**: `your-secret-key-here`

### 3. Test the Connection

1. **Go to your forum**
2. **Click "Log In"**
3. **Click the "TCP" button**
4. **You should be redirected to TCP for authentication**
5. **After logging in, you should be redirected back to Flarum**

## Security Considerations

### Client Secret Generation

Generate a strong client secret:

```bash
# Option 1: Using openssl
openssl rand -base64 32

# Option 2: Using node.js
node -e "console.log(require('crypto').randomBytes(32).toString('base64'))"

# Option 3: Using Python
python3 -c "import secrets; print(secrets.token_urlsafe(32))"
```

### HTTPS Requirements

- **Production**: Both TCP and Flarum must use HTTPS
- **Development**: HTTP is allowed for localhost testing

## Troubleshooting

### Common Issues

1. **"Invalid client" error**:
   - Check that Client ID and Client Secret match between TCP and Flarum
   - Verify the redirect URI is correct

2. **"Redirect URI mismatch" error**:
   - Ensure `OIDC_REDIRECT_URI` in TCP matches Flarum's callback URL
   - The URL should be: `https://your-flarum-site.com/auth/linkedin`

3. **"Connection refused" error**:
   - Verify TCP server is running
   - Check that OIDC endpoints are accessible
   - Test: `curl https://your-tcp-server.com/api/oidc/.well-known/openid_configuration`

### Debug Steps

1. **Check TCP logs** for OIDC-related errors
2. **Check Flarum logs** for authentication errors
3. **Verify environment variables** are loaded correctly
4. **Test OIDC endpoints** manually

## OIDC Endpoints

Your TCP server provides these OIDC endpoints:

- **Discovery**: `https://your-tcp-server.com/api/oidc/.well-known/openid_configuration`
- **Authorization**: `https://your-tcp-server.com/api/oidc/authorize`
- **Token**: `https://your-tcp-server.com/api/oidc/token`
- **UserInfo**: `https://your-tcp-server.com/api/oidc/userinfo`
- **JWKS**: `https://your-tcp-server.com/api/oidc/jwks`

## User Data Mapping

When a user logs in via TCP, Flarum receives:

- **Email**: User's email address
- **Username**: User's screen name
- **Avatar**: User's profile picture (if available)
- **Additional Data**: Organization info, etc.

## Support

If you encounter issues:

1. **Check the logs** on both TCP and Flarum servers
2. **Verify all configuration** matches between systems
3. **Test OIDC endpoints** manually
4. **Ensure HTTPS** is properly configured for production

## Example Configuration

### TCP `.env` file:
```env
OIDC_ENABLED=true
OIDC_CLIENT_ID=flarum-tcp-oidc
OIDC_CLIENT_SECRET=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
OIDC_REDIRECT_URI=https://forum.yourdomain.com/auth/linkedin
```

### Flarum Admin Settings:
- **TCP Server URL**: `https://tcp.yourdomain.com`
- **Client ID**: `flarum-tcp-oidc`
- **Client Secret**: `abc123def456ghi789jkl012mno345pqr678stu901vwx234yz`

## Installation Process for Flarum

### Prerequisites
- Flarum 2.0+ installed
- Composer installed
- SSH access to your Flarum server

### Step 1: Install the Extension

Navigate to your Flarum installation directory and run:

```bash
# Navigate to your Flarum directory
cd /path/to/your/flarum

# Install the extension via Composer
composer require lstechneighbor/flarum-tcp-oidc

# Clear Flarum cache
php flarum cache:clear
```

### Step 2: Enable the Extension

```bash
# Enable the extension
php flarum extension:enable lstechneighbor-tcp-oidc

# Clear cache again after enabling
php flarum cache:clear
```

### Step 3: Configure the Extension

1. **Go to Admin Panel** → **Settings**
2. **Find "TCP" in the OAuth providers section**
3. **Enable TCP provider**
4. **Configure the settings**:
   - **TCP Server URL**: `https://your-tcp-server.com`
   - **Client ID**: `your-client-id-here`
   - **Client Secret**: `your-client-secret-here`

### Step 4: Verify Installation

```bash
# Check if extension is enabled
php flarum extension:list

# Clear cache one final time
php flarum cache:clear
```

### Alternative Installation Methods

#### Method 1: Manual Installation (Development)
```bash
# Clone the repository
git clone https://github.com/LSTechneighbor/flarum-tcp-oidc.git

# Copy to Flarum extensions directory
cp -r flarum-tcp-oidc /path/to/your/flarum/extensions/

# Install dependencies
cd /path/to/your/flarum/extensions/flarum-tcp-oidc
composer install

# Build assets
cd js
npm install
npm run build

# Enable extension
cd /path/to/your/flarum
php flarum extension:enable lstechneighbor-tcp-oidc
php flarum cache:clear
```

#### Method 2: Using Flarum CLI (if available)
```bash
# Install via Flarum CLI
flarum extension:install lstechneighbor/flarum-tcp-oidc

# Clear cache
php flarum cache:clear
```

### Troubleshooting Installation

#### Common Issues:

1. **Composer Memory Limit**:
   ```bash
   # Increase PHP memory limit
   COMPOSER_MEMORY_LIMIT=-1 composer require lstechneighbor/flarum-tcp-oidc
   ```

2. **Permission Issues**:
   ```bash
   # Fix directory permissions
   chmod -R 755 /path/to/your/flarum
   chown -R www-data:www-data /path/to/your/flarum
   ```

3. **Extension Not Found**:
   ```bash
   # Update Composer repositories
   composer update
   php flarum cache:clear
   ```

4. **Assets Not Building**:
   ```bash
   # Rebuild assets manually
   cd extensions/flarum-tcp-oidc/js
   npm install
   npm run build
   cd ../../..
   php flarum cache:clear
   ```

### Post-Installation Checklist

- [ ] Extension appears in Admin → Extensions
- [ ] TCP provider is visible in Admin → Settings
- [ ] TCP login button appears on login page
- [ ] Configuration fields are accessible
- [ ] No errors in Flarum logs

### Uninstall Commands

If you need to remove the extension:

```bash
# Disable the extension
php flarum extension:disable lstechneighbor-tcp-oidc

# Remove via Composer
composer remove lstechneighbor/flarum-tcp-oidc

# Clear cache
php flarum cache:clear
```

