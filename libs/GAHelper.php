<?php

namespace F3CMS;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\ApiCore\ApiException;

class GAHelper extends Helper
{
    const METRIC_PAGE_VIEWS          = 'screenPageViews';
    const METRIC_SESSIONS            = 'sessions';
    const METRIC_ACTIVE_1_DAY_USERS  = 'active1DayUsers';
    const METRIC_ACTIVE_7_DAY_USERS  = 'active7DayUsers';
    const METRIC_ACTIVE_28_DAY_USERS = 'active28DayUsers';

    private $propertyId;
    private $client;

    /**
     * @param string $propertyId            例如 '123456789'
     * @param string $serviceAccountKeyPath
     */
    public function __construct($propertyId, $serviceAccountKeyPath)
    {
        $this->propertyId = $propertyId;
        $this->client     = new BetaAnalyticsDataClient([
            'credentials' => $serviceAccountKeyPath,
        ]);
    }

    /**
     * @param string $start
     * @param string $end
     *
     * @return array [['date' => '2024-05-24', 'cnt' => 123], ...]
     */
    public function byDate(string $start = '28daysAgo', string $end = 'today'): array
    {
        return $this->runReport(['date'], self::METRIC_PAGE_VIEWS, $start, $end);
    }

    /**
     * @param string $start
     * @param string $end
     *
     * @return array [['country' => 'Taiwan', 'cnt' => 123], ...]
     */
    public function byCountry(string $start = '28daysAgo', string $end = 'today'): array
    {
        return $this->runReport(['country'], self::METRIC_PAGE_VIEWS, $start, $end);
    }

    /**
     * 通用報表查詢
     *
     * @param array  $dimensions
     * @param string $metric
     * @param string $start
     * @param string $end
     *
     * @return array
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
            error_log('API Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 維度值格式化
     */
    private static function _formatDimensionValue(string $dimension, string $value): string
    {
        if ('date' === $dimension && preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        }

        return $value;
    }
}
