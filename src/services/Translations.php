<?php

declare(strict_types=1);

namespace developion\craft\translations\services;

use Craft;
use craft\db\ActiveRecord;
use craft\db\Query;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\models\Site;
use developion\craft\translations\migrations\Table;
use developion\craft\translations\Plugin;
use developion\craft\translations\records\Collection;
use developion\craft\translations\records\RegisteredTranslations;
use developion\craft\translations\records\Translations as TranslationRecord;
use FilesystemIterator;
use Locale;
use yii\base\Component;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use yii\db\Expression;

class Translations extends Component
{

	public function scanProject(array $roots = []): array
	{
		if (!$roots) {
			$roots = $this->defaultRoots();
		}

		$all = array_merge(
			$this->scanTwig($roots),
			$this->scanHtml($roots),
			$this->scanPhp($roots),
			$this->scanJs($roots),
		);

		$seen = [];
		$deduped = [];

		foreach ($all as $row) {
			$key = $row['category'] . '|' . $row['text'];
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$deduped[] = $row;
		}

		return $deduped;
	}

	public function scanTwig(array $roots): array
	{
		return $this->scanByExtension('twig', $roots, $this->twigPatterns(), 'twig');
	}

	public function scanHtml(array $roots): array
	{
		$results = $this->scanByExtension('html', $roots, $this->twigPatterns(), 'html');

		return array_merge(
			$results,
			$this->scanByExtension('htm', $roots, $this->twigPatterns(), 'html')
		);
	}

