<?php

declare(strict_types=1);

namespace developion\craft\translations\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;
use craft\web\Response;
use craft\web\UploadedFile;
use developion\craft\translations\Plugin;
use developion\craft\translations\records\Collection;
use developion\craft\translations\records\Translations;
use Locale;
use yii\web\BadRequestHttpException;

class TranslationsController extends Controller
{
	public function actionCategories(): Response
	{
		$lang = Craft::$app->request->getQueryParam('lang', Craft::$app->language);
		$service = Plugin::getInstance()->getTranslations();

		$langMap = $service->getLanguages();
		$collection = $service->getTranslationsByCollection($lang);

		return $this->renderTemplate('craft-translations/category/_index.twig', [
			'collection' => $collection,
			'languages' => $langMap,
			'lang' => $lang,
			'workingLang' => Locale::getDisplayLanguage($lang),
		]);
	}

	public function actionCreateCategory(?int $id = null): Response
	{
		$service = Plugin::getInstance()->getTranslations();
		$lang = $service->getCollectionById($id)->language;

		return $this->renderTemplate('craft-translations/category/_create.twig', [
			'data' => $service->getCollectionById($id),
			'workingLang' => Locale::getDisplayLanguage($lang),
		]);
	}

	public function actionSaveCategory(): void
	{
		$this->requirePostRequest();
		$id = (int) $this->request->getBodyParam('id');
		$translations = $this->request->getBodyParam('translations');
		try {
			$translation = Translations::findOne(['id' => $id]);
			$translation->translations = $translations;
			$translation->update();
		} catch (\Throwable $th) {
			dd($th->getMessage());
		}
	}

	public function actionDelete(): Response
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$id = $this->request->getBodyParam('id');
		Translations::deleteAll(['id' => $id]);

