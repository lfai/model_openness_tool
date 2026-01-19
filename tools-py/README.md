# Model Openness Tool - Python Tools

Automated tools for working with the Model Openness Framework (MOF), including scraping model information from HuggingFace and identifying missing models.

## Tools Included

### 1. Model Scraper (`model_scraper.py`)
Generates draft YAML files for individual models by scraping HuggingFace.

### 2. Missing Models Finder (`find_missing_models.py`)
Identifies popular HuggingFace models that are not yet in the MOT database.

## Overview

These tools automate data collection and gap analysis for model evaluation by:
- Fetching model metadata from HuggingFace API
- Analyzing repository contents to detect available MOF components
- Identifying license information
- Generating MOF-compliant YAML files with confidence scores
- Finding missing models that should be added to MOT
- Flagging areas requiring manual review

**⚠️ Important**: Generated YAML files are **DRAFTS** that require manual review and validation before submission to the MOT database.

## Installation

### Prerequisites
- Python 3.8 or higher
- pip package manager

### Setup

1. Navigate to the tools-py directory:
```bash
cd tools-py
```

2. Install dependencies:
```bash
pip install -r requirements.txt
```

## Usage

### Tool 1: Model Scraper

#### Basic Usage

Scrape a model from HuggingFace:
```bash
python model_scraper.py <model_id>
```

Example:
```bash
python model_scraper.py meta-llama/Llama-3-8B
```

#### Advanced Options

Specify output directory:
```bash
python model_scraper.py meta-llama/Llama-3-8B --output-dir ../models
```

Use HuggingFace token for gated models:
```bash
python model_scraper.py meta-llama/Llama-3-8B --hf-token YOUR_TOKEN
```

### Tool 2: Missing Models Finder

#### Basic Usage

Find missing models with default settings (min 1000 downloads):
```bash
python find_missing_models.py
```

#### Advanced Options

Set minimum download threshold:
```bash
python find_missing_models.py --min-downloads 10000
```

Limit number of models to check:
```bash
python find_missing_models.py --limit 500
```

Save report to file:
```bash
python find_missing_models.py --output missing_models_report.txt
```

Filter by model type:
```bash
python find_missing_models.py --model-type text-generation
```

#### Command-Line Arguments

- `--min-downloads`: Minimum number of downloads to consider (default: 1000)
- `--limit`: Maximum number of models to fetch from HuggingFace (default: 1000)
- `--models-dir`: Path to MOT models directory (default: ../models)
- `--output`: Output file for report (default: print to console)
- `--model-type`: Filter by model type (e.g., text-generation, image-to-text)

#### Example Workflow

**Option A: Manual Workflow (Selective)**

1. Find missing high-priority models:
```bash
python find_missing_models.py --min-downloads 50000 --output report.txt
```

2. Review the report and identify models to add

3. Scrape the identified models:
```bash
python model_scraper.py meta-llama/Llama-3-8B
python model_scraper.py mistralai/Mistral-7B-v0.1
```

4. Review and validate the generated YAML files

5. Submit PRs to add models to MOT

**Option B: Batch Processing (Automated)**

Use the batch script to automatically find and scrape missing models:

```bash
./batch_scrape_missing.sh
```

This will:
1. Find missing models (default: 50k+ downloads)
2. Extract model IDs from the report
3. Prompt for confirmation
4. Scrape first 20 models automatically
5. Generate summary with success/failure counts

**Customize batch processing:**

```bash
# Set minimum downloads threshold
MIN_DOWNLOADS=100000 ./batch_scrape_missing.sh

# Process more models (default is 20)
MAX_MODELS=50 ./batch_scrape_missing.sh

# Process ALL missing models (use with caution!)
MAX_MODELS=999 ./batch_scrape_missing.sh

# Use HuggingFace token for gated models
HF_TOKEN=your_token ./batch_scrape_missing.sh

# Change output directory
OUTPUT_DIR=./draft_models ./batch_scrape_missing.sh

# Combine multiple options
MIN_DOWNLOADS=100000 MAX_MODELS=50 HF_TOKEN=your_token ./batch_scrape_missing.sh
```

