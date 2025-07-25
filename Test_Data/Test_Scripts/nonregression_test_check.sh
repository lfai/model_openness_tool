#!/bin/bash

# This script is used to run a simple non-regression test against a local instance of the MOT.
# This is the second part of the test. This part downloads, using the REST API, the models
# that are in the database into a file and checks that it is identical to the test reference file.
# This only works after the first part, nonregression_test_setup.sh has been run.

if [ ! -f test_setup_results.json ]
then echo "Error: test_setup_results.json not found" && exit 1
fi

# we exclude date fields that are volatile
curl -o - http://127.0.0.1:8888/api/v1/models | jq | grep -v date > results.json

diff -q results.json test_setup_results.json
if [ $? -ne 0 ] ; then
  echo 'Test failed'
  exit 1
fi
echo 'Test passed'
exit 0
