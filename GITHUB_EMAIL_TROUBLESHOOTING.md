# GitHub Email Retrieval Troubleshooting Guide

## Problem: GitHub Login Fails to Retrieve User's Email Address

This is a common issue when integrating GitHub OAuth. Here are the causes and solutions.

## Root Causes

### 1. User's Email is Private on GitHub

**Most Common Cause**: Users can set their email addresses as private in GitHub settings.

**Solution**: Request the `user:email` scope and use the GitHub API to fetch emails.

### 2. Missing OAuth Scope

**Cause**: The OAuth app doesn't request the `user:email` scope.

**Solution**: Ensure the scope is configured in Drupal.

### 3. No Public Email Set

**Cause**: User hasn't set a public email address in their GitHub profile.

**Solution**: Use the GitHub API `/user/emails` endpoint to get all emails.

## Solutions

### Solution 1: Update OAuth Scopes (Required)

1. **Check Current Scopes:**
   ```bash
   drush config:get social_auth_github.settings scopes
   ```

2. **Update Scopes to Include user:email:**
   - Go to: `/admin/config/social-api/social-auth/github`
   - In the "Scopes" field, enter: `repo,user:email`
   - Save configuration

3. **Clear Cache:**
   ```bash
   drush cr
   ```

4. **Users Must Re-authenticate:**
   - Existing users need to revoke and re-authorize the app
   - Go to: https://github.com/settings/applications
   - Find your OAuth app and click "Revoke"
   - Log in again through Drupal

### Solution 2: Update GitHubSubscriber to Fetch Email

The `GitHubSubscriber` needs to fetch the email from GitHub's API if it's not in the initial response.

**File**: `web/modules/mof/src/EventSubscriber/GitHubSubscriber.php`

**Current Code** (lines 25-29):
```php
public function onUserCreated(UserEvent $event) {
  $user = $event->getUser();
  $user_data = $event->getSocialAuthUser()->getAdditionalData();
  $user->setEmail($user_data['resource_owner'][0]['email'])->save();
}
```

**Problem**: This assumes email is in `resource_owner[0]['email']`, which may not exist if the email is private.

**Updated Code**:
```php
public function onUserCreated(UserEvent $event) {
  $user = $event->getUser();
  $social_auth_user = $event->getSocialAuthUser();
  $user_data = $social_auth_user->getAdditionalData();
  
  // Try to get email from resource_owner first
  $email = $user_data['resource_owner'][0]['email'] ?? null;
  
  // If email is null or empty, fetch from GitHub API
  if (empty($email)) {
    $email = $this->fetchPrimaryEmail($social_auth_user->getToken());
  }
  
  // Set email if we found one
  if (!empty($email)) {
    $user->setEmail($email)->save();
  }
}

/**
 * Fetch the user's primary email from GitHub API.
 *
 * @param string $token
 *   The OAuth access token.
 *
 * @return string|null
 *   The primary email address or null if not found.
 */
private function fetchPrimaryEmail(string $token): ?string {
  try {
    $client = \Drupal::httpClient();
    $response = $client->request('GET', 'https://api.github.com/user/emails', [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/vnd.github+json',
      ],
    ]);
    
    $emails = json_decode($response->getBody()->getContents(), TRUE);
    
    // Find the primary email
    foreach ($emails as $email_data) {
      if ($email_data['primary'] && $email_data['verified']) {
        return $email_data['email'];
      }
    }
    
    // If no primary email, return the first verified email
    foreach ($emails as $email_data) {
      if ($email_data['verified']) {
        return $email_data['email'];
      }
    }
  }
  catch (\Exception $e) {
    \Drupal::logger('mof')->error('Failed to fetch email from GitHub: @message', [
      '@message' => $e->getMessage(),
    ]);
  }
  
  return null;
}
```

### Solution 3: Check GitHub User Settings

**For Users**: Ensure email visibility settings are correct:

1. Go to: https://github.com/settings/emails
2. Check "Keep my email addresses private" setting
3. If checked, GitHub won't share your email in the OAuth response
4. The app must use the `/user/emails` API endpoint (Solution 2)

### Solution 4: Verify OAuth App Permissions

**In GitHub OAuth App Settings:**

1. Go to: https://github.com/settings/developers
2. Click on your OAuth app
3. Check "User permissions"
4. Ensure "Email addresses" is set to "Read-only"

## Testing the Fix

