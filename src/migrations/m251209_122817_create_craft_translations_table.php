<?php

namespace developion\craft\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251209_122817_create_craft_translations_table migration.
 */
class m251209_122817_create_craft_translations_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{
		if (!Craft::$app->getDb()->getTableSchema(Table::CRAFT_TRANSLATIONS)) {
			$this->createTable(Table::CRAFT_TRANSLATIONS, [
				'id' => $this->primaryKey(),
				'collectionId' => $this->integer()->notNull(),
				'language' => $this->string(),
				'name' => $this->string(),
				'translations' => $this->json(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
			]);
		}

		$this->createIndex(
			null,
			Table::CRAFT_TRANSLATIONS,
			['collectionId']
		);

		$this->addForeignKey(
			null,
			Table::CRAFT_TRANSLATIONS,
			['collectionId'],
			Table::CRAFT_TRANSLATION_COLLECTION,
			['id'],
			'RESTRICT',
			'CASCADE'
		);


		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool
	{
		$this->dropTable(Table::CRAFT_TRANSLATIONS);
		return true;
	}
}
