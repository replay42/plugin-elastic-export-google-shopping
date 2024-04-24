<?php

namespace ElasticExportGoogleShopping;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

class ElasticExportGoogleShoppingServiceProvider extends DataExchangeServiceProvider
{
	public function register()
	{
	}

	public function exports(ExportPresetContainer $container)
	{
		$container->add(
			'BK GoogleShopping Plugin v2',
			'ElasticExportGoogleShopping\ResultField\GoogleShopping',
			'ElasticExportGoogleShopping\Generator\GoogleShopping',
            '',
			true,
            true
		);
	}
}
