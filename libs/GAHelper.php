<?php

namespace F3CMS;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\ApiCore\ApiException;

/**
 * GAHelper 類別提供與 Google Analytics API 的互動功能，
 * 包括報表查詢與數據格式化。
 */
class GAHelper extends Helper
{
    /**
     * Google Analytics 的常用指標常數。
     */
    const METRIC_PAGE_VIEWS          = 'screenPageViews'; // 頁面瀏覽次數
    const METRIC_SESSIONS            = 'sessions';       // 會話數
    const METRIC_ACTIVE_1_DAY_USERS  = 'active1DayUsers'; // 1 天內活躍用戶
    const METRIC_ACTIVE_7_DAY_USERS  = 'active7DayUsers'; // 7 天內活躍用戶
    const METRIC_ACTIVE_28_DAY_USERS = 'active28DayUsers'; // 28 天內活躍用戶

    private $propertyId; // GA Property ID
    private $client;     // GA API 客戶端

    /**
     * 建構子，初始化 Google Analytics 客戶端。
     *
     * @param string $propertyId GA Property ID，例如 '123456789'
     * @param string $serviceAccountKeyPath 服務帳戶金鑰路徑
     */
    public function __construct($propertyId, $serviceAccountKeyPath)
    {
        $this->propertyId = $propertyId;
        $this->client     = new BetaAnalyticsDataClient([
            'credentials' => $serviceAccountKeyPath,
        ]);
    }

    /**
     * 根據日期範圍查詢頁面瀏覽數據。
     *
     * @param string $start 起始日期，預設為 '28daysAgo'
     * @param string $end 結束日期，預設為 'today'
     * @return array 包含日期與頁面瀏覽數的陣列
     */
    public function byDate(string $start = '28daysAgo', string $end = 'today'): array
    {
        return $this->runReport(['date'], self::METRIC_PAGE_VIEWS, $start, $end);
    }

    /**
     * 根據國家查詢頁面瀏覽數據。
     *
     * @param string $start 起始日期，預設為 '28daysAgo'
     * @param string $end 結束日期，預設為 'today'
     * @return array 包含國家與頁面瀏覽數的陣列
     */
    public function byCountry(string $start = '28daysAgo', string $end = 'today'): array
    {
        return $this->runReport(['country'], self::METRIC_PAGE_VIEWS, $start, $end);
    }

    /**
     * 通用報表查詢方法。
     *
     * @param array $dimensions 維度名稱陣列
     * @param string $metric 指標名稱
     * @param string $start 起始日期
     * @param string $end 結束日期
     * @return array 查詢結果陣列
     */
    private function runReport(array $dimensions, string $metric, string $start, string $end): array
    {
        try {
            $request = (new RunReportRequest())
                ->setProperty('properties/' . $this->propertyId)
                ->setDateRanges([
                    new DateRange([
                        'start_date' => $start,
                        'end_date'   => $end,
                    ]),
                ])
                ->setDimensions(array_map(fn ($d) => new Dimension(['name' => $d]), $dimensions))
                ->setMetrics([new Metric(['name' => $metric])]);
            $response = $this->client->runReport($request);

            $result = [];
            foreach ($response->getRows() as $row) {
                $item = [];
                foreach ($row->getDimensionValues() as $i => $dimVal) {
                    $item['x'] = self::_formatDimensionValue($dimensions[$i], $dimVal->getValue());
                }
                $item['y'] = (int) $row->getMetricValues()[0]->getValue();
                $result[]  = $item;
            }

            return $result;
        } catch (ApiException $e) {
            // log error
            // error_log('API Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 格式化維度值。
     *
     * @param string $dimension 維度名稱
     * @param string $value 維度值
     * @return string 格式化後的維度值
     */
    private static function _formatDimensionValue(string $dimension, string $value): string
    {
        if ('date' === $dimension && preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        }

        return $value;
    }
}
