<?php

namespace app\components;

use Yii;

/**
 * ViewUtils component
 * 
 * Provides utility functions for view rendering
 */
class ViewUtils
{
    /**
     * Show table sorter JavaScript
     * 
     * @param string $tableId Table ID
     * @param string $options JSON options for table sorter
     */
    public function showTableSorter($tableId, $options = '{}')
    {
        // Register table sorter assets if needed
        $view = Yii::$app->view;
        
        // Output table sorter initialization script
        echo "<table id='$tableId' class='dataGrid2'>";
    }
}