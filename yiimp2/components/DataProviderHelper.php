<?php

namespace app\components;

use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\Sort;
use yii\data\Pagination;

/**
 * DataProviderHelper provides utilities for creating data providers
 * with consistent sorting, filtering, and pagination
 */
class DataProviderHelper
{
    /**
     * Creates an ActiveDataProvider with default configuration
     * 
     * @param \yii\db\ActiveQuery $query The query to use
     * @param array $config Additional configuration options
     * @return ActiveDataProvider
     */
    public static function createActiveProvider($query, $config = [])
    {
        $defaultConfig = [
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
                'pageSizeParam' => 'per-page',
                'pageSizeLimit' => [1, 100],
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ];
        
        $config = array_merge($defaultConfig, $config);
        
        return new ActiveDataProvider($config);
    }
    
    /**
     * Creates an ArrayDataProvider with default configuration
     * 
     * @param array $data The data array
     * @param array $config Additional configuration options
     * @return ArrayDataProvider
     */
    public static function createArrayProvider($data, $config = [])
    {
        $defaultConfig = [
            'allModels' => $data,
            'pagination' => [
                'pageSize' => 20,
                'pageSizeParam' => 'per-page',
                'pageSizeLimit' => [1, 100],
            ],
            'sort' => [
                'attributes' => [],
            ],
        ];
        
        $config = array_merge($defaultConfig, $config);
        
        return new ArrayDataProvider($config);
    }
    
    /**
     * Creates a Sort object with common attributes
     * 
     * @param array $attributes Sort attributes configuration
     * @param array $defaultOrder Default sort order
     * @return Sort
     */
    public static function createSort($attributes, $defaultOrder = [])
    {
        return new Sort([
            'attributes' => $attributes,
            'defaultOrder' => $defaultOrder,
        ]);
    }
    
    /**
     * Creates a Pagination object with default configuration
     * 
     * @param int $totalCount Total number of items
     * @param int $pageSize Items per page
     * @return Pagination
     */
    public static function createPagination($totalCount, $pageSize = 20)
    {
        return new Pagination([
            'totalCount' => $totalCount,
            'pageSize' => $pageSize,
            'pageSizeParam' => 'per-page',
            'pageSizeLimit' => [1, 100],
        ]);
    }
    
    /**
     * Applies search filters to a query
     * 
     * @param \yii\db\ActiveQuery $query The query to filter
     * @param array $filters Array of filter conditions
     * @return \yii\db\ActiveQuery
     */
    public static function applyFilters($query, $filters)
    {
        foreach ($filters as $attribute => $value) {
            if ($value !== null && $value !== '') {
                if (is_array($value)) {
                    $query->andWhere(['in', $attribute, $value]);
                } elseif (is_string($value) && strpos($value, '%') !== false) {
                    $query->andWhere(['like', $attribute, $value, false]);
                } else {
                    $query->andWhere([$attribute => $value]);
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Applies date range filter to a query
     * 
     * @param \yii\db\ActiveQuery $query The query to filter
     * @param string $attribute The date attribute name
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @return \yii\db\ActiveQuery
     */
    public static function applyDateRangeFilter($query, $attribute, $startDate = null, $endDate = null)
    {
        if ($startDate !== null && $startDate !== '') {
            $query->andWhere(['>=', $attribute, strtotime($startDate . ' 00:00:00')]);
        }
        
        if ($endDate !== null && $endDate !== '') {
            $query->andWhere(['<=', $attribute, strtotime($endDate . ' 23:59:59')]);
        }
        
        return $query;
    }
    
    /**
     * Applies search filter to multiple attributes
     * 
     * @param \yii\db\ActiveQuery $query The query to filter
     * @param array $attributes Array of attribute names to search
     * @param string $searchTerm The search term
     * @return \yii\db\ActiveQuery
     */
    public static function applyMultiAttributeSearch($query, $attributes, $searchTerm)
    {
        if ($searchTerm !== null && $searchTerm !== '') {
            $conditions = ['or'];
            foreach ($attributes as $attribute) {
                $conditions[] = ['like', $attribute, $searchTerm];
            }
            $query->andWhere($conditions);
        }
        
        return $query;
    }
}