**Environment Variables:**
- `MIN_DOWNLOADS`: Minimum download threshold (default: 50000)
- `LIMIT`: Max models to check in HuggingFace (default: 1000)
- `MAX_MODELS`: Max models to scrape in one batch (default: 20)
- `OUTPUT_DIR`: Output directory for YAML files (default: ../models)
- `HF_TOKEN`: HuggingFace API token for gated models (optional)

**Note:** The default limit of 20 models is a safety measure. Each model takes ~2-5 seconds to scrape, so 20 models = ~1-2 minutes. Increase MAX_MODELS carefully based on your needs.

### Advanced Options

Specify output directory:
```bash
python model_scraper.py meta-llama/Llama-3-8B --output-dir ../models
```

Use HuggingFace token for gated models:
```bash
python model_scraper.py meta-llama/Llama-3-8B --hf-token YOUR_TOKEN
```

### Command-Line Arguments

- `model_id` (required): HuggingFace model ID (e.g., `meta-llama/Llama-3-8B`)
- `--output-dir`: Output directory for YAML files (default: `../models`)
- `--hf-token`: HuggingFace API token for accessing gated models

## What the Scraper Does

### 1. Data Collection
- Fetches model metadata from HuggingFace API
- Downloads and parses model card (README.md)
- Lists repository files to detect available components
- Extracts license information

### 2. Component Detection

The scraper automatically detects the following MOF components:

**Code Components:**
- Model parameters (Final) - Detects `.bin`, `.safetensors`, `.pt`, `.pth`, `.ckpt` files
- Model metadata - Detects `config.json`, `model_config.json`
- Model architecture - Detects Python files with modeling code
- Inference code - Detects files with inference/generation keywords

**Data Components:**
- Training dataset - Detects references in model card

**Documentation Components:**
- Model card - Detects README.md
- Technical report - Detects references in model card
- Research paper - Detects paper/arxiv references
- Evaluation results - Detects benchmark/performance mentions

### 3. License Detection
- Extracts license from HuggingFace model metadata
- Checks for LICENSE files in repository
- Defaults to "unlicensed" when uncertain (requires manual review)

### 4. Repository Detection

The scraper automatically detects GitHub repositories using multiple strategies with confidence scoring:

**Detection Methods (in priority order):**

1. **Model Card Parsing** (70-90% confidence)
   - Searches the model card (README.md) for GitHub URLs
   - Filters for the most relevant repository (matching model/organization name)
   - Higher confidence when repository name closely matches model name
   - Example: Found `https://github.com/bigscience-workshop/bigscience` for BLOOM model

2. **Pattern-Based Inference** (60-65% confidence)
   - Attempts direct mapping: `organization/model` → `github.com/organization/model`
   - Validates repository existence via HTTP HEAD request
   - Example: `mistralai/Mistral-7B-v0.1` → `github.com/mistralai/Mistral-7B-v0.1`

3. **Name Variations** (60% confidence)
   - Tries base model names without version suffixes
   - Tests multiple naming patterns (e.g., `Mistral-7B-v0.1` → `Mistral`)
   - Validates each attempt before accepting

**Output Format:**
```yaml
# Repository detected: 70% confidence
release:
  repository: https://github.com/bigscience-workshop/bigscience
```

**When No Repository Found:**
- Repository field left empty (requires manual addition)
- Common for models without public code repositories
- May indicate closed-source or proprietary models

**Manual Review Required:**
- Verify detected repository is correct and official
- Check if repository contains actual model code/weights
- Some models may have multiple repositories (training vs. inference)

### 5. Confidence Scoring

Each detected component includes a confidence score:
- **95%**: High confidence (e.g., model parameters detected via file extensions)
- **80-90%**: Good confidence (e.g., config files, model card)
- **60-75%**: Medium confidence (e.g., references in documentation)
- **50%**: Low confidence (requires verification)

## Output Format

Generated YAML files include:
- MOF framework metadata
- Model release information (name, version, date, producer)
- Detected components with descriptions and licenses
- Confidence scores in comments
- Links to HuggingFace repository