	private function twigPatterns(): array
	{
		return [
			"/(?<!\\w)
            ' \\s*
                (?<message>(?:\\\\.|[^'\\\\\\r\\n])+?)
            \\s* '
            \\s* \\| \\s* t \\b
            (?:
                \\s* \\(
                    \\s*
                    (?<q>[\"'])
                    (?<category>[^\"'\\),]+)
                    \\k<q>
                    \\s*
                \\)
            )?
            /x",
			'/(?<!\\w)
            " \\s*
                (?<message>(?:\\\\.|[^"\\\\\\r\\n])+?)
            \\s* "
            \\s* \\| \\s* t \\b
            (?:
                \\s* \\(
                    \\s*
                    (?<q>[\"\'])
                    (?<category>[^\"\'\\),]+)
                    \\k<q>
                    \\s*
                \\)
            )?
            /x',
		];
	}

	public function scanPhp(array $roots): array
	{
		$patterns = [
			'/Craft::(?<method>t|translate)\(\s*
                (?<q1>[\'"])(?<category>[^\'"\),]+)\k<q1>\s*,\s*
                (?<q2>[\'"])(?<message>(?:\\\\.|[^\\\\\r\n])+?)\k<q2>
            \s*(?:,|\))
            /sx',
		];

		return $this->scanByExtension('php', $roots, $patterns, 'php');
	}

	public function scanJs(array $roots): array
	{
		$patterns = [
			'/Craft\.(?<method>t|translate)\(\s*
                (?<q1>[\'"])(?<category>[^\'"\),]+)\k<q1>\s*,\s*
                (?<q2>[\'"])(?<message>(?:\\\\.|[^\\\\\r\n])+?)\k<q2>
            \s*\)
            /sx',
		];

		return $this->scanByExtension('js', $roots, $patterns, 'js');
	}

	private function scanByExtension(string $ext, array $roots, array $patterns, string $type): array
	{
		$results = [];
		$extRegex = '/^.+\.' . preg_quote($ext, '/') . '$/i';

		foreach ($roots as $root) {
			$scanPath = Craft::getAlias($root);
			if (!is_dir($scanPath)) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($scanPath, FilesystemIterator::SKIP_DOTS)
			);

			foreach (new RegexIterator($iterator, $extRegex, RegexIterator::GET_MATCH) as $match) {
				$file = $match[0];

				$contents = @file_get_contents($file);
				if ($contents === false) {
					continue;
				}

				$lineOffsets = $this->buildLineOffsets($contents);

				foreach ($patterns as $pattern) {
					if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
						continue;
					}

					foreach ($matches['message'] as $idx => [$text, $pos]) {
						$text = trim($text, "\"' \t\n\r");
						if ($text === '') {
							continue;
						}

						$category = $matches['category'][$idx][0] ?? 'site';
						$category = trim($category, "\"' \t\n\r");

						// HARD SAFETY: only allow plain string categories
						if ($category === '' || preg_match('/[\\$\\(\\)\\[\\]\\{\\}\\:\\>]/', $category)) {
							$category = 'site';
						}

						$filter = match ($type) {
							'php' => 'Craft::' . ($matches['method'][$idx][0] ?? 't'),
							'js'  => 'Craft.' . ($matches['method'][$idx][0] ?? 't'),
							default => 't',
						};

						$results[] = [
							'type'     => $type,
							'file'     => $file,
							'line'     => $this->offsetToLine($pos, $lineOffsets),
							'text'     => $text,
							'filter'   => $filter,
							'category' => $category ?: 'site',
						];
					}
				}
			}
		}

		return $results;
	}

	private function defaultRoots(): array
	{
		$plugins = Craft::$app->getPlugins()->getAllPluginInfo();

		return [
			'@templates',
			'@root/src',
			'@root/modules',
			'@root/plugins',
			'@webroot/assets/js',
			...array_map(fn($p) => $p['basePath'], $plugins),
		];
	}

	private function buildLineOffsets(string $contents): array
	{
		$lines = preg_split("/\R/", $contents);
		$offset = 0;
		$map = [];

		foreach ($lines as $i => $line) {
			$map[$i + 1] = $offset;
			$offset += strlen($line) + 1;
		}

		return $map;
	}

	private function offsetToLine(int $pos, array $map): int
	{
		$line = 1;
		foreach ($map as $ln => $start) {
			if ($start <= $pos) {
				$line = $ln;
			} else {
				break;
			}
		}
		return $line;
	}

	public function getTranslationCategory(): array
	{
		$plugins = Craft::$app->plugins->getComposerPluginInfo();

		$handles = [];

		foreach ($plugins as $handle => $plugin) {
			$handles[] = [
				"app" => "App",
			];

			$handles[] = [
				"site" => "Site",
			];

			$handles[] = [
				"{$handle}" => "{$plugin['name']}"
			];
		}

		return array_merge(...$handles);
	}

	public function getTranslationLanguage()
	{
		return Craft::$app->getI18n()->getSiteLocaleIds();
	}

	public function getTranlsationNavs($siteId)
	{
		return TranslationRecord::findAll(['siteId' => $siteId]);
	}

	public function getRegisteredTranslations()
	{
		$regTrans = RegisteredTranslations::find()->all();
		$results = [];
		foreach ($regTrans as $value) {
			$results[] = [
				'label' => $value->text,
				'value' => $value->text,
			];
		}

		return $results;
	}

	public function applyMissingTranslation(\yii\i18n\MissingTranslationEvent $event)
	{
		$records = TranslationRecord::find()
			->where([
				'language' => $event->language,
			])
			->with('collection')
			->all();
		foreach ($records as $record) {
			if ($event->category != '_craft-cookies') return;

			if (($record->collection->handle ?? null) !== $event->category) continue;

			$pairs = $record->translations ?? [];

			if (is_string($pairs)) {
				$pairs = json_decode($pairs, true) ?: [];
			}
			foreach ((array) $pairs as $item) {
				if (is_object($item)) $item = (array) $item;

				if (!isset($item['translate']) || $item['translate'] === '') continue;

				if ($item['original'] === $event->message) {
					$event->translatedMessage = $item['translate'];
					$event->handled = true;
					return $event;
				}
			}
		}
	}

	public function getSiteId($site): int
	{
		$siteId = Craft::$app->getSites()->getSiteByHandle($site)->getId();
		return $siteId;
	}

	public function getSite(): Site|null
	{
		$sitesService = Craft::$app->getSites();

		return Craft::$app->getRequest()->getIsCpRequest()
			? Cp::requestedSite()
			: $sitesService->getCurrentSite();
	}

	public function getCollectionId(string $handle): int
	{
		$id = (new Query())
			->select('id')
			->from(Table::CRAFT_TRANSLATION_COLLECTION)
			->where(['handle' => $handle])
			->scalar();

		if ($id) {
			return (int)$id;
		}

		Craft::$app->getDb()->createCommand()->insert(
			Table::CRAFT_TRANSLATION_COLLECTION,
			[
				'handle' => $handle,
				'dateCreated' => new Expression('NOW()'),
				'dateUpdated' => new Expression('NOW()'),
			]
		)->execute();

		return (int) Craft::$app->getDb()->getLastInsertID();
	}

	public function getCollection(string $language): ActiveRecord|array
	{
		return Collection::find()
			->where(['language' => $language])
			->all();
	}

	public function getCollectionById(int $id): TranslationRecord
	{
		return TranslationRecord::find()
			->where(['id' => $id])
			->one();
	}

	public function setTableValues(int $id): array
	{
		return $this->getCollectionById($id)->translations;
	}

	public function getLanguages(): array
	{
		$sites = Craft::$app->sites->getAllSites();

		$langMap = [];
		foreach ($sites as $site) {
			$raw = (string)$site->language;
			$base = strtolower(strtok($raw, '-_'));

			$label = class_exists(Locale::class)
				? Locale::getDisplayLanguage($base, $base)
				: strtoupper($base);

			$langMap[$base] = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
		}

		return $langMap;
	}

	public function getTranslationsByCollection(string $language): array
	{
		return TranslationRecord::find()
			->with('collection')
			->where(['language' => $language])
			->all();
	}

	public function getCollectionData()
	{
		$categories = [];

		foreach ($this->scanProject() as $item) {
			if (!empty($item['category'])) {
				$categories[] = $item['category'];
			}
		}

		return array_values(array_unique($categories));
	}

	public function populateCollection()
	{
		$now = Db::prepareDateForDb(new \DateTime());

		$rows = [];

		foreach ($this->getCollectionData() as $collection) {
			$rows[] = [
				'handle' => $collection,
				'dateCreated' => $now,
				'dateUpdated' => $now,
			];
		}

		if (!empty($rows)) {
			Craft::$app->db
				->createCommand()
				->batchInsert(
					Table::CRAFT_TRANSLATION_COLLECTION,
					['handle', 'dateCreated', 'dateUpdated'],
					$rows
				)
				->execute();
		}
	}

	public function populateRegTrans()
	{
		$chunks = collect(Plugin::getInstance()->getTranslations()->scanProject())->all();;
		foreach ($chunks as $chunk) {

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
	}

	public function populateTranslations()
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
