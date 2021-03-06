<?php

$config = \Pimcore\WorkflowManagement\Workflow\Config::getWorkflowManagementConfig(true);
if (!$config) {
    return;
}

$config = $config['workflows'];

$workflows = [];

if(is_array($config)) {
    foreach ($config as $workflow) {
        $workflow['creationDate'] = \Carbon\Carbon::now()->getTimestamp();
        $workflow['modificationDate'] = \Carbon\Carbon::now()->getTimestamp();

        $workflows[$workflow['id']] = $workflow;
    }
}

$contents = to_php_data_file_format($workflows);
\Pimcore\File::putPhpFile(\Pimcore\Config::locateConfigFile('workflowmanagement.php'), $contents);
