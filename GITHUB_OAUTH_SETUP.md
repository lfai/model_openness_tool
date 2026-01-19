# GitHub OAuth Configuration Guide

This guide explains how to configure the `social_auth_github` module with valid OAuth credentials for the Model Openness Tool.

## Prerequisites

- Drupal site with `social_auth_github` module installed
- GitHub account with admin access to create OAuth Apps
- Access to Drupal admin interface

## Step 1: Create a GitHub OAuth App

1. **Go to GitHub Developer Settings**
   - Navigate to: https://github.com/settings/developers
   - Or: GitHub → Settings → Developer settings → OAuth Apps

2. **Click "New OAuth App"**

3. **Fill in the Application Details:**
   - **Application name**: `Model Openness Tool` (or your preferred name)
   - **Homepage URL**: `https://mot.isitopen.ai` (or your site URL)
   - **Application description**: `OAuth integration for Model Openness Tool`
   - **Authorization callback URL**: `https://mot.isitopen.ai/user/login/github/callback`
     - Replace `mot.isitopen.ai` with your actual domain
     - For local development: `http://localhost/user/login/github/callback`
     - The path `/user/login/github/callback` is standard for social_auth_github

4. **Click "Register application"**

5. **Note Your Credentials:**
   - **Client ID**: Copy this value (e.g., `Iv1.a1b2c3d4e5f6g7h8`)
   - **Client Secret**: Click "Generate a new client secret" and copy it immediately
     - ⚠️ **Important**: Save this secret securely - you won't be able to see it again!

## Step 2: Configure OAuth Scopes

The PR submission feature requires specific GitHub API scopes:

1. **In your GitHub OAuth App settings**, scroll to "Permissions"
2. **Required scopes for PR submission:**
   - `repo` - Full control of private repositories (includes public repos)
     - This is needed to create forks, branches, commits, and pull requests
   - `user:email` - Access user email addresses (automatically included by social_auth_github)

**Note**: The `repo` scope is automatically requested when users authenticate. You don't need to configure this in GitHub - it's set in the Drupal module configuration.

## Step 3: Configure Drupal Module

### Option A: Via Drupal Admin UI

1. **Navigate to Social Auth GitHub Settings:**
   - Go to: `/admin/config/social-api/social-auth/github`
   - Or: Configuration → Social API → Social Auth → GitHub

2. **Enter Your OAuth Credentials:**
   - **Client ID**: Paste the Client ID from GitHub
   - **Client Secret**: Paste the Client Secret from GitHub

3. **Configure Scopes:**
   - **Scopes**: Enter `repo,user:email`
     - The `repo` scope is required for PR submission
     - The `user:email` scope is required for user authentication

4. **Save Configuration**

### Option B: Via Drush/Configuration

If you prefer to configure via configuration files:

1. **Export current configuration:**
   ```bash
   drush config:get social_auth_github.settings
   ```

2. **Edit the configuration:**
   ```bash
   drush config:edit social_auth_github.settings
   ```

3. **Set the values:**
   ```yaml
   client_id: 'YOUR_CLIENT_ID'
   client_secret: 'YOUR_CLIENT_SECRET'
   scopes: 'repo,user:email'
   ```

4. **Import the configuration:**
   ```bash
   drush config:import
   ```

## Step 4: Test the Configuration

### Test Authentication

1. **Clear Drupal cache:**
   ```bash
   drush cr
   ```

2. **Test Login:**
   - Log out of Drupal
   - Go to `/user/login`
   - Click "Log in with GitHub" (or navigate to `/user/login/github`)
   - You should be redirected to GitHub
   - Authorize the application
   - You should be redirected back to Drupal and logged in

3. **Verify Token Storage:**
   ```bash
   drush sql:query "SELECT * FROM social_auth WHERE plugin_id = 'social_auth_github' LIMIT 1;"
   ```
   - You should see a record with an encrypted token

### Test PR Submission

1. **Navigate to Model Evaluation:**
   - Go to `/model/evaluate`
   - Fill out the form with test data
   - Click "Evaluate"

2. **Verify PR Button:**
   - You should see a "Submit Pull Request" button
   - If not logged in, you should see "Login with GitHub to Submit PR"

