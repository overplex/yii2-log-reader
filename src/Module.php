<?php

namespace kriss\logReader;

use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\web\Application;
use yii\web\GroupUrlRule;

/**
 * LogReader module definition class
 *
 * @property Log[] $logs
 * @property integer $totalCount
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * @var array
     */
    public $aliases = [];
    /**
     * @var array
     */
    public $levelClasses = [
        'trace' => 'label-default',
        'info' => 'label-info',
        'warning' => 'label-warning',
        'error' => 'label-danger',
    ];
    /**
     * @var string
     */
    public $defaultLevelClass = 'label-default';
    /**
     * @var int
     */
    public $defaultTailLine = 100;

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof Application) {
            $app->getUrlManager()->addRules([[
                'class' => GroupUrlRule::class,
                'prefix' => $this->id,
                'rules' => [
                    '' => 'default/index',
                    '<action:[\w-]+>/<slug:[\w-]+>' => 'default/<action>',
                    '<action:[\w-]+>' => 'default/<action>',
                ],
            ]], false);
        } else {
            throw new InvalidConfigException('Can use for web application only.');
        }
    }

    /**
     * @return Log[]
     */
    public function getLogs()
    {
        $logs = [];
        foreach ($this->aliases as $name => $alias) {
            $logs[] = new Log($name, $alias);
        }

        return $logs;
    }

    /**
     * @param string $slug
     * @param null|string $stamp
     * @return null|Log
     */
    public function findLog($slug, $stamp)
    {
        foreach ($this->aliases as $name => $alias) {
            if ($slug === Log::extractSlug($name)) {
                return new Log($name, $alias, $stamp);
            }
        }

        return null;
    }

    /**
     * @param Log $log
     * @return Log[]
     */
    public function getHistory(Log $log)
    {
        $logs = [];
        foreach (glob(Log::extractFileName($log->alias, '*')) as $fileName) {
            $logs[] = new Log($log->name, $log->alias, Log::extractFileStamp($log->alias, $fileName));
        }

        return $logs;
    }

    /**
     * @return integer
     */
    public function getTotalCount()
    {
        $total = 0;
        foreach ($this->getLogs() as $log) {
            foreach ($log->getCounts() as $count) {
                $total += $count;
            }
        }

        return $total;
    }

    /**
     * @param string $report
     * @return array
     */
    public function parseLog($report)
    {
        $l = '(trace|info|warning|error)';
        $pattern = "/^(\]?)\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [\]\[\w\-\.]*\[$l\]/Um";

        if (!preg_match_all($pattern, $report, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $result = [];
        $previousPos = 0;
        $previousLevel = '';

        foreach ($matches[0] as $index => $match) {
            $levelPos = $matches[2][$index][0];
            $datePos = ($matches[1][$index][0] === ']') ? $match[1] + 1 : $match[1];

            if ($index > 0) {
                $text = substr($report, $previousPos, $datePos - $previousPos);
                $result[] = [
                    'text' => $text,
                    'level' => $previousLevel,
                    'start' => $previousPos,
                    'end' => $datePos
                ];
            }

            $previousPos = $datePos;
            $previousLevel = $levelPos;
        }

        $text = substr($report, $previousPos);
        $result[] = [
            'text' => $text,
            'level' => $previousLevel,
            'start' => $previousPos,
            'end' => null
        ];

        return $result;
    }

    public function deleteSection($content, $start, $end)
    {
        return ($end === null)
            ? substr_replace($content, PHP_EOL, $start)
            : substr_replace($content, PHP_EOL, $start, $end - $start);
    }

    public function deleteContaining($content, $text, &$count = 0)
    {
        $reports = array_reverse($this->parseLog($content));

        foreach ($reports as $report) {
            if (stripos($report['text'], $text) !== false) {
                $content = $this->deleteSection($content, $report['start'], $report['end']);
                $count++;
            }
        }

        return $content;
    }
}
