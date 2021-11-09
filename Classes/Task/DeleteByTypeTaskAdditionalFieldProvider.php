<?php
namespace HMMH\SolrFileIndexer\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Sascha Wilking <sascha.wilking@hmmh.de> hmmh
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;

/**
 * Class DeleteByTypeTaskAdditionalFieldProvider
 *
 * @package HMMH\SolrFileIndexer\Task
 */
class DeleteByTypeTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * Gets additional fields to render in the form to add/edit a task
     *
     * @param array $taskInfo Values of the fields from the add/edit task form
     * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task The task object being edited. Null when adding a task!
     * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return array A two dimensional array, array('Identifier' => array('fieldId' => array('code' => '', 'label' => '', 'cshKey' => '', 'cshLabel' => ''))
     */
    public function getAdditionalFields(array &$taskInfo, $task, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule)
    {
        $additionalFields = [];

        $fieldId = 'task_siteRootPageId';
        $fieldCode = '<input type="text" class="form-control" name="tx_scheduler[' . $fieldId . ']" id="' .
            $fieldId . '" value="' . $task->siteRootPageId . '" >';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Site Root Page ID',
            'cshKey' => '',
            'cshLabel' => ''
        ];

        $fieldId = 'task_reindexing';
        $checked = $task->reindexing ? 'checked="checked"' : '';
        $fieldCode = '<input type="checkbox" class="checkbox" name="tx_scheduler[' . $fieldId . ']" id="' .
            $fieldId . '" value="1" ' . $checked . '>';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Reindexing after delete',
            'cshKey' => '',
            'cshLabel' => ''
        ];

        return $additionalFields;
    }

    /**
     * Validates the additional fields' values
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return bool TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(array &$submittedData, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule)
    {
        $result = true;

        if (empty($submittedData['task_siteRootPageId'])) {
            // @extensionScannerIgnoreLine
            $this->addMessage('Missing site root page ID', FlashMessage::ERROR);
            $result = false;
        }

        return $result;
    }

    /**
     * Takes care of saving the additional fields' values in the task's object
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task Reference to the scheduler backend module
     */
    public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task)
    {
        $task->siteRootPageId = (int)$submittedData['task_siteRootPageId'];
        $task->reindexing = (int)$submittedData['task_reindexing'];
    }
}
