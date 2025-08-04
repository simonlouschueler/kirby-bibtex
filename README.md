# BibTeX Plugin for Kirby

A citation plugin for Kirby CMS that works with BetterBibTeX JSON exports from Zotero, allowing you to manage citations and generate bibliographies in APA style.

## Features

- **APA Style Formatting**: All citations and bibliographies follow APA (American Psychological Association) style guidelines
- **Citations**: Convert `@key` references in your content to formatted citations
- **Bibliography**: Generate a complete bibliography list from all BetterBibTeX entries
- **Fallback Logic**: Automatically falls back from page-level to site-level bibliography data
- **Multiple Sources**: Support for both text field and file upload (`.json` files)
- **Rich Metadata**: Supports various item types including books, articles, presentations, interviews, podcasts, films, and more

## Installation

1. Copy the plugin folder to your `site/plugins/` directory
2. The plugin will be automatically loaded by Kirby

## Usage

### Setting up Bibliography Data

You can provide bibliography data in two ways:

1. **Text Field**: Add a `bibliography` field to your page/site blueprint with BetterBibTeX JSON content
2. **File Upload**: Add a `bibfile` field to your page/site blueprint for `.json` file uploads

The plugin follows this priority order:
1. Page-level bibliography (text field)
2. Page-level bibliography (file upload)
3. Site-level bibliography (text field)
4. Site-level bibliography (file upload)

### Exporting from Zotero

1. Install the BetterBibTeX plugin in Zotero
2. Select your library or collection
3. Right-click and choose “Export Library…”
4. Select “BetterBibTeX JSON” as the format
5. Save the `.json` file or copy the content to your Kirby field

### Citations in Content

Use `@key` syntax in your content to create citations:

```markdown
This is a reference to @Fischer-2001-UserModelingHuman.

You can also use complex citations: [as discussed in @Cooper-2014-FaceEssentialsInteraction, pp. 45]
```

### Generating Bibliographies

#### Field Method

Add a field to your blueprint and use the `bibliography()` method:

```php
<?= $page->bibliography()->bibliography() ?>
```

#### Page Method

Use the `getBibliography()` method directly on a page:

```php
<?= $page->getBibliography() ?>
```

#### Site Method

Use the `getBibliography()` method on the site object:

```php
<?= $site->getBibliography() ?>
```

## Supported Item Types

The plugin supports various BetterBibTeX item types with APA-style formatting:

- **Books**: Title, authors, year, edition, publisher
- **Journal Articles**: Title, authors, year, publication title, volume, pages, DOI
- **Web Pages**: Title, authors, year, website title, URL, access date
- **Presentations**: Title, authors, year, presentation type, meeting name, URL
- **Interviews**: Title, authors, year, interview medium, URL
- **Blog Posts**: Title, authors, year, blog title, URL, access date
- **Podcasts**: Title, authors, year, series title, episode number, URL
- **Films**: Title, authors, year, genre, URL
- **Newspaper Articles**: Title, authors, year, publication title, URL, access date

## Example BetterBibTeX JSON Structure

```json
{
  "items": [
    {
      "itemType": "book",
      "title": "About Face: The Essentials of Interaction Design",
      "date": "2014",
      "creators": [
        {
          "firstName": "Alan",
          "lastName": "Cooper",
          "creatorType": "author"
        }
      ],
      "edition": "Fourth edition",
      "publisher": "Wiley",
      "citationKey": "Cooper-2014-FaceEssentialsInteraction"
    },
    {
      "itemType": "presentation",
      "title": "Serious play",
      "date": "2024",
      "creators": [
        {
          "firstName": "Andy",
          "lastName": "Allen",
          "creatorType": "presenter"
        }
      ],
      "presentationType": "Video",
      "meetingName": "Config 2024",
      "url": "https://www.youtube.com/watch?v=wBnIyD5I8mM",
      "citationKey": "Allen-2024-SeriousPlay"
    },
    {
      "itemType": "journalArticle",
      "title": "User Modeling in Human–Computer Interaction",
      "date": "2001",
      "creators": [
        {
          "firstName": "Gerhard",
          "lastName": "Fischer",
          "creatorType": "author"
        }
      ],
      "publicationTitle": "User Modeling and User-Adapted Interaction",
      "volume": "11",
      "pages": "65–86",
      "DOI": "https://doi.org/10.1023/A:1011145532042",
      "citationKey": "Fischer-2001-UserModelingHuman"
    }
  ]
}
```

## Output Examples

### Citations
- Simple: `@Fischer-2001-UserModelingHuman` → Fischer, 2001
- Complex: `[as discussed in @Cooper-2014-FaceEssentialsInteraction, pp. 45]` → as discussed in Cooper, 2014, pp. 45

### Bibliography
The bibliography will be rendered as an unordered list with proper APA-style formatting:

1. Cooper, A. (2014). About Face: The Essentials of Interaction Design (Fourth edition). Wiley.
2. Allen, A. (2024). Serious play [Video]. Config 2024. https://www.youtube.com/watch?v=wBnIyD5I8mM
3. Fischer, G. (2001). User Modeling in Human–Computer Interaction. User Modeling and User-Adapted Interaction, 11, 65–86. https://doi.org/10.1023/A:1011145532042

## Available Methods

### Field Methods
- `bib()` - Render citations in text content
- `citations()` - Alias for `bib()`
- `bibliography()` - Generate full bibliography list

### Page Methods
- `getBibEntries()` - Get raw bibliography entries
- `getBibliography()` - Generate full bibliography list

### Site Methods
- `getBibEntries()` - Get raw bibliography entries
- `getBibliography()` - Generate full bibliography list

## HTML Structure

The bibliography is rendered as an unordered list with the following HTML structure:

```html
<ul class="bibliography">
  <li id="Cooper-2014-FaceEssentialsInteraction">
    <span>Cooper, A.</span> (2014). <i>About Face: The Essentials of Interaction Design</i> (Fourth edition). Wiley.
  </li>
  <li id="Allen-2024-SeriousPlay">
    <span>Allen, A.</span> (2024). <i>Serious play</i> [Video]. Config 2024. <a href="https://www.youtube.com/watch?v=wBnIyD5I8mM"><span>youtube.com</span>/watch?v=wBnIyD5I8mM</a>
  </li>
  <li id="Fischer-2001-UserModelingHuman">
    <span>Fischer, G.</span> (2001). <i>User Modeling in Human–Computer Interaction</i>. User Modeling and User-Adapted Interaction, 11, 65–86. <a href="https://doi.org/10.1023/A:1011145532042"><span>doi.org</span>/10.1023/A:1011145532042</a>
  </li>
</ul>
```

Citations in content are wrapped in spans with the class `citation`:

```html
<p>This is a reference to <span class="citation"><a href="#Fischer-2001-UserModelingHuman">Fischer, 2001</a></span>.</p>

<p>You can also use complex citations: <span class="citation"><a href="#Cooper-2014-FaceEssentialsInteraction">as discussed in Cooper, 2014, pp. 45</a></span></p>
```

You can style these elements with CSS to match your design:

```css
/* Bibliography list styling */
.bibliography {
  /* Your styles here */
}

.bibliography li {
  /* Your styles here */
}

/* Citation styling */
.citation {
  /* Your styles here */
}

.citation a {
  /* Your styles here */
}
```