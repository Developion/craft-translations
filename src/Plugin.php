<?php

declare(strict_types=1);

namespace developion\craft\translations;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use developion\craft\translations\i18n\CustomMessageSource;
use developion\craft\translations\models\Settings;
use developion\craft\translations\records\Collection;
use developion\craft\translations\services\Translations;
use developion\craft\translations\traits\Services;
use developion\craft\translations\web\assets\TranslationsAsset;
use yii\i18n\MissingTranslationEvent;
use yii\i18n\PhpMessageSource;

/**
 * Craft Translations plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Developion <admin@developion.com>
 * @copyright Developion
 * @property-read Translations $translations
 * @license MIT
 */
class Plugin extends BasePlugin
{
	use Services;

	public string $schemaVersion = '1.0.0';
	public bool $hasCpSettings = true;
	public bool $hasCpSection = true;

	public static function config(): array
	{
		return [
			'components' => [
				'translations' => Translations::class,
			],
		];
	}

	public function init(): void
	{
		parent::init();

		$this->attachEventHandlers();
		Craft::$app->onInit(function () {});

		if (Craft::$app->getRequest()->getIsCpRequest()) {
			Craft::$app->getView()->registerAssetBundle(
				TranslationsAsset::class
			);
		}

		$cfg = [
			'class' => CustomMessageSource::class,
			'forceTranslation' => true,
		];

		$collection = Collection::find()->all();
		foreach ($collection as $coll) {
			Craft::$app->i18n->translations["$coll->handle*"] = $cfg;
		}
	}

	protected function createSettingsModel(): ?Model
	{
		return Craft::createObject(Settings::class);
	}

	protected function settingsHtml(): ?string
	{
		$service = Plugin::getInstance()->getTranslations();
		return Craft::$app->view->renderTemplate('craft-translations/_settings.twig', [
			'plugin' => $this,
			'translationCategory' => $service->getTranslationCategory(),
			'translationLanguage' => $service->getTranslationLanguage(),
		]);
	}

	private function attachEventHandlers(): void
	{
		// $this->addTranslationStrings();

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				// Show categories on the main page
				$event->rules['craft-translations'] = 'craft-translations/translations/categories';
				// Create new category
				$event->rules['craft-translations/category/'] = 'craft-translations/translations/create-category';
				$event->rules['craft-translations/category/<id:\d+>'] = 'craft-translations/translations/create-category';
				$event->rules['craft-translations/save-category/<id:\d+>'] = 'craft-translations/translations/save-category';

				$event->rules['craft-translations/export'] = 'craft-translations/translations/export';
				$event->rules['craft-translations/import'] = 'craft-translations/translations/import';
			}
		);
	}

	private function addTranslationStrings()
	{
		Event::on(
			PhpMessageSource::class,
			PhpMessageSource::EVENT_MISSING_TRANSLATION,
			function (MissingTranslationEvent $event) {
				Plugin::getInstance()
					->getTranslations()
					->applyMissingTranslation($event);
			}
		);
	}

	public function getCpNavItem(): ?array
	{
		$navItems = parent::getCpNavItem();

		if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
			$navItems['subnav']['settings'] = [
				'label' => Craft::t('craft-translations', 'Settings'),
				'url' => 'settings/plugins/craft-translations',
			];
		}

		return $navItems;
	}
}
