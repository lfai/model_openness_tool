#!/usr/bin/env python3

import yaml
import sys
import subprocess
import json
import random
from collections import OrderedDict

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

licenses = yaml.load(open('licenses.yml', 'r'), Loader=yaml.FullLoader)
mof_licenses = yaml.load(open('valid-mof-licenses.yml', 'r'), Loader=yaml.FullLoader)
components = yaml.load(open('Components.yml', 'r'), Loader=yaml.FullLoader)
valid_licenses = yaml.load(open('valid-licenses.yml', 'r'), Loader=yaml.FullLoader)
invalid_licenses = yaml.load(open('invalid-licenses.yml', 'r'), Loader=yaml.FullLoader)



def default_yaml_info():
    default_dict = {"framework" : {"name" : 'Model Openness Framework', "version" : '1.0' , 'date' : '2024-12-15'}, 
                    "release" : {"name" : '', "version" : '', "date" : '2025-06-12', "type" : '', "architecture" : '', 
                                 "origin" : '', "producer" : '', "contact" : '', "repository" : '', "huggingface" : '', 
                                 "license" : {}}}
    return default_dict

def set_release_info(yaml_dict, name, version, date, type, architecture, origin, producer, contact, repository, huggingface, component_flag=0,
                     global_license_flag=0, component_license_flag=0, valid_license_flag=0, invalid_license_flag=0, type_appropriate_license_flag=0, Classification_Str=''):
    yaml_dict['release']['name'] = get_meaningful_name(name, component_flag, global_license_flag, 
                                component_license_flag, valid_license_flag, invalid_license_flag, 
                                type_appropriate_license_flag, Classification_Str)
    yaml_dict['release']['version'] = version
    yaml_dict['release']['date'] = date
    yaml_dict['release']['type'] = type
    yaml_dict['release']['architecture'] = architecture
    yaml_dict['release']['origin'] = origin
    yaml_dict['release']['producer'] = producer
    yaml_dict['release']['contact'] = contact
    yaml_dict['release']['repository'] = repository
    yaml_dict['release']['huggingface'] = huggingface
    return yaml_dict['release']['name'], yaml_dict

def get_meaningful_name(test_str, component_flag=0, global_license_flag=0, component_license_flag=0, 
                        valid_license_flag=0, invalid_license_flag=0, type_appropriate_license_flag=0, Classification_Str=''):
    return f"{test_str}_{Classification_Str}_{component_flag}C_{global_license_flag}G_{component_license_flag}L_{valid_license_flag}V_{invalid_license_flag}I_{type_appropriate_license_flag}T"

def write_yaml_file(yaml_dict, file_name):
    with open(file_name, 'w') as file:
        yaml.dump(yaml_dict, file, default_flow_style=False, sort_keys=False)
    print(f"YAML file '{file_name}' written successfully.")

def set_global_license(yaml_dict, license_type, license_dict, licenseId, license_path=''):
    yaml_dict['release']['license'][license_type] = {"name": licenseId, "path": license_path}
    return yaml_dict

def set_component_info(yaml_dict, component_id, component_dict, license_dict, component_path=None, license=None, license_path=None):
    if 'components' not in yaml_dict['release']:
        yaml_dict['release']['components'] = []
    component = {"name": component_dict[component_id]['name'],
        "description": component_dict[component_id]['description']}
    if component_path:
        component['component_path'] = component_path
    if license:
        component['license'] = license
    if license_path:
        component['license_path'] = license_path
    yaml_dict['release']['components'].append(component)
    return yaml_dict

def get_valid_license_from_component(valid_license_dict, mof_license_dict, component_dict, component_id, type_appropriate=False):
    if type_appropriate:
        contentType = component_dict[component_id]['contentType']
        if contentType == 'data':
            return random.choice(OPEN_DATA_LICENSES)
        type_appropriate_licenses = [licenseId for licenseId,license in mof_license_dict.items() if license['ContentType'] == contentType]
        return random.choice(type_appropriate_licenses)
    else:
        return random.choice(list(valid_license_dict.keys()))


def get_invalid_license_from_component(invalid_license_dict):
    return random.choice(list(invalid_license_dict.keys()))

def get_random_component_ids(num_components, component_dict, component_ids):
    available_components = [comp_id for comp_id in component_dict.keys() if comp_id not in component_ids]
    return random.sample(available_components, num_components)

