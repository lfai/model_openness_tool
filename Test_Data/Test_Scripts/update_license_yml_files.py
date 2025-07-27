#!/usr/bin/env python3

import yaml
import sys
import json

OPEN_DATA_LICENSES = [
    'CC0-1.0',
    'CC-BY-1.0',
    'CC-BY-2.0',
    'CC-BY-2.5',
    'CC-BY-2.5-AU',
    'CC-BY-3.0',
    'CC-BY-3.0-AT',
    'CC-BY-3.0-AU',
    'CC-BY-3.0-DE',
    'CC-BY-3.0-IGO',
    'CC-BY-3.0-NL',
    'CC-BY-3.0-US',
    'CC-BY-4.0',
    'CC-BY-SA-1.0',
    'CC-BY-SA-2.0',
    'CC-BY-SA-2.0-UK',
    'CC-BY-SA-2.1-JP',
    'CC-BY-SA-2.5',
    'CC-BY-SA-3.0',
    'CC-BY-SA-3.0-AT',
    'CC-BY-SA-4.0',
    'CDLA-Permissive-1.0',
    'CDLA-Permissive-2.0',
    'CDLA-Sharing-1.0',
    'ODC-PDDL-1.0',
    'ODC-By-1.0',
    'ODbL-1.0',
    'GFDL-1.3',
    'OGL-Canada-2.0',
    'OGL-UK-2.0',
    'OGL-UK-3.0',
  ]
license_file = sys.argv[1]
mof_license_file = sys.argv[2]
if not license_file:
    print("Usage: python update_license_yml_files.py <license__json_file> <mof_license_json_file>")
    sys.exit(1)
with open(license_file, "r", encoding = 'utf-8') as f:
    licenses = json.load(f)
with open(mof_license_file, "r", encoding = 'utf-8') as f:
    mof_licenses = json.load(f)

license_data = licenses['licenses']
mof_license_data = mof_licenses['licenses']

license_dict = {license['licenseId']: license for license in license_data}
mof_license_dict = {license['licenseId']: license for license in mof_license_data}

valid_license_dict = {}

def filter_license_dict(license_dict):
    return {k: v for k, v in license_dict.items() if v.get('isOsiApproved', False) or v.get('isFsfLibre', False) or k in OPEN_DATA_LICENSES}

valid_license_dict = filter_license_dict(license_dict)
valid_mof_license_dict = filter_license_dict(mof_license_dict)


invalid_license_dict = {k: v for k, v in license_dict.items() if k not in valid_license_dict}

def write_yaml_file(yaml_dict, file_name):
    with open(file_name, 'w') as file:
        yaml.dump(yaml_dict, file, default_flow_style=False, sort_keys=False)
    print(f"YAML file '{file_name}' written")

write_yaml_file(license_dict, 'licenses.yml')
write_yaml_file(mof_license_dict, 'mof-licenses.yml')
write_yaml_file(valid_license_dict, 'valid-licenses.yml')
write_yaml_file(valid_mof_license_dict, 'valid-mof-licenses.yml')
write_yaml_file(invalid_license_dict, 'invalid-licenses.yml')