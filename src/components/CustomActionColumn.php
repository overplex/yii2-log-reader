<?php

namespace kriss\logReader\components;

class CustomActionColumn extends \yii\grid\ActionColumn
{
    public $filter = '';


    protected function renderFilterCellContent()
    {
        return trim($this->filter) !== '' ? $this->filter : parent::renderFilterCellContent();
    }
}