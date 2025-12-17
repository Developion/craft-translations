<?php

declare(strict_types=1);

namespace developion\craft\translations\controllers;

use Craft;
use craft\web\Controller;
use developion\craft\translations\records\Settings;

class SettingsController extends Controller
{
	public function actionSave()
	{
		$this->requirePostRequest();
		$translations = $this->request->getRequiredBodyParam('translations');
		Settings::deleteAll();

		try {
			foreach ($translations as $translation) {
				$transactions = Craft::$app->getDb()->beginTransaction();
				$settings = new Settings();
				$settings->siteId = 1;
				$settings->original = $translation['original'];
				$settings->translate = $translation['translate'];
				$settings->pluginHandle = $translation['pluginHandle'];

				$settings->save();

				$transactions->commit();
			}
		} catch (\Throwable $th) {
			$transactions->rollBack();
			return $th->getMessage();
		}
	}

	public function actionGeneral()
	{
		$translations = $this->getTranslations();
		$sites = Craft::$app->getSites()->getAllSites();

		$plugins = Craft::$app->plugins->getComposerPluginInfo();

		return $this->renderTemplate('craft-translations/generalSettings.twig', compact('translations', 'navs'));
	}

	public function getTranslations(): array
	{
		$translations = Settings::find()->all();
		$result = [];
		foreach ($translations as $key => $value) {
			$result[$key] = [
				'original' => $value->original,
				'translate' => $value->translate,
				'pluginHandle' => $value->pluginHandle
			];
		}

		return $result;
	}
}
