<?php

namespace BibCite;

class BibParser
{
	public static function load($page)
	{
		// Try to get bibliography from text field first (check current object first, then fall back to site)
		$bibliography = '';
		
		// First, check the current object (page or site)
		if ($page->bibliography()->isNotEmpty()) {
			$bibliography = $page->bibliography()->value();
		}
		// If current object is empty, check current object's content field
		elseif ($page->content()->bibliography()->isNotEmpty()) {
			$bibliography = $page->content()->bibliography()->value();
		}
		// If still empty and this is a page object, fall back to site
		elseif (method_exists($page, 'site') && $page->site() !== $page) {
			// This is a page object, check site level
			if ($page->site()->bibliography()->isNotEmpty()) {
				$bibliography = $page->site()->bibliography()->value();
			}
			// If site level is empty, check site content
			elseif ($page->site()->content()->bibliography()->isNotEmpty()) {
				$bibliography = $page->site()->content()->bibliography()->value();
			}
		}
		
		// If no text content, try file upload as fallback (same priority order)
		if (empty($bibliography)) {
			$bibFile = null;
			
			// First, check the current object
			if ($page->bibfile()->toFile()) {
				$bibFile = $page->bibfile()->toFile();
			}
			// If current object is empty, check current object's content field
			elseif ($page->content()->bibfile()->toFile()) {
				$bibFile = $page->content()->bibfile()->toFile();
			}
			// If still empty and this is a page object, fall back to site
			elseif (method_exists($page, 'site') && $page->site() !== $page) {
				// This is a page object, check site level
				if ($page->site()->bibfile()->toFile()) {
					$bibFile = $page->site()->bibfile()->toFile();
				}
				// If site level is empty, check site content
				elseif ($page->site()->content()->bibfile()->toFile()) {
					$bibFile = $page->site()->content()->bibfile()->toFile();
				}
			}
			
			if ($bibFile && $bibFile->extension() === 'json') {
				$bibliography = $bibFile->read();
			}
		}

		if (empty($bibliography)) {
			return [];
		}

		// Parse as JSON (BetterBibTeX format)
		$jsonData = json_decode($bibliography, true);
		if ($jsonData && isset($jsonData['items']) && is_array($jsonData['items'])) {
			return self::parseJsonBibliography($jsonData);
		}

		return [];
	}

	private static function parseJsonBibliography($jsonData)
	{
		$bib = [];
		
		foreach ($jsonData['items'] as $item) {
			$citationKey = $item['citationKey'] ?? 'unknown';
			
			// Extract author information (first author for display)
			$author = 'Unknown';
			if (isset($item['creators']) && is_array($item['creators']) && !empty($item['creators'])) {
				$firstCreator = $item['creators'][0];
				if (isset($firstCreator['lastName'])) {
					$author = $firstCreator['lastName'];
				} elseif (isset($firstCreator['name'])) {
					$author = $firstCreator['name'];
				}
			}
			
			// Extract all authors for disambiguation comparison
			$allAuthors = self::extractAllAuthorLastNames($item['creators'] ?? []);
			
			// Extract year/date
			$year = self::formatDate($item['date'] ?? '');
			
			$bib[$citationKey] = [
				'type' => $item['itemType'] ?? 'misc',
				'author' => $author,
				'allAuthors' => $allAuthors,
				'year' => $year,
				'data' => $item
			];
		}
		
		// Add disambiguation letters for entries with same author and date
		$bib = self::addDisambiguationLetters($bib);
		
		return $bib;
	}