types = ['distribution', 'code', 'data', 'document']
def get_random_global_license(global_license_types, mof_license_dict):
    available_license_types = [contentType for contentType in types if contentType not in global_license_types]
    random_type = random.choice(available_license_types)
    if random_type == 'distribution':
        type_appropriate_licenses = list(mof_license_dict.keys())
    elif random_type == 'data':
        type_appropriate_licenses = OPEN_DATA_LICENSES
    else:
        type_appropriate_licenses = [licenseId for licenseId,license in mof_license_dict.items() if license['ContentType'] == random_type]
    return (random_type, random.choice(type_appropriate_licenses))




Class_1 = [0,1,2,3,4,6,7,8,9,12,13,14,15,16]

#Doesn't Include Supporting Library and Tools to match expected evaluation on website but documentation indicates
#that supporting libraries and tools should be included in Class 2
Class_2T = [0,6,14,16,12,13,2,3,4,9]
Class_2R = [0,6,15,16,12,13,2,3,4,9]
Class_3T = [0,6,14,16,12,13]
Class_3R = [0,6,15,16,12,13]

def calculate_model_classification(component_ids):
    Classification_Str = ''
    if all(component_id in component_ids for component_id in Class_1):
        Classification_Str += 'C1_100%'
    else:
        num_class_1_components = sum(1 for component_id in component_ids if component_id in Class_1)

     #Since The tech report MAY be omitted if a research paper is provided which
     #means that the number of components in Class 1 may be 13 instead of 14
        if 15 in component_ids:
            class_1_percentage = (num_class_1_components / len(Class_1) - 1) * 100
        else:
            class_1_percentage = (num_class_1_components / len(Class_1)) * 100
        if class_1_percentage > 100:
            class_1_percentage = 100
        Classification_Str += f'C1_{class_1_percentage:.0f}%'
    if all(component_id in component_ids for component_id in Class_2T) or all(component_id in component_ids for component_id in Class_2R):
        Classification_Str += '-C2_100%'
    else:
        num_class_2_components = sum(1 for component_id in component_ids if component_id in Class_2T or component_id in Class_2R)
        if 15 in component_ids and 14 in component_ids:
            num_class_2_components -= 1
        class_2_percentage = (num_class_2_components / len(Class_2T)) * 100
        Classification_Str += f'-C2_{class_2_percentage:.0f}%'
    if all(component_id in component_ids for component_id in Class_3T) or all(component_id in component_ids for component_id in Class_3R):
        Classification_Str += '-C3_100%'
    else:
        num_class_3_components = sum(1 for component_id in component_ids if component_id in Class_3T or component_id in Class_3R)
        if 15 in component_ids and 14 in component_ids:
            num_class_3_components -= 1
        class_3_percentage = (num_class_3_components / len(Class_3T)) * 100
        Classification_Str += f'-C3_{class_3_percentage:.0f}%'
    return Classification_Str

def set_flags(num_components, num_global_licenses, num_component_licenses, num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses):
    component_flag = num_components
    global_license_flag = num_global_licenses
    component_license_flag = num_component_licenses 
    valid_license_flag = num_valid_licenses 
    invalid_license_flag = num_invalid_licenses 
    type_appropriate_license_flag = num_type_appropriate_licenses
    return component_flag, global_license_flag, component_license_flag, valid_license_flag, invalid_license_flag, type_appropriate_license_flag

global_license_type_to_component_id_dict = {'distribution' : [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16],
                                            'code' : [0,1,2,3,4,5],
                                            'data' : [6,7,8,9,10],
                                            'document' : [11,12,13,14,15,16]}

def add_valid_component_ids_from_global_licenses(valid_component_ids, licensed_component_ids, global_license_types, component_ids):
    component_ids_to_add = set(component_ids) - set(licensed_component_ids)
    extra_components = []
    additional_valid_id_count = 0
    for component_id in component_ids_to_add:
        for license_type in global_license_types:
            if component_id in global_license_type_to_component_id_dict[license_type]:
                additional_valid_id_count += 1
                extra_components.append(component_id)
                break
    return extra_components, additional_valid_id_count

