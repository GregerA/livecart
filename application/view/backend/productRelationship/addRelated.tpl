<span>
    <fieldset class="container">
        <div class="productRelationship_image">
            {if $product.DefaultImage}
                <img src="{$product.DefaultImage.paths[1]}" alt="{$product.DefaultImage.title}" title="{$product.DefaultImage[1].title}" />
            {/if}
        </div>
        <span class="productRelationship_title">{$product.name_lang}</span>
    </fieldset>
    <div class="clear: both"></div>
</span>