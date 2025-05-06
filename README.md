# Model Openness Tool

The Model Openness Tool (MOT) is designed to facilitate the evaluation and classification of machine learning models based on the [Model Openness Framework (MOF)](https://lfaidata.foundation/wp-content/uploads/sites/3/2025/01/05_White_paper_MOF_Specification.pdf). This tool provides a comprehensive platform for model producers to assess their models against the 16 components of the MOF, ensuring transparency, reproducibility, and usability. MOT not only evaluates the openness of the license for each component but also ranks the models, helping the community identify models that adhere to the principles of open science.

## Features

* Model Evaluation: Assess machine learning models against the MOF's 16 components.
* License Evaluation: Analyze and validate the openness of licenses used for each model component.
* Model Submission: Enable producers to submit their models for classification and listing via a GitHub Pull Request.
* Ranking and Listing: Display all submitted models along with their rankings and adherence to each MOF class.

## Evaluating a Model

* Prepare your model's artifacts according to the guidelines specified in the MOF.
* Evaluate your model through the 'Evaluate Model' function of the MOT interface, providing details and licenses for your model and its components.
* Receive feedback on the classification and suggestions for achieving higher openness levels.
* At the end of the evaluation you have the option to download the model for addition to the list of models displayed on the MOT by submitting your model file for addition via a Pull Request against [the MOT GitHub repository](https://github.com/lfai/model_openness_tool/).

### Understanding the Model evaluation form

The Model evaluation form is composed of different sections:
* Model details - the identification of the model along some metadata including the model producer, model type, etc.
* Global licenses - this allows you to specify the license(s) that cover the whole model distribution, all of the code components, all of the data components, and all the document components. These are default licenses that can be overriden at the component level in the following section.
* Code components - this allows you to specify which code components are included in the model distribution and for each of them, its license if it is different from the global one with the license file location, along with the component location.
* Data components - this allows you to specify which data components are included in the model distribution and for each of them, its license if it is different from the global one with the license file location, along with the component location.
* Document components - this allows you to specify which document components are included in the model distribution and for each of them, its license if it is different from the global one with the license file location, along with the component location.

While you are encouraged to provide as much information as possible, very little is actually required to be able to get a first evaluation. After submission the result is displayed for you to consult and you have the possibility to go back to the form, add more data or modify what you previously entered, and try again.This provides with an iterative workflow.

When done, you can download the model configuration file which you can then submit for addition to the MOT.

### Understanding the Model evaluation

The MOF classification is based on both completeness and openness. The former depends on which components are included in the model distribution and the latter depends on the openness of the license(s) under which the components are made available. It is therefore crucial to understand which license applies to each component, acknowledging that model distributions can come with a whole set of various licenses applying to different components.

The model evaluation determines whether a component is included, which license applies, and whether it is open as follows:

- Is the component included?
- If yes, is there a license attached to the component? If yes, use that.
- If not, is there a type-specific global license for the component? If yes, use that.
- If not, is there a global license? If yes, use that.
- If not, there is no license.

Then:
- Is the license a type-appropriate open license? If yes, ok.
- If not, is the license an open license? If yes, ok but display warning.
- If not, the license is not open. (no license is not open btw)

We also need to take into account a few special cases:

- If paper is included, the technical report is optional.
- The paper may be replaced by a detailed tech report (note: the easiest in this case is for you to claim on the input form that the paper is included.)
- The evaluation results may be included in the technical report, research paper, or model card (in any of those cases, evaluation results will be considered included.)
- If the model card contains data info, the data card is optional (note: the easiest in this case is for you to claim on the input form that the data card is included.)

## Viewing Model Rankings

Access the MOT's web interface to view a detailed list of all models submitted, their openness classification, and how they rank against each MOF class.

## Contributing new models or changes to existing models using GitHub

Anyone can submit changes to the MOT data through GitHub. New models
can also be submitted for addition this way. Simply submit your new or updated model file (e.g., `<your-model>.yml`) via a Pull Request against [the MOT GitHub repository](https://github.com/lfai/model_openness_tool/).

## Contributing to the MOT

Contributions to the Model Openness Tool are welcome! To contribute to the software, please see the [CONTRIBUTING.md](CONTRIBUTING.md) file.

## License

The Model Openness Tool is open-sourced under the MIT license. See the [LICENSE](LICENSE) file for more details.

## Support

For support, feature requests, or bug reports, please file an issue through the GitHub issue tracker associated with this repository.

## Acknowledgements

This tool was developed in collaboration with the authors of the Model Openness Framework and the Linux Foundation. Special thanks to Matt White, Ibrahim Haddad, Cailean Osborne, Xiao-Yang Liu, Ahmed Abdelmonsef, Sachin Mathew Varghese, and Arnaud J Le Hors for their invaluable input and guidance. The code was primarily written by [Greg MacKenzie](https://gregcube.com/).
