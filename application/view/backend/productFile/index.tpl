<div id="productFileMsg_{$productID}" style="display: none;"></div>

{* upper menu *}
<fieldset class="container">
	<ul class="menu" id="productFile_menu_{$productID}">
	    <li><a href="#new_file" id="productFile_new_{$productID}_show">{t _add_new_file}</a></li>
	    <li><a href="#cancel_group" id="productFile_new_{$productID}_cancel" class="hidden">{t _cancel_adding_new_file}</a></li>
	    <li><a href="#new_group" id="productFileGroup_new_{$productID}_show">{t _add_new_group}</a></li>
	    <li><a href="#cancel_group" id="productFileGroup_new_{$productID}_cancel" class="hidden">{t _cancel_adding_file_group}</a></li>
	</ul>
</fieldset>

{* new form *}
<div id="productFileGroup_new_{$productID}_form"></div>
<div id="productFile_new_{$productID}_form"></div>

{* Files with no group *}
<ul id="productFile_list_{$productID}_" class="productFile_list activeList_add_sort activeList_add_edit activeList_add_delete activeList_accept_productFile_list">
{foreach item="productFile" from=$productFilesWithGroups}
    {if $productFile.ProductFileGroup.ID}{php}break;{/php}{/if}
    {if $productFile.ID} 
        <li id="productFile_list_{$productID}_{$productFile.ProductFileGroup.ID}_{$productFile.ID}">
    	    <span class="productFile_item_title">{$productFile.title_lang}</span>
        </li>
    {/if}
{/foreach}
</ul>

{* Files in groups *}
<ul id="productFileGroup_list_{$productID}" class="activeList_add_sort activeList_add_edit activeList_add_delete productFileGroup_list">
{foreach item="productFile" from=$productFilesWithGroups}
    {if !$productFile.ProductFileGroup.ID}{php}continue;{/php}{/if}
    
    {if $lastProductFileGroup != $productFile.ProductFileGroup.ID }
        {if $lastProductFileGroup > 0}</ul></li>{/if}
        <li id="productFileGroup_list_{$productID}_{$productFile.ProductFileGroup.ID}" class="productFileGroup_item">
            <span class="productFileGroup_title">{$productFile.ProductFileGroup.name_lang}</span>
            {include file="backend/productFileGroup/form.tpl"}	
            <ul id="productFile_list_{$productID}_{$productFile.ProductFileGroup.ID}" class="productFile_list activeList_add_sort activeList_add_edit activeList_add_delete activeList_accept_productFile_list">
    {/if}
    
    {if $productFile.ID} {* For empty groups *}
    <li id="productFile_list_{$productID}_{$productFile.ProductFileGroup.ID}_{$productFile.ID}">
    	<span class="productFile_item_title">{$productFile.title_lang}</span>
    </li>
    {/if}

    {assign var="lastProductFileGroup" value=$productFile.ProductFileGroup.ID}
{/foreach}
</ul>


{literal}
<script type="text/javascript">
    Backend.availableLanguages = {/literal}{json array=$languages}{literal};
    
    with(Backend.ProductFile)
    {
        Links.save     = '{/literal}{link controller=backend.productFile action=save}{literal}';
        Links.deleteFile   = '{/literal}{link controller=backend.productFile action=delete}{literal}';
        Links.sort     = '{/literal}{link controller=backend.productFile action=sort}{literal}';
        Links.edit     = '{/literal}{link controller=backend.productFile action=edit}{literal}';
        Links.download     = '{/literal}{link controller=backend.productFile action=download}{literal}';
        
        Messages.areYouSureYouWantToDelete = '{/literal}{t _are_you_sure_you_want_to_delete|addslashes}{literal}';
        
        with(Group)
        {
            Links.save     = '{/literal}{link controller=backend.productFileGroup action=save}{literal}';
            Links.remove   = '{/literal}{link controller=backend.productFileGroup action=delete}{literal}';
            Links.sort     = '{/literal}{link controller=backend.productFileGroup action=sort}?target=productFileGroup_list_{$productID}{literal}';
            Links.edit     = '{/literal}{link controller=backend.productFileGroup action=edit}{literal}';
            
            Messages.areYouSureYouWantToDelete = '{/literal}{t _are_you_sure_you_want_to_delete_group|addslashes}{literal}'
        }
    }    
    // create empty form
    
    $("productFileGroup_new_{/literal}{$productID}{literal}_form").update($("productFileGroup_item_blank").innerHTML);
    $("productFile_new_{/literal}{$productID}{literal}_form").update($("productFile_item_blank").innerHTML);
    var emptyModel = new Backend.ProductFile.Model({Product: {ID: {/literal}{$productID}{literal}}}, Backend.availableLanguages);
    new Backend.ProductFile.Controller($("productFile_new_{/literal}{$productID}{literal}_form").down('.productFile_form'), emptyModel);
    var emptyGroupModel = new Backend.ProductFile.Group.Model({Product: {ID: {/literal}{$productID}{literal}}}, Backend.availableLanguages);
    new Backend.ProductFile.Group.Controller($("productFileGroup_new_{/literal}{$productID}{literal}_form").down('.productFileGroup_form'), emptyGroupModel);
    
    try
    {
        Event.observe($("productFileGroup_new_{/literal}{$productID}{literal}_show"), "click", function(e) 
        {
            Event.stop(e);
            var newForm = Backend.ProductFile.Group.Controller.prototype.getInstance($("productFileGroup_new_{/literal}{$productID}{literal}_form").down('.productFileGroup_form')).showNewForm();
        });

        Event.observe($("productFile_new_{/literal}{$productID}{literal}_show"), 'click', function(e) {
            Event.stop(e);
            var newForm = Backend.ProductFile.Controller.prototype.getInstance($("productFile_new_{/literal}{$productID}{literal}_form").down('.productFile_form')).showNewForm();
        });

        {/literal}    
        var groupList = ActiveList.prototype.getInstance('productFileGroup_list_{$productID}', Backend.ProductFile.Group.Callbacks);  
        ActiveList.prototype.getInstance("productFile_list_{$productID}_", Backend.ProductFile.Callbacks);
        {assign var="lastFileGroup" value="-1"}
        {foreach item="file" from=$productFilesWithGroups}
            {if $file.ProductFileGroup && $lastFileGroup != $file.ProductFileGroup.ID}
                 ActiveList.prototype.getInstance('productFile_list_{$productID}_{$file.ProductFileGroup.ID}', Backend.ProductFile.Callbacks);
            {/if}
            {assign var="lastFileGroup" value=$file.ProductFileGroup.ID}
        {/foreach}
        {literal}
        
        groupList.createSortable();
    }
    catch(e)
    {
        console.info(e);
    }
</script>
{/literal}