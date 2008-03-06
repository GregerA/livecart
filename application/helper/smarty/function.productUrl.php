<?php

ClassLoader::import('application.helper.CreateHandleString');

/**
 * Generates product page URL
 *
 * @param array $params
 * @param Smarty $smarty
 * @return string
 *
 * @package application.helper.smarty
 * @author Integry Systems
 */
function smarty_function_productUrl($params, LiveCartSmarty $smarty)
{
	return createProductUrl($params, $smarty->getApplication());
}

function createProductUrl($params, LiveCart $application)
{
	$product = $params['product'];
	$handle = createHandleString($product['name_lang']);

	$urlParams = array('controller' => 'product',
					   'action' => 'index',
					   'producthandle' => $handle,
					   'id' => $product['ID']);

	$url = $application->getRouter()->createUrl($urlParams, true);

	if (!empty($params['filterChainHandle']))
	{
		$url = $application->getRouter()->setUrlQueryParam($url, 'filters', $params['filterChainHandle']);
	}

	return $url;
}

?>