.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _users-manual:

For Developers
==============

TypoScript Configuration
------------------------

::

  plugin.tx_solr {
    index {
      queue {
        sys_file_metadata = 1
        sys_file_metadata {
          initialization = HMMH\SolrFileIndexer\IndexQueue\FileInitializer
          indexer = HMMH\SolrFileIndexer\Indexer\FileIndexer
          allowedFileTypes = 'pdf','doc','docx','xlsx'

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

The configuration can be updated as required. The following parameters will be added to EXT:solr:

plugin.tx_solr.index.queue.sys_file_metadata.allowedFileTypes
-------------------------------------------------------------

Specifies the allowed file types to be indexed.

:aspect:`Option path`
      plugin.tx_solr.index.queue.sys_file_metadata.allowedFileTypes

:aspect:`Data type`
      string

:aspect:`Default`
      'pdf','doc','docx','xlsx'

