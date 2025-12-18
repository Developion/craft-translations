<?php

namespace developion\craft\translations\i18n;

use craft\db\Query;
use developion\craft\translations\migrations\Table;
use yii\i18n\MessageSource;
use yii\i18n\PhpMessageSource;

class CustomMessageSource extends MessageSource
{
	private PhpMessageSource $fallback;

	public function init(): void
	{
		parent::init();
		$this->fallback = new PhpMessageSource();
	}

	protected function loadMessages($category, $language)
	{
		$fallback = $this->fallback->loadMessages($category, $language) ?? [];

		$lang = strtolower(substr($language, 0, 2));

		$rows = (new Query())
			->select(['t.translations', 't.language'])
			->from(['t' => Table::CRAFT_TRANSLATIONS])
			->innerJoin(['c' => Table::CRAFT_TRANSLATION_COLLECTION], '[[c.id]] = [[t.collectionId]]')
			->where([
				'c.handle'   => $category,
				't.language' => $lang,
			])
			->all();

		$overrides = [];

		foreach ($rows as $row) {
			$decoded = json_decode((string)$row['translations'], true);
			if (!is_array($decoded)) {
				continue;
			}

			if (isset($decoded[0]) && is_array($decoded[0])) {
				foreach ($decoded as $item) {
					if (isset($item['original'], $item['translate'])) {
						$overrides[(string)$item['original']] = (string)$item['translate'];
					}
				}
				continue;
			}

			foreach ($decoded as $orig => $tran) {
				$overrides[(string)$orig] = (string)$tran;
			}
		}

		return array_merge($fallback, $overrides);
	}
}