	public static function render($text, $page)
	{
		// Check if text is null or empty
		if (empty($text)) {
			return '';
		}

		// Load bibliography fresh each time
		$bib = self::load($page);

		// Return original text if no bibliography
		if (empty($bib)) {
			return $text;
		}

		// Handle complex citations: [prefix @key suffix]
		// Example: [Jobs, as quoted in @Fischer-2001-UserModelingHuman, pp. 64]
		// Only match brackets that contain @citation-key pattern
		$text = preg_replace_callback('/\[([^\]]*@[A-Za-z0-9\-_]+[^\]]*)\]/', function ($matches) use ($bib) {
			$content = $matches[1];
			
			// Extract the citation key from the content (first @key pattern found)
			if (preg_match('/@([A-Za-z0-9\-_]+)/', $content, $keyMatch)) {
				$key = $keyMatch[1];
				$data = $bib[$key] ?? ['author' => $key, 'year' => 'n.d.'];
				
				// Split content into prefix and suffix around the @key
				$parts = preg_split('/@' . preg_quote($key, '/') . '/', $content, 2);
				$prefix = trim($parts[0] ?? '');
				$suffix = trim($parts[1] ?? '');
				
				// Ensure there's a space between prefix and author name
				$label = $prefix . (empty($prefix) ? '' : ' ') . $data['author'] . ', ' . $data['year'] . $suffix;

				return '<span class="citation"><a href="#' . $key . '">(' . htmlspecialchars($label) . ')</a></span>';
			}
			
			// If no valid citation key found, return original brackets unchanged
			return '[' . $content . ']';
		}, $text);

		// Handle simple citations: @key
		// Example: @Fischer-2001-UserModelingHuman or @Walker-2003-GutsNewMachine
		// Format: Author <span class="citation"><a href="#key">Year</a></span>
		$text = preg_replace_callback('/@([A-Za-z0-9\-_]+)/', function ($matches) use ($bib) {
			$key = $matches[1];
			$data = $bib[$key] ?? ['author' => $key, 'year' => 'n.d.'];

			return htmlspecialchars($data['author']) . ' <span class="citation"><a href="#' . $key . '">(' . htmlspecialchars($data['year']) . ')</a></span>';
		}, $text);

		return $text;
	}

	public static function getEntries($page = null)
	{
		if ($page) {
			return self::load($page);
		}
		return [];
	}

	public static function renderBibliography($page = null)
	{
		$bib = self::load($page);
		
		if (empty($bib)) {
			return '';
		}
		
		$bibliography = [];
		
		foreach ($bib as $key => $entry) {
			$citation = self::formatJsonCitation($entry['data'], $entry['year']);
			$bibliography[] = '<li id="' . htmlspecialchars($key) . '">' . $citation . '</li>';
		}
		
		if (empty($bibliography)) {
			return '';
		}
		
		return '<ul class="bibliography">' . implode("\n", $bibliography) . '</ul>';
	}

	private static function formatJsonCitation($item, $disambiguatedYear = null)
	{
		$title = $item['title'] ?? '';
		$year = $disambiguatedYear ?? self::formatDate($item['date'] ?? '');
		
		// Format authors
		$formattedAuthors = self::formatJsonAuthors($item['creators'] ?? []);
		
		// Apply Smartypants to various fields if available
		$smartypantsFunction = null;
		if (function_exists('smartypants')) {
			$smartypantsFunction = 'smartypants';
		} elseif (class_exists('Kirby\Text\Smartypants')) {
			$smartypantsFunction = ['\Kirby\Text\Smartypants', 'parse'];
		}
		
		$formattedTitle = $smartypantsFunction ? call_user_func($smartypantsFunction, $title) : $title;
		
		// Format based on entry type
		switch ($item['itemType'] ?? 'misc') {
			case 'book':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['edition'])) {
					$formattedEdition = $smartypantsFunction ? call_user_func($smartypantsFunction, $item['edition']) : $item['edition'];
					$citation .= ' (' . $formattedEdition . ')';
				}
				if (!empty($item['publisher'])) {
					$formattedPublisher = $smartypantsFunction ? call_user_func($smartypantsFunction, $item['publisher']) : $item['publisher'];
					$citation .= '. ' . $formattedPublisher . '.';
				} else {
					$citation .= '.';
				}
				break;
				
