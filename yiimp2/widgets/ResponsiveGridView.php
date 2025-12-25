<?php

namespace app\widgets;

use yii\grid\GridView;
use yii\helpers\Html;

/**
 * ResponsiveGridView extends Yii2 GridView with responsive features
 * 
 * This widget provides:
 * - Responsive table layout
 * - Mobile-friendly display
 * - Enhanced sorting and filtering
 * - Pagination
 */
class ResponsiveGridView extends GridView
{
    /**
     * @var bool Whether to make the table responsive with horizontal scrolling
     */
    public $responsive = true;
    
    /**
     * @var bool Whether to enable mobile-friendly stacked layout
     */
    public $mobileStack = false;
    
    /**
     * @var array Additional CSS classes for the table
     */
    public $tableOptions = ['class' => 'table table-striped table-hover'];
    
    /**
     * @var array Options for the responsive wrapper
     */
    public $responsiveWrapperOptions = ['class' => 'table-responsive'];
    
    /**
     * @var array Summary options
     */
    public $summaryOptions = ['class' => 'text-muted mb-2'];
    
    /**
     * @var array Pager options
     */
    public $pager = [
        'class' => 'yii\bootstrap5\LinkPager',
        'options' => ['class' => 'pagination justify-content-center'],
        'linkOptions' => ['class' => 'page-link'],
        'activePageCssClass' => 'active',
        'disabledPageCssClass' => 'disabled',
        'maxButtonCount' => 5,
    ];
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
        // Add mobile stack class if enabled
        if ($this->mobileStack) {
            Html::addCssClass($this->tableOptions, 'table-mobile-stack');
        }
        
        // Set default layout
        if ($this->layout === "{summary}\n{items}\n{pager}") {
            $this->layout = "{summary}\n{items}\n<div class='d-flex justify-content-between align-items-center mt-3'>{pager}</div>";
        }
    }
    
    /**
     * @inheritdoc
     */
    public function run()
    {
        if ($this->responsive) {
            echo Html::beginTag('div', $this->responsiveWrapperOptions);
        }
        
        parent::run();
        
        if ($this->responsive) {
            echo Html::endTag('div');
        }
    }
    
    /**
     * @inheritdoc
     */
    public function renderTableHeader()
    {
        $cells = [];
        foreach ($this->columns as $column) {
            /* @var $column \yii\grid\Column */
            $cells[] = $column->renderHeaderCell();
        }
        $content = Html::tag('tr', implode('', $cells), $this->headerRowOptions);
        
        if ($this->filterPosition === self::FILTER_POS_HEADER) {
            $content = $this->renderFilters() . $content;
        } elseif ($this->filterPosition === self::FILTER_POS_BODY) {
            $content .= $this->renderFilters();
        }
        
        return "<thead>\n" . $content . "\n</thead>";
    }
    
    /**
     * Renders the filter row
     * @return string the rendering result
     */
    public function renderFilters()
    {
        if ($this->filterModel !== null) {
            $cells = [];
            foreach ($this->columns as $column) {
                /* @var $column \yii\grid\Column */
                $cells[] = $column->renderFilterCell();
            }
            
            return Html::tag('tr', implode('', $cells), ['class' => 'filters']);
        }
        
        return '';
    }
}
