#!/bin/bash

# This script is used to run a simple non-regression test against a local instance of MOT.
# This is the first part, and it simply downloads, using the REST API, the models that are in
# the database into a file that can be checked against later on.
# This should be run before any changes are made. Once changes have been made, simply run the
# second part nonregression_test_check.sh to check that the changes have not broken anything.

# we exclude date fields that are volatile
curl -o - http://127.0.0.1:8888/api/v1/models | jq | grep -v date > test_setup_results.json
