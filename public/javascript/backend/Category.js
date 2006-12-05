if (Backend == undefined)
{
	var Backend = {}
}

Backend.Category = {

	/**
	 * category tab controll instance
	 */
	tabControl: null,

	/**
	 * Category tree browser instance
	 */
	treeBrowser: null,

	/**
	 * Id of currenty selected category. Used for category tab content switching
	 */
	activeCategoryId: null,

	/**
	 * Category module initialization
	 */
	init: function()
	{
		this.initCategoryBrowser();
		this.initTabs();
	},

	/**
	 * Builds category tree browser object (dhtmlxTree) and initializes its params
	 */
	initCategoryBrowser: function()
	{
		this.treeBrowser = new dhtmlXTreeObject("categoryBrowser","","", 0);
		this.treeBrowser.setImagePath("image/backend/dhtmlxtree/");
		this.treeBrowser.setOnClickHandler(this.activateCategory);
		this.treeBrowser.enableDragAndDrop(1);
	},

	initTabs: function()
	{
		this.tabControl = new CategoryTabControl(this.treeBrowser, 'tabList', 'sectionContainer', 'image/indicator.gif');
	},

	/**
	 * Tree browser onClick handler. Activates selected category by realoading active
	 *  tab with category specific data
	 */
	activateCategory: function(categoryId)
	{
		//alert('activating category: ' + categoryId);
		Element.update('activeCategoryPath', Backend.Category.getPath(categoryId));

		Backend.Category.tabControl.switchCategory(categoryId, Backend.Category.activeCategoryId);
		Backend.Category.activeCategoryId = categoryId;
	},

	getPath: function(nodeId)
	{
		var path = new Array();
		var parentId = nodeId;
		var nodeStr = '';
		do
		{
			nodeStr = Backend.Category.treeBrowser.getItemText(parentId)
			path.push(nodeStr);
			parentId = this.treeBrowser.getParentId(parentId)
		}
		while(parentId != 0)

		path = path.reverse();
		var pathStr = path.join(' > ');
		return pathStr;
	},

	createNewBranch: function()
	{
		var url = this.getUrlForNewNode(this.treeBrowser.getSelectedItemId());
		var ajaxRequest = new Ajax.Request(
			url,
			{
				method: 'post',
				parameters: '',
				onComplete: this.afterNewBranchCreated
			});
	},

	afterNewBranchCreated: function(response)
	{
		eval('var newCategory = ' + response.responseText);
		var parentCategoryId = Backend.Category.treeBrowser.getSelectedItemId();
		Backend.Category.treeBrowser.insertNewItem(parentCategoryId, newCategory.ID, newCategory.name, 0, 0, 0, 0, 'SELECT');
		Backend.Category.tabControl.activateTab($('tabMainDetails'), newCategory.ID);
	},

	/**
	 * Gets an URL for creating a new node (uses a globaly defined variable "newNodeUrl")
	 */
	getUrlForNewNode: function(parentNodeId)
	{
		return this.buildUrl(newNodeUrl, parentNodeId);
	},

	getUrlForNodeRemoval: function(nodeId)
	{
		return this.buildUrl(removeNodeUrl, nodeId);
	},

	buildUrl: function(urlPattern, id)
	{
		return urlPattern.replace('%id%', id);
	},

	/**
	 * Removes a selected category (including sub-trees) from a store
	 */
	removeBranch: function()
	{
		var nodeIdToRemove = this.treeBrowser.getSelectedItemId();
		parentNodeId = this.treeBrowser.getParentId(nodeIdToRemove);

		var url = this.getUrlForNodeRemoval(nodeIdToRemove);
		var ajaxRequest = new Ajax.Request(
			url,
			{
				method: 'post'
			});

		this.treeBrowser.deleteItem(nodeIdToRemove, true);
		this.activateCategory(parentNodeId);
	}

}

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

