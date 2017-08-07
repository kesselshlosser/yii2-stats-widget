<?php
namespace pavlm\yii\stats\actions;

use Yii;
use yii\base\Action;
use pavlm\yii\stats\data\StatsDataProvider;
use yii\base\InvalidConfigException;
use yii\web\Response;
use pavlm\yii\stats\data\RangePagination;

class StatsAction extends Action
{
    
    /**
     * @var StatsDataProvider|\Closure
     */
    public $dataProvider;
    
    public $dateFormat = 'Y-m-d\TH:i:s';
    
    /**
     * @param string $period
     * @param string $range
     * @param string $start
     * @param string $end
     * @throws InvalidConfigException
     */
    protected function prepare($period = null, $range = null, $start = null, $end = null)
    {
        if (!$this->dataProvider) {
            throw new InvalidConfigException();
        }
        if (($provider = $this->dataProvider) && is_callable($this->dataProvider)) {
            $this->dataProvider = $provider($this);
        }
        $dateStart = null;
        if ($start) {
            $dateStart = \DateTime::createFromFormat($this->dateFormat, $start, $this->dataProvider->timeZone);
        }
        if ($period) {
            $groupInterval = new \DateInterval($period);
            $this->dataProvider->groupInterval = $groupInterval;
        }
        if ($range || $start) {
            $pagination = new RangePagination(
                $range ?: $this->dataProvider->pagination->getInterval(), 
                $dateStart ? $dateStart->getTimestamp() : null);
            $this->dataProvider->pagination = $pagination;
        }
    }
    
    /**
     * @param string $period
     * @param string $range
     * @param string $start
     * @param string $end
     * @return mixed[]
     */
    public function run($period = null, $range = null, $start = null, $end = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->prepare($period, $range, $start, $end);
        
        $prevDate = $this->dataProvider->pagination->getPrevRangeStart();
        $nextDate = $this->dataProvider->pagination->getNextRangeStart();
        $intervalSpec = function ($interval) {
            return trim(preg_replace('#(?<=[A-Z])0.#', '', $interval->format('P%yY%mM%dDT%hH%iM%sS')), 'T');
        };
        
        $data = [
            'stats' => [
                'data' => $this->dataProvider->getDataArray(),
                'totalValue' => $this->dataProvider->getTotalValue(),
            ],
            'state' => [
                'period' => $intervalSpec($this->dataProvider->groupInterval),
                'range' => $intervalSpec($this->dataProvider->pagination->getInterval()),
                'start' => $start,
                'prev' => $prevDate->format($this->dateFormat),
                'next' => $nextDate->format($this->dateFormat),
            ],
        ];
        return $data;
    }
    
}