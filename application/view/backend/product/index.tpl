<div>

<fieldset class="container">
	<ul class="menu">
		<li>
			<a href="#" onclick="Backend.Product.showAddForm(this.parentNode.parentNode.parentNode.parentNode, {$categoryID}); return false;">
				Add New Product
			</a>
			<span class="progressIndicator" style="display: none;"></span>
		</li>
	</ul>  
</fieldset>

<span id="bookmark"></span>

<div style="width: 98%;">
<table class="productHead" id="products_{$categoryID}_header">
	<tr class="headRow">
		<th class="cell_cb"><input type="checkbox" class="checkbox" /></th>
		<th class="first cell_sku">
			<span class="fieldName">sku_</span>
			<input type="text" class="text" id="filter_sku" name="filter_sku" value="{tn SKU}" />
		</th>
		<th class="cell_name">
            <span class="fieldName">name_</span>
    		<input type="text" class="text" id="filter_name" name="filter_name" value="{tn Name}" />                    
        </th>	
		<th class="cell_manuf">
            <span class="fieldName">manufacturer_</span>
    		<input type="text" class="text" id="filter_manufacturer" name="filter_manufacturer" value="{tn Manufacturer}" />  
        </th>	
		<th class="cell_price"><span class="fieldName">price_</span>Price <small>({$currency})</small></th>
		<th class="cell_stock"><span class="fieldName">stockCount_</span>In stock</th>	
		<th class="cell_enabled"><span class="fieldName">isEnabled_</span>Enabled</th>	
	</tr>
</table>
</div>

<div style="width: 98%;">
<table class="activeGrid productList" id="products_{$categoryID}">
	<tbody>
		{include file="backend/product/productList.tpl"}
	</tbody>
</table>
</div>

</div>

{literal}
<script type="text/javascript">
    window.openProduct = function(id, e) 
    {
		Backend.Product.Editor.prototype.setCurrentProductId(id); 
        $('productIndicator_' + id).style.display = '';
		TabControl.prototype.getInstance('productManagerContainer', Backend.Product.Editor.prototype.craftProductUrl, Backend.Product.Editor.prototype.craftProductId); 
        if(Backend.Product.Editor.prototype.hasInstance(id)) 
		{
			Backend.Product.Editor.prototype.getInstance(id);			
		}
//        Event.stop(e);
    }

	var grid = new ActiveGrid($('products_{/literal}{$categoryID}'), '{link controller=backend.product action=lists}', {$totalCount});{literal}
    new ActiveGridFilter($('filter_sku'), grid);
    new ActiveGridFilter($('filter_name'), grid);
    new ActiveGridFilter($('filter_manufacturer'), grid);
</script>
{/literal}