Example output structure:
```yaml
# AUTO-GENERATED DRAFT - REQUIRES MANUAL REVIEW
# Generated by Model Openness Tool scraper
# Source: HuggingFace model meta-llama/Llama-3-8B
# 
# Component confidence scores:
#   - Model parameters (Final): 95% confidence
#   - Model card: 95% confidence
# 
framework:
  name: Model Openness Framework
  version: '1.0'
  date: '2024-12-15'
release:
  name: Llama-3-8B
  version: 8B
  date: '2024-10-03'
  license: {}
  type: language
  architecture: transformer decoder
  origin: llama-3-8b
  producer: Meta
  contact: ''
  huggingface: https://huggingface.co/meta-llama/Llama-3-8B
  components:
    - name: Model parameters (Final)
      description: Trained model parameters, weights and biases
      license: llama-3
```

## Manual Review Checklist

After generating a YAML file, you **must** review and verify:

### 1. Model Metadata
- [ ] Verify model name and version
- [ ] Confirm producer/organization
- [ ] Check release date accuracy
- [ ] Validate model type (language, vision, multimodal, etc.)
- [ ] Verify architecture classification

### 2. Components
- [ ] Confirm all detected components are actually available
- [ ] Add any missing components not detected by scraper
- [ ] Verify component descriptions are accurate
- [ ] Update component locations/URLs if needed

### 3. Licenses
- [ ] **Critical**: Verify all license information
- [ ] Check if licenses are correctly identified as open/closed
- [ ] Add license file paths where available
- [ ] Replace "unlicensed" with actual license names
- [ ] Ensure license compatibility across components

### 4. Additional Information
- [ ] Add contact information if available
- [ ] Add GitHub repository URL if different from HuggingFace
- [ ] Include paper URLs (arXiv, conference proceedings)
- [ ] Add any special notes or caveats

## Validation

After manual review, validate the YAML file:

```bash
cd ..
php scripts/validate-model.php models/Your-Model.yml
```

The validation script checks:
- Schema compliance with `schema/mof_schema.json`
- Required fields are present
- Data types are correct
- Enum values are valid

## Submission Workflow

1. **Generate draft**: Run the scraper
2. **Manual review**: Edit the generated YAML file
3. **Validate**: Run validation script
4. **Test locally**: Import into local MOT instance
5. **Submit PR**: Create pull request to add model to MOT

See [CONTRIBUTING.md](../CONTRIBUTING.md) for detailed submission instructions.

## Limitations

### Current Limitations
- Only supports HuggingFace as a data source
- Cannot access gated models without API token
- License detection is basic (requires manual verification)
- Cannot determine if training code/data is actually available
- May miss components not clearly documented
- Cannot validate license openness automatically

### Known Issues
- Some model cards use non-standard formats
- License information may be incomplete or ambiguous
- Component availability may be overstated
- Confidence scores are heuristic-based

## Future Enhancements

Potential improvements:
- [ ] Support for additional sources (GitHub, Papers with Code, etc.)
- [ ] LLM-powered content analysis for better component detection
- [ ] Automated license file parsing and classification
- [ ] Integration with license databases (SPDX, OSI)
- [ ] Batch processing for multiple models
- [ ] Interactive mode for guided review
- [ ] Comparison with existing MOT entries
- [ ] GitHub API integration for repository analysis

## Troubleshooting

### Common Issues

**"Failed to scrape model data"**
- Check model ID is correct (format: `organization/model-name`)
- Verify internet connection
- For gated models, provide HuggingFace token with `--hf-token`

**"Model not found"**
- Ensure model exists on HuggingFace
- Check for typos in model ID
- Some models may be private or deleted

**"Permission denied"**
- Gated models require authentication
- Get HuggingFace token from https://huggingface.co/settings/tokens
- Use `--hf-token` argument

**Low confidence scores**
- Normal for models with minimal documentation
- Requires more thorough manual review
- Consider contacting model producer for clarification

## Contributing

To improve the scraper:
1. Fork the repository
2. Make your changes
3. Test with various models
4. Submit a pull request

See [CONTRIBUTING.md](../CONTRIBUTING.md) for guidelines.

## License

This scraper is part of the Model Openness Tool, licensed under MIT.

## Support

For issues or questions:
- File an issue on GitHub
- Check existing issues for solutions
- Consult the main MOT documentation

## Acknowledgments

This scraper was developed to accelerate the MOT model evaluation process while maintaining the quality and accuracy standards of the Model Openness Framework.