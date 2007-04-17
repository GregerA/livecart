{includeJs file="library/dhtmlxtree/dhtmlXCommon.js"}
{includeJs file="library/dhtmlxtree/dhtmlXTree.js"}
{includeJs file="library/form/Validator.js"}
{includeJs file="library/form/ActiveForm.js"}
{includeJs file="library/SectionExpander.js"}
{includeJs file="library/TabControl.js"}
{includeJs file="library/ActiveList.js"}
{includeJs file="backend/DeliveryZone.js"}

{includeCss file="library/dhtmlxtree/dhtmlXTree.css"}
{includeCss file="library/TabControl.css"}
{includeCss file="library/ActiveList.css"}
{includeCss file="backend/DeliveryZone.css"}

{pageTitle help="deliveryZone"}{t _livecart_delivery_zones}{/pageTitle}
{include file="layout/backend/header.tpl"}

<script type="text/javascript">
    Backend.DeliveryZone.countryGroups = {$countryGroups};
</script>

<div id="deliveryZoneWrapper" class="maxHeight h--50">
	<div id="deliveryZoneBrowserWithControlls">
    	<div id="deliveryZoneBrowser" class="treeBrowser"></div>
        <div id="deliveryZoneBrowserControls">
            <input type="text" name="name" id="newZoneInput" />
            <input type="button" class="button" value="{t _add}" id="newZoneInputButton"  />
            <br />
            <a href="#delete">{t _delete}</a>
        </div>
	</div>
    
    <div id="deliveryZoneManagerContainer" class="managerContainer">
    	<div class="tabContainer">
    		<ul class="tabList tabs">
    			<li id="tabDeliveryZoneCountry" class="tab active">
    				<a href="{link controller=backend.deliveryZone action=countriesAndStates id=_id_}">{t Countries and States}</a>
    				<span class="tabHelp">deliveryZone.countriesAndStates</span>
    			</li>
    			
    			<li id="tabDeliveryZoneShipping" class="tab inactive">
    				<a href="{link controller=backend.deliveryZone action=shippingRates id=_id_}">{t Shipping Rates}</a>
    				<span class="tabHelp">deliveryZone.shippingRates</span>
    			</li>
    			
    			<li id="tabDeliveryZoneTaxes" class="tab inactive">
    				<a href="{link controller=backend.deliveryZone action=taxRates id=_id_}">{t Tax Rates}</a>
    				<span class="tabHelp">deliveryZone.taxRates</span>
    			</li>
			</ul>
    	</div>
    	<div class="sectionContainer maxHeight h--50"></div>
    </div>
</div>

<div id="activeDeliveryZonePath"></div>

{literal}
<script type="text/javascript">
    Backend.DeliveryZone.prototype.Links.edit = '{/literal}{link controller=backend.deliveryZone action=edit}?id=_id_{literal}';
    Backend.DeliveryZone.prototype.Links.save = '{/literal}{link controller=backend.deliveryZone action=save}{literal}';
    Backend.DeliveryZone.prototype.Links.saveCountries = '{/literal}{link controller=backend.deliveryZone action=saveCountries}{literal}';
    Backend.DeliveryZone.prototype.Links.saveStates = '{/literal}{link controller=backend.deliveryZone action=saveStates}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.deleteCityMask = '{/literal}{link controller=backend.deliveryZone action=deleteCityMask}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.saveCityMask = '{/literal}{link controller=backend.deliveryZone action=saveCityMask}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.deleteZipMask = '{/literal}{link controller=backend.deliveryZone action=deleteZipMask}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.saveZipMask = '{/literal}{link controller=backend.deliveryZone action=saveZipMask}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.deleteAddressMask = '{/literal}{link controller=backend.deliveryZone action=deleteAddressMask}{literal}';
    Backend.DeliveryZone.CountriesAndStates.prototype.Links.saveAddressMask = '{/literal}{link controller=backend.deliveryZone action=saveAddressMask}{literal}';
	var zones = new Backend.DeliveryZone({/literal}{$zones}{literal});
</script>
{/literal}

{include file="layout/backend/footer.tpl"}