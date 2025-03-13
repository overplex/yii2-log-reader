<?php

namespace kriss\logReader\models;

use yii\base\Model;
use yii\data\ArrayDataProvider;

class ReportSearch extends Model
{
    public $text;

    private $models = [];


    public function rules()
    {
        return [
            [['text'], 'safe'],
        ];
    }

    public function setModels($models)
    {
        $this->models = $models;
    }

    public function search($params)
    {
        $this->load($params);

        if (!$this->validate()) {
            return new ArrayDataProvider([
                'allModels' => [],
            ]);
        }

        if ($this->text !== null) {
            $this->text = trim($this->text);
            if ($this->text !== '') {
                $this->models = array_filter($this->models, function ($item) {
                    return stripos($item['text'], $this->text) !== false;
                });
            }
        }

        return new ArrayDataProvider([
            'allModels' => $this->models,
        ]);
    }
}