var CategoryTabControl = Class.create();
CategoryTabControl.prototype = {

	activeTab: null,
	indicatorImageName: null,
	treeBrowser: null,
	sectionContainerName: null,

	initialize: function(treeBrowser, tabContainerName, sectionContainerName, indicatorImageName)
	{
		this.treeBrowser = treeBrowser;
		this.sectionContainerName = sectionContainerName;

		if (indicatorImageName != undefined)
		{
			this.indicatorImageName = indicatorImageName;
		}

		var tabList = document.getElementsByClassName("tab");
		for (var i = 0; i < tabList.length; i++)
		{
			tabList[i].onclick = this.handleTabClick.bindAsEventListener(this);
			tabList[i].onmouseover = this.handleTabMouseOver.bindAsEventListener(this);
			tabList[i].onmouseout = this.handleTabMouseOut.bindAsEventListener(this);

			aElementList = tabList[i].getElementsByTagName('a');
			if (aElementList.length > 0)
			{
				// Getting an URL pattern that tab is pointing to by analysing "<A>" element
				tabList[i].url = aElementList[0].href;
				new Insertion.After(aElementList[0], aElementList[0].innerHTML);
				// inserting indicator element which will be show on tab activation
				new Insertion.Before(aElementList[0], '<img src="' + this.indicatorImageName + '" class="tabIndicator" id="' + tabList[i].id + 'Indicator" alt="Tab indicator" style="display:none"/> ');
				Element.remove(aElementList[0]);
			}

			if (tabList[i].id == '')
			{
				tabList[i].id = 'tab' + i;
			}
			if (Element.hasClassName(tabList[i], 'active'))
			{
				this.activeTab = tabList[i];
				var containerId = this.getContainerId(tabList[i].id, treeBrowser.getSelectedItemId());
				if ($(containerId) != undefined)
				{
					Element.show(containerId);
				}
			}
			else
			{
				//Element.hide(this.getContainerId(tabList[i].id, treeBrowser.getSelectedItemId()));
			}
		}
	},

	handleTabMouseOver: function(evt)
	{
		if (this.activeTab != evt.target)
		{
			Element.removeClassName(evt.target, 'inactive');
			Element.addClassName(evt.target, 'hover');
		}
	},

	handleTabMouseOut: function(evt)
	{
		if (this.activeTab != evt.target)
		{
			Element.removeClassName(evt.target, 'hover');
			Element.addClassName(evt.target, 'inactive');
		}
	},

	/**
	 * Tab click event handler (performs tab styling and content activation)
	 */
	handleTabClick: function(evt)
	{
		var targetTab = evt.target;
		this.activateTab(targetTab);
	},

	/**
	 * Activates a given tab of currenty selected category
	 */
	activateTab: function(targetTab, categoryIdToActivate)
	{
		//alert('activating: ' + targetTab.id + " " + categoryIdToActivate);
		if (categoryIdToActivate == undefined)
		{
			var categoryId = this.treeBrowser.getSelectedItemId();
		}
		else
		{
			var categoryId = categoryIdToActivate;
		}
		var tabId = targetTab.id;

		if (this.activeTab == targetTab)
		{
			var containerId = this.getContainerId(targetTab.id, categoryId)
			if ($(containerId) != undefined)
			{
				if (!Element.empty(containerId))
				{
					Element.show(this.getContainerId(targetTab.id, categoryId));
					return;
				}
			}
		}

		if (this.activeTab != null)
		{
			Element.removeClassName(this.activeTab, 'active');
			Element.addClassName(this.activeTab, 'inactive');
			var activeContainerId = this.getContainerId(this.activeTab.id, categoryId);
			if ($(activeContainerId) != undefined)
			{
				Element.hide(activeContainerId);
			}
		}

		this.activeTab = targetTab;
		Element.removeClassName(this.activeTab, 'hover');
		Element.addClassName(this.activeTab, 'active');

		this.loadTabContent(tabId, categoryId);
		Element.show(this.getContainerId(this.activeTab.id, categoryId));
	},

	loadTabContent: function(tabId, categoryId)
	{
		var containerId = this.getContainerId(tabId, categoryId);

		if ($(containerId) == undefined)
		{
			new Insertion.Bottom(this.sectionContainerName, '<div id="' + containerId + '"></div>');
		}
		if (Element.empty(containerId))
		{
			new LiveCart.AjaxUpdater(this.getTabUrl(tabId, categoryId),
									 this.getContainerId(tabId, categoryId),
									 this.getIndicatorId(tabId));
		}
	},

	getIndicatorId: function(tabName)
	{
		return tabName + 'Indicator';
	},

	getContainerId: function(tabName, categoryId)
	{
		return tabName + 'Content_' + categoryId;
	},

	getTabUrl: function(tabName, categoryId)
	{
		var url = $(tabName).url.replace('%id%', categoryId);
		return url;
	},

	/**
	 * Reset content related to a given tab. When tab will be activated content must
	 * be resent
	 */
	resetContent: function(tabObj, categoryId)
	{
		var contentContainerId = this.getContainerId(tabObj.id, categoryId);
		if ($(contentContainerId) != undefined)
		{
			$(contentContainerId).innerHTML = '';
			Element.hide(contentContainerId);
		}
	},

	reloadActiveTab: function()
	{
		categoryId = this.treeBrowser.getSelectedItemId();
		this.resetContent(this.activeTab, categoryId);
		this.activateTab(this.activeTab, categoryId);
	},

	switchCategory: function(currentCategory, previousActiveCategoryId)
	{
		//alert('switching category: from' + previousActiveCategoryId + ' to ' +  currentCategory);
		if (previousActiveCategoryId != null)
		{
			prevContainer = this.getContainerId(this.activeTab.id, previousActiveCategoryId);
			if ($(prevContainer) != undefined)
			{
				Element.hide(prevContainer);
			}
		}
		this.activateTab(this.activeTab, currentCategory);
	},

	getActiveTab: function()
	{
		return this.activeTab;
	},

	setTabUrl: function(tabId, url)
	{
		$('tabId').url = url;
	}
}