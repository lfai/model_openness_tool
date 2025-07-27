# Contributing to the MOT

We welcome contributions to the MOT project in the form of additions or changes to the models listed
on the MOT website as well as bug fixes and improvements to the MOT website itself.

Thank you for contributing to the MOT.

## Contributing Models to the MOT

If you'd like to contribute a new model or changes to an existing model via GitHub, please follow
the steps below to ensure your model is validated and accepted.

### Steps to Contribute a Model

1. **Fork the repository**
   - First, fork the main repository to your personal GitHub account. 
   - Clone the forked repository to your local machine for development.

2. **Add your model**
   - Place your model file (`<your-model>.yml`) in the `models` directory of the repository.
   - Ensure your model adheres to the schema located at `schema/mof_schema.json`. (see note below on validation)

4. **Submit a Pull Request**
   - Once your model file passes local validation, commit your changes and push them to your fork.
   - Submit a Pull Request (PR) to the main repository, ensuring the model is in the `models/` directory.
   
5. **Approval process**
   - After submitting your PR, a maintainer will manually review and approve it.
   - Once the PR is merged, the GitHub workflow will automatically validate the model again and publish it, provided it passes validation.

### Validating your model file locally

Before creating a Pull Request, if your model file wasn't generated using the MOT Model evaluation download function, you should validate your model file locally to ensure it conforms to the project's rules.

**Note:** Ensuring that your model file adheres to the schema defined in `schema/mof_schema.json` by doing a local validation before submitting a PR can save time and ensure quicker approval of your contribution.

To do so, follow these steps:

- Ensure you have the following installed and set up in your local environment:

  - **PHP**: Required to run the validation script.
  - **Composer**: Used to manage project dependencies.

- If you don’t already have PHP and Composer installed, you can find installation
instructions on their respective websites:
  - [PHP Installation](https://www.php.net/manual/en/install.php)
  - [Composer Installation](https://getcomposer.org/doc/00-intro.md)

- Once you have these tools installed, proceed with the following command to validate your model file:
   ```
  composer install
  php scripts/validate-model.php models/<your-model>.yml
   ```

  This will check for any issues with your model file before you submit it.
  
  Note: make sure you have created a `.env` file, you can use the below command to create an empty `.env` file.
  ```shell
   touch .env
  ```

## Contributing to the MOT software

To submit bug fixes and improvements to the MOT software please follow these steps:

* Fork the repository on GitHub.
* Create a new branch for your feature or fix.
* Install **PHP** and **Composer** as describe above to test locally your changes.
* Submit a pull request with a detailed description of your changes.

Note that all changes to the code should carry a sign-off.

See the [INSTALL.md](INSTALL.md) file for more information on how to setup your environment to run MOT.

Before submitting any PR, please, verify that your changes do not have any unexpected side effects using the [non-regression tests](Test_Data/README.md#non-regression-tests).

## Legal stuff

To handle the legal aspects of contributions, like many other
open source projects nowadays, we use the [Developer's
Certificate of Origin 1.1 (DCO)](DCO1.1.txt) approach.

We simply ask that when you submit a patch for review, you include a
sign-off statement in the commit message.

Here is an example Signed-off-by line, which indicates that the
submitter accepts the DCO:

   ```
   Signed-off-by: John Doe <john.doe@example.com>
   ```

You can include this automatically when you commit a change to your
local git repository using ``git commit -s``.