		return $this->asSuccess();
	}

	public function actionExport()
	{
		$handle = fopen('php://temp', 'r+');

		fputcsv($handle, [
			'collection',
			'original',
			'translation',
			'language',
		]);

		$rows = (new Query())
			->select([
				'collection' => 'tc.handle',
				't.language',
				't.name',
				't.translations',
				't.dateCreated',
				't.dateUpdated',
			])
			->from(['t' => '{{%craft_translations}}'])
			->innerJoin(
				['tc' => '{{%craft_translation_collecion}}'],
				'[[tc.id]] = [[t.collectionId]]'
			)
			->all();

		foreach ($rows as $row) {
			foreach (json_decode($row['translations']) as $translation) {
				fputcsv($handle, [
					$row['collection'],
					$translation->original ?? '',
					$translation->translate ?? '',
					$row['language'],
				]);
			}
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		$filename = 'translations-export-' . date('Y-m-d_H-i') . '.csv';

		return Craft::$app->response->sendContentAsFile(
			$csv,
			$filename,
			[
				'mimeType' => 'text/csv',
				'inline' => false,
			]
		);
	}

	public function actionImport(): Response
	{
		$file = UploadedFile::getInstanceByName('csv');

		if (!$file) {
			Craft::$app->session->setError('No CSV file uploaded.');
			return $this->redirect('craft-translations');
		}

		$collections = Collection::find()->indexBy('handle')->all();

		$handle = fopen($file->tempName, 'r');

		$header = fgetcsv($handle);

		if ($header !== ['collection', 'original', 'translation', 'language']) {
			Craft::$app->session->setError('Invalid CSV format.');
			fclose($handle);
			return $this->redirect('craft-translations/import');
		}

		while (($row = fgetcsv($handle)) !== false) {
			[$collectionHandle, $original, $translation, $language] = $row;

			if (!$collectionHandle || !$original || !$language) {
				continue;
			}

			$collection = $collections[$collectionHandle] ?? null;
			if (!$collection) {
				continue;
			}

			$category = Translations::find()
				->where([
					'collectionId' => $collection->id,
					'language' => $language,
				])
				->one();

			if (!$category) {
				$category = new Translations();
				$category->collectionId = $collection->id;
				$category->language = $language;
				$category->translations = [];
			}

			$translations = $category->translations ?? [];
			$updated = false;

			foreach ($translations as &$item) {

				if ($item['original'] === $original) {
					$item['translate'] = $translation;
					$updated = true;
					break;
				}
			}

			if (!$updated) {
				$translations[] = [
					'original' => $original,
					'translate' => $translation,
				];
			}

			$category->translations = $translations;
			$category->update(false);
		}

		fclose($handle);


		Craft::$app->session->setNotice('Translations imported successfully.');

		return $this->redirect('craft-translations');
	}


	public function actionExportSingle(): Response
	{
		$handleParam = Craft::$app->request->getRequiredParam('handle');

		$handle = fopen('php://temp', 'r+');

		fputcsv($handle, [
			'collection',
			'original',
			'translation',
			'language',
		]);

		$rows = (new Query())
			->select([
				'collection' => 'tc.handle',
				't.language',
				't.translations',
			])
			->from(['t' => '{{%craft_translations}}'])
			->innerJoin(
				['tc' => '{{%craft_translation_collecion}}'],
				'[[tc.id]] = [[t.collectionId]]'
			)
			->where(['tc.handle' => $handleParam])
			->all();

		if (empty($rows)) {
			throw new BadRequestHttpException('Collection not found or empty.');
		}

		foreach ($rows as $row) {
			$translations = json_decode($row['translations'], true) ?? [];

			foreach ($translations as $translation) {
				fputcsv($handle, [
					$row['collection'],
					$translation['original'] ?? '',
					$translation['translate'] ?? '',
					$row['language'],
				]);
			}
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		$filename = sprintf(
			'translations-%s-%s.csv',
			$handleParam,
			date('Y-m-d_H-i')
		);

		return Craft::$app->response->sendContentAsFile(
			$csv,
			$filename,
			[
				'mimeType' => 'text/csv',
				'inline' => false,
			]
		);
	}


	public function actionImportSingle(): Response
	{
		Craft::$app->response->format = Response::FORMAT_JSON;

		try {
			if (!Craft::$app->request->getIsPost()) {
				return $this->asJson([
					'success' => false,
					'message' => 'Invalid request method.',
				]);
			}

			$file = UploadedFile::getInstanceByName('csv');
			$collectionHandleParam = Craft::$app->request->getParam('handle');

			if (!$file) {
				return $this->asJson([
					'success' => false,
					'message' => 'No CSV file uploaded.',
				]);
			}

			if (!$collectionHandleParam) {
				return $this->asJson([
					'success' => false,
					'message' => 'Missing collection handle.',
				]);
			}

			$collection = Collection::find()
				->where(['handle' => $collectionHandleParam])
				->one();

			if (!$collection) {
				return $this->asJson([
					'success' => false,
					'message' => 'Invalid collection handle.',
				]);
			}

			$handle = fopen($file->tempName, 'r');
			$header = fgetcsv($handle);

			if ($header !== ['collection', 'original', 'translation', 'language']) {
				fclose($handle);
				return $this->asJson([
					'success' => false,
					'message' => 'Invalid CSV format.',
				]);
			}

			$updatedCount = 0;

			while (($row = fgetcsv($handle)) !== false) {
				[$collectionHandle, $original, $translation, $language] = $row;

				if (
					$collectionHandle !== $collectionHandleParam ||
					!$original ||
					!$language
				) {
					continue;
				}

				$category = Translations::find()
					->where([
						'collectionId' => $collection->id,
						'language' => $language,
					])
					->one();

				if (!$category) {
					continue;
				}

				$translations = $category->translations ?? [];
				$changed = false;

				foreach ($translations as $index => $item) {
					if (($item['original'] ?? null) === $original) {
						if (($item['translate'] ?? null) !== $translation) {
							$translations[$index]['translate'] = $translation;
							$changed = true;
						}
						break;
					}
				}

				if (!$changed) {
					continue;
				}

				$category->translations = array_values($translations);
				$category->save(false);
				$updatedCount++;
			}

			fclose($handle);

			return $this->asJson([
				'success' => true,
				'message' => "Import finished. Updated {$updatedCount} translations.",
			]);

		} catch (\Throwable $e) {
			Craft::error($e->getMessage(), __METHOD__);

			return $this->asJson([
				'success' => false,
				'message' => 'Server error during import.',
			]);
		}
	}
}
