<?php

namespace developion\craft\translations\jobs;

use Craft;
use craft\queue\BaseJob;
use developion\craft\translations\migrations\Table;
use developion\craft\translations\Plugin;
use Throwable;

class PrepareRegisteredTranslationJob extends BaseJob
{
	public function execute($queue): void
	{
		try {
			$chunks = collect(Plugin::getInstance()->getTranslations()->scanProject());
			$chunks
				->chunk(100)
				->all();
			foreach ($chunks as $key => $chunk) {
				$this->setProgress(
					$queue,
					($key + 1) / count($chunks),
					Craft::t('app', '{step, number} of {total, number}', [
						'step' => $key + 1,
						'total' => count($chunks),
					])
				);

				$categoryId = Plugin::getInstance()->getTranslations()->getCollectionId($chunk['category']);

				Craft::$app->getDb()->createCommand()->insert(
					Table::REGISTERED_CRAFT_TRANSLATIONS,
					[
						'collectionId' => $categoryId,
						'siteId' => 1,
						'text' => $chunk['text'],
						'dateCreated' => new \yii\db\Expression('NOW()'),
						'dateUpdated' => new \yii\db\Expression('NOW()'),
					]
				)->execute();
			}
		} catch (Throwable $error) {
			Craft::info($error->getTrace(), __CLASS__);
			throw $error;
		}
	}

	protected function defaultDescription(): string
	{
		return Craft::t('craft-translations', 'Translations Plugin: Inserting translations');
	}
}
