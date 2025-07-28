#!/bin/bash

# This script is used to run a simple test against the MOF.
# It loads the model test files, and downloads the result using the REST API.
# It then checks that the result matches the reference file.
# This only works against an empty database (fresh MOT install).

rm -rf Test_Data/.processed
vendor/bin/drush scr scripts/sync_models.php ../Test_Data

# we exclude date fields that are volatile
curl -o - http://127.0.0.1:8888/api/v1/models | jq | grep -v date > results.json

diff -q results.json Test_Data/expected_results.json
if [ $? -ne 0 ] ; then
  echo 'Test failed'
  exit 1
fi
echo 'Test passed'
exit 0
