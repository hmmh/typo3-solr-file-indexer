# EXT solr_file_indexer

This extension gives you the capability to index individual documents using Solr.

Apache Tika, which is capable of detecting and extracting metadata from approx. 1200 different file types is used for document content analysis. The configuration for Tika can be implemented directly within the extension, Solr server functionality is then used for parsing.

Individual documents can be added to the search index for the default language or any localisation, likewise site roots can be selected for which the document is to indexed.


## Installation

The extension has the following requirements:

* TYPO3 CMS >= 12.4
* PHP >= 8.1
* EXT: TYPO3 Solr >= 12.0.0

The extension can be installed using composer.

After installation activate the extension within the extension manager.

## Changes in v3

* New mode to enable indexing: The setting of which document should be indexed is no longer made in the metadata. The setting is now made in File collections.
* New command line task which handles the indexing process
* New localization management: There is no longer a global setting to index documents for all languages. This is now controlled via file collections, which can be translated for the appropriate languages.
* Replace Hooks with Events
* Replace scheduler task for cleanup with a command line task
* Remove Dashboard Widgest from this Extension (now available in hmmh/solr-file-indexer-admin)

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

Create or add a File collection, choose the type you want (folder, category, static) and select the desired site roots within the "Search" tab.

You have to set the scheduler task (Execute console command) "solr_file_indexer:item-queue-worker" as a recurring task, i.e. all 10 minutes. The task handles, which files are added or updated in the solr index queue and which has to removed. The index queue workers will add the document/metadata to the search index during the next cycle.

Whenever a file collection is deleted or set to hidden, the documents automatically removed from the corresponding Solr index queue and search index during the next cycle of "solr_file_indexer:item-queue-worker".


In order to index documents for all (or desired) languages, a file collection can either be created for language "All" or localized. A localized file collection can contain a different type and different documents.

## Scheduler Tasks

There is a command line task "solr_file_indexer:delete-by-type" that can delete files types (i.e. "sys_file_metadata") from the search index. File types are deleted from the Solr server search index for all languages but only for a specified site root.

Also there is the task "solr_file_indexer:item-queue-worker" which is relevant for the indexing process and must integrated as recurring task.