			case 'journalArticle':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['publicationTitle'])) {
					$formattedJournal = $smartypantsFunction ? call_user_func($smartypantsFunction, $item['publicationTitle']) : $item['publicationTitle'];
					$citation .= '. ' . $formattedJournal;
					if (!empty($item['volume'])) {
						$citation .= ', ' . htmlspecialchars($item['volume']);
					}
					if (!empty($item['pages'])) {
						$citation .= ', ' . htmlspecialchars($item['pages']);
					}
					$citation .= '.';
				} else {
					$citation .= '.';
				}
				$doi = $item['DOI'] ?? $item['doi'] ?? null;
				if (!empty($doi)) {
					$citation .= '<br><a href="' . htmlspecialchars($doi) . '" target="_blank">' . self::formatUrlLabel($doi) . '</a>';
				}
				break;
				
			case 'webpage':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['websiteTitle'])) {
					$citation .= '. ' . htmlspecialchars($item['websiteTitle']) . '.';
				}
				if (!empty($item['url'])) {
					$accessDate = !empty($item['accessDate']) ? self::formatAccessDate($item['accessDate']) : '';
					$citation .= ' ' . $accessDate . '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'presentation':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['presentationType'])) {
					$citation .= ' [' . htmlspecialchars($item['presentationType']) . '].';
				}
				if (!empty($item['meetingName'])) {
					$citation .= ' ' . htmlspecialchars($item['meetingName']) . '.';
				}
				if (!empty($item['url'])) {
					$citation .= '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'interview':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['interviewMedium'])) {
					$citation .= '. ' . htmlspecialchars($item['interviewMedium']) . '.';
				}
				if (!empty($item['url'])) {
					$citation .= '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'blogPost':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['blogTitle'])) {
					$citation .= '. ' . htmlspecialchars($item['blogTitle']) . '.';
				}
				if (!empty($item['url'])) {
					$accessDate = !empty($item['accessDate']) ? self::formatAccessDate($item['accessDate']) : '';
					$citation .= ' ' . $accessDate . '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'podcast':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['seriesTitle'])) {
					$citation .= '. ' . htmlspecialchars($item['seriesTitle']) . '';
					if (!empty($item['episodeNumber'])) {
						$citation .= ' (No. ' . htmlspecialchars($item['episodeNumber']) . ')';
					}
					$citation .= '.';
				}
				if (!empty($item['url'])) {
					$citation .= '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'film':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['genre'])) {
					$citation .= ' [' . htmlspecialchars($item['genre']) . '].';
				}
				if (!empty($item['url'])) {
					$citation .= '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			case 'newspaperArticle':
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				if (!empty($item['publicationTitle'])) {
					$citation .= '. ' . htmlspecialchars($item['publicationTitle']) . '.';
				}
				if (!empty($item['url'])) {
					$accessDate = !empty($item['accessDate']) ? self::formatAccessDate($item['accessDate']) : '';
					$citation .= ' ' . $accessDate . '<br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				}
				break;
				
			default:
				$citation = '<span>' . htmlspecialchars($formattedAuthors) . '</span> (' . $year . '). <i>' . $formattedTitle . '</i>';
				$doi = $item['DOI'] ?? $item['doi'] ?? null;
				if (!empty($doi)) {
					$citation .= '. <br><a href="' . htmlspecialchars($doi) . '" target="_blank">' . self::formatUrlLabel($doi) . '</a>';
				} elseif (!empty($item['url'])) {
					$citation .= '. <br><a href="' . htmlspecialchars($item['url']) . '" target="_blank">' . self::formatUrlLabel($item['url']) . '</a>';
				} else {
					$citation .= '.';
				}
				break;
		}
		
		return $citation;
	}

	private static function formatJsonAuthors($creators)
	{
		if (empty($creators) || !is_array($creators)) {
			return 'Unknown';
		}
		
		$formattedAuthors = [];
		
		foreach ($creators as $creator) {
			if (isset($creator['lastName'])) {
				$lastName = $creator['lastName'];
				$firstName = $creator['firstName'] ?? '';
				
				// Convert first name to initials
				$initials = self::convertToInitials($firstName);
				$formattedAuthors[] = $lastName . ', ' . $initials;
			} elseif (isset($creator['name'])) {
				$formattedAuthors[] = $creator['name'];
			}
		}
		
		// Format multiple authors
		if (count($formattedAuthors) === 1) {
			return $formattedAuthors[0];
		} elseif (count($formattedAuthors) === 2) {
			return $formattedAuthors[0] . ' & ' . $formattedAuthors[1];
		} else {
			$last = array_pop($formattedAuthors);
			return implode(', ', $formattedAuthors) . ' & ' . $last;
		}
	}

	private static function extractYearFromDate($date)
	{
		if (empty($date)) {
			return 'n.d.';
		}
		
		if (!is_string($date)) {
			return 'n.d.';
		}
		
		// Try ISO 8601 datetime format: YYYY-MM-DDTHH:MM:SSZ (year at the beginning)
		if (preg_match('/^(\d{4})-\d{2}-\d{2}T/', $date, $matches)) {
			return $matches[1];
		}
		
		// Try YYYY-MM-DD format (year at the beginning)
		if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $date, $matches)) {
			return $matches[1];
		}
		
		// Try MM/DD/YYYY or M/D/YYYY format (year at the end, allowing single-digit month/day)
		if (preg_match('/\d{1,2}\/\d{1,2}\/(\d{4})$/', $date, $matches)) {
			return $matches[1];
		}
		
		// Fallback: check if date starts with 4 digits (YYYY format)
		if (preg_match('/^\d{4}/', $date, $matches)) {
			return $matches[0];
		}
		
		return 'n.d.';
	}

	private static function formatDate($date)
	{
		if (empty($date)) {
			return 'n.d.';
		}
		
		if (!is_string($date)) {
			return 'n.d.';
		}
		
		// Try to parse ISO 8601 datetime format: YYYY-MM-DDTHH:MM:SSZ
		$parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $date);
		if ($parsedDate === false) {
			// Try without Z (timezone)
			$parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $date);
		}
		if ($parsedDate === false) {
			// Try with microseconds
			$parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $date);
		}
		if ($parsedDate === false) {
			// Try with timezone offset
			$parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $date);
		}
		
		if ($parsedDate !== false) {
			return $parsedDate->format('Y, F j');
		}
		
		// Try YYYY-MM-DD format
		$parsedDate = \DateTime::createFromFormat('Y-m-d', $date);
		if ($parsedDate !== false) {
			return $parsedDate->format('Y, F j');
		}
		
		// Try MM/DD/YYYY or M/D/YYYY format (with or without leading zeros)
		$parsedDate = \DateTime::createFromFormat('m/d/Y', $date);
		if ($parsedDate === false) {
			// Try with single-digit month/day format (n = month without leading zeros, j = day without leading zeros)
			$parsedDate = \DateTime::createFromFormat('n/j/Y', $date);
		}
		if ($parsedDate !== false) {
			return $parsedDate->format('Y, F j');
		}
		
		// Fallback: return just the year if it starts with 4 digits
		if (preg_match('/^(\d{4})/', $date, $matches)) {
			return $matches[1];
		}
		
		return 'n.d.';
	}

	private static function formatAccessDate($accessDate)
	{
		if (empty($accessDate)) {
			return '';
		}
		
		// Try to parse the date - BetterBibTeX accessDate is typically in ISO format
		$date = null;
		
		// Try different date formats
		$formats = ['Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s', 'Y-m-d', 'Y-m', 'Y'];
		
		foreach ($formats as $format) {
			$date = \DateTime::createFromFormat($format, $accessDate);
			if ($date !== false) {
				break;
			}
		}
		
		if ($date === false) {
			// If we can't parse it, just return the original string
			return 'Retrieved ' . htmlspecialchars($accessDate) . ', from ';
		}
		
		// Format as "Retrieved Month Day, Year, from"
		$formattedDate = $date->format('F j, Y');
		return 'Retrieved ' . $formattedDate . ', from ';
	}

	private static function convertToInitials($firstName)
	{
		$words = explode(' ', trim($firstName));
		$initials = [];
		
		foreach ($words as $word) {
			$word = trim($word);
			if (!empty($word)) {
				$initials[] = substr($word, 0, 1) . '.';
			}
		}
		
		return implode(' ', $initials);
	}

	private static function removeProtocolFromUrl($url)
	{
		// Remove http:// or https:// from the beginning of the URL
		$url = preg_replace('/^https?:\/\//', '', $url);
		// Remove www. from the beginning of the URL
		$url = preg_replace('/^www\./', '', $url);
		return $url;
	}

	private static function formatUrlLabel($url)
	{
		// Remove protocol and www
		$url = self::removeProtocolFromUrl($url);
		// Wrap the domain (first part) into a span, and remove trailing slash
		return preg_replace('/^([^\/]+)(\/.*?)\/?$/', '<span>$1</span>$2', $url);
	}

	/**
	 * Add disambiguation letters (a, b, c, etc.) to entries with the same author last name and date
	 */
	private static function addDisambiguationLetters($bib)
	{
		// Group entries by all authors and normalized year
		$groups = [];
		
		foreach ($bib as $key => $entry) {
			$allAuthors = $entry['allAuthors'] ?? [];
			$year = $entry['year'];
			
			// Normalize year for comparison (extract just the year number or "n.d.")
			$normalizedYear = self::normalizeYearForComparison($year);
			
			// Create a group key from all authors (sorted for consistent comparison) and normalized year
			$authorsKey = implode('|', $allAuthors);
			$groupKey = $authorsKey . '|' . $normalizedYear;
			
			if (!isset($groups[$groupKey])) {
				$groups[$groupKey] = [];
			}
			
			$groups[$groupKey][] = $key;
		}
		
		// Add disambiguation letters to entries in groups with multiple entries
		foreach ($groups as $groupKeys) {
			if (count($groupKeys) > 1) {
				// Sort keys to ensure consistent ordering
				sort($groupKeys);
				
				// Add letters a, b, c, etc.
				$letterIndex = 0;
				foreach ($groupKeys as $key) {
					$originalYear = $bib[$key]['year'];
					$bib[$key]['year'] = self::appendDisambiguationLetter($originalYear, $letterIndex);
					$letterIndex++;
				}
			}
		}
		
		return $bib;
	}

	/**
	 * Extract all author last names from creators array for comparison
	 */
	private static function extractAllAuthorLastNames($creators)
	{
		$lastNames = [];
		
		if (empty($creators) || !is_array($creators)) {
			return ['Unknown'];
		}
		
		foreach ($creators as $creator) {
			if (isset($creator['lastName'])) {
				$lastNames[] = $creator['lastName'];
			} elseif (isset($creator['name'])) {
				// If only name is available, use it as-is
				$lastNames[] = $creator['name'];
			}
		}
		
		// Sort to ensure consistent comparison regardless of order
		sort($lastNames);
		
		return empty($lastNames) ? ['Unknown'] : $lastNames;
	}

	/**
	 * Normalize year string for comparison (extract just the year number or "n.d.")
	 */
	private static function normalizeYearForComparison($year)
	{
		if ($year === 'n.d.') {
			return 'n.d.';
		}
		
		// Extract year number from formats like "2020" or "2020, January 1"
		if (preg_match('/^(\d{4})/', $year, $matches)) {
			return $matches[1];
		}
		
		return $year;
	}

	/**
	 * Append disambiguation letter to year string
	 */
	private static function appendDisambiguationLetter($year, $letterIndex)
	{
		// Convert letter index to letter (0->a, 1->b, etc.)
		$letterChar = chr(97 + $letterIndex); // 97 is ASCII for 'a'
		
		// For "n.d." entries, use "n.d.-a" format instead of "n.d.a"
		if ($year === 'n.d.') {
			return 'n.d.-' . $letterChar;
		}
		
		// Append letter to the year
		return $year . $letterChar;
	}
}