3. **Test PR Creation:**
   - Click "Submit Pull Request"
   - Wait for the process to complete
   - You should receive a success message with a PR link

## Troubleshooting

### Issue: "Callback URL mismatch"

**Cause**: The callback URL in GitHub doesn't match your Drupal site URL.

**Solution**: 
- Verify the callback URL in GitHub matches: `https://YOUR_DOMAIN/user/login/github/callback`
- Check for trailing slashes or http vs https mismatches

### Issue: "Invalid client_id or client_secret"

**Cause**: Incorrect credentials entered in Drupal.

**Solution**:
- Double-check the Client ID and Client Secret in GitHub
- Regenerate the Client Secret if needed
- Re-enter the credentials in Drupal
- Clear Drupal cache: `drush cr`

### Issue: "Insufficient permissions" when creating PR

**Cause**: The OAuth token doesn't have the `repo` scope.

**Solution**:
1. Go to `/admin/config/social-api/social-auth/github`
2. Ensure "Scopes" field contains: `repo,user:email`
3. Save configuration
4. Users need to re-authenticate to get the new scopes
5. Go to GitHub → Settings → Applications → Authorized OAuth Apps
6. Revoke access to the MOT app
7. Log in again through Drupal

### Issue: "Token not found" error

**Cause**: User's OAuth token is not stored or has expired.

**Solution**:
- Log out and log back in with GitHub
- Check the `social_auth` table for the user's record
- Verify the token is encrypted and stored

### Issue: Rate limiting errors

**Cause**: GitHub API rate limits exceeded.

**Solution**:
- Authenticated requests have a limit of 5,000 requests/hour
- Check current rate limit: `curl -H "Authorization: Bearer YOUR_TOKEN" https://api.github.com/rate_limit`
- Wait for the rate limit to reset (shown in the response)

## Security Best Practices

1. **Keep Client Secret Secure:**
   - Never commit the Client Secret to version control
   - Use environment variables or secure configuration management
   - Rotate the secret periodically

2. **Use HTTPS:**
   - Always use HTTPS for production sites
   - GitHub requires HTTPS for OAuth callbacks in production

3. **Limit Scope Access:**
   - Only request the scopes you need
   - The `repo` scope is powerful - ensure users understand what they're authorizing

4. **Monitor OAuth App:**
   - Regularly check the OAuth app's activity in GitHub
   - Review authorized users
   - Monitor for suspicious activity

## Environment-Specific Configuration

### Local Development

For local development, you may need a separate OAuth app:

1. Create a new OAuth App in GitHub for development
2. Set callback URL to: `http://localhost/user/login/github/callback`
3. Use different Client ID/Secret for local environment
4. Consider using `.env` files to manage credentials

### Staging Environment

1. Create a separate OAuth App for staging
2. Set callback URL to your staging domain
3. Use separate credentials from production

### Production Environment

1. Use production OAuth App credentials
2. Ensure HTTPS is enabled
3. Monitor logs for authentication issues
4. Set up alerts for OAuth failures

## Additional Resources

- **Drupal Social Auth GitHub Module**: https://www.drupal.org/project/social_auth_github
- **GitHub OAuth Documentation**: https://docs.github.com/en/developers/apps/building-oauth-apps
- **GitHub API Scopes**: https://docs.github.com/en/developers/apps/building-oauth-apps/scopes-for-oauth-apps
- **Drupal Social API**: https://www.drupal.org/project/social_api

## Support

If you encounter issues:

1. Check Drupal logs: `drush watchdog:show`
2. Check PHP error logs
3. Verify GitHub OAuth app settings
4. Test with a fresh browser session (incognito mode)
5. Review the `social_auth` database table for stored tokens

## Configuration Checklist

- [ ] GitHub OAuth App created
- [ ] Client ID and Client Secret copied
- [ ] Callback URL matches Drupal site
- [ ] Scopes include `repo,user:email`
- [ ] Credentials entered in Drupal
- [ ] Configuration saved
- [ ] Drupal cache cleared
- [ ] Test authentication successful
- [ ] Token stored in database
- [ ] PR submission button visible
- [ ] Test PR creation successful