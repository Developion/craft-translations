<?php
declare(strict_types=1);

namespace developion\craft\translations\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use developion\craft\translations\migrations\Table;

class Collection extends ActiveRecord
{
	public static function tableName(): string
	{
		return Table::CRAFT_TRANSLATION_COLLECTION;
	}

	public function getTranslations(): ActiveQuery
	{
		return $this->hasMany(Translations::class, ['collectionId' => 'id']);
	}
}
