# Testing the GitHub Pull Request Submission Feature

This document provides instructions for testing the new GitHub Pull Request submission feature in the Model Openness Tool.

## Prerequisites

1. **Drupal Installation**: Ensure the MOT is properly installed and running
2. **GitHub Account**: You need a GitHub account to test the PR submission
3. **GitHub OAuth Configuration**: The `social_auth_github` module must be configured with valid OAuth credentials
4. **Clear Cache**: After implementing the changes, clear Drupal cache:
   ```bash
   drush cr
   ```

## Test Scenarios

### Scenario 1: Unauthenticated User Flow

**Objective**: Verify that unauthenticated users are prompted to login

**Steps**:
1. Open the MOT in an incognito/private browser window
2. Navigate to `/model/evaluate`
3. Fill out the model evaluation form with test data
4. Click "Evaluate"
5. On the results page, verify you see a "Login with GitHub to Submit PR" button
6. Click the button
7. **Expected**: You should be redirected to GitHub OAuth login
8. After logging in, **Expected**: You should be redirected back to the evaluation form
9. Re-evaluate the model
10. **Expected**: Now you should see a "Submit Pull Request" button instead

### Scenario 2: Authenticated User - Successful PR Submission

**Objective**: Verify successful PR creation for authenticated users

**Steps**:
1. Login to MOT with your GitHub account
2. Navigate to `/model/evaluate`
3. Fill out the model evaluation form with valid test data:
   - Model name: `Test-Model-[timestamp]`
   - Organization: `Test Organization`
   - Add at least one component with a valid license
4. Click "Evaluate"
5. On the results page, verify you see a "Submit Pull Request" button
6. Click the "Submit Pull Request" button
7. **Expected**: 
   - Button text changes to "Submitting..."
   - After a few seconds, a success message appears
   - A link to the created PR is displayed
   - The button is hidden after successful submission
8. Click the PR link
9. **Expected**: GitHub opens showing your newly created pull request
10. Verify the PR contains:
    - Title: "Add model: Test-Model-[timestamp]"
    - Body with model details and MOF classification
    - A new YAML file in the `models/` directory

### Scenario 3: Error Handling - No Session Data

**Objective**: Verify error handling when session data is missing

**Steps**:
1. Login to MOT with your GitHub account
2. Navigate directly to `/model/evaluate/submit-pr` (without evaluating a model)
3. **Expected**: JSON response with error: "No model data found. Please evaluate a model first."

### Scenario 4: Error Handling - GitHub API Errors

**Objective**: Verify error handling for GitHub API failures

**Steps**:
1. Login to MOT with your GitHub account
2. Evaluate a model
3. If possible, temporarily revoke the GitHub OAuth token or simulate an API error
4. Click "Submit Pull Request"
5. **Expected**: Error message displayed to the user with details

### Scenario 5: Fork Creation for First-Time Contributors

**Objective**: Verify fork is created for users who haven't forked the repository

**Steps**:
1. Use a GitHub account that has NOT forked `lfai/model_openness_tool`
2. Login and evaluate a model
3. Click "Submit Pull Request"
4. **Expected**: 
   - System creates a fork automatically
   - PR is created from your fork to the upstream repository
5. Check your GitHub account
6. **Expected**: You now have a fork of `lfai/model_openness_tool`

### Scenario 6: Existing Fork

**Objective**: Verify PR submission works with existing forks

**Steps**:
1. Use a GitHub account that already has a fork of `lfai/model_openness_tool`
2. Login and evaluate a model
3. Click "Submit Pull Request"
4. **Expected**: 
   - System uses your existing fork
   - Creates a new branch with timestamp
   - PR is created successfully

## Verification Checklist

After testing, verify the following:

- [ ] Unauthenticated users see login button
- [ ] Login redirects back to evaluation form
- [ ] Authenticated users see PR submission button
- [ ] Button shows loading state during submission
- [ ] Success message displays with PR link
- [ ] Error messages are clear and helpful
- [ ] Fork is created if needed
- [ ] Branch name is unique (includes timestamp)
- [ ] YAML file is correctly placed in `models/` directory
- [ ] PR title and body are properly formatted
- [ ] PR includes MOF classification status
- [ ] Multiple submissions create separate PRs
- [ ] JavaScript handles errors gracefully

## Common Issues and Solutions

### Issue: "GitHub access token not available"
**Solution**: Ensure the user is logged in with GitHub and the OAuth token is properly stored in the `social_auth` table.

### Issue: "Failed to create fork"
**Solution**: Check GitHub API rate limits and ensure the OAuth token has the necessary permissions (repo scope).

### Issue: "Branch already exists"
**Solution**: The branch name includes a timestamp, so this should be rare. If it occurs, the system should handle it gracefully.

### Issue: JavaScript not loading
**Solution**: Clear Drupal cache (`drush cr`) and verify the library is properly registered in `mof.libraries.yml`.

## Database Verification

To verify the GitHub token is stored correctly:

```sql
SELECT * FROM social_auth WHERE plugin_id = 'social_auth_github' AND user_id = [YOUR_USER_ID];
```

The `token` field should contain an encrypted token value.

## Logs to Check

Monitor these logs during testing:

1. **Drupal Watchdog**: Check for MOF-related errors
   ```bash
   drush watchdog:show --type=mof
   ```

2. **Browser Console**: Check for JavaScript errors

3. **Network Tab**: Monitor the AJAX request to `/model/evaluate/submit-pr`

## Clean Up After Testing

After testing, you may want to:

1. Close or delete test PRs on GitHub
2. Delete test branches from your fork
3. Clear test model data from the session

## Notes

- The PR submission uses the GitHub REST API v3
- Rate limits apply (5000 requests/hour for authenticated users)
- The OAuth token requires `repo` scope for creating PRs
- Fork creation may take a few seconds on GitHub's side