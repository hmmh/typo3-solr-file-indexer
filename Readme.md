# EXT solr_file_indexer

This extension gives you the capability to index individual documents using Solr.

Apache Tika, which is capable of detecting and extracting metadata from approx. 1200 different file types is used for document content analysis. The configuration for Tika can be implemented directly within the extension, Solr server functionality is then used for parsing.

Individual documents can be added to the search index for the default language or any localisation, likewise site roots can be selected for which the document is to indexed.


## Installation

The extension has the following requirements:

* TYPO3 CMS >= 8.7
* PHP >= 7.0
* EXT: TYPO3 Solr ab 7.0.0

The extension can be installed using composer.

After installation activate the extension within the extension manager.

## Configuration

Example:

````
plugin.tx_solr {
  index {
    queue {
      sys_file_metadata = 1
      sys_file_metadata {
        initialization = HMMH\SolrFileIndexer\IndexQueue\FileInitializer
        indexer = HMMH\SolrFileIndexer\Indexer\FileIndexer
        allowedFileTypes = pdf,doc,docx,xlsx

        fields {
          title = title
          created = crdate
          changed = tstamp

          size_intS = SOLR_RELATION
          size_intS {
            localField = file
            foreignLabelField = size
          }

          fileExtension = SOLR_RELATION
          fileExtension {
            localField = file
            foreignLabelField = extension
          }

          title_stringS = SOLR_RELATION
          title_stringS {
            localField = file
            foreignLabelField = name
          }

          description = description
          keywords = keywords
          author = creator
        }
      }
    }
  }
}
````

To activate the indexing configuration set "plugin.tx_solr.index.queue.sys_file_metadata" to "1".

Configure "allowedFileTypes" with a comma seperated list of permitted file types.

The "fields" parameter provides a mapping from the "sys_file_metadata" fields to the respective Solr "sys_file" fields.

## Adding a document to the search index

Edit the metadata for a document then select the desired site roots within the "Extended" tab and save.

The document will then be added to the Solr index queue, the index queue workers will add the document/metadata to the search index during the next cycle.

Whenever a site root is deleted the document/metadata is automatically removed from the corresponding Solr index queue and search index.

## Scheduler Tasks

There is an Extbase scheduler task that can delete files types (i.e. "sys_file_metadata" and or "pages") from the search index. File types are deleted from the Solr server search index for all languages but only for a specified site root.

