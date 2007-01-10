/**
 * ActiveList
 *
 * Sortable list
 *
 * @example
 * <code>
 * <ul id="specField_items_list" class="activeList_add_sort activeList_add_edit activeList_add_delete">
 *    <li id="specField_items_list_96" class="">Item 1</li>
 *    <li id="specField_items_list_95"  class="">Item 2</li>
 *    <li id="specField_items_list_100" class="activeList_remove_sort">Item 3</li>
 *    <li id="specField_items_list_101" class="">Item 4</li>
 *    <li id="specField_items_list_102" class="">Item 5</li>
 * </ul>
 *
 * <script type="text/javascript">
 *     new ActiveList('specField_items_list', {
 *         beforeEdit:     function(li)
 *         {
 *             if(this.isContainerEmpty()) return 'edit.php?id='+this.getRecordId(li)
 *             else his.toggleContainer()
 *         },
 *         beforeSort:     function(li, order) { return 'sort.php?' + order },
 *         beforeDelete:   function(li)
 *         {
 *             if(confirm('Are you sure you wish to remove record #' + this.getRecordId(li) + '?')) return 'delete.php?id='+this.getRecordId(li)
 *         },
 *         afterEdit:      function(li, response) { this.getContainer().innerHTML = response; this.toggleContainer();  },
 *         afterSort:      function(li, response) { alert( 'Record #' + this.getRecordId(li) + ' changed position'); },
 *         afterDelete:    function(li, response)  { this.remove(li); }
 *     });
 * </script>
 * </code>
 *
 * First argument passed to active list constructor is list id, and the second is hash object of callbacks
 * Events in active list will automatically call two actions one before ajax request to server and one after.
 * Those callbacks which are called before the request hase "before" prefix. Those which will be called after - "after".
 *
 * Functions which are called before request must return a link or a false value. If a link returned then
 * request to that link is made. On the other hand if false is returned then no request is send and "after" function
 * is not called. This is useful for caching.
 *
 * Note that there are some usefful function you can use inside your callbacks
 * this.isContainerEmpty() - Returns if container is empty
 * this.getRecordId(li) - Get real item's id (used to identify that item in database)
 * this.getContainer() - Get items container. Also every action has it's own container
 *
 * There are also some usefull variables available to you in callback
 * this - A reference to ActiveList object.
 * li - Current item
 * order - Serialized order
 * response - Ajax response text
 *
 * @version 1.1
 * @author Sergej Andrejev, Rinalds Uzkalns
 *
 */
if (LiveCart == undefined)
{
    var LiveCart = {}
}

