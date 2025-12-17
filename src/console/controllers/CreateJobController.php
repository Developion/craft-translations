<?php

declare(strict_types=1);

namespace developion\craft\translations\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Db;
use craft\helpers\Queue;
use developion\craft\translations\jobs\PrepareRegisteredTranslationJob;
use developion\craft\translations\Plugin;
use developion\craft\translations\records\Collection;
use developion\craft\translations\records\RegisteredTranslations;

/**
 * Create Job controller
 */
class CreateJobController extends Controller
{
	public $defaultAction = 'index';

	public function options($actionID): array
	{
		$options = parent::options($actionID);
		switch ($actionID) {
			case 'index':
				// $options[] = '...';
				break;
		}
		return $options;
	}

	/**
	 * craft-translations/create-job command
	 */
	public function actionIndex()
	{
		Db::delete('registered_craft_translations');
		Queue::push(new PrepareRegisteredTranslationJob());
	}

	public function actionPopulate()
	{

		$collections = Collection::find()
			->with('translations')
			->orderBy(['handle' => SORT_ASC])
			->all();

		$languages = Plugin::getInstance()->getTranslations()->getLanguages();

		$now = (new \DateTime())->format('Y-m-d H:i:s');

		$data = [];
		foreach ($collections as $collection) {
			$tableValues = RegisteredTranslations::find()
				->where(['collectionId' => $collection->id])
				->all();

			$records = [];
			foreach ($tableValues as $value) {
				$records[] = [
					'original' => $value->text,
					'translate' => '',
				];
			}

			foreach (array_keys($languages) as $lang) {
				$data[] = [
					'collectionId' => $collection->id,
					'language' => $lang,
					'translation' => json_encode($records),
					'dateCreated' => $now,
					'dateUpdated' => $now
				];
			}
		}
		Craft::$app->db->createCommand()->batchInsert(
			'{{%craft_translations}}',
			['collectionId', 'language', 'translations', 'dateCreated', 'dateUpdated'],
			$data
		)->execute();
	}
}
