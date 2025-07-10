# Model Test Files
This directory contains dummy model yaml files that have different possible configurations of a model file. In particular the current model files address the following configurations: A full test file with all licenses and components specified, a minimal file with as little information required for a proper model file, model files that exactly meet each class specification, model files with no licenses, model files with only global licenses, model files with only component licenses (includes valid, invalid, and missing licenses), and random test files that have any possible configuration.

## File Naming
The model files are named to match their expected evaluation from the model openness tool. This information includes the class evaluation, the number of components, the number of licenses (global and component), the number of invalid licenses, and the number of type-appropriate licenses. Here is the scheme of a model file name:

`{User Input Name}_C1_{Class 1 Progress Percentage}_C2_{Class 2 Progress Percentage}_{C3}_{Class 3 Progress Percentage}_{Number of Components}C_{Number of Global Licenses}G_{Number of Component Licenses}L_{Number of Valid Licenses}V_{Number of Invalid Licenses}I_{Number of Type-Appropriate Licenses}T.yml`

As an example a model file named `RandomTestFile_C1_33%-C2_45%-C3_50%_7C_2G_7L_5V_2I_1T.yml` would meet 33% of the Class 1 requirements, 45% of the Class 2 requirements, 50% of the Class 3 requirements, and have 7 components with 2 global licenses and 7 component specific licenses of which 5 are valid and 2 are invalid with only 1 type-appropriate license. 

## Test File Generation
This directory additionally contains a script that can be used to generate model files according to user specification or randomly. The script is located in the `Test_Scripts` directory. The `generate-test-files.py` script was used to generate models in this directory that follow the naming scheme above.

Currently there are still a few bugs in the script, so a command line interface has not yet been implemented, however there are example calls in the script in the main function that can be modified to generate the desired test files.

One notable bug is that the type-appropriate license count is not currently being generated correctly, so the files in this directory do not have the correct type-appropriate license counts. This appears to potentially be an issue with how open-data licenses are counted, so it may be an issue with how the model openness tool considers open-data licenses.

Additionally, there is a script called `update_license_yml_files.py` that can be used to update the license files in this directory to the latest version of the model openness tool. This script will update the license files in this directory to match the latest version of the model openness tool, so it should be run before running the `generate-test-files.py` script to ensure that the generated test files are up-to-date with the latest version of the model openness tool. However, the current version of the output yml files from this script are in the `Test_Scripts` so they can be used to generate model files without having to update the yml files first.

The script `update_license_yml_files.py` can be run with the following command: `python update_license_yml_files.py <license_json_file> <mof_license_json_file>`

