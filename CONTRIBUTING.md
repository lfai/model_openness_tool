# Contributing to the MOT

We welcome contributions to the MOT project in the form of additions or changes to the models listed
on the MOT website as well as bug fixes and improvements to the MOT website itself.

Thank you for contributing to the MOT.

## Contributing Models to the MOT

If you'd like to contribute a new model or changes to an existing model via GitHub, please follow
the steps below to ensure your model is validated and accepted.

### Prerequisites

Before you start contributing, ensure you have the following installed and
set up in your local environment:

- **PHP**: Required to run the validation script.
- **Composer**: Used to manage project dependencies.

If you don’t already have PHP and Composer installed, you can find installation
instructions on their respective websites:
- [PHP Installation](https://www.php.net/manual/en/install.php)
- [Composer Installation](https://getcomposer.org/doc/00-intro.md)

Once you have these tools installed, proceed with the following steps.

### Steps to Contribute a Model

1. **Fork the repository**
   - First, fork the main repository to your personal GitHub account. 
   - Clone the forked repository to your local machine for development.

2. **Add your model**
   - Place your model file (`<your-model>.yml`) in the `models` directory of the repository.
   - Ensure your model adheres to the schema located at `schema/mof_schema.json`.

3. **Validate your model locally**
   - Before creating a Pull Request, validate your model locally to ensure it conforms to the project's rules.
   - Run the following command to validate your model:
     ```
		 composer install
     php scripts/validate-model.php models/<your-model>.yml
     ```
   - This will check for any issues with your model before you submit it.

4. **Submit a pull request**
   - Once your model passes local validation, commit your changes and push them to your fork.
   - Submit a Pull Request (PR) to the main repository, ensuring the model is in the `models/` directory.
   
5. **Approval process**
   - After submitting your PR, a maintainer will manually review and approve it.
   - Once the PR is merged, the GitHub workflow will automatically validate the model again and publish it, provided it passes validation.

### Additional Notes

- Ensure that your model adheres to the schema defined in `schema/mof_schema.json`.
- Running local validation before submitting a PR can save time and ensure quicker approval of your contribution.

## Contributing to the MOT software

To submit bug fixes and improvements to the MOT software please follow these steps:

* Fork the repository on GitHub.
* Create a new branch for your feature or fix.
* Submit a pull request with a detailed description of your changes.

Note that all changes to the code should carry a sign-off.

See the [INSTALL.md](INSTALL.md) file for more information on how to setup your environment to run MOT.
