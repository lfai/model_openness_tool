#!/bin/bash

# Batch Scrape Missing Models
# This script finds missing models and generates draft YAML files for each

set -e  # Exit on error

# Configuration
MIN_DOWNLOADS=${MIN_DOWNLOADS:-50000}  # Default: 50k downloads
LIMIT=${LIMIT:-1000}                   # Default: check 1000 models
MAX_MODELS=${MAX_MODELS:-20}           # Default: process 20 models (safety limit)
OUTPUT_DIR=${OUTPUT_DIR:-../models}    # Default: ../models directory
REPORT_FILE="missing_models_report.txt"
HF_TOKEN=${HF_TOKEN:-""}              # Optional HuggingFace token

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Batch Scrape Missing Models${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Step 1: Find missing models
echo -e "${YELLOW}Step 1: Finding missing models...${NC}"
echo "  Min downloads: $MIN_DOWNLOADS"
echo "  Limit: $LIMIT models"
echo ""

python find_missing_models.py \
    --min-downloads "$MIN_DOWNLOADS" \
    --limit "$LIMIT" \
    --output "$REPORT_FILE"

if [ ! -f "$REPORT_FILE" ]; then
    echo -e "${RED}Error: Report file not generated${NC}"
    exit 1
fi

# Step 2: Extract model IDs from report
echo ""
echo -e "${YELLOW}Step 2: Extracting model IDs from report...${NC}"

# Extract lines with model IDs (format: "  org/model-name    downloads")
# Look for lines starting with spaces followed by org/model pattern
MODEL_IDS=$(grep -E "^  [a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+" "$REPORT_FILE" | \
    awk '{print $1}' | \
    head -n "$MAX_MODELS")

MODEL_COUNT=$(echo "$MODEL_IDS" | wc -l | tr -d ' ')

if [ -z "$MODEL_IDS" ]; then
    echo -e "${RED}No missing models found in report${NC}"
    exit 0
fi

echo "  Found $MODEL_COUNT models to scrape"
echo ""

# Step 3: Confirm with user
echo -e "${YELLOW}Models to scrape:${NC}"
echo "$MODEL_IDS" | nl
echo ""
echo -e "${YELLOW}This will generate $MODEL_COUNT YAML files in: $OUTPUT_DIR${NC}"
read -p "Continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted by user"
    exit 0
fi

# Step 4: Scrape each model
echo ""
echo -e "${YELLOW}Step 3: Scraping models...${NC}"
echo ""

SUCCESS_COUNT=0
FAIL_COUNT=0
FAILED_MODELS=()

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# Loop through each model
CURRENT=0
while IFS= read -r MODEL_ID; do
    CURRENT=$((CURRENT + 1))
    echo -e "${BLUE}[$CURRENT/$MODEL_COUNT] Scraping: $MODEL_ID${NC}"
    
    # Build command with optional token
    CMD="python model_scraper.py \"$MODEL_ID\" --output-dir \"$OUTPUT_DIR\""
    if [ -n "$HF_TOKEN" ]; then
        CMD="$CMD --hf-token \"$HF_TOKEN\""
    fi
    
    # Run scraper
    if eval "$CMD"; then
        echo -e "${GREEN}  ✓ Success${NC}"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        echo -e "${RED}  ✗ Failed${NC}"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        FAILED_MODELS+=("$MODEL_ID")
    fi
    
    echo ""
    
    # Small delay to avoid rate limiting
    sleep 1
done <<< "$MODEL_IDS"

# Step 5: Summary
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Summary${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}Successful: $SUCCESS_COUNT${NC}"
echo -e "${RED}Failed: $FAIL_COUNT${NC}"
echo ""

if [ $FAIL_COUNT -gt 0 ]; then
    echo -e "${RED}Failed models:${NC}"
    for MODEL in "${FAILED_MODELS[@]}"; do
        echo "  - $MODEL"
    done
    echo ""
fi

echo -e "${YELLOW}Next steps:${NC}"
echo "1. Review generated YAML files in: $OUTPUT_DIR"
echo "2. Manually verify and edit each file"
echo "3. Validate with: php ../scripts/validate-model.php models/Your-Model.yml"
echo "4. Submit PRs for reviewed models"
echo ""
echo "Report saved to: $REPORT_FILE"
