<?php

declare(strict_types=1);

namespace developion\craft\translations\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TranslationsAsset extends AssetBundle
{
	public function init()
	{
		$this->sourcePath = __DIR__ . '/dist';

		$this->depends = [
			CpAsset::class,
		];

		$this->js = [
			'translation-autocomplete.js',
		];

		$this->css = [
			'translation-autocomplete.css'
		];

		parent::init();
	}
}
