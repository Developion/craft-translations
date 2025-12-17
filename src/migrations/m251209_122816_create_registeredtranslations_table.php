<?php

namespace developion\craft\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251209_122816_create_registeredtranslations_table migration.
 */
class m251209_122816_create_registeredtranslations_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{
		if (!Craft::$app->getDb()->getTableSchema(Table::REGISTERED_CRAFT_TRANSLATIONS)) {
			$this->createTable(Table::REGISTERED_CRAFT_TRANSLATIONS, [
				'id' => $this->primaryKey(),
				'collectionId' => $this->integer()->notNull(),
				'siteId' => $this->integer()->notNull(),
				'text' => $this->text(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
		}

		$this->createIndex(
			null,
			Table::REGISTERED_CRAFT_TRANSLATIONS,
			['collectionId']
		);

		$this->addForeignKey(
			null,
			Table::REGISTERED_CRAFT_TRANSLATIONS,
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
		$this->dropTable(Table::REGISTERED_CRAFT_TRANSLATIONS);
		return true;
	}
}