ActiveList = Class.create();
ActiveList.prototype = {
    /**
     * Item icons which will apear in top left corner on each item of the list
     *
     * @var Hash
     */
    icons: {
        'sort':     "image/silk/arrow_switch.png",
        'edit':     "image/silk/pencil.png",
        'delete':   "image/silk/cancel.png",
        'view':     "image/silk/zoom.png",
        'progress': "image/silk/additional/animated_progress_brown.gif"
    },

    /**
     * User obligated to pass this callbacks to constructor when he creates
     * new active list.
     *
     * @var array
     */
    requiredCallbacks: [],

    /**
     * When active list is created it depends on automatically generated html
     * content.That means that active list uses class names to find icons and
     * containers in list. Be sure you are using unique prefix
     *
     * @var string
     */
    cssPrefix: 'activeList_',

    /**
     * List order is send back only if last sort accured more then M milliseconds ago.
     * M is that value
     *
     * @var int
     */
    keyboardSortTimeout: 1000,

    /**
     * Tab index of every active list element. Most of the time this value is not important
     * so any would work fine
     *
     * @var int
     */
    tabIndex: 666,

    /**
     * Constructor
     *
     * @param string|ElementUl ul List id field or an actual reference to list
     * @param Hash callbacks Function which will be executed on various events (like sorting, deleting editing)
     *
     * @access public
     */
    initialize: function(ul, callbacks)
    {
        this.ul = typeof(ul) == 'string' ? $(ul) : ul;

        Element.addClassName(this.ul, this.ul.id);

        // Check if ul has an id
        if(!this.ul.id)
        {
            alert('Active record main UL element is required to have an id. Also all list items should take that id plus "_"  as a prefix');
            return false;
        }

        // Check if all required callbacks are passed
        var missedCallbacks = [];
        for(var i = 0; i < this.requiredCallbacks.length; i++)
        {
            var before = ('before-' + this.requiredCallbacks[i]).camelize();
            var after = ('after-' + this.requiredCallbacks[i]).camelize();

            if(!callbacks[before]) missedCallbacks[missedCallbacks.length] = before;
            if(!callbacks[after]) missedCallbacks[missedCallbacks.length] = after;
        }
        if(missedCallbacks.length > 0)
        {
                alert('Callback' + (missedCallbacks.length > 1 ? 's' : '') + ' are missing (' + missedCallbacks.join(', ') +')' );
                return false;
        }

        this.callbacks = callbacks;
        this.dragged = false;

        this.createSortable();
    },


    colorizeItems: function()
    {
        var liArray = this.ul.getElementsByTagName("li");

        var k = 0;
        for(var i = 0; i < liArray.length; i++)
        {
            if(this.ul == liArray[i].parentNode && !Element.hasClassName(liArray[i], 'ignore') && !Element.hasClassName(liArray[i], 'dom_template'))
            {
                this.colorizeItem(liArray[i], k);
                k++;
            }
        }
    },



    colorizeItem: function(li, position)
    {
        if(position % 2 == 0)
        {
            Element.removeClassName(li, this.cssPrefix + "odd");
            Element.addClassName(li, this.cssPrefix + "even");
        }
        else
        {
            Element.removeClassName(li, this.cssPrefix + "even");
            Element.addClassName(li, this.cssPrefix + "odd");
        }
    },


    /**
     * Toggle item container On/Off
     *
     * @param HtmlElementLi A reference to item element. Default is current item
     * @param string action Every action has its own container. You could toggle another action container, but default is to toggle current action's container
     *
     * @access public
     */
    toggleContainer: function(li, action)
    {
        var container = this.getContainer(li ? li : false, action ? action : this.getAction(this.toggleContainer.caller));
        if(BrowserDetect.browser != 'Explorer')
        {
            if(container.style.display == 'none')
            {
                Effect.BlindDown(container.id, {duration: 0.5});
                Effect.Appear(container.id, {duration: 1.0});
                setTimeout(function() { container.style.height = 'auto'; container.style.display = 'block'}, 300);
            }
            else
            {
                Effect.BlindUp(container.id, {duration: 0.2});
                setTimeout(function() { container.style.display = 'none'}, 40);
            }
        } 
        else
        {
            container.style.display = (container.style.display == 'none') ? 'block' : 'none';
        }
    },

    /**
     * Check if item container is empty
     *
     * @param HtmlElementLi A reference to item element. Default is current item
     * @param string action Every action has its own container. You could toggle another action container, but default is to toggle current action's container
     *
     * @access public
     *
     * @return bool
     */
    isContainerEmpty: function(li, action)
    {
        return this.getContainer(li ? li : false, action ? action : this.getAction(this.isContainerEmpty.caller)).firstChild ? false : true;
    },

    /**
     * Get item container
     *
     * @param HtmlElementLi A reference to item element. Default is current item
     * @param string action Every action has its own container. You could toggle another action container, but default is to toggle current action's container
     *
     * @access private
     *
     * @return ElementDiv A refference to container node
     */
    getContainer: function(li, action)
    {
        if(!li) li = this._currentLi;
        if(!action) action = this.getAction(this.getContainer.caller); // if this function was called from user then we could try to auto-detect action

        return document.getElementsByClassName(this.cssPrefix + action + 'Container' , li)[0];
    },

    /**
     * Get item's id. Not as a dom element but real id, which is used id database
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access public
     *
     * @return string element id
     */
    getRecordId: function(li)
    {
        if(!li) li = this._currentLi;
        return li.id.substring(this.ul.id.length+1);
    },

    /**
     * Rebind all icons in item
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access public
     */
    rebindIcons: function(li)
    {
        var self = this;


        $A(this.ul.className.split(' ')).each(function(className)
        {
            var container = document.getElementsByClassName(self.cssPrefix + 'icons', li)[0];

            var regex = new RegExp('^' + self.cssPrefix + '(add|remove)_(\\w+)(_(before|after)_(\\w+))*');
            var tmp = regex.exec(className);

            if(!tmp) return;

            var icon = {};
            icon.type = tmp[1];
            icon.action = tmp[2];
            icon.image = self.icons[icon.action];
            icon.position = tmp[4];
            icon.sibling = tmp[5];

            if(icon.action != 'sort') 
            {
                li[icon.action].onclick = function() { self.bindAction(li, icon.action) }
                li[icon.action + 'Container'] = document.getElementsByClassName(self.cssPrefix + icon.action + 'Container', li)[0];
            }
        });
    },


    /**
     * Add new item to Active Record. You have 3 choices. Either to add whole element, add array of elements or add all elements
     * inside given dom element
     *
     * @param int id Id of new element (Same ID which is stored in database)
     * @param HTMLElement|array dom Any HTML Dom element or array array of Dom elements
     * @param bool insights Use elements inside of given node
     *
     * @access public
     *
     * @return HTMLElementLi Reference to new active list record
     */
    addRecord: function(id, dom, insights)
    {
        var li = document.createElement('li');
        li.id = this.ul.id + "_" + id;

        if (dom[0])
        {
            for(var i = 0; i < dom.length; i++)
            {
                // Sory for cloning, but JS just sucks hard at dom :''(
                // I just hope that every boy will use it in such situations where cloning is OK
                // Please forgive me if you will create links to elements you want to add and they will just not work
                // My suggestion is to create those links after you have added new record to list
                li.appendChild(dom[i].cloneNode(true));
            }
        }
        else
        {
            if(insights)
            {
                var elements = dom.getElementsByTagName("*");
                while(elements.length > 0)
                {
                    if(dom == elements[0].parentNode)
                    {
                        var test = elements[0];
                        li.appendChild(elements[0]);
                    }
                }
            }
            else
            {
                li.appendChild(dom);
            }
        }

        this.ul.appendChild(li);
        this.decorateLi(li);
        this.rebindIcons(li);
        this.createSortable();

        return li;
    },


    /***************************************************************************
    /*           Private methods                                               *
    /***************************************************************************

    /**
     * Go throug all list elements and decorate them with icons, containers, etc
     *
     * @access private
     */
    decorateItems: function()
    {

        // This fixes some strange explorer bug/"my stypidity"
        // Basically, what is happening is thet when I push edit button (pencil)
        // on first element, everything just dissapears. All other elements
        // are fine though. To fix this I am adding an hidden first element
        var liArray = this.getChildList();
        for(var i = 0; i < liArray.length; i++)
        {
                this.decorateLi(liArray[i]);
                this.colorizeItem(liArray[i], i);
        }
        
    },

    /**
     * Decorate list element with icons, progress bar, container, tabIndex, etc
     *
     * @param HtmlElementLi Element to decorate
     *
     * @access private
     */
    decorateLi: function(li)
    {
        var self = this;

        // Bind events
        li.onmouseover    = function() {self.showMenu(li) }
        li.onmouseout     = function() {self.hideMenu(li) }

        // KEYBOARD NAVIGATION BREAKS FORM FIELDS
//        li.onkeydown      = function(e) { self.navigate(new KeyboardEvent(e), li) }
//        li.onclick        = li.focus();

        // Add tab index
//        li.tabIndex       = this.tabIndex;


        // Create icons container. All icons will be placed incide it
        var iconsDiv = document.getElementsByClassName(self.cssPrefix + 'icons', li)[0];
        if(!iconsDiv)
        {
            iconsDiv = document.createElement('span');
            Element.addClassName(iconsDiv, self.cssPrefix + 'icons');
            li.insertBefore(iconsDiv, li.firstChild);
        }

        // add all icons
        $A(this.ul.className.split(' ')).each(function(className)
        {
            // If icon is not progress and it was added to a whole list or only this item then put that icon into container
            self.addIconToContainer(li, className);
        });

        // progress is not a div like all other icons. It has no fixed size and is not clickable.
        // This is done to properly handle animated images because i am not sure if all browsers will
        // handle animated backgrounds in the same way. Also differently from icons progress icon
        // can vary in size while all other icons are always the same size
        var iconProgress = document.getElementsByClassName(self.cssPrefix + 'progress', li)[0];
        if(!iconProgress)
        {
            iconProgress = document.createElement('img');
            iconProgress.src = this.icons.progress
            iconProgress.style.visibility = 'hidden';
            Element.addClassName(iconProgress, self.cssPrefix + 'progress');
            iconsDiv.appendChild(iconProgress);
        }

        li.progress = iconProgress;
    },

    addIconToContainer: function(li, className)
    {
        var container = document.getElementsByClassName(this.cssPrefix + 'icons', li)[0];

        var regex = new RegExp('^' + this.cssPrefix + '(add|remove)_(\\w+)(_(before|after)_(\\w+))*');
        var tmp = regex.exec(className);

        if(!tmp) return;

        var icon = {};

        icon.type = tmp[1];
        icon.action = tmp[2];
        icon.image = this.icons[icon.action];
        icon.position = tmp[4];
        icon.sibling = tmp[5];

        // all icons except sort has onclick event handler defined by user
        if(icon.action != 'sort')
        {
            var iconImage = document.getElementsByClassName(this.cssPrefix + icon.action, li)[0];
            if(!iconImage)
            {
                iconImage = document.createElement('img');
                iconImage.src = icon.image;
                iconImage.style.visibility = 'hidden';
                Element.addClassName(iconImage, this.cssPrefix + icon.action);
                Element.addClassName(iconImage, this.cssPrefix + 'icons_container');
    
    
    
                // If icon is removed from this item than do not display the icon
                if((Element.hasClassName(li, this.cssPrefix + 'remove_' + icon.action) || !Element.hasClassName(this.ul, this.cssPrefix + 'add_' + icon.action)) && !Element.hasClassName(li, this.cssPrefix + 'add_' + icon.action))
                {
                    iconImage.style.display = 'none';
                }
    
                // Show icon
                container.appendChild(iconImage);
            }
    
            // create shortcut
            li[icon.action] = iconImage;


            var self = this;
            iconImage.onclick = function() {  self.bindAction(li, icon.action) }

            var container = document.createElement('div');
            container.style.display = 'none';
            Element.addClassName(container, self.cssPrefix + icon.action + 'Container');
            Element.addClassName(container, self.cssPrefix + 'container');
            container.id = self.cssPrefix + icon.action + 'Container_' + li.id;
            li.appendChild(container);
            li[icon.action + 'Container'] = container;
        }
    },

    /**
     * Get action associated with user specified callback
     *
     * @param callback Callback to user defined action handler function
     *
     * @access private
     *
     * @return string action Action associated with callback
     */
    getAction: function(caller)
    {
        var action = '';
        for(key in this.callbacks)
        {
            if(this.callbacks[key] == caller)
            {
                action = key.replace(/^(after|before)/, '').toLowerCase();
                break;
            }
        }

        return action;
    },

    /**
     * This function executes user specified callback. For example if action was
     * 'delete' then the beforeDelete function will be called
     * which should return a valud url adress. After that when AJAX response has
     * arrived the afterDelete function will be called
     *
     * @param HtmlElementLi A reference to item element
     * @param string action Action
     *
     * @access private
     */
    bindAction: function(li, action)
    {
        this.rebindIcons(li);

        if(action != 'sort')
        {
            this._currentLi = li;
            var url = this.callbacks[('before-'+action).camelize()].call(this, li);

            if(!url) return false;

            var self = this;
            // display feedback
            this.onProgress(li);

            // execute the action
            new Ajax.Request(
                    url,
                    {
                        method: 'get',

                        // the object context mystically dissapears when onComplete function is called,
                        // so the only way I could make it work is this
                        onComplete: function(param)
                        {
                            self.callUserCallback(action, param, li);
                        }
                    });
        }
    },

    /**
     * Toggle progress bar on list element
     *
     * @param HtmlElementLi A reference to item element
     *
     * @access private
     */
    toggleProgress: function(li)
    {
        li.progress.style.visibility = (li.progress.style.visibility == 'visible') ? 'hidden' : 'visible';
    },

    offProgress: function(li)
    {
        li.progress.style.visibility = 'hidden';
    },

    onProgress: function(li)
    {
        li.progress.style.visibility = 'visible';
    },

    /**
     * Call a user defined callback function
     *
     * @param string action Action
     * @param XMLHttpRequest response An AJAX response object
     * @param HtmlElementLi A reference to item element. Default is current item
     *
     * @access private
     */
    callUserCallback: function(action, response, li)
    {
        this._currentLi = li;
        this.callbacks[('after-'+action).camelize()].call(this, li, response.responseText);
        this.offProgress(li);
    },

    /**
     * Initialize Scriptaculous Sortable on the list
     *
     * @access private
     */
    createSortable: function ()
    {
        var self = this;

        this.decorateItems();
        Element.addClassName(this.ul, this.cssPrefix.substr(0, this.cssPrefix.length-1));
        
        if(Element.hasClassName(this.ul, this.cssPrefix + 'add_sort'))
        {
            Sortable.create(this.ul.id,
            {
                dropOnEmpty:   true,
                constraint:    false,
                constraint:    'vertical',
                handle:        this.cssPrefix + 'sort',
                               // the object context mystically dissapears when onComplete function is called,
                               // so the only way I could make it work is this
                onChange:      function(elementObj) { 
                    self.dragged = elementObj; 
                },
                onUpdate:      function() { self.saveSortOrder(); }
            });
        }
        
        
    },

    /**
     * Display list item's menu. Show all item icons except progress
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access private
     */
    showMenu: function(li)
    {
        $H(this.icons).each(function(icon)
        {
            if(li[icon.key] && icon.key != 'progress') li[icon.key].style.visibility = 'visible';
        });
    },

    /**
     * Hides list item's menu
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access private
     */
    hideMenu: function(li)
    {
        $H(this.icons).each(function(icon)
        {
            if(li[icon.key] && icon.key != 'progress')
            {
                li[icon.key].style.visibility = 'hidden';
            }
        });
    },

    /**
     * Initiates item order (position) saving action
     *
     * @access private
     */
    saveSortOrder: function()
    {
        var self = this;

        var order = Sortable.serialize(this.ul.id);

        if(order)
        {
            // display feedback
            this.onProgress(this.dragged);

            // execute the action
            this._currentLi = this.dragged;

            var url = this.callbacks.beforeSort.call(this, this.dragged, order);
            new Ajax.Request(url,
            {
                method: 'get',

                // the object context mystically dissapears when onComplete function is called,
                // so the only way I could make it work is this
                onComplete: function(param)
                {
                    self.restoreDraggedItem(param.responseText);
                }
            });
        }
    },


    /**
     * This function is called when sort response arives
     *
     * @param XMLHttpRequest originalRequest Ajax request object
     *
     * @access private
     */
    restoreDraggedItem: function(item)
    {
        this.rebindIcons(this.dragged);
        this.hideMenu(this.dragged);
        

        this._currentLi = this.dragged;
        var url = this.callbacks.afterSort.call(this, this.dragged, item);
        this.colorizeItems();
        this.offProgress(this.dragged);

        this.dragged = false;
    },

    /**
     * Keyboard access functionality
     *     - navigate list using up/down arrow keys
     *     - move items up/down using Shift + up/down arrow keys
     *     - delete items with Del key
     *     - drop focus ("exit" list) with Esc key
     *
     * @param KeyboardEvent keyboard KeyboardEvent object
     * @param HtmlElementLi li A reference to item element
     *
     * @access private
     *
     * @todo Edit items with Enter key
     */
    navigate: function(keyboard, li)
    {
        switch(keyboard.getKey())
        {
            case keyboard.KEY_UP: // sort/navigate up
                if (keyboard.isShift())
                {
                    prev = this.getPrevSibling(li);

                    prev = (prev == prev.parentNode.lastChild) ? null : prev;

                    this.moveNode(li, prev);
                }
            break;

            case keyboard.KEY_DOWN: // sort/navigate down
//                this.getNextSibling(li).focus();

                if (keyboard.isShift())
                {
                    var next = this.getNextSibling(li);
                    if (next != next.parentNode.firstChild) next = next.nextSibling;

                    this.moveNode(li, next);
                }
            break;

            case keyboard.KEY_DEL: // delete
//                this.getPrevSibling(li).focus();
                if(this.icons['delete']) this.bindAction(li, 'delete');
            break;

            case keyboard.KEY_ESC:  // escape - lose focus
                li.blur();
            break;
        }

//        keyboard.deselectText();
    },

    /**
     * Moves list node
     *
     * @param HtmlElementLi li A reference to item element
     * @param HtmlElementLi beforeNode A reference to item element
     *
     * @access private
     */
    moveNode: function(li, beforeNode)
    {
        var self = this;

        this.dragged = li;

        li.parentNode.insertBefore(this.dragged, beforeNode);
//        this.dragged.focus();

        this.sortTimerStart = (new Date()).getTime();
        setTimeout(function(e)
        {
            if((new Date()).getTime() - self.sortTimerStart >= 1000)
            {
                self.saveSortOrder();
            }
        }, this.keyboardSortTimeout);
    },

    /**
     * Gets next sibling for element in node list.
     * If the element is the last node, the first node is being returned
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access private
     *
     * @return HtmlElementLi Next sibling
     */
    getNextSibling: function(element)
    {
        return element.nextSibling ? element.nextSibling : element.parentNode.firstChild;
    },

    /**
     * Gets previous sibling for element in node list.
     * If the element is the first node, the last node is being returned
     *
     * @param HtmlElementLi li A reference to item element
     *
     * @access private
     *
     * @return Node Previous sibling
     */
    getPrevSibling: function(element)
    {
        return !element.previousSibling ? element.parentNode.lastChild : element.previousSibling;
    },


    remove: function(li)
    {
        if(BrowserDetect.browser != 'Explorer')
        {
            Effect.SwitchOff(li, {duration: 1});
            setTimeout(function() { Element.remove(li); }, 10000);
        }
        else
        {
            Element.remove(li);
        }
    },
    
    collapseAll: function()
    {
        var childList = this.getChildList();
        
        for(var i = 0; i < childList.length; i++)
        {
            childList[i].style.display = 'none';
        }
    },
    
    getChildList: function()
    {
        
        var liArray = this.ul.getElementsByTagName("li");
        var childList = [];
        
        for(var i = 0; i < liArray.length; i++)
        {
            if(this.ul == liArray[i].parentNode && !Element.hasClassName(liArray[i], 'ignore') && !Element.hasClassName(liArray[i], 'dom_template'))
            {
                childList[childList.length] = liArray[i];
            }
        }
        
        return childList;
    }

}