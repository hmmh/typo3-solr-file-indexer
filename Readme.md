# EXT solr_file_indexer

Mit der Extension können einzelne Dokumente über Solr indexiert werden. Für das Parsen des Dokumentinhaltes wird Apache Tika
verwendet, das ca. 1200 Dateiformate parsen kann. Die Einstellung für Tika kann in der entsprechenden Extension direkt vorgenommen 
werden. Für das Parsing reicht der Solr-Server, der die entsprechende Funktion mitbringt.

Jedes Dokument kann individuell in der Default-Sprache oder für jede Lokalisierung dem Suchindex hinzugefügt werden. Ebenso
kann für jedes Dokument individuell gewählt werden, für welche Siteroots es indexiert werden soll.

## Installation

Die Extension hat folgende Abhängigkeiten
* TYPO3 CMS >= 8.7
* PHP >= 7.0
* EXT: TYPO3 Solr ab 7.0.0
* EXT: TYPO3 Tika ab 2.4.0

Die Extension kann per Composer über den Key "hmmh/solr-file" aus dem Repository 
"ssh://git@bitbucket.hmmh.de:29418/u3uiws/typo3_extension_solr-file.git" installiert werden.

Anschließend muss die Extension im Extensionmanager aktiviert werden.

## Konfiguration

Beispiel:

````
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
````

Um die Indexkonfiguration zu aktivieren muss "plugin.tx_solr.index.queue.sys_file_metadata" auf "1" gesetzt werden.
Unter den "allowedFileTypes" kann eine Liste der Dokumente angegeben werden, die Indexiert werden dürfen. Unter "fields"
sind die einzelnen Mappings der Felder aus "sys_file_metadata" bzw. "sys_file" auf die enstprechenden Solr-Felder
definiert.

## Dokument zum Suchindex hinzufügen

Die Metadaten des Dokuments bearbeiten und durch unter "Erweitert" die gewünschten Siteroots auswählen und speichern. Das
Dokument wird anschließend automatisch in die Index-Queue von Solr hinzugefügt und beim nächsten Durchlauf des Index Queue Workers
dem Suchindex hinzugefügt.

Wenn ein Siteroot entfernt wird, wird das Dokument automatisch aus der Queue und auch aus dem Suchindex des Solr-Servers entfernt.

## Scheduler-Tasks

Es gibt einen Extbase-Scheduler-Task der es ermöglicht, alle Dokumente eines Types vom Suchindex zu entfernen, z.B. 
"sys_file_metadata" oder auch "pages". Alle Dokumente diesen Typs werden dann vom Suchindex des Solr-Servers entfernt 
(für alle Sprachen, aber nur für das angegebene Siteroot)

## Offene Punkte

- Tests, Tests, Tests
- Dokumentation im RST-Format
