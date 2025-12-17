<?php

declare(strict_types=1);

namespace developion\craft\translations\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use developion\craft\translations\migrations\Table;

class RegisteredTranslations extends ActiveRecord
{
	public static function tableName(): string
	{
		return Table::REGISTERED_CRAFT_TRANSLATIONS;
	}

	public function getCollection(): ActiveQuery
	{
		return $this->hasOne(Collection::class, ['id' => 'collectionId']);
	}
}