def generate_test_file(num_components, num_global_licenses, num_component_licenses, num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses, 
                       component_ids=[], license_ids = [], global_license_types = [], global_license_ids = [], component_paths = [], global_license_paths = [], license_paths = [],
                       name = "Test", version = "Test10B", date= "2025-06-17", type = "multimodal", architecture="RNN", origin="Pre-Test",
                       producer="Test2", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path="./"):
    yaml_info = default_yaml_info()
    component_flag, global_license_flag, component_license_flag, valid_license_flag, invalid_license_flag, type_appropriate_flag = 0,0,0,0,0,0
    if num_global_licenses > 0:
        if num_global_licenses > len(global_license_types):
            for _ in range(num_global_licenses - len(global_license_types)):
                random_type, global_license_id = get_random_global_license(global_license_types, mof_licenses)
                global_license_ids.append(global_license_id)
                global_license_types.append(random_type)
        num_global_licenses = len(global_license_ids)
    if num_components > 0:
        if num_components > len(component_ids):
            extra_component_ids = get_random_component_ids(num_components - len(component_ids), components, component_ids)
            component_ids += extra_component_ids
        num_components = len(component_ids)
    if num_component_licenses > 0:
        if num_component_licenses > len(license_ids):
            count_valid_license = sum(1 for license_id in license_ids if license_id in valid_licenses)
            count_invalid_license = sum(1 for license_id in license_ids if license_id in invalid_licenses)
            count_type_appropriate_license = sum(1 for license_id, component_id in zip(license_ids, component_ids) if license_id in mof_licenses
                                            and mof_licenses[license_id]['contentType'] == components[component_id]['contentType'])
            for i in range(len(license_ids), num_component_licenses):
                if count_type_appropriate_license < num_type_appropriate_licenses:
                    license_id = get_valid_license_from_component(valid_licenses, mof_licenses, components, component_ids[i], type_appropriate=True)
                    count_type_appropriate_license += 1
                    count_valid_license += 1
                elif count_valid_license < num_valid_licenses:
                    license_id = get_valid_license_from_component(valid_licenses, mof_licenses, components, component_ids[i])
                    count_valid_license += 1
                elif count_invalid_license < num_invalid_licenses:
                    license_id = get_invalid_license_from_component(invalid_licenses)
                    count_invalid_license += 1
                else:
                    print("Warning: Input licenses are not sufficient to generate the required number of component licenses.")
                    print("Generating random licenses instead.")
                    license_id = random.choice(list(licenses.keys()))
                    if license_id in mof_licenses and mof_licenses[license_id]['contentType'] == components[component_ids[i]]['contentType']:
                        count_type_appropriate_license += 1
                    elif license_id in valid_licenses:
                        count_valid_license += 1
                    else:
                        count_invalid_license += 1
                license_ids.append(license_id)
    component_flag, global_license_flag, component_license_flag, valid_license_flag, invalid_license_flag, type_appropriate_flag = set_flags(num_components, num_global_licenses, 
                                                                                                            num_component_licenses, num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses)
    global_license_type_to_id_dict = {license_type: license_id for license_type, license_id in zip(global_license_types, global_license_ids)}
    if len(global_license_paths) < len(global_license_types):
        global_license_paths += [''] * (len(global_license_types) - len(global_license_paths))
    global_license_type_to_path_dict = {license_type: license_path for license_type, license_path in zip(global_license_types, global_license_paths)}
    type_order = ['distribution', 'code', 'data', 'document']
    global_license_type_to_id_dict = OrderedDict(
        (k, global_license_type_to_id_dict[k])
        for k in type_order if k in global_license_type_to_id_dict
    )
    global_license_type_to_path_dict = OrderedDict(
        (k, global_license_type_to_path_dict[k])
        for k in type_order if k in global_license_type_to_path_dict
    )
    global_license_types = sorted(global_license_types, key=lambda x: type_order.index(x))   

    for contentType in global_license_types:
        yaml_info = set_global_license(yaml_info, contentType, licenses, global_license_type_to_id_dict[contentType], license_path=global_license_type_to_path_dict[contentType])
    
    if len(component_paths) < len(component_ids):
        component_paths += [None] * (len(component_ids) - len(component_paths))
    if len(license_paths) < len(component_ids):
        license_paths += [None] * (len(component_ids) - len(license_paths))
    if len(license_ids) < len(component_ids):
        license_ids += [None] * (len(component_ids) - len(license_ids))
    combined_list = list(zip(component_ids, license_ids, component_paths, license_paths))
    combined_list.sort(key = lambda x: x[0]) # Sort by component_id
    if num_components > 0:
        component_ids, license_ids, component_paths, license_paths = map(list, zip(*combined_list))
    else:
        component_ids, license_ids, component_paths, license_paths = [], [], [], []
    for i in range(num_components):
        yaml_info = set_component_info(yaml_info, component_ids[i], components, licenses, 
                                       component_path=component_paths[i], license=license_ids[i], license_path=license_paths[i])
    
    valid_component_ids = [component_id for component_id,license_id in zip(component_ids,license_ids) if license_id in valid_licenses]
    licensed_component_ids = [component_id for component_id,license_id in zip(component_ids,license_ids) if license_id in licenses]
    extra_components, additional_valid_id_count = add_valid_component_ids_from_global_licenses(valid_component_ids, licensed_component_ids, global_license_types, component_ids)
    valid_component_ids += extra_components
    valid_license_flag += additional_valid_id_count
    type_appropriate_flag += additional_valid_id_count
    Classification_Str = calculate_model_classification(valid_component_ids)
    file_name, yaml_info = set_release_info(yaml_info, name, version, date, type, architecture, origin, producer, contact, repository, huggingface,
                                 component_flag=component_flag,
                                 global_license_flag=global_license_flag, component_license_flag=component_license_flag,
                                 valid_license_flag=valid_license_flag, invalid_license_flag=invalid_license_flag,
                                 type_appropriate_license_flag=type_appropriate_flag, Classification_Str=Classification_Str)
    write_yaml_file(yaml_info, f"{save_path}{file_name}.yml")                                                                                                                  
    
                

