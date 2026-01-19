#!/bin/bash
# Example usage of the Model Openness Tool Python scripts

echo "Model Openness Tool - Python Scripts Examples"
echo "=============================================="
echo ""

# Example 1: Find missing models
echo "Example 1: Find missing models from HuggingFace"
echo "------------------------------------------------"
echo "Command: python find_missing_models.py --min-downloads 10000"
echo ""
echo "This will:"
echo "  - Compare HuggingFace models with MOT database"
echo "  - Identify popular models not yet in MOT"
echo "  - Generate a prioritized report"
echo ""
# Uncomment to run:
# python find_missing_models.py --min-downloads 10000

# Example 2: Save missing models report to file
echo "Example 2: Save missing models report"
echo "--------------------------------------"
echo "Command: python find_missing_models.py --output missing_report.txt"
echo ""
# Uncomment to run:
# python find_missing_models.py --output missing_report.txt

# Example 3: Basic model scraping
echo "Example 3: Scraping a single model"
echo "-----------------------------------"
echo "Command: python model_scraper.py google/gemma-2b"
echo ""
# Uncomment to run:
# python model_scraper.py google/gemma-2b

# Example 4: Scraping with custom output directory
echo "Example 4: Custom output directory"
echo "-----------------------------------"
echo "Command: python model_scraper.py microsoft/phi-2 --output-dir ./drafts"
echo ""
# Uncomment to run:
# python model_scraper.py microsoft/phi-2 --output-dir ./drafts

# Example 5: Scraping a gated model (requires token)
echo "Example 5: Gated model (requires HuggingFace token)"
echo "----------------------------------------------------"
echo "Command: python model_scraper.py meta-llama/Llama-3-8B --hf-token YOUR_TOKEN"
echo ""
echo "Get your token from: https://huggingface.co/settings/tokens"
echo ""
# Uncomment and add your token to run:
# python model_scraper.py meta-llama/Llama-3-8B --hf-token YOUR_TOKEN

# Example 6: Complete workflow
echo "Example 6: Complete workflow (find missing + scrape)"
echo "-----------------------------------------------------"
echo "Step 1: Find missing models"
echo "  python find_missing_models.py --min-downloads 50000 --output report.txt"
echo ""
echo "Step 2: Review report.txt and identify models to add"
echo ""
echo "Step 3: Scrape identified models"
echo "  python model_scraper.py meta-llama/Llama-3-8B"
echo "  python model_scraper.py mistralai/Mistral-7B-v0.1"
echo ""
echo "Step 4: Review and validate generated YAML files"
echo "  php ../scripts/validate-model.php ../models/Llama-3-8B.yml"
echo ""
echo "Step 5: Submit PR to add models to MOT"
echo ""

# Example 7: Batch processing multiple models
echo "Example 7: Batch processing"
echo "----------------------------"
echo "Processing multiple models in sequence:"
echo ""

models=(
    "google/gemma-2b"
    "microsoft/phi-2"
    "mistralai/Mistral-7B-v0.1"
)

for model in "${models[@]}"; do
    echo "  - $model"
done
echo ""
echo "To run batch processing, uncomment the loop below in this script"
echo ""

# Uncomment to run batch processing:
# for model in "${models[@]}"; do
#     echo "Processing: $model"
#     python model_scraper.py "$model"
#     echo ""
# done

echo "=============================================="
echo "Summary of available tools:"
echo "  1. find_missing_models.py - Identify models to add"
echo "  2. model_scraper.py - Generate draft YAML files"
echo ""
echo "Next steps after scraping:"
echo "  1. Review generated YAML files in ../models/"
echo "  2. Manually verify and edit the files"
echo "  3. Validate: php ../scripts/validate-model.php ../models/YourModel.yml"
echo "  4. Submit PR to add to MOT database"
echo ""
