# Deployment Process Documentation

## Overview

This document provides an overview of the deployment process for the MOT app,
including details about the test and deploy workflows,
GitHub environment secrets, and deployment triggers.

To install and run MOT locally, see [INSTALL.md](INSTALL.md).

## Workflows

### 1. Test workflow

- **Trigger:** 
  - Automatically runs on a push to the `main` branch.
  - Can also be triggered manually using `workflow_dispatch`.
  
- **Purpose:**
  - This workflow sets up a basic environment, installs the MOT, and runs some Drupal unit tests.

- **Outcome:**
  - If the tests pass, the Deploy workflow is triggered automatically.
  - If tests fail, deployment is halted.

### 2. Deploy workflow

- **Trigger:**
  - Automatically triggered by the success of the Tests workflow.
  - Can also be triggered manually using `workflow_dispatch`.

- **Environments:** 
  The deploy workflow operates in two environments:
  - **Stage**
  - **Production**

- **GitHub Secrets:**
  The following secrets are required to deploy to either Stage or Production environments:

  | Secret Name   | Description                                  |
  |---------------|----------------------------------------------|
  | `DEPLOY_USER` | The username with access to deploy the site. |
  | `DEPLOY_HOST` | The server hostname (e.g. `mot.isitopen.ai`). |
  | `DEPLOY_PATH` | The path to the repository on the server (e.g. `/var/www/html`). |
  | `DEPLOY_KEY`  | The SSH private key used for deployment.       |


### Manual Invocation

The Test and Deploy workflows can be invoked manually using the
`workflow_dispatch` event. This can be useful when you need to deploy without
going through the tests process or when deploying to the stage environment.

---

## Notes

- Ensure all necessary GitHub secrets are configured in the repository environment before deploying.
- The Deploy workflow is dependent on the success of the Tests workflow unless manually triggered.
- `DEPLOY_USER` and `DEPLOY_KEY` must have the appropriate permissions to access the server.

---

**Last updated:** October 4, 2024

