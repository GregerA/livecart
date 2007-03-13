/**
 *	Requires rico.js
 *
 */
ActiveGrid = Class.create();

ActiveGrid.prototype = 
{
  	/**
  	 *	Data table element instance
  	 */
  	tableInstance: null,
  	
  	/**
  	 *	Select All checkbox instance
  	 */
  	selectAllInstance: null,

  	/**
  	 *	Data feed URL
  	 */
  	dataUrl: null,
  	
  	/**
  	 *	Rico LiveGrid instance
  	 */
	ricoGrid: null,
  	
  	/**
  	 *	Array containing IDs of selected rows
  	 */
	selectedRows: {},
	
  	/**
  	 *	Set to true when Select All is used (so all records are selected by default)
  	 */
	inverseSelection: false,
	
	filters: {},
  	
	initialize: function(tableInstance, dataUrl, totalCount, options)
  	{
		this.tableInstance = tableInstance;
		this.dataUrl = dataUrl;

		this.ricoGrid = new Rico.LiveGrid(this.tableInstance.id, 15, totalCount, dataUrl, 
								{
								  prefetchBuffer: true, 
								  onscroll: this.onScroll.bind(this),  
								  sortAscendImg: 'http://openrico.org/images/sort_asc.gif',
						          sortDescendImg: 'http://openrico.org/images/sort_desc.gif' 
								}
							);		
	
		var headerRow = this._getHeaderRow();
		this.selectAllInstance = headerRow.getElementsByTagName('input')[0];
		this.selectAllInstance.onclick = this.selectAll.bindAsEventListener(this); 
			
		this.onScroll(this.ricoGrid, 0);
	},
	
	onScroll: function(liveGrid, offset) 
	{
        var rows = this.tableInstance.getElementsByTagName('tr');
		for (k = 0; k < rows.length; k++)
		{
		  	rows[k].onclick = this.selectRow.bindAsEventListener(this);
		  	rows[k].onmouseover = this.highlightRow.bindAsEventListener(this);
		  	rows[k].onmouseout = this.removeRowHighlight.bindAsEventListener(this);
		}
		
		// make header row cells the same width as table cells
		if (rows.length > 0)
		{
			this._levelColumns(rows[0], this._getHeaderRow());	  
		}
		
		Backend.Product.updateHeader(liveGrid, offset);
		
		this._markSelectedRows();
	},
	
	reloadGrid: function()
	{
    	this.ricoGrid.options.requestParameters = [];
        var i = 0;
        for (k in this.filters)
    	{
            if (k.substr(0, 7) == 'filter_')
            {
                this.ricoGrid.options.requestParameters[i++] = 'filters[' + k.substr(7, 1000) + ']' + '=' + this.filters[k];
            }
        }
        this.ricoGrid.buffer.clear();
        this.ricoGrid.resetContents();
        this.ricoGrid.requestContentRefresh(0, true);    
        this.ricoGrid.fetchBuffer(0, false, true);
    },
	
	/**
	 *	Select all rows
	 */
	selectAll: function(e)
	{
		this.selectedRows = new Object;		
		this.inverseSelection = this.selectAllInstance.checked;		
		this._markSelectedRows();
	},
	
	/**
	 *	Mark rows checkbox when a row is clicked
	 */
	selectRow: function(e)
	{
		var row = this._getTargetRow(e);
		var inp = row.getElementsByTagName('input')[0];
		
		id = this._getRecordId(row);
		
		if (!this.selectedRows[id])
		{
			this.selectedRows[id] = 0;  
		}
		
		this.selectedRows[id] = !this.selectedRows[id];
		
		this._selectRow(row);
	},

	/**
	 *	Highlight a row when moving a mouse over it
	 */
	highlightRow: function(event)
	{
		Element.addClassName(this._getTargetRow(event), 'activeGrid_highlight');
	},

	/**
	 *	Remove row highlighting when mouse is moved out of the row
	 */
	removeRowHighlight: function(event)
	{
		Element.removeClassName(this._getTargetRow(event), 'activeGrid_highlight');	  
	},

    setFilterValue: function(key, value)
    {
        this.filters[key] = value;
    },

	_markSelectedRows: function()
	{
		var rows = this.tableInstance.getElementsByTagName('tr');
		for (k = 0; k < rows.length; k++)
		{
			this._selectRow(rows[k]);  
		}	  	
	},
	
	_selectRow: function(rowInstance)
	{
		var id = this._getRecordId(rowInstance);
		var inp = rowInstance.getElementsByTagName('input')[0];
		
		if (inp)
		{
			var checked = this.selectedRows[id];
			if (this.inverseSelection)
			{
			  	checked = !checked;
			}
			
			inp.checked = checked;
		}
	},
	
	_getRecordId: function(rowInstance)
	{
		var inp = rowInstance.getElementsByTagName('input')[0];
		if (!inp)
		{
		  	return 0;
		}
		
		var nameParts = inp.name.split('[');
		var id = nameParts[nameParts.length - 1];
		return id.substr(0, id.length - 1);	  
	},
	
	/**
	 *	Make header and table content columns the same width
	 */
	_levelColumns: function(tableRow, headerRow)
	{
		tableCells = tableRow.getElementsByTagName('td');  
		headerCells = headerRow.getElementsByTagName('th'); 
		
		for (k = 0; k < tableCells.length; k++)
		{
		  	headerCells[k].style.width = tableCells[k].clientWidth + 'px';
		}
	},
	
	/**
	 *	Return event target row element
	 */
	_getTargetRow: function(event)
	{
		target = Event.element(event);

		while (target.tagName != 'TR')  
		{
		  	target = target.parentNode;
		}
		
		return target;
	},
	
	_getHeaderRow: function()
	{
		return $(this.tableInstance.id + '_header').getElementsByTagName('tr')[0];
	}
}

ActiveGridFilter = Class.create();

ActiveGridFilter.prototype = 
{
    element: null,
    
    activeGridInstance: null,
    
    initialize: function(element, activeGridInstance)
    {
        this.element = element;
        this.activeGridInstance = activeGridInstance;
        
        this.element.onclick = this.filterFocus.bindAsEventListener(this);
        this.element.onblur = this.filterBlur.bindAsEventListener(this);        
        this.element.onchange = this.setFilterValue.bindAsEventListener(this);  
        
   		Element.addClassName(this.element, 'activeGrid_filter_blur');          
    },

	filterFocus: function()
	{
		if (!this.element.columnName)
		{
			this.element.columnName = this.element.value;	
		}
		
		if (this.element.value == this.element.columnName)
		{
			this.element.value = '';
		}
		
  		Element.removeClassName(this.element, 'activeGrid_filter_blur');
		Element.addClassName(this.element, 'activeGrid_filter_select');		
	},

	filterBlur: function()
	{
		if ('' == this.element.value)
		{
			this.element.value = this.element.columnName;
		}

		if (this.element.value == this.element.columnName)
		{
    		Element.addClassName(this.element, 'activeGrid_filter_blur');
			Element.removeClassName(this.element, 'activeGrid_filter_select');
		}
	},
	
	setFilterValue: function()
	{
        this.activeGridInstance.setFilterValue(this.element.id, this.element.value);
		this.activeGridInstance.reloadGrid();        
    }
}