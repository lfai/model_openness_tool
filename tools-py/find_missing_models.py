#!/usr/bin/env python3
"""
Model Openness Tool - Missing Models Finder

This script identifies models on HuggingFace that are not yet in the MOT database.
It compares popular/trending models from HuggingFace against existing MOT YAML files.

Usage:
    python find_missing_models.py [--min-downloads MIN] [--limit LIMIT] [--output OUTPUT]
    
Example:
    python find_missing_models.py --min-downloads 10000 --limit 500
"""

import argparse
import json
import os
import re
import sys
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple
from urllib.parse import quote

import requests
import yaml


class MissingModelsFinder:
    """Finds models on HuggingFace that are missing from MOT."""
    
    def __init__(self, models_dir: str = "../models"):
        """Initialize the finder.
        
        Args:
            models_dir: Path to MOT models directory
        """
        self.models_dir = Path(models_dir)
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'MOT-Missing-Models-Finder/1.0'
        })
    
    def get_mot_models(self) -> Dict[str, Dict]:
        """Get all models currently in MOT database.
        
        Returns:
            Dictionary mapping model names/IDs to their metadata
        """
        print("Loading existing MOT models...")
        mot_models = {}
        
        if not self.models_dir.exists():
            print(f"Warning: Models directory not found: {self.models_dir}")
            return mot_models
        
        yaml_files = list(self.models_dir.glob("*.yml"))
        print(f"Found {len(yaml_files)} YAML files in MOT database")
        
        for yaml_file in yaml_files:
            try:
                with open(yaml_file, 'r', encoding='utf-8') as f:
                    data = yaml.safe_load(f)
                
                if not data or 'release' not in data:
                    continue
                
                release = data['release']
                model_name = release.get('name', '')
                origin = release.get('origin', '')
                huggingface = release.get('huggingface', '')
                
                # Store multiple identifiers for matching
                identifiers = set()
                if model_name:
                    identifiers.add(model_name.lower())
                    identifiers.add(model_name.lower().replace('-', '_'))
                    identifiers.add(model_name.lower().replace('_', '-'))
                if origin:
                    identifiers.add(origin.lower())
                if huggingface:
                    # Extract model ID from HuggingFace URL
                    hf_id = huggingface.replace('https://huggingface.co/', '')
                    identifiers.add(hf_id.lower())
                
                mot_models[yaml_file.stem] = {
                    'name': model_name,
                    'origin': origin,
                    'huggingface': huggingface,
                    'identifiers': identifiers,
                    'file': yaml_file.name
                }
                
            except Exception as e:
                print(f"Warning: Error reading {yaml_file.name}: {e}")
                continue
        
        print(f"Loaded {len(mot_models)} models from MOT database\n")
        return mot_models
    
    def get_huggingface_models(
        self,
        min_downloads: int = 1000,
        limit: int = 1000,
        model_type: Optional[str] = None
    ) -> List[Dict]:
        """Get popular models from HuggingFace.
        
        Args:
            min_downloads: Minimum number of downloads to consider
            limit: Maximum number of models to fetch
            model_type: Filter by model type (e.g., 'text-generation')
            
        Returns:
            List of model dictionaries
        """
        print(f"Fetching models from HuggingFace (min downloads: {min_downloads:,})...")
        
        models = []
        page = 0
        
        while len(models) < limit:
            # HuggingFace API endpoint for models
            url = "https://huggingface.co/api/models"
            params = {
                'sort': 'downloads',
                'direction': -1,
                'limit': 100,
                'skip': page * 100,
                'full': True
            }
            
            if model_type:
                params['filter'] = model_type
            
            try:
                response = self.session.get(url, params=params, timeout=30)
                response.raise_for_status()
                batch = response.json()
                
                if not batch:
                    break
                
                for model in batch:
                    downloads = model.get('downloads', 0)
                    if downloads >= min_downloads:
                        models.append(model)
                    
                    if len(models) >= limit:
                        break
                
                page += 1
                print(f"  Fetched {len(models)} models so far...", end='\r')
                
            except requests.exceptions.RequestException as e:
                print(f"\nError fetching models: {e}")
                break
        
        print(f"\nFetched {len(models)} models from HuggingFace\n")
        return models
    
    def normalize_model_id(self, model_id: str) -> Set[str]:
        """Generate normalized variations of a model ID for matching.
        
        Args:
            model_id: Model ID (e.g., 'meta-llama/Llama-3-8B')
            
        Returns:
            Set of normalized variations
        """
        variations = set()
        
        # Original
        variations.add(model_id.lower())
        
        # Without organization prefix
        if '/' in model_id:
            model_name = model_id.split('/')[-1]
            variations.add(model_name.lower())
            variations.add(model_name.lower().replace('-', '_'))
            variations.add(model_name.lower().replace('_', '-'))
        
        # With underscores/hyphens swapped
        variations.add(model_id.lower().replace('-', '_'))
        variations.add(model_id.lower().replace('_', '-'))
        
        return variations
    
    def is_model_in_mot(self, hf_model: Dict, mot_models: Dict) -> Tuple[bool, str]:
        """Check if a HuggingFace model is already in MOT.
        
        Args:
            hf_model: HuggingFace model dictionary
            mot_models: Dictionary of MOT models
            
        Returns:
            Tuple of (is_present, matched_file)
        """
        model_id = hf_model.get('id', '')
        variations = self.normalize_model_id(model_id)
        
        for mot_file, mot_data in mot_models.items():
            mot_identifiers = mot_data.get('identifiers', set())
            
            # Check if any variation matches
            if variations & mot_identifiers:
                return True, mot_data['file']
        
        return False, ''
    
    def categorize_missing_models(
        self, 
        missing_models: List[Dict]
    ) -> Dict[str, List[Dict]]:
        """Categorize missing models by type and popularity.
        
        Args:
            missing_models: List of missing model dictionaries
            
        Returns:
            Dictionary of categorized models
        """
        categories = {
            'high_priority': [],      # >100k downloads
            'medium_priority': [],    # 10k-100k downloads
            'low_priority': [],       # <10k downloads
            'by_type': {}
        }
        
        for model in missing_models:
            downloads = model.get('downloads', 0)
            tags = model.get('tags', [])
            
            # Priority by downloads
            if downloads >= 100000:
                categories['high_priority'].append(model)
            elif downloads >= 10000:
                categories['medium_priority'].append(model)
            else:
                categories['low_priority'].append(model)
            
            # By type
            model_type = 'other'
            type_tags = [
                'text-generation', 'text2text-generation', 
                'image-to-text', 'text-to-image',
                'automatic-speech-recognition', 'audio-classification',
                'image-classification', 'object-detection'
            ]
            
            for tag in tags:
                if tag in type_tags:
                    model_type = tag
                    break
            
            if model_type not in categories['by_type']:
                categories['by_type'][model_type] = []
            categories['by_type'][model_type].append(model)
        
        return categories
    
    def generate_report(
        self,
        missing_models: List[Dict],
        mot_models: Dict,
        output_file: Optional[str] = None
    ) -> str:
        """Generate a report of missing models.
        
        Args:
            missing_models: List of missing model dictionaries
            mot_models: Dictionary of MOT models
            output_file: Optional file to save report
            
        Returns:
            Report text
        """
        categories = self.categorize_missing_models(missing_models)
        
        report_lines = []
        report_lines.append("=" * 80)
        report_lines.append("MODEL OPENNESS TOOL - MISSING MODELS REPORT")
        report_lines.append("=" * 80)
        report_lines.append("")
        
        # Summary
        report_lines.append("SUMMARY")
        report_lines.append("-" * 80)
        report_lines.append(f"Models in MOT database:     {len(mot_models):,}")
        report_lines.append(f"Missing models found:       {len(missing_models):,}")
        report_lines.append(f"  - High priority (>100k):  {len(categories['high_priority']):,}")
        report_lines.append(f"  - Medium priority (10k+): {len(categories['medium_priority']):,}")
        report_lines.append(f"  - Low priority (<10k):    {len(categories['low_priority']):,}")
        report_lines.append("")
        
        # By type
        report_lines.append("MISSING MODELS BY TYPE")
        report_lines.append("-" * 80)
        for model_type, models in sorted(categories['by_type'].items(), 
                                        key=lambda x: len(x[1]), 
                                        reverse=True):
            report_lines.append(f"  {model_type:30s} {len(models):5,} models")
        report_lines.append("")
        
        # High priority models
        if categories['high_priority']:
            report_lines.append("HIGH PRIORITY MODELS (>100,000 downloads)")
            report_lines.append("-" * 80)
            for model in sorted(categories['high_priority'], 
                              key=lambda x: x.get('downloads', 0), 
                              reverse=True)[:50]:  # Top 50
                model_id = model.get('id', 'unknown')
                downloads = model.get('downloads', 0)
                tags = ', '.join(model.get('tags', [])[:3])
                report_lines.append(f"  {model_id:50s} {downloads:>10,} downloads")
                if tags:
                    report_lines.append(f"    Tags: {tags}")
                report_lines.append(f"    URL: https://huggingface.co/{model_id}")
                report_lines.append("")
        
        # Medium priority models
        if categories['medium_priority']:
            report_lines.append("MEDIUM PRIORITY MODELS (10,000-100,000 downloads)")
            report_lines.append("-" * 80)
            report_lines.append(f"Total: {len(categories['medium_priority'])} models")
            report_lines.append("Top 20:")
            for model in sorted(categories['medium_priority'], 
                              key=lambda x: x.get('downloads', 0), 
                              reverse=True)[:20]:
                model_id = model.get('id', 'unknown')
                downloads = model.get('downloads', 0)
                report_lines.append(f"  {model_id:50s} {downloads:>10,} downloads")
            report_lines.append("")
        
        # Commands to scrape
        report_lines.append("SUGGESTED SCRAPING COMMANDS")
        report_lines.append("-" * 80)
        report_lines.append("High priority models (copy and run):")
        report_lines.append("")
        for model in sorted(categories['high_priority'], 
                          key=lambda x: x.get('downloads', 0), 
                          reverse=True)[:10]:
            model_id = model.get('id', 'unknown')
            report_lines.append(f"python model_scraper.py {model_id}")
        report_lines.append("")
        
        report_lines.append("=" * 80)
        
        report_text = '\n'.join(report_lines)
        
        # Save to file if specified
        if output_file:
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(report_text)
            print(f"\nReport saved to: {output_file}")
        
        return report_text


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description='Find models on HuggingFace that are missing from MOT'
    )
    parser.add_argument(
        '--min-downloads',
        type=int,
        default=1000,
        help='Minimum number of downloads to consider (default: 1000)'
    )
    parser.add_argument(
        '--limit',
        type=int,
        default=1000,
        help='Maximum number of models to fetch from HuggingFace (default: 1000)'
    )
    parser.add_argument(
        '--models-dir',
        default='../models',
        help='Path to MOT models directory (default: ../models)'
    )
    parser.add_argument(
        '--output',
        help='Output file for report (default: print to console)'
    )
    parser.add_argument(
        '--model-type',
        help='Filter by model type (e.g., text-generation, image-to-text)'
    )
    
    args = parser.parse_args()
    
    # Initialize finder
    finder = MissingModelsFinder(models_dir=args.models_dir)
    
    print("=" * 80)
    print("MODEL OPENNESS TOOL - MISSING MODELS FINDER")
    print("=" * 80)
    print()
    
    # Get MOT models
    mot_models = finder.get_mot_models()
    
    # Get HuggingFace models
    hf_models = finder.get_huggingface_models(
        min_downloads=args.min_downloads,
        limit=args.limit,
        model_type=args.model_type
    )
    
    # Find missing models
    print("Comparing models...")
    missing_models = []
    
    for hf_model in hf_models:
        is_present, matched_file = finder.is_model_in_mot(hf_model, mot_models)
        if not is_present:
            missing_models.append(hf_model)
    
    print(f"Found {len(missing_models)} missing models\n")
    
    # Generate report
    report = finder.generate_report(
        missing_models,
        mot_models,
        output_file=args.output
    )
    
    # Print report
    print(report)
    
    # Summary
    print("\n" + "=" * 80)
    print("NEXT STEPS")
    print("=" * 80)
    print("1. Review the high priority models above")
    print("2. Use model_scraper.py to generate draft YAML files")
    print("3. Manually review and validate the generated files")
    print("4. Submit PRs to add models to MOT database")
    print()
    print("Example workflow:")
    print("  python model_scraper.py meta-llama/Llama-3-8B")
    print("  # Review and edit ../models/Llama-3-8B.yml")
    print("  php ../scripts/validate-model.php ../models/Llama-3-8B.yml")
    print("  # Submit PR")
    print()


if __name__ == '__main__':
    main()

