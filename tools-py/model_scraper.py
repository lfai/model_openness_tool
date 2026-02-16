#!/usr/bin/env python3
"""
Model Openness Tool - Automated Model Data Scraper

This script scrapes model information from HuggingFace and other sources
to generate draft YAML files for the Model Openness Framework (MOF).

Usage:
    python model_scraper.py <model_id> [--output-dir OUTPUT_DIR]
    
Example:
    python model_scraper.py meta-llama/Llama-3-8B --output-dir ../models
"""

import argparse
import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple
from urllib.parse import urlparse

import requests
import yaml


class ModelScraper:
    """Scrapes model information from various sources."""
    
    # MOF Component names as defined in the framework
    MOF_COMPONENTS = {
        'code': [
            'Training code',
            'Inference code',
            'Model architecture',
            'Supporting libraries and tools',
        ],
        'data': [
            'Training dataset',
            'Training data preprocessing',
            'Evaluation dataset',
            'Evaluation data preprocessing',
            'Sample model outputs',
            'Data card',
        ],
        'document': [
            'Technical report',
            'Research paper',
            'Model card',
            'Evaluation results',
            'Evaluation methodology',
        ],
        'parameters': [
            'Model parameters (Final)',
            'Model metadata',
        ]
    }
    
    # Known open licenses
    OPEN_LICENSES = {
        'apache-2.0', 'mit', 'bsd', 'gpl', 'lgpl', 'mpl-2.0',
        'cc-by-4.0', 'cc-by-sa-4.0', 'openrail', 'bigscience-openrail-m',
        'bigscience-bloom-rail-1.0', 'creativeml-openrail-m'
    }
    
    def __init__(self, hf_token: Optional[str] = None):
        """Initialize the scraper.
        
        Args:
            hf_token: Optional HuggingFace API token for accessing gated models
        """
        self.hf_token = hf_token
        self.session = requests.Session()
        if hf_token:
            self.session.headers.update({'Authorization': f'Bearer {hf_token}'})
    
    def normalize_model_input(self, model_input: str) -> str:
        """Normalize model input to extract model ID.
        
        Accepts either:
        - Full URL: https://huggingface.co/EleutherAI/polyglot-ko-12.8b
        - Model ID: EleutherAI/polyglot-ko-12.8b
        
        Args:
            model_input: HuggingFace URL or model ID
            
        Returns:
            Normalized model ID (org/model)
        """
        # If it's a URL, extract the model ID
        if model_input.startswith('http://') or model_input.startswith('https://'):
            parsed = urlparse(model_input)
            # Remove leading slash and extract path
            path = parsed.path.lstrip('/')
            # Remove any trailing slashes or fragments
            model_id = path.rstrip('/')
            return model_id
        
        # Otherwise, assume it's already a model ID
        return model_input.strip()
    
    def scrape_huggingface_model(self, model_id: str) -> Dict:
        """Scrape model information from HuggingFace.
        
        Args:
            model_id: HuggingFace model ID (e.g., 'meta-llama/Llama-3-8B')
            
        Returns:
            Dictionary containing scraped model information
        """
        print(f"Scraping HuggingFace model: {model_id}")
        
        # Get model info from HuggingFace API
        api_url = f"https://huggingface.co/api/models/{model_id}"
        
        try:
            response = self.session.get(api_url, timeout=30)
            response.raise_for_status()
            model_info = response.json()
        except requests.exceptions.RequestException as e:
            print(f"Error fetching model info: {e}")
            return {}
        
        # Get model card content
        card_url = f"https://huggingface.co/{model_id}/raw/main/README.md"
        model_card_content = ""
        try:
            card_response = self.session.get(card_url, timeout=30)
            if card_response.status_code == 200:
                model_card_content = card_response.text
        except requests.exceptions.RequestException:
            pass
        
        # Get repository files list
        files_url = f"https://huggingface.co/api/models/{model_id}/tree/main"
        repo_files = []
        try:
            files_response = self.session.get(files_url, timeout=30)
            if files_response.status_code == 200:
                repo_files = [f['path'] for f in files_response.json()]
        except requests.exceptions.RequestException:
            pass
        
        # Extract information
        scraped_data = {
            'model_id': model_id,
            'model_info': model_info,
            'model_card': model_card_content,
            'repo_files': repo_files,
            'confidence': {}
        }
        
        return scraped_data
    
    def detect_components(self, scraped_data: Dict) -> List[Dict]:
        """Detect which MOF components are available.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            
        Returns:
            List of component dictionaries with name, description, license, and confidence
        """
        components = []
        repo_files = scraped_data.get('repo_files', [])
        model_card = scraped_data.get('model_card', '').lower()
        model_info = scraped_data.get('model_info', {})
        
        # Get the global license (applies to all components by default)
        global_license = self._detect_license(scraped_data)
        
        # Detect Model parameters (Final)
        if any(f.endswith(('.bin', '.safetensors', '.pt', '.pth', '.ckpt')) for f in repo_files):
            components.append({
                'name': 'Model parameters (Final)',
                'description': 'Trained model parameters, weights and biases',
                'license': global_license,
                'confidence': 0.95,
                'location': 'HuggingFace repository'
            })
        
        # Detect Model metadata
        if any(f in repo_files for f in ['config.json', 'model_config.json', 'configuration.json']):
            components.append({
                'name': 'Model metadata',
                'description': 'Any model metadata including training configuration and optimizer states',
                'license': global_license,
                'confidence': 0.90,
                'location': 'HuggingFace repository'
            })
        
        # Detect Model architecture
        if any(f.endswith('.py') for f in repo_files) or 'modeling' in ' '.join(repo_files):
            components.append({
                'name': 'Model architecture',
                'description': "Well commented code for the model's architecture",
                'license': global_license,
                'confidence': 0.85,
                'location': 'HuggingFace repository'
            })
        
        # Detect Inference code
        if any('inference' in f.lower() or 'generate' in f.lower() for f in repo_files):
            components.append({
                'name': 'Inference code',
                'description': 'Code used for running the model to make predictions',
                'license': global_license,
                'confidence': 0.80,
                'location': 'HuggingFace repository'
            })
        
        # Detect Model card
        if 'README.md' in repo_files or model_card:
            components.append({
                'name': 'Model card',
                'description': 'Model details including performance metrics, intended use, and limitations',
                'license': global_license,
                'confidence': 0.95,
                'location': 'HuggingFace repository'
            })
        
        # Detect Technical report (check model card for links)
        if any(keyword in model_card for keyword in ['technical report', 'tech report', 'documentation']):
            components.append({
                'name': 'Technical report',
                'description': 'Technical report detailing capabilities and usage instructions for the model',
                'license': global_license,
                'confidence': 0.60,
                'location': 'Referenced in model card'
            })
        
        # Detect Research paper
        if any(keyword in model_card for keyword in ['paper', 'arxiv', 'publication']):
            components.append({
                'name': 'Research paper',
                'description': 'Research paper detailing the development and capabilities of the model',
                'license': global_license,
                'confidence': 0.70,
                'location': 'Referenced in model card'
            })
        
        # Detect Evaluation results
        if any(keyword in model_card for keyword in ['evaluation', 'benchmark', 'performance', 'results']):
            components.append({
                'name': 'Evaluation results',
                'description': 'The results from evaluating the model',
                'license': global_license,
                'confidence': 0.75,
                'location': 'Model card'
            })
        
        # Detect Training dataset (check for dataset references)
        if any(keyword in model_card for keyword in ['training data', 'trained on', 'dataset']):
            components.append({
                'name': 'Training dataset',
                'description': 'The dataset used to train the model',
                'license': global_license,
                'confidence': 0.50,
                'location': 'Referenced in model card'
            })
        
        return components
    
    def _detect_license(self, scraped_data: Dict) -> str:
        """Detect the license for the model.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            
        Returns:
            License name or 'unlicensed'
        """
        model_info = scraped_data.get('model_info', {})
        
        # Check for license in model info
        if 'cardData' in model_info and 'license' in model_info['cardData']:
            license_name = model_info['cardData']['license']
            if license_name and license_name != 'other':
                return license_name
        
        # Check for LICENSE file in repo
        repo_files = scraped_data.get('repo_files', [])
        if any(f.upper() == 'LICENSE' or f.upper() == 'LICENSE.md' for f in repo_files):
            # Would need to fetch and parse the LICENSE file
            return 'unlicensed'  # Placeholder - needs manual review
        
        return 'unlicensed'
    
    def _detect_repository(self, scraped_data: Dict) -> Tuple[str, float]:
        """Detect GitHub repository with confidence score.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            
        Returns:
            Tuple of (repository_url, confidence_score)
        """
        model_id = scraped_data.get('model_id', '')
        model_card = scraped_data.get('model_card', '')
        
        # Method 1: Parse model card for GitHub links
        github_pattern = r'https://github\.com/[^/\s"<>]+/[^/\s"<>]+'
        github_urls = re.findall(github_pattern, model_card)
        
        if github_urls:
            # Filter for most relevant (matching model name)
            model_name = model_id.split('/')[-1].lower()
            for url in github_urls:
                # Clean up URL (remove trailing punctuation/markdown)
                url = url.rstrip(')')
                if model_name in url.lower():
                    return url, 0.90
            # Return first GitHub URL found
            return github_urls[0].rstrip(')'), 0.70
        
        # Method 2: Try pattern-based inference
        inferred_repo = f"https://github.com/{model_id}"
        if self._check_repo_exists(inferred_repo):
            return inferred_repo, 0.65
        
        # Method 3: Try organization/model-base-name
        if '/' in model_id:
            org, name = model_id.split('/', 1)
            # Try various name variations
            base_names = [
                name.split('-')[0],  # e.g., Mistral-7B-v0.1 → Mistral
                name.lower(),
                name,
            ]
            for base_name in base_names:
                inferred_repo = f"https://github.com/{org}/{base_name}"
                if self._check_repo_exists(inferred_repo):
                    return inferred_repo, 0.60
        
        return '', 0.0
    
    def _check_repo_exists(self, repo_url: str) -> bool:
        """Check if GitHub repo exists.
        
        Args:
            repo_url: GitHub repository URL
            
        Returns:
            True if repo exists, False otherwise
        """
        try:
            response = self.session.head(repo_url, timeout=5, allow_redirects=True)
            return response.status_code == 200
        except:
            return False
    
    def _extract_model_name_from_card(self, scraped_data: Dict) -> Optional[str]:
        """Extract model name from model card metadata.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            
        Returns:
            Model name from card or None if not found
        """
        model_info = scraped_data.get('model_info', {})
        
        # Try to get model name from cardData
        if 'cardData' in model_info:
            card_data = model_info['cardData']
            # Check for model_name field
            if 'model_name' in card_data:
                return card_data['model_name']
            # Check for title field
            if 'title' in card_data:
                return card_data['title']
        
        # Try to extract from model card YAML frontmatter
        model_card = scraped_data.get('model_card', '')
        if model_card:
            # Look for YAML frontmatter (between --- markers)
            yaml_match = re.search(r'^---\s*\n(.*?)\n---', model_card, re.DOTALL | re.MULTILINE)
            if yaml_match:
                try:
                    frontmatter = yaml.safe_load(yaml_match.group(1))
                    if isinstance(frontmatter, dict):
                        if 'model_name' in frontmatter:
                            return frontmatter['model_name']
                        if 'title' in frontmatter:
                            return frontmatter['title']
                except:
                    pass
            
            # Try to extract from first heading
            heading_match = re.search(r'^#\s+(.+)$', model_card, re.MULTILINE)
            if heading_match:
                heading = heading_match.group(1).strip()
                # Clean up common prefixes
                heading = re.sub(r'^(Model Card for|Model:|Model\s+)', '', heading, flags=re.IGNORECASE).strip()
                if heading and len(heading) < 100:  # Reasonable length for a model name
                    return heading
        
        return None
    
    def _extract_model_metadata(self, scraped_data: Dict) -> Dict:
        """Extract model metadata from scraped data.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            
        Returns:
            Dictionary with model metadata
        """
        model_info = scraped_data.get('model_info', {})
        model_id = scraped_data.get('model_id', '')
        
        # Extract producer from model_id
        producer = model_id.split('/')[0] if '/' in model_id else 'Unknown'
        producer = producer.replace('-', ' ').replace('_', ' ').title()
        
        # Try to extract model name from model card first
        model_name = self._extract_model_name_from_card(scraped_data)
        
        # Fall back to model_id if not found in card
        if not model_name:
            model_name = model_id.split('/')[-1] if '/' in model_id else model_id
        
        # Detect model type from tags
        tags = model_info.get('tags', [])
        model_type = ''
        type_mapping = {
            'text-generation': 'language',
            'text2text-generation': 'language',
            'image-to-text': 'multimodal',
            'text-to-image': 'image',
            'image-classification': 'vision',
            'object-detection': 'vision',
            'automatic-speech-recognition': 'audio',
        }
        
        for tag in tags:
            if tag in type_mapping:
                model_type = type_mapping[tag]
                break
        
        # Detect architecture
        architecture = ''
        model_card = scraped_data.get('model_card', '').lower()
        if 'transformer' in model_card or 'transformer' in str(tags).lower():
            if 'decoder' in model_card:
                architecture = 'transformer decoder'
            elif 'encoder' in model_card:
                architecture = 'transformer encoder-decoder'
            else:
                architecture = 'transformer'
        elif 'diffusion' in model_card or 'diffusion' in str(tags).lower():
            architecture = 'diffusion'
        
        # Extract version (often in model name)
        version_match = re.search(r'(\d+\.?\d*[BMK]?)', model_name)
        version = version_match.group(1) if version_match else '1.0'
        
        # Get last modified date
        last_modified = model_info.get('lastModified', '')
        if last_modified:
            date = last_modified.split('T')[0]
        else:
            date = datetime.now().strftime('%Y-%m-%d')
        
        # Detect repository
        repository, repo_confidence = self._detect_repository(scraped_data)
        
        metadata = {
            'name': model_name,
            'version': version,
            'producer': producer,
            'type': model_type,
            'architecture': architecture,
            'date': date,
            'origin': model_name.lower(),
            'huggingface': f"https://huggingface.co/{model_id}",
        }
        
        # Add repository if found
        if repository:
            metadata['repository'] = repository
            metadata['repository_confidence'] = repo_confidence
        
        return metadata
    
    def _format_yaml_mot_style(self, metadata: Dict, components: List[Dict]) -> str:
        """Format YAML in MOT style with proper indentation and quotes.
        
        Args:
            metadata: Model metadata dictionary
            components: List of component dictionaries
            
        Returns:
            Formatted YAML string matching MOT style
        """
        lines = []
        
        # Framework section
        lines.append("framework:")
        lines.append("  name: 'Model Openness Framework'")
        lines.append("  version: '1.0'")
        lines.append("  date: '2024-12-15'")
        
        # Release section
        lines.append("release:")
        lines.append(f"  name: {metadata['name']}")
        lines.append(f"  version: '{metadata['version']}'")
        lines.append(f"  date: '{metadata['date']}'")
        lines.append("  license: {  }")
        lines.append(f"  type: '{metadata['type']}'")
        lines.append(f"  architecture: '{metadata['architecture']}'")
        lines.append(f"  origin: {metadata['origin']}")
        lines.append(f"  producer: '{metadata['producer']}'")
        lines.append("  contact: ''")
        
        # Add repository if present
        if metadata.get('repository'):
            lines.append(f"  repository: '{metadata['repository']}'")
        
        # Add huggingface if present
        if metadata.get('huggingface'):
            lines.append(f"  huggingface: '{metadata['huggingface']}'")
        
        # Components section
        lines.append("  components:")
        for comp in components:
            lines.append("    -")
            lines.append(f"      name: '{comp['name']}'")
            lines.append(f"      description: \"{comp['description']}\"")
            
            # Format license - handle different types
            license_val = comp['license']
            if isinstance(license_val, list):
                # Multiple licenses - just use first one for now
                license_val = license_val[0] if license_val else 'unlicensed'
            
            if license_val and license_val != 'unlicensed':
                lines.append(f"      license: '{license_val}'")
            else:
                lines.append(f"      license: unlicensed")
        
        return '\n'.join(lines)
    
    def generate_yaml(self, scraped_data: Dict, output_path: Optional[str] = None) -> str:
        """Generate MOF-compliant YAML from scraped data.
        
        Args:
            scraped_data: Dictionary containing scraped model information
            output_path: Optional path to save the YAML file
            
        Returns:
            YAML string
        """
        # Extract metadata
        metadata = self._extract_model_metadata(scraped_data)
        
        # Detect components
        components = self.detect_components(scraped_data)
        
        # Format in MOT style
        yaml_output = self._format_yaml_mot_style(metadata, components)
        
        # Save to file if path provided
        if output_path:
            with open(output_path, 'w', encoding='utf-8') as f:
                f.write(yaml_output)
            print(f"YAML saved to: {output_path}")
        
        return yaml_output


def main():
    """Main entry point for the scraper."""
    parser = argparse.ArgumentParser(
        description='Scrape model information and generate MOF YAML files'
    )
    parser.add_argument(
        'model_id',
        help='HuggingFace model ID or URL (e.g., meta-llama/Llama-3-8B or https://huggingface.co/meta-llama/Llama-3-8B)'
    )
    parser.add_argument(
        '--output-dir',
        default='../models',
        help='Output directory for YAML files (default: ../models)'
    )
    parser.add_argument(
        '--hf-token',
        help='HuggingFace API token for accessing gated models'
    )
    
    args = parser.parse_args()
    
    # Initialize scraper
    scraper = ModelScraper(hf_token=args.hf_token)
    
    # Normalize model input (handle URLs)
    model_id = scraper.normalize_model_input(args.model_id)
    
    # Scrape model data
    print(f"\n{'='*60}")
    print(f"Scraping model: {model_id}")
    print(f"{'='*60}\n")
    
    scraped_data = scraper.scrape_huggingface_model(model_id)
    
    if not scraped_data:
        print("Failed to scrape model data")
        sys.exit(1)
    
    # Extract metadata to get the proper model name
    metadata = scraper._extract_model_metadata(scraped_data)
    model_name = metadata.get('name', model_id.split('/')[-1])
    
    # Generate output filename using the extracted model name
    output_path = Path(args.output_dir) / f"{model_name}.yml"
    output_path.parent.mkdir(parents=True, exist_ok=True)
    
    # Generate YAML
    print(f"\n{'='*60}")
    print("Generating YAML...")
    print(f"{'='*60}\n")
    
    yaml_output = scraper.generate_yaml(scraped_data, str(output_path))
    
    print(f"\n{'='*60}")
    print("DRAFT YAML GENERATED")
    print(f"{'='*60}\n")
    print("⚠️  IMPORTANT: This is a DRAFT that requires manual review!")
    print("    - Verify all component availability")
    print("    - Confirm license information")
    print("    - Add missing components")
    print("    - Update confidence scores")
    print(f"\nOutput saved to: {output_path}")
    print(f"\nNext steps:")
    print(f"  1. Review and edit: {output_path}")
    print(f"  2. Validate: php scripts/validate-model.php {output_path}")
    print(f"  3. Submit PR to add to MOT database")
    

if __name__ == '__main__':
    main()