### Test 1: Check OAuth Scopes

```bash
# Check configured scopes
drush config:get social_auth_github.settings scopes

# Should output: repo,user:email
```

### Test 2: Test with Private Email User

1. Set your GitHub email to private:
   - Go to: https://github.com/settings/emails
   - Check "Keep my email addresses private"

2. Revoke the OAuth app:
   - Go to: https://github.com/settings/applications
   - Revoke access to your MOT app

3. Log in through Drupal again

4. Check if email was retrieved:
   ```bash
   drush sql:query "SELECT mail FROM users_field_data WHERE uid = YOUR_USER_ID;"
   ```

### Test 3: Check Social Auth Data

```bash
# Check what data is stored
drush sql:query "SELECT additional_data FROM social_auth WHERE plugin_id = 'social_auth_github' LIMIT 1;"
```

The `additional_data` should contain the email information.

## Implementation Steps

### Step 1: Update GitHubSubscriber

Create the updated file with the email fetching logic:

```bash
# Edit the file
nano web/modules/mof/src/EventSubscriber/GitHubSubscriber.php
```

### Step 2: Update OAuth Scopes

```bash
# Via Drush
drush config:set social_auth_github.settings scopes "repo,user:email"

# Or via UI
# Navigate to /admin/config/social-api/social-auth/github
# Set scopes to: repo,user:email
```

### Step 3: Clear Cache

```bash
drush cr
```

### Step 4: Test

1. Revoke existing OAuth authorization
2. Log in again
3. Verify email is retrieved

## Alternative: Use GitHub Username as Email

If email retrieval continues to fail, you can use the GitHub username:

```php
public function onUserCreated(UserEvent $event) {
  $user = $event->getUser();
  $social_auth_user = $event->getSocialAuthUser();
  $user_data = $social_auth_user->getAdditionalData();
  
  // Try to get email
  $email = $user_data['resource_owner'][0]['email'] ?? null;
  
  if (empty($email)) {
    $email = $this->fetchPrimaryEmail($social_auth_user->getToken());
  }
  
  // Fallback to GitHub username + noreply
  if (empty($email)) {
    $username = $user_data['resource_owner'][0]['login'] ?? $user->getAccountName();
    $email = $username . '@users.noreply.github.com';
  }
  
  $user->setEmail($email)->save();
}
```

## Debugging

### Enable Detailed Logging

Add logging to see what data is received:

```php
public function onUserCreated(UserEvent $event) {
  $user = $event->getUser();
  $social_auth_user = $event->getSocialAuthUser();
  $user_data = $social_auth_user->getAdditionalData();
  
  // Log the received data
  \Drupal::logger('mof')->debug('GitHub user data: @data', [
    '@data' => print_r($user_data, TRUE),
  ]);
  
  // ... rest of the code
}
```

Check logs:
```bash
drush watchdog:show --type=mof
```

### Check GitHub API Response

Test the GitHub API directly:

```bash
# Replace YOUR_TOKEN with an actual OAuth token
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/vnd.github+json" \
     https://api.github.com/user/emails
```

Expected response:
```json
[
  {
    "email": "user@example.com",
    "primary": true,
    "verified": true,
    "visibility": "private"
  }
]
```

## Common Error Messages

### "Email address is required"

**Cause**: Drupal requires an email for user creation.

**Solution**: Implement the email fetching logic or use a fallback email.

### "Could not authenticate user"

**Cause**: OAuth flow failed or email couldn't be retrieved.

**Solution**: Check OAuth scopes and implement email fetching.

### "Invalid email address"

**Cause**: The email format is incorrect or null.

**Solution**: Validate email before setting it on the user object.

## Summary Checklist

- [ ] OAuth scopes include `user:email`
- [ ] GitHubSubscriber updated to fetch email from API
- [ ] Drupal cache cleared
- [ ] Users re-authenticated after scope change
- [ ] Email retrieval tested with private email users
- [ ] Logging enabled for debugging
- [ ] Fallback email strategy implemented
- [ ] GitHub API `/user/emails` endpoint accessible

## Additional Resources

- **GitHub OAuth Scopes**: https://docs.github.com/en/developers/apps/building-oauth-apps/scopes-for-oauth-apps
- **GitHub User Emails API**: https://docs.github.com/en/rest/users/emails
- **Drupal Social Auth**: https://www.drupal.org/project/social_auth