/**
 *	@author Integry Systems
 */

Backend.Settings = Class.create();

Backend.Settings.prototype =
{
  	treeBrowser: null,

  	urls: new Array(),

	initialize: function(categories)
	{
		this.treeBrowser = new dhtmlXTreeObject("settingsBrowser","","", false);
		Backend.Breadcrumb.setTree(this.treeBrowser);

		this.treeBrowser.def_img_x = 'auto';
		this.treeBrowser.def_img_y = 'auto';

		this.treeBrowser.setImagePath("image/backend/dhtmlxtree/");
		this.treeBrowser.setOnClickHandler(this.activateCategory.bind(this));

		this.treeBrowser.showFeedback =
			function(itemId)
			{
				if (!this.iconUrls)
				{
					this.iconUrls = new Object();
				}

				if (!this.iconUrls[itemId])
				{
					this.iconUrls[itemId] = this.getItemImage(itemId, 0, 0);
					var img = this._globalIdStorageFind(itemId).htmlNode.down('img', 2);
					img.originalSrc = img.src;
					img.src = 'image/indicator.gif';
				}
			}

		this.treeBrowser.hideFeedback =
			function(itemId)
			{
				if (null != this.iconUrls[itemId])
				{
					this.iconUrls[itemId] = this.getItemImage(itemId, 0, 0);
					var img = this._globalIdStorageFind(itemId).htmlNode.down('img', 2);
					img.src = img.originalSrc;
					this.iconUrls[itemId] = null;
				}
			}

		this.insertTreeBranch(categories, 0);
		this.treeBrowser.closeAllItems(0);
	},

	insertTreeBranch: function(treeBranch, rootId)
	{
		for (k in treeBranch)
		{
		  	if('function' != typeof treeBranch[k])
		  	{
				this.treeBrowser.insertNewItem(rootId, k, treeBranch[k].name, null, 0, 0, 0, '', 1);
				this.treeBrowser.showItemSign(k, 1);

				if (treeBranch[k].subs)
				{
					this.insertTreeBranch(treeBranch[k].subs, k);
				}
			}
		}
	},

	activateCategory: function(id)
	{
		Backend.Breadcrumb.display(id);
		this.treeBrowser.showFeedback(id);
		var url = this.urls['edit'].replace('_id_', id);
		var upd = new LiveCart.AjaxRequest(url, 'settingsIndicator', function(response) { this.displayCategory(response, id); }.bind(this));
	},

	displayCategory: function(response, id)
	{
		this.treeBrowser.hideFeedback(id);

		if (!response.responseText)
		{
			return false;
		}

		$('settingsContent').update(response.responseText);

		var cancel = document.getElementsByClassName('cancel', $('settingsContent'))[0];
		Event.observe(cancel, 'click', this.resetForm.bindAsEventListener(this));
	},

	resetForm: function(e)
	{
		var el = Event.element(e);
		while (el.tagName != 'FORM')
		{
			el = el.parentNode;
		}

		el.reset();
	},

	save: function(form)
	{
		new LiveCart.AjaxRequest(form, null, this.displaySaveConfirmation.bind(this));
	},

	displaySaveConfirmation: function()
	{
		new Backend.SaveConfirmationMessage(document.getElementsByClassName('yellowMessage')[0]);
	}
}

Backend.Settings.Editor = Class.create();
Backend.Settings.Editor.prototype =
{
	handlers:
	{
		'ENABLED_COUNTRIES':
			function()
			{
				var cont = $('setting_ENABLED_COUNTRIES');
				var menu = cont.insertBefore($('handler_ENABLED_COUNTRIES').cloneNode(true), cont.firstChild);

				var select =
					function(e)
					{
						Event.stop(e);

						var state = Event.element(e).hasClassName('countrySelect');

						checkboxes = $('setting_ENABLED_COUNTRIES').getElementsByTagName('input');

						for (k = 0; k < checkboxes.length; k++)
						{
						  	checkboxes[k].checked = state;
						}
					}

				Event.observe(menu.down('.countrySelect'), 'click', select);
				Event.observe(menu.down('.countryDeselect'), 'click', select);
			},

		'ALLOWED_SORT_ORDER':
			function()
			{
				var values = $('SORT_ORDER').getElementsBySelector('option');
				var change =
					function(e)
					{
						var el = Event.element(e);

						if (!el)
						{
							el = e;
						}

						if (el.checked)
						{
							Element.show(el.param);
						}
						else
						{
							// at least one option must always be selected
							if (this.values)
							{
								var isSelected = false;
								for (k = 0; k < this.values.length; k++)
								{
									if ($('ALLOWED_SORT_ORDER[' + this.values[k].value + ']').checked)
									{
										isSelected = true;
										break;
									}
								}

								if (!isSelected)
								{
									el.checked = true;
									return;
								}
							}

							Element.hide(el.param);
						}
					}

				this.values = values;

				for (k = 0; k < values.length; k++)
				{
					var el = $('ALLOWED_SORT_ORDER[' + values[k].value + ']');
					el.param = values[k];

					Event.observe(el, 'change', change.bind(this));
					change(el);
				}
			},

		'EMAIL_METHOD':
			function()
			{
				var change =
					function()
					{
						var display = ($('EMAIL_METHOD').value == 'SMTP');
						[$('setting_SMTP_SERVER'), $('setting_SMTP_PORT'), $('setting_SMTP_USERNAME'), $('setting_SMTP_PASSWORD')].each(function(element) { if (display) { element.show(); } else {element.hide();} });
					}

				change();

				$('SMTP_PASSWORD').type = 'password';
				Event.observe($('EMAIL_METHOD'), 'change', change);
			},

		'THEME':
			function()
			{
				var img = document.createElement('img');
				img.id = 'themePreview';
				$('setting_THEME').appendChild(img);

				var change =
					function()
					{
						var img = $('themePreview');
						img.src = 'theme' + (this.value != 'none' ? '/' + this.value : '') + '/preview_small.png';
						img.href = 'theme' + (this.value != 'none' ? '/' + this.value : '') + '/preview.png';
						img.title = this.value;
						img.onclick =
							function()
							{
								showLightbox(this);
							}
					}

				change.bind($('THEME'))();

				Event.observe($('THEME'), 'change', change);
			}

	},

	initialize: function(container)
	{
		var settings = container.getElementsBySelector('div.setting');
		for (k = 0; k < settings.length; k++)
		{
			var id = settings[k].id.substr(8);
			if (this.handlers[id])
			{
				this.handlers[id]();
			}
		}
	}
}

Event.observe(window, 'load',
	function()
	{
		window.loadingImage = 'image/loading.gif';
		window.closeButton = 'image/silk/gif/cross.gif';
		initLightbox();
	}
);