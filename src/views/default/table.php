<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\StringHelper;

/**
 * @var string $slug
 * @var string $stamp
 * @var string $name
 * @var \yii\web\View $this
 * @var \yii\data\ArrayDataProvider $dataProvider
 * @var \kriss\logReader\models\ReportSearch $searchModel
 */

$this->title = $name;
$this->params['breadcrumbs'][] = ['label' => 'Logs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $name;

$this->registerCss(<<<CSS
    .table-parsed {
        table-layout: fixed;
        margin-top: -36px;
    }
    
    .table-parsed > thead > tr > td,
    .table-parsed > tbody > tr > td {
        border: 1px solid #000;
    }
    
    .table-parsed > thead > tr > th {
        visibility: hidden;
        border-bottom: 1px solid #000;
    }
    
    .table-parsed > thead > tr > td:last-child {
        padding: 14px 8px;
    }
    
    .table-parsed > thead > tr > th:last-child,
    .table-parsed > thead > tr > td:last-child,
    .table-parsed > tbody > tr > td:last-child {
        width: 62px;
    }
    
    .table-parsed__text {
        cursor: pointer;
        height: 21px;
        overflow: hidden;
        display: block;
        font-weight: 400;
        margin: 0;
    }
    
    .table-parsed__row_level_trace td {
        background-color: #fff;
    }
    
    .table-parsed__row_level_info td {
        background-color: #c9daff;
    }
    
    .table-parsed__row_level_warning td {
        background-color: #fff5b7;
    }
    
    .table-parsed__row_level_error td {
        background-color: #ffc8c8;
    }
    
    .table-parsed__checkbox {
        display: none;
    }
    
    .table-parsed__checkbox:checked + .table-parsed__text {
        height: unset;
    }
CSS
);

$this->registerJsVar('deleteTableItemsContainingUrl', Url::to(['delete-table-items-containing',
    'slug' => $slug,
    'stamp' => $stamp
]));

$this->registerJsVar('deleteAllTableItems', Url::to(['delete-all-table-items',
    'slug' => $slug,
    'stamp' => $stamp
]));

$this->registerJs(<<<JS
    $('#btn-delete-items').on('click', function() {
        const text = $('.report-search-text-cell > input').val().trim();
        if (text.length > 0) {
            if (confirm('Are you sure you want to delete records containing the text "' + text + '"?')) {
                window.location.href = deleteTableItemsContainingUrl + '?text=' + encodeURI(text);
            }
        } else if (confirm('Are you sure you want to delete all records?')) {
            window.location.href = deleteAllTableItems;
        }
    });
JS
);

?>

<?= GridView::widget([
    'tableOptions' => ['class' => 'table table-parsed'],
    'options' => ['class' => 'grid-view table-responsive'],
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'rowOptions' => function ($model) {
        if (in_array($model['level'], ['trace', 'info', 'warning', 'error'])) {
            return ['class' => 'table-parsed__row_level_' . $model['level']];
        }

        return [];
    },
    'columns' => [
        [
            'attribute' => 'text',
            'format' => 'raw',
            'filterOptions' => ['class' => 'report-search-text-cell'],
            'value' => function ($model) {
                $id = 'log-checkbox-' . $model['start'];

                $text = preg_replace('/\n/', ' ', $model['text'], 1);
                $text = trim(nl2br(htmlspecialchars($text)));

                return Html::checkbox(null, false, [
                        'class' => 'table-parsed__checkbox',
                        'id' => $id
                    ]) . Html::label($text, $id, [
                        'class' => 'table-parsed__text'
                    ]);
            }
        ], [
            'class' => '\kriss\logReader\components\CustomActionColumn',
            'template' => '{delete-table-item}',
            'urlCreator' => function ($action, $model) use ($slug, $stamp) {
                return [$action,
                    'slug' => $slug,
                    'stamp' => $stamp,
                    'start' => $model['start'],
                    'end' => $model['end']
                ];
            },
            'buttons' => [
                'delete-table-item' => function ($url) {
                    return Html::a('Delete', $url, [
                        'class' => 'btn btn-xs btn-danger',
                        'data' => ['method' => 'post', 'confirm' => 'Are you sure?'],
                    ]);
                },
            ],
            'filter' => Html::button('Delete', [
                'class' => 'btn btn-xs btn-danger',
                'id' => 'btn-delete-items'
            ]),
        ],
    ],
]) ?>
