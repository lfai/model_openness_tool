I'm trying to update information about various open source LLMs for the Model Openness Tool.  Read this page, including the tooltips:  https://mot-dev.isitopen.ai/model/evaluate

This page ultimately populates a YAML document, such as:

framework:
  name: 'Model Openness Framework'
  version: '1.0'
  date: '2024-12-15'
release:
  name: DeepSeek-R1
  version: 671B
  date: '2025-01-20'
  license: {  }
  type: language
  architecture: ''
  origin: ''
  producer: 'DeepSeek AI'
  contact: ''
  repository: 'https://github.com/deepseek-ai/DeepSeek-R1'
  huggingface: 'https://huggingface.co/deepseek-ai/DeepSeek-R1'
  components:
    -
      name: 'Model architecture'
      description: "Well commented code for the model's architecture"
      license: MIT
    -
      name: 'Inference code'
      description: 'Code used for running the model to make predictions'
      license: MIT
    -
      name: 'Supporting libraries and tools'
      description: "Libraries and tools used in the model's development"
      license: MIT
    -
      name: 'Model parameters (Final)'
      description: 'Trained model parameters, weights and biases'
      license: MIT
    -
      name: 'Evaluation data'
      description: 'Data used for evaluating the model'
      license: unlicensed
    -
      name: 'Model metadata'
      description: 'Any model metadata including training configuration and optimizer states'
      license: MIT
    -
      name: 'Model card'
      description: 'Model details including performance metrics, intended use, and limitations'
      license: unlicensed
    -
      name: 'Technical report'
      description: 'Technical report detailing capabilities and usage instructions for the model'
      license: unlicensed
    -
      name: 'Research paper'
      description: 'Research paper detailing the development and capabilities of the model'
      license: 'arXiv.org perpetual non-exclusive license 1.0'
    -
      name: 'Evaluation results'
      description: 'The results from evaluating the model'
      license: unlicensed

Analyze the form and correlate the YAML properties with the form fields.