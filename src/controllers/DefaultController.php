<?php

namespace kriss\logReader\controllers;

use kriss\logReader\Log;
use kriss\logReader\models\CleanForm;
use kriss\logReader\models\ReportSearch;
use kriss\logReader\models\ZipLogForm;
use kriss\logReader\Module;
use Yii;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{
    /**
     * @var Module
     */
    public $module;

    public function actionIndex()
    {
        $this->rememberUrl();
        
        $dataProvider = new ArrayDataProvider([
            'allModels' => $this->module->getLogs(),
            'sort' => [
                'attributes' => [
                    'name',
                    'size' => ['default' => SORT_DESC],
                    'updatedAt' => ['default' => SORT_DESC],
                ],
            ],
            'pagination' => ['pageSize' => 0],
        ]);
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'defaultTailLine' => $this->module->defaultTailLine,
        ]);
    }

    public function actionView($slug, $stamp = null)
    {
        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            return Yii::$app->response->sendFile($log->fileName, basename($log->fileName), [
                'mimeType' => 'text/plain',
                'inline' => true
            ]);
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionTable($slug, $stamp = null)
    {
        $this->rememberUrl();

        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            $content = file_get_contents($log->fileName);
            $models = $this->module->parseLog($content);
            $searchModel = new ReportSearch();
            $searchModel->setModels(array_reverse($models));
            $dataProvider = $searchModel->search(Yii::$app->request->get());

            return $this->render('table', [
                'name' => $log->name,
                'slug' => $slug,
                'stamp' => $stamp,
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
            ]);
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionDeleteTableItem($slug, $start, $stamp = null, $end = null)
    {
        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            $content = file_get_contents($log->fileName);
            $content = $this->module->deleteSection($content, $start, $end);
            file_put_contents($log->fileName, $content);

            Yii::$app->session->setFlash('success', 'Delete table item success');

            return $this->redirectPrevious();
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionDeleteTableItemsContaining($slug, $text, $stamp = null)
    {
        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            $count = 0;
            $text = trim($text);
            $content = file_get_contents($log->fileName);
            $content = $this->module->deleteContaining($content, $text, $count);
            file_put_contents($log->fileName, $content);

            if ($text) {
                Yii::$app->session->setFlash('success', "Delete table items success ($count)");
            } else {
                Yii::$app->session->setFlash('success', "Delete all table items success ($count)");
            }

            return $this->redirect(['table', 'slug' => $slug]);
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionDeleteAllTableItems($slug, $stamp = null)
    {
        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            file_put_contents($log->fileName, '');

            Yii::$app->session->setFlash('success', 'Delete all table items success');

            return $this->redirectPrevious();
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionArchive($slug)
    {
        if ($this->find($slug, null)->archive(date('YmdHis'))) {
            Yii::$app->session->setFlash('success', 'Archive success');
            return $this->redirect(['history', 'slug' => $slug]);
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionHistory($slug)
    {
        $this->rememberUrl();

        $log = $this->find($slug, null);
        $allLogs = $this->module->getHistory($log);

        $fullSize = array_sum(ArrayHelper::getColumn($allLogs, 'size'));

        $dataProvider = new ArrayDataProvider([
            'allModels' => $allLogs,
            'sort' => [
                'attributes' => [
                    'fileName',
                    'size' => ['default' => SORT_DESC],
                    'updatedAt' => ['default' => SORT_DESC],
                ],
                'defaultOrder' => ['updatedAt' => SORT_DESC],
            ],
        ]);

        return $this->render('history', [
            'name' => $log->name,
            'dataProvider' => $dataProvider,
            'fullSize' => $fullSize,
            'defaultTailLine' => $this->module->defaultTailLine,
        ]);
    }

    public function actionZip($slug)
    {
        $log = $this->find($slug, null);
        $model = new ZipLogForm(['log' => $log]);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $result = $model->zip();
            if ($result !== false) {
                Yii::$app->session->setFlash('success', 'Zip success');
                return $this->redirectPrevious();
            } else {
                Yii::$app->session->setFlash('error', 'Zip error: ', implode('<br>', $model->getFirstErrors()));
            }
        }
        return $this->render('zip', [
            'model' => $model,
        ]);
    }

    public function actionClean($slug)
    {
        $log = $this->find($slug, null);
        $model = new CleanForm(['log' => $log]);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $result = $model->clean();
            if ($result !== false) {
                Yii::$app->session->setFlash('success', 'Clean success');
                return $this->redirectPrevious();
            } else {
                Yii::$app->session->setFlash('error', 'Clean error: ', implode('<br>', $model->getFirstErrors()));
            }
        }
        return $this->render('clean', [
            'model' => $model,
        ]);
    }

    public function actionDelete($slug, $stamp = null, $since = null)
    {
        $log = $this->find($slug, $stamp);
        if ($since) {
            if ($log->updatedAt != $since) {
                Yii::$app->session->setFlash('error', 'Delete error: file has updated');
                return $this->redirectPrevious();
            }
        }
        if (unlink($log->fileName)) {
            Yii::$app->session->setFlash('success', 'Delete success');
        } else {
            Yii::$app->session->setFlash('error', 'Delete error');
        }
        return $this->redirectPrevious();
    }

    public function actionDownload($slug, $stamp = null)
    {
        $log = $this->find($slug, $stamp);
        if ($log->isExist) {
            Yii::$app->response->sendFile($log->fileName)->send();
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    public function actionTail($slug, $line = 100, $stamp = null)
    {
        $log = $this->find($slug, $stamp);
        $line = intval($line);
        if ($log->isExist) {
            $result = shell_exec("tail -n {$line} {$log->fileName}");

            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
            return $result;
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    /**
     * @param string $slug
     * @param null|string $stamp
     * @return Log
     * @throws NotFoundHttpException
     */
    protected function find($slug, $stamp)
    {
        if ($log = $this->module->findLog($slug, $stamp)) {
            return $log;
        } else {
            throw new NotFoundHttpException('Log not found.');
        }
    }

    protected function rememberUrl($url = '')
    {
        Url::remember($url, '__logReaderReturnUrl');
    }

    protected function redirectPrevious()
    {
        return $this->redirect(Url::previous('__logReaderReturnUrl'));
    }
}