def main():

    global_license_paths = [f'https://example.com/global_license_{i}' for i in range(4)]
    component_paths = [f'https://example.com/component_{i}' for i in range(17)]
    license_paths = [f'https://example.com/license_{i}' for i in range(17)]

    ## Minimal Test File
    # generate_test_file(1,0,0,0,0,0, [], [], [], [], [], [], [], 
    #                    name="MinimalFile", version="0B", date="2025-06-17", type="multimodal", architecture="RNN", 
    #                    origin="Pre-Test", producer="Test", contact="", 
    #                    repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")
    
    # Full Test File
    # generate_test_file(17,4,17,17,0,17, 
    #                    [], [], [], [], 
    #                    component_paths=component_paths, 
    #                    global_license_paths=global_license_paths, 
    #                    license_paths=license_paths, 
    #                    name="FullTestFile", version="1B", 
    #                    date="2025-06-17", type="multimodal", 
    #                    architecture="RNN", origin="Pre-Test", 
    #                    producer="Test", contact="", 
    #                    repository="https://github.com", 
    #                    huggingface="https://huggingface.co", 
    #                    save_path = "./Test_Files/")

    # Class 1 Test File
    # generate_test_file(15, 2, 15, 15, 0, 15, [0,1,2,3,4,5,6,7,8,9,11,12,13,15,16], [], [], [], component_paths=component_paths[:15], global_license_paths=global_license_paths[:15], license_paths=license_paths[:15], 
    #                    name="Class1TestFile", version="2B", date="2025-06-17", type="multimodal", architecture="RNN", origin="Pre-Test",
    #                    producer="Test", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")

    # Class 2T Test File
    # generate_test_file(11, 1, 11, 11, 0, 11, [0,6,14,16,12,13,2,3,4,9,5], [], [], [], component_paths=component_paths[:11], global_license_paths=global_license_paths[:1], license_paths=license_paths[:11],
    #                    name="Class2TTestFile", version="3B", date="2025-06-17", type="multimodal", architecture="RNN", origin="Pre-Test",
    #                    producer="Test", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")

    # Class 2R Test File
    # generate_test_file(11, 1, 11, 11, 0, 11, [0,6,15,16,12,13,2,3,4,9,5], [], [], [], component_paths=component_paths[:11], global_license_paths=global_license_paths[:1], license_paths=license_paths[:11],
    #                    name="Class2RTestFile", version="4B", date="2025-06-17", type="multimodal", architecture="RNN", origin="Pre-Test",
    #                    producer="Test", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")

    # Class 3T Test File
    # generate_test_file(6, 1, 6, 6, 0, 6, [0,6,14,16,12,13], [], [], [], component_paths=component_paths[:6], global_license_paths=global_license_paths[:1], license_paths=license_paths[:6],
    #                    name="Class3TTestFile", version="5B", date="2025-06-17", type="multimodal", architecture="RNN", origin="Pre-Test",
    #                    producer="Test", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")
    
    # # Class 3R Test File
    # generate_test_file(6, 1, 6, 6, 0, 6, [0,6,15,16,12,13], [], [], [], component_paths=component_paths[:6], global_license_paths=global_license_paths[:1], license_paths=license_paths[:6],
    #                    name="Class3RTestFile", version="6B", date="2025-06-17", type="multimodal", architecture="RNN", origin="Pre-Test",
    #                    producer="Test", contact="", repository="https://github.com", huggingface="https://huggingface.co", save_path = "./Test_Files/")

    # Varied Component Number with Only Global Licenses

    # for i in range(5):
    #     num_components = random.randint(1,16)
    #     num_global_licenses = random.randint(1, 4)
    #     num_component_licenses = 0
    #     num_valid_licenses = 0
    #     num_invalid_licenses = 0
    #     num_type_appropriate_licenses = 0

    #     example_global_paths = [f'https://example.com/global_license_{i}' for i in range(num_global_licenses)]
    #     global_license_paths = example_global_paths[:num_global_licenses]
        
    #     print(f"Generating test file with {num_components} components and {num_global_licenses} global licenses.")
    #     generate_test_file(num_components, num_global_licenses, num_component_licenses, 
    #                        num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses,
    #                        save_path="./Test_Files/", component_ids=[], license_ids=[],
    #                        global_license_types=[], global_license_ids=[],
    #                        component_paths=[], global_license_paths=global_license_paths, license_paths=[],
    #                        name=f"OnlyGlobalLicense", version=f"{i}B")

    # Varied Component Number Without Licenses

    # for i in range(5):
    #     num_components = random.randint(1,16)
    #     num_global_licenses = 0
    #     num_component_licenses = 0
    #     num_valid_licenses = 0
    #     num_invalid_licenses = 0
    #     num_type_appropriate_licenses = 0

    #     example_component_paths = [f'https://example.com/component_{i}' for i in range(num_components)]
    #     component_paths = example_component_paths[:num_components]

    #     print(f"Generating test file with {num_components} components and no licenses.")
    #     generate_test_file(num_components, num_global_licenses, num_component_licenses,
    #                        num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses,
    #                        save_path="./Test_Files/", component_ids=[], license_ids=[],
    #                        global_license_types=[], global_license_ids=[],
    #                        component_paths=component_paths, global_license_paths=[], license_paths=[],
    #                        name=f"NoLicenseFile", version=f"{i}B")
    
    # # Varied Component Number with Random Licenses (Valid and Invalid)

    for i in range(5):
        num_components = random.randint(1,16)
        num_global_licenses = random.randint(0, 4)
        num_component_licenses = num_components
        num_valid_licenses = random.randint(0, num_component_licenses)
        num_invalid_licenses = num_component_licenses - num_valid_licenses
        num_type_appropriate_licenses = random.randint(0, num_valid_licenses)

        example_global_paths = [f'https://example.com/global_license_{i}' for i in range(num_global_licenses)]
        example_component_paths = [f'https://example.com/component_{i}' for i in range(num_components)]
        example_license_paths = [f'https://example.com/license_{i}' for i in range(num_component_licenses)]
        global_license_paths = example_global_paths[:num_global_licenses]
        component_paths = example_component_paths[:num_components]
        license_paths = example_license_paths[:num_component_licenses]

        component_ids = []
        license_ids = []
        global_license_types = []
        global_license_ids = []

        print(f"Generating test file with {num_components} components, {num_global_licenses} global licenses, "
              f"{num_component_licenses} component licenses, {num_valid_licenses} valid licenses, "
              f"{num_invalid_licenses} invalid licenses, and {num_type_appropriate_licenses} type appropriate licenses.")
        
        generate_test_file(num_components, num_global_licenses, num_component_licenses,
                           num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses,
                           save_path="./Test_Files/", component_ids=component_ids, license_ids=license_ids,
                           global_license_types=global_license_types, global_license_ids=global_license_ids,
                           component_paths=component_paths, global_license_paths=global_license_paths,
                           license_paths=license_paths,
                           name=f"RandomTestFile", version=f"{i+1}B")

    # # Varied Component Number with valid and invalid licenses without global licenses
    # for i in range(5):
    #     num_components = random.randint(1,16)
    #     num_global_licenses = 0
    #     num_component_licenses = num_components
    #     num_valid_licenses = random.randint(0, num_component_licenses)
    #     num_invalid_licenses = num_component_licenses - num_valid_licenses
    #     num_type_appropriate_licenses = random.randint(0, num_valid_licenses)

    #     example_component_paths = [f'https://example.com/component_{i}' for i in range(num_components)]
    #     example_license_paths = [f'https://example.com/license_{i}' for i in range(num_component_licenses)]
    #     component_paths = example_component_paths[:num_components] 
    #     license_paths = example_license_paths[:num_component_licenses]

    #     component_ids = []
    #     license_ids = []
    #     global_license_types = []
    #     global_license_ids = []

    #     print(f"Generating test file with {num_components} components, {num_component_licenses} component licenses, "
    #           f"{num_valid_licenses} valid licenses, {num_invalid_licenses} invalid licenses, and {num_type_appropriate_licenses} type appropriate licenses.")
        
    #     generate_test_file(num_components, num_global_licenses, num_component_licenses,
    #                        num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses,
    #                        save_path="./Test_Files/", component_ids=component_ids, license_ids=license_ids,
    #                        global_license_types=global_license_types, global_license_ids=global_license_ids,
    #                        component_paths=component_paths, global_license_paths=[],
    #                        license_paths=license_paths,
    #                        name=f"OnlyComponentLicenses", version=f"{i+1}B")





    # generate_test_file(12, 4, 0, 0, 0, 0, save_path="./Test_Files", component_ids=[0,6,14,16,12,5], license_ids=[], global_license_types=[],
    # global_license_ids=[], component_paths=[], global_license_paths=[],license_paths=[])

    # if len(sys.argv) < 2:
    #     print("Usage: python generate-test-files.py <number_of_test_files>")
    #     sys.exit(1)
    
    # num_test_files = int(sys.argv[1])
    # for i in range(num_test_files):
    #     num_components = random.randint(0, 16)
    #     if num_components != 0:
    #         num_global_licenses = random.randint(0, 4)
    #     else:
    #         num_global_licenses = 0
    #     num_component_licenses = random.randint(0, num_components)
    #     num_valid_licenses = random.randint(0, num_component_licenses)
    #     num_invalid_licenses = num_component_licenses - num_valid_licenses
    #     num_type_appropriate_licenses = random.randint(0, num_valid_licenses)

    #     example_global_paths = [f'https://example.com/global_license_{i}' for i in range(num_global_licenses)]
    #     example_component_paths = [f'https://example.com/component_{i}' for i in range(num_components)]
    #     example_license_paths = [f'https://example.com/license_{i}' for i in range(num_component_licenses)]
    #     num_global_paths = random.randint(0, num_global_licenses)
    #     num_component_paths = random.randint(0, num_components)
    #     num_license_paths = random.randint(0, num_component_licenses)

    #     component_ids = []
    #     license_ids = []
    #     global_license_types = []
    #     global_license_ids = []
    #     global_license_paths = example_global_paths[:num_global_paths]
    #     component_paths = example_component_paths[:num_component_paths]
    #     license_paths = example_license_paths[:num_license_paths]

    #     print("Generating test file with the following parameters:")
    #     print(f"Components: {num_components}, Global Licenses: {num_global_licenses}, Component Licenses: {num_component_licenses}, "
    #           f"Valid Licenses: {num_valid_licenses}, Invalid Licenses: {num_invalid_licenses}, Type Appropriate Licenses: {num_type_appropriate_licenses}")
    #     generate_test_file(num_components, num_global_licenses, num_component_licenses, 
    #                        num_valid_licenses, num_invalid_licenses, num_type_appropriate_licenses,
    #                        save_path = "../models/", component_ids=component_ids, license_ids=license_ids,
    #                        global_license_types=global_license_types, global_license_ids=global_license_ids,
    #                        component_paths=component_paths, license_paths=license_paths,
    #                        name=f"RandomTestFile_{i+1}", version=f"{i+1}B")

main()