<?php

@include_once __DIR__ . '/lib/BibParser.php';

use Kirby\Cms\App;
use Kirby\Content\Field;

Kirby::plugin('simonlou/kirby-bibtex', [
	'description' => 'BibTeX citation plugin for Kirby CMS',
	'author' => 'Simon Lou',
	'license' => 'MIT',
	'version' => '1.0.0',
	'fieldMethods' => [
		'bib' => function (Field $field) {
			$page = $field->parent();
			$value = $field->value();
			
			// Handle null or empty values
			if ($value === null || empty($value)) {
				return '';
			}
			
			return \BibCite\BibParser::render($value, $page);
		},
		'citations' => function (Field $field) {
			$page = $field->parent();
			$value = $field->value();
			
			// Handle null or empty values
			if ($value === null || empty($value)) {
				return '';
			}
			
			return \BibCite\BibParser::render($value, $page);
		},
		'bibliography' => function (Field $field) {
			$page = $field->parent();
			
			// The field value is not used for bibliography generation
			// Instead, we use the same logic as getBibEntries to find bibliography data
			return \BibCite\BibParser::renderBibliography($page);
		}
	],
	'pageMethods' => [
		'getBibEntries' => function () {
			// Try text field first (check both page and site)
			$bibliography = '';
			
			// Check page level first
			if ($this->bibliography()->isNotEmpty()) {
				$bibliography = $this->bibliography()->value();
			}
			// If page level is empty, check site level
			elseif ($this->site()->bibliography()->isNotEmpty()) {
				$bibliography = $this->site()->bibliography()->value();
			}
			
			// If no text content, try file upload as fallback (check both page and site)
			if (empty($bibliography)) {
				$bibFile = null;
				
				// Check page level first
				if ($this->bibfile()->toFile()) {
					$bibFile = $this->bibfile()->toFile();
				}
				// If page level is empty, check site level
				elseif ($this->site()->bibfile()->toFile()) {
					$bibFile = $this->site()->bibfile()->toFile();
				}
				
				if (!$bibFile || $bibFile->extension() !== 'bib') {
					return [];
				}
			}
			
			return \BibCite\BibParser::getEntries($this);
		},
		'getBibliography' => function () {
			return \BibCite\BibParser::renderBibliography($this);
		}
	],
	'siteMethods' => [
		'getBibEntries' => function () {
			// Try text field first (check site level)
			$bibliography = '';
			
			// Check site level
			if ($this->bibliography()->isNotEmpty()) {
				$bibliography = $this->bibliography()->value();
			}
			
			// If no text content, try file upload as fallback
			if (empty($bibliography)) {
				$bibFile = $this->bibfile()->toFile();
				if (!$bibFile || $bibFile->extension() !== 'bib') {
					return [];
				}
			}
			
			return \BibCite\BibParser::getEntries($this);
		},
		'getBibliography' => function () {
			return \BibCite\BibParser::renderBibliography($this);
		}
	]
]);
