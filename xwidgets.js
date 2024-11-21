/*

	xWidgets.
	Simple HTML widgets for a better life!

*/
var xwidgets={

	widgets:{},

	/*

		xwidgets.selector()
		HTML ComboBox.

		Example:

			var selector=new xwidgets.selector({
				"id":"element",
				"items":[
					{"caption":"Default", "value":""},
					{"caption":"Item 1", "value":"1"},
					{"caption":"Item 2", "value":"2"},
					{"caption":"Item 3", "value":"3"}
				],
				"value":"",
				"onchange":function(selector, item){
					alert(item.value);
				}
			});

		Parameters:

			id:string (required)
				Element or Element identifier.

			items:object (optional)
				List of items.

			item:object
				Return current item

			value:integer|string (optional)
				Select an item by its index/key (if defined).

			render (optional)
				Render item.
				:function(self, item, index, selected)
					Renderer item using custom function.
					Parámeters:
						self - same class
						item - item to render
						key - item key (can be null if empty caption)
						selected - is item selected?
					Return:
						HTML string or DOM Element

		Events:

			onchange(self, item)
				Fires on item selection change.

	*/
	selector:function(o) {
		var self=this;
		self.o=o;
		if (!isset(self.o.value)) self.o.value=null;

		// select by index
		self.select=function(index){
			if (isset(self.o.value)) for (var i in self.o.items) if (self.o.value === self.o.items[i].value) {
				classDel(self.items[i], "widget_selector_item_active");
				break;
			}
			classAdd(self.items[index], "widget_selector_item_active");
			self.o.value=self.o.items[index].value;
			if (self.o.onchange) self.o.onchange(self, self.item());
		};

		// get selected item
		self.item=function(){
			for (var i in self.o.items) if (self.o.value === self.o.items[i].value) {
				return self.o.items[i];
			}
			return false;
		};

		// get/set value
		self.value=function(value){
			if (isset(value)) {
				self.o.value=value;
				if (self.o.onchange) self.o.onchange(self, self.o);
				self.refresh();
			}
			return self.o.value;
		};

		// refresh element
		self.refresh=function(){

			// render items
			self.items=[];
			for (var index in self.o.items) {
				var item=self.o.items[index];
				var down=(isset(self.o.down)?self.o.down(self, item):false);
				var selected=(self.o.value === item.value);
				var caption=item.caption;
				if (self.o.render) caption=self.o.render(self, item, index, selected);
				var child=(
					typeof(caption) == "function"
					?caption
					:newElement("div", {
						"class":"widget_selector_item_caption",
						"html":caption
					})
				);
				self.items.push(newElement("div", {
					"class":"widget_selector_item"+(selected?" widget_selector_item_active":""),
					"attributes":{
						"tabindex":0, // selectable
						"data-index":index
					},
					"properties":{
						"onkeypress":function(e){
							if (e.keyCode == 32 || e.keyCode == 13) {
								var index=this.getAttribute("data-index");
								self.select(index);
								e.preventDefault();
							}
						},
						"onclick":function(){
							var index=this.getAttribute("data-index");
							self.select(index);
						}
					},
					"childs":(child?[child]:[])
				}));
			}

			// widget container
			self.widget=newElement("div", {
				"class":"noselect",
				"childs":self.items
			});

			// selector
			self.selector=newElement("span", {
				"class":"widget_selector",
				"childs":[self.widget]
			});

			// render
			gidset(self.o.id, "");
			gid(self.o.id).appendChild(self.selector);

		};

		// refresh
		self.refresh();

		// register self
		xwidgets.widgets[self.o.id]=self;

	},

	/*

		xwidgets.hsearch()
		HTML SearchBox.

		Example:

			var hsearch=new xwidgets.hsearch({
				"id":"element",
				"render":function(self, item){
					return (item
						?"<b>"+item.caption+"</b> ("+item.id+")"
						:"- Please, select an option -"
					);
				},
				"search":function(self, search){
					var items=[
						{"id":1, "caption":"First Option"},
						{"id":2, "caption":"Second Option"}
					];
					var r=[];
					for (var i in items) {
						var item=items[i];
						if (!search || self.searchWords(item.caption, search)) {
							r.push(self.optionElement({
								"html":"<b>"+item.caption+"</b> ("+item.id+")",
								"item":item
								//"onselect":function(self){ self.select(item); }
							}));
						}
					}
					return r;
				}
			});
			//alert(hsearch.item());

		Parameters:

			id:element (required)
				Element or element identifier.

			render:function(self, item) (required)
				returns Rendered element or HTML string.

			search:function(self, search) (required)
				returns Rendered element or HTML string of options.

			item:any (optional)
				Sets initial item.

		Methods:

			.close()
				Close search.

			.open()
				Open search.

			.init()
				Initialize (called at startup).
	
			.item(item):any
				Get/Set current item.

			.optionElement(options):element
				Creates a clickable option element.

				options:object (required)

					Parameters:

						class:string (optional)
							CSS class/classes to apply.

						item:any (optional, required if no onselect)
							Item to be selected.

						html:string (optional, required if no child)
							HTML content.

						child:array (optional, required if no html)
							Array of child elements.

					Events:

						onselect(self, options) (optional, required if no item)
							Fired on element click/selection.

			.refresh()
				Refresh item.

			.search()
				Invocate search.

			.searchWords(text, search):boolean
				Do a natural case insensitive word search in the specified text.

			.select(item)
				Sets item selection and close search (if opened).

		Properties:

			.opened:boolean (readonly)
				Returns current dropdown state.

		Events:

			onchange(self, item)
				Fires on item change.

			onclose(self)
				Fires on close search.

			onopen(self)
				Fires on open search.

			onselect(self, item)
				Fires on item selection.

	*/
	"hsearch":function(o) {
		var self=this;
		self.o=o;

		self.select=function(item){
			var item=(typeof(item) != "undefined"?item:null);
			self.item(item);
			if (self.o.onselect) self.o.onselect(self, item);
			setTimeout(function(){
				self.close();
			},1);
		};

		self.open=function(){
			self.opened=true;
			classAdd(self.o.id, "hsearch_open");
			gidfocus(self._search);
			self.search();
			if (self.o.onopen) self.o.onopen(self);
		};

		self.closeChecked=function(){
			setTimeout(function(){
				//alert(document.activeElement+"/"+gid(self.o.id).matches(':focus-within'));
				if (!gid(self.o.id).matches(':focus-within')) self.close();
			}, 1);
		};

		self.close=function(){
			if (self.opened) {
				self.opened=false;
				classDel(self.o.id, "hsearch_open");
				if (self.o.onclose) self.o.onclose(self);
			}
		};

		self.search=function(){
			if (typeof(self.o.search) !== "function") return false;
			var s=self.o.search(self, gid(self._search).value);
			gidset(self._results, (s?(typeof s == "string"?s:""):(self.o.noresults?self.o.noresults:"<i>Sin resultados</i>")));
			if (s instanceof Array) {
				for (var i=0; i < s.length; i++) gid(self._results).appendChild(s[i]);
			} else if (typeof s != "string") {
				gid(self._results).appendChild(s);
			}
			classEnable(self._pop, "hsearch_empty", !s);
		};

		self.searchWords=function(text, search){
			var w=search.toLowerCase().split(" ");
			if (!w.length) return false;
			for (var i in w)
				if (text.toLowerCase().indexOf(w[i]) == -1)
					return false;
			return true;
		};

		self.item=function(item){
			if (typeof(item) != "undefined") {
				self.o.item=item;
				self.refresh();
				if (self.o.onchange) self.o.onchange(self, item);
			}
			return self.o.item;
		};

		self.refresh=function(){
			var e=self.o.render(self, self.o.item);
			gidset(self._item, (typeof e == "string"?e:""));
			if (e && typeof e != "string") gid(self._item).appendChild(e);
		};

		self.optionElement=function(o){
			function select() {
				if (o.onselect) o.onselect(self, o);
				if (o.item) self.select(o.item);
			}
			return newElement("div", {
				"class":"cmb_item"+(o.class?" "+o.class:""),
				"attributes":{
					"tabindex":"0",
				},
				"properties":{
					"onclick":function(){ select(); },
					"onkeypress":function(){ if (event.keyCode == 13) select(); }
				},
				"html":(o.html?o.html:""),
				"child":(o.child?o.child:"")
			});
		};

		self.value=function(){
			if (self.o.key) {
				var item=self.item();
				return (typeof(item) == "object"?item[self.o.key]:null);
			}
			return null;
		};

		self.init=function(){

			// ensure requisites
			if (!self.o.render) return console.error("hsearch: render method required.");

			// defaults
			self.o.key=self.o.key||"id";

			classAdd(self.o.id, "hsearch");

			gid(self.o.id).onclick=function(){
				if (!self.opened) self.open();
			};

			self._item=newElement("div", {
				"class":"hsearch_item",
				"attributes":{
					"tabindex":"0",
				},
				"properties":{
					"onclick":function(){
						self.open();
					},
					"onkeypress":function(){
						if ([13,32].includes(event.keyCode) && self.canopen) {
							self.open();
							event.preventDefault();
						}
					},
					"onkeydown":function(){
						if ([9,16,13].includes(event.keyCode)) return;
						if (self.canopen) self.open();
					},
					"onfocus":function(){
						self.canopen=true;
					},
					"onblur":function(){
						//console.log("blur:"+this.contains(document.activeElement));
						self.canopen=false;
					}
				}
			});

			self._pop=newElement("div", {
				"class":"hsearch_pop",
				"attributes":{
					"tabindex":"0",
				},
				"properties":{
					"onblur":function(){
						self.closeChecked();
						//console.log("blur:"+gid(self.o.id).matches(':focus-within'));
					}
				}
			});

			self._search=newElement("input", {
				"class":"hsearch_input",
				"attributes":{
					"type":"text",
					"value":""
				},
				"properties":{
					"ondblclick":function(){
						this.value="";
						self.search();
					},
					"oninput":function(){
						self.search();
					},
					"onkeyup":function(){
						if (event.keyCode == 27 && self.opened) self.close();
					},
					"onblur":function(){
						self.closeChecked();
					}
				}
			});

			self._search_container=newElement("div", {
				"class":"hsearch_input_container",
				"childs":[self._search]
			});

			self._results=newElement("div");

			gidset(self.o.id, "");
			self._pop.appendChild(self._results);
			gid(self.o.id).appendChild(self._search_container);
			gid(self.o.id).appendChild(self._item);
			gid(self.o.id).appendChild(self._pop);

			self.refresh();

		};

		self.init();

	},

	/*

		xwidgets.hcombo()
		HTML ComboBox.

		Example:

			var items=[
				{"id":1,"caption":"Option oen"},
				{"id":3,"caption":"Option two"},
				{"id":4,"caption":"Option four (no three)"},
				{"id":5,"caption":"Option five"},
				{"id":6,"caption":"Option six"},
				{"id":7,"caption":"Option seven"},
				{"id":8,"caption":"Option eight"},
				{"id":9,"caption":"Option nine"},
				{"id":10,"caption":"Option ten"}
			];

			var hcombo=new xwidgets.hcombo({
				id:"element",
				items:items,
				key:"id",
				render:"caption",
				empty:"- Select one or more -",
				multiple:true,
				index:2,
				keys:5
			});
			//alert(hcombo.index());

			var hcombo=new xwidgets.hcombo({
				id:"element",
				items:items,
				key:"id",
				caption:function(self, items){
					if (!items || !array_count(items)) return null;
					var h="";
					for (var i in items)
						h+=(h?", ":"")+items[i].caption;
					return h;
				},
				render:function(self, item, index, selected){
					return "* <b>"+item.caption+"</b>";
				},
				multiple:true,
				index:[2,3] //values:[4,5]
			});
			//alert(hcombo.values());

		Parameters:

			id:string (required)
				Element or Element identifier.

			items:object (optional)
				List of items.

			caption:string (optional)
				Render caption using key string as caption.

			caption:function(item, index) (optional)
				Renderer caption. Returns HTML.

			disabled:boolean (opcional)
				Set disabled field.

			readonly:boolean (opcional)
				Set read-only field.

			render (optional)
				Render item.
				:string
					Render using specified field as caption.
				:function(self, item, index, selected)
					Renderer item using custom function.
					Parámeters:
						self - same class
						item - item to render
						key - item key (can be null if empty caption)
						selected - is item selected?
					Return:
						HTML string.

			multiple:boolean (optional)
				True to enable multiple selection.

			empty:boolean (optional)
				Enable empty item (null item).

			search (optional)
				Enable search.
				:boolean
					Uses caption as searchable text.
				:string
					Uses custom field as searchable text.
				:function(self, item, index, search)
					Uses custom function.
					If string returned, uses it for searchable text.
					If boolean returned, uses it as match.

			key:string (optional)
				Set the field containing the key.

			keys:array (optional)
				Requires parameter key defined.
				Selects several items by its key.

			index (optional)
				:integer
					Select one item by its index.
				:array
					Selects several items by its index.

			value:integer|string (optional)
				Select an item by its index/key (if defined).

			values:array (optional)
				Selects several items by its index/key (if defined).

		Methods:

			.id()
				Returns identifier_string/element.

			.id(element|string)
				Sets new identifier_string/element for render.

			.focus()
				Focus combobox.

			.open()
				Open dropdown.

			.close()
				Close dropdown.

			.swap()
				Conmutes open/close dropdown.

			.select(index)
				Select an item by its index. If multiple and items is already selected, unselects it.
				Return: true if selected, false if not selected, null if not an item.

			.unselect()
				Un select all items.

			.isselected(index)
				Returns if an item is selected by its index.

			.renderCaption()
				Returns HTML for the current caption.

			.renderItem(item, index)
				Returns HTML for an item.

			.indexFirst()
				Get first index for all items (including empty item).

			.indexLast()
				Get last index for all items (including empty item).

			.count()
				Count number of visible elements (including empty item).

			.clear()
				Clear items from dropdown combo.

			.disable(disabled)
				Enable/Disable combo.

			.add(item)
				Adds one item to the dropdown combo at the end.

			.replace(item, index)
				Replaces one item into desired position.

			.focusedItem()
				Get current index of focused item (including empty item).

			.focusItem(index)
				Set focus to the item by index.

			.focusSelectedItem()
				Focus last selected item, or false if not selected.

			.focusFirstItem()
				Focus first item (including empty item).

			.focusLastItem()
				Focus last item (including empty item).

			.focusPrevItem()
				Focus previous item (including empty item).

			.focusNextItem()
				Focus next item (including empty item).

			.itemElement(item, index)
				Get item DOM element.

			.refresh()
				Refresh all.

			.refreshCaption()
				Refresh only caption.

			.refreshItem(index)
				Refresh item by index.

			.refreshItems()
				Refresh all items.

			.hasText(haystack, needle)
				Natural search a needle by words in a text haystack.
				Return: true if found, false if not.

			.readonly(readonly)
				Enable/Disable read-only.

			.resize()
				Resize event.

			.key([key:string])
				Get/Set the field containing the key.

			.keys([keys:string/array])
				Selects one/several items by its key.
				Return: selected keys.

			.index([indexes:integer/array])
				Select one/several items by its index.
				Return: selected index (single)/indexes (multiple).

			.values([keys:string/array])
				Get/Set selected keys by index or key, if defined.
				Return: items by index or key, if defined..

			.search([search:string])
				Get/set search value
				Return: search value.

			.destroy()
				Frees resources.

			.init()
				Initialize combobox (called at startup).

		Properties:

			.opened:boolean (readonly)
				Returns current dropdown state.

		Events:

			onselect(self, item, index)
				Fires on item selection.

			onclick(self, item, index)
				Fires on item click. If returns false, cancel item selection.

	*/
	hcombo:function(o){
		var self=this;

		// public vars
		self.opened=false;

		// get/set id
		self.id=function(id){
			if (isset(id)) self.o.id=id;
			return self.o.id;
		};

		// focus combobox
		self.focus=function(){
			if (self.e.cmb_input) {
				self.e.cmb_input.select();
				self.e.cmb_input.focus();
			} else {
				self.e.cmb_group.focus();
			}
		};

		// focus searchbox
		self.focusSearch=function(){
			if (self.e.cmb_search) {
				self.e.cmb_search.select();
				self.e.cmb_search.focus();
				return true;
			}
			return false;
		};

		// get/set disabled state
		self.disabled=function(disabled){
			self.o.disabled=disabled;
			self.close();
			self.refresh();
		};

		// get/set readonly state
		self.readonly=function(readonly){
			self.o.readonly=readonly;
			self.close();
			self.refresh();
		};

		// open combobox
		self.open=function(){
			if (self.o.disabled || self.o.readonly) return false;
			var first_time=(self.openedtimes?false:true);
			if (!self.openedtimes) self.openedtimes=0;
			self.openedtimes++;
			self.opened=true;
			if (self.closeTimeout) clearTimeout(self.closeTimeout);
			classAdd(self.e.cmb_group, "cmb_open");
			self.resize();
			if (first_time) {
				self.e.cmb_items.scrollTop=0;
				self.focusSearch();
				self.update({"focusSearch":true});
			}
			return true;
		};

		// close combobox
		self.close=function(focused){
			self.closeTimeout=setTimeout(function(){
				self.opened=false;
				delete self.closeTimeout;
				classDel(self.e.cmb_group, "cmb_open");
				if (focused) self.focus();
			}, 1);
		};

		// swap open/close
		self.swap=function(focused){
			if (self.opened) self.close(focused);
			else self.open(focused);
		};

		// select by index
		self.select=function(index){
			if (!isset(index)) return false;
			var item=null;
			if (!isNaN(index) && self.e.items[index] || (!index && self.o.empty && !self.o.multiple)) {
				if (!self.o.multiple) self.unselect();
				self.o.selectedindex=index;
				if (!self.o.selected) self.o.selected=[];
				if (isset(self.o.selected[index])) delete self.o.selected[index];
				else self.o.selected[index]=self.o.options[index];
				item=self.o.selecteditem=(self.o.selected[index]?self.o.selected[index]:false);
				if (self.o.key) {
					var lastitem=(index > self.o.options.length-1?null:self.o.options[index]);
					var key=(lastitem === null?"":(self.o.key?lastitem[self.o.key]:index));
					if (item) {
						self.o.selectedkeys[key]=item;
						var found=false;
						for (var i in self.o.selecteditems) if (self.o.selecteditems[i][self.o.key] == key) {
							found=true;
							break;
						}
						if (!found) self.o.selecteditems.push(item);
					} else {
						delete self.o.selectedkeys[key];
						for (var i in self.o.selecteditems) if (self.o.selecteditems[i][self.o.key] == key) {
							delete self.o.selecteditems[i];
							self.o.selecteditems=array_values(self.o.selecteditems);
							break;
						}
					}
				}
				self.refreshItem(index);
				self.refreshCaption();
				if (self.o.editable && self.e.cmb_input) {
					var value=strip_tags(self.renderCaption());
					if (typeof(self.o.editable) == "function") value=self.o.editable(self, item, value);
					self.e.cmb_input.value=value;
				}
				if (self.o.onselect) self.o.onselect(self, item, index);
				if (self.o.onchange) self.o.onchange(self, item, index);
				if (self.o.input) gidval(self.o.input, self.value());
			}
			return item;
		};

		// select first item
		self.selectFirst=function(){
			if (self.count()) if (!self.isselected(0)) self.select(0);
		};

		// select last item
		self.selectLast=function(){
			var last=self.count()-1;
			if (last >= 0) if (!self.isselected(last)) self.select(last);
		};

		// unselect all items
		self.unselect=function(){
			self.o.selected=[];
			self.o.selectedkeys={};
			self.o.selecteditems=[];
			delete self.o.selectedindex;
			delete self.o.selecteditem;
			if (self.e.items)
				for (var i in self.e.items)
					self.refreshItem(i);
		};

		// returns if an item is selected by its index
		self.isselected=function(index){
			if (!isset(index)) return false;
			return (self.o.selected && self.o.selected[index]?true:false);
		};

		// render caption
		self.renderCaption=function(){
			var html="";
			try {
				var selected=(self.o.key && self.o.multiple?self.o.selecteditems:self.o.selected);
				if (typeof(self.o.caption) == "function") {
					html=self.o.caption(self, (self.o.multiple?selected:self.o.selecteditem));
				} else if (typeof(self.o.caption) == "string") {
					if (selected)
						for (var i in selected)
							if (selected[i])
								html+=(html?", ":"")+selected[i][self.o.caption];
					if (!html && self.o.empty) html=null;
				} else {
					if (selected)
						for (var i in selected)
							if (selected[i])
								html+=(html?", ":"")+self.renderItem(selected[i]);
					if (!html && self.o.empty) html=null;
				}
				if (html === null) html=(self.o.editable?"":self.o.emptyCaption);
			} catch(e) {
				html="!caption: "+e;
			}
			return html;
		};

		// render item
		self.renderItem=function(item, index){
			if (item === null || index === null) return self.o.emptyCaption;
			var html="";
			try {
				if (typeof(self.o.render) == "function") {
					html=self.o.render(self, item, index, self.isselected(index));
				} else if (typeof(self.o.render) == "string") {
					html=(item?(isset(item[self.o.render])?item[self.o.render]:""):"");
				} else {
					html=(isset(item)?item:"");
				}
				if (self.o.itemclass) html="<div class='"+self.o.itemclass+"'>"+html+"</div>";
			} catch(e) {
				html="!item("+index+"): "+e;
			}
			return html;
		};

		// return first index
		self.indexFirst=function(){
			if (self.o.empty && !self.o.del) return null;
			if (self.e.items) for (var i in self.e.items) if (i !== "null") return parseInt(i);
			return false;
		};

		// return last index
		self.indexLast=function(){
			var index=false;
			if (self.e.items) for (var i in self.e.items) if (i !== "null") index=i;
			return parseInt(index);
		};

		// get aproximate page size
		self.pageSize=function(){
			// get first element height
			var min=5;
			var eh=false;
			var wh=self.e.cmb_items.offsetHeight;
			for (var i in self.e.items) {
				var e=self.e.items[i];
				eh=e.offsetHeight;
				break;
			}
			if (eh>0 && wh>0) {
				var ps=Math.floor(wh/eh)-1;
				return (ps>1?ps:1);
			}
			return 10;
		};

		// get focused item index
		self.focusedItem=function(){
			if (self.o.empty && document.activeElement == self.e.items[null]) return null;
			if (self.e.items)
				for (var i in self.e.items)
					if (document.activeElement == self.e.items[i])
						return parseInt(i);
			return false;
		};

		// focus an item
		self.focusItem=function(index){
			if (!isset(self.e.items[index])) return false;
			self.e.items[index].focus();
			return true;
		};

		// focus last selected item
		self.focusSelectedItem=function(){
			if (isset(self.o.selectedindex)) return self.focusItem(self.o.selectedindex);
			return false;
		};

		// focus first item
		self.focusFirstItem=function(){
			var i=self.indexFirst();
			if (i !== false) self.focusItem(i);
			return false;
		};

		// focus last item
		self.focusLastItem=function(){
			var i=self.indexLast();
			if (i !== false) self.focusItem(i);
			return false;
		};

		// focus previous item
		self.focusPrevItem=function(index){
			var index=index || self.focusedItem();
			var last=false;
			if (self.e.items) {
				if (index === 0 && self.o.empty) {
					self.focusItem(null);
				} else {
					for (var i in self.e.items) if (i !== "null") {
						if (i == index) return (last === false?false:self.focusItem(last));
						last=i;
					}
				}
			}
			return false;
		};

		// focus next item
		self.focusNextItem=function(index){
			var index=(isset(index)?index:self.focusedItem());
			var found=false;
			if (self.e.items) {
				if (index === null && self.o.empty) {
					self.focusItem(0);
				} else {
					for (var i in self.e.items) if (i !== "null") {
						if (found) return self.focusItem(i);
						found=(i == index);
					}
				}
			}
			return false;
		};

		// get item DOM element
		self.itemElement=function(item, index){
			return newElement("div", {
				"class":"cmb_item"+(self.isselected(index)?" cmb_item_selected":"")+(item?"":" cmb_item_empty"),
				"attributes":{
					"tabindex":"0",
					"data-index":(index >= 0?index:"")
				},
				"events":{
					"mousedown":function(){
						this.focus();
					},
					"keypress":function(e){
						var index=this.getAttribute("data-index");
						var r=true;
						index=(index === null?null:parseInt(index));
						switch (e.keyCode) {

						case 13:
							if (!self.isselected(index)) self.select(index);
							if (self.o.onclick) r=self.o.onclick(self, item, index);
							if (!isset(r) || r) {
								self.focus();
								self.close();
							}
							break;

						case 32:
							self.select(index);
							if (self.o.onclick) r=self.o.onclick(self, item, index);
							if (!isset(r) || r) {
								if (!self.o.multiple) {
									self.close();
									self.focus();
								}
								self.focusSelectedItem();
								if (self.o.multiple) self.focusNextItem();
							}
							break;

						}
						e.stopPropagation();
						e.preventDefault();
					},
					"click":function(){
						var index=this.getAttribute("data-index");
						var r=true;
						self.select(index);
						if (self.o.onclick) r=self.o.onclick(self, item, index);
						if (!isset(r) || r) {
							if (self.o.multiple) {
								self.focusItem(index);
							} else {
								self.focus();
								self.close();
							}
						}
					}
				},
				"html":self.renderItem(item, index)
			});
		};

		// get/set items
		self.items=function(items){
			if (isset(items)) {
				self.o.items=items;
				self.refreshItems();
			}
			return self.o.items;
		};

		// count number of visible elements
		self.count=function(){
			return self.o.options.length;
		};

		// clear items
		self.clear=function(){
			if (!self.o.items) self.o.items=[];
			if (self.e.items) for (var index in self.e.items) {
				if (self.e.items[index]) self.e.items[index].parentNode.removeChild(self.e.items[index]);
				delete self.e.items[index];
			}
			self.o.selected=[];
			self.o.options=[];
			self.o.keyindex={};
		};

		// add an item (option if index defined)
		self.add=function(item, index){
			var isnew=!isset(index);
			if (isnew) self.o.items.push(item);
			var index=(isnew || !index?self.count():index);
			var key=(item === null?"":(self.o.key?item[self.o.key]:index));
			var replace=isset(self.o.options[index]);
			//alert("add "+index+" key "+key+" item "+adump(item));
			if (replace) self.replace(item, index);
			else {
				self.o.keyindex[key]=index;
				self.o.options[index]=item;
				self.e.items[index]=self.itemElement(item, index);
				self.e.cmb_items.appendChild(self.e.items[index]);
			}
			return self.e.items[index];
		};

		// replace an option by its index
		self.replace=function(item, index){
			var key=(item === null?"":(self.o.key?item[self.o.key]:index));
			self.o.keyindex[key]=index;
			self.o.options[index]=item;
			if (isset(self.o.options[index])) {
				var rindex=(self.o.empty && !self.o.multiple?index-1:index);
				if (isset(self.o.items[rindex])) self.o.items[rindex]=item;
				self.refreshItem(index);
				return true;
			}
			return false;
		};

		// delete an item by its index
		self.del=function(index){
			var oindex=index;
			var index=(self.o.empty && !self.o.multiple?index-1:index);
			if (index >=0 && index < self.count()) {
				if (isset(self.o.items[index])) {
					var lastindex=self.o.selectedindex;
					delete self.o.items[index];
					self.o.items=array_values(self.o.items);
					delete self.o.selectedindex;
					delete self.o.selecteditem;
					self.refreshItems();
					self.refreshCaption();
					return true;
				}
			}
			return false;
		};

		// refresh combobox caption
		self.refreshCaption=function(){
			if (self.e.cmb_caption) gidset(self.e.cmb_caption, self.renderCaption());
		};

		// refresh an item
		self.refreshItem=function(index){
			if (!self.e.items[index]) return;
			var item=self.itemElement(self.o.options[index], index);
			self.e.items[index].parentNode.replaceChild(item, self.e.items[index]);
			self.e.items[index]=item;
		};

		// check if an item is visible
		self.visibleItem=function(item, index){
			var visible=true;
			if (self.o.search || self.o.filter) {
				var input=(self.e.cmb_search?self.e.cmb_search:self.e.cmb_input);
				if (input) {
					var search=input.value;
					if (search.length) {
						var filter=self.o.search || self.o.filter;
						var text="";
						switch (typeof(filter)) {
						case "function": // use custom function
							text=filter(self, item, index, search);
							break;
						case "string": // use custom field
							text=item[filter];
							break;
						case "boolean": // use item renderer (by default)
						default:
							text=self.renderItem(item, index);
							break;
						}
						// search text
						if (text === true || text === false) visible=text;
						else if (text && text.length && !self.hasText(text, search)) visible=false;
					}
				}
			}
			return visible;
		};

		// refresh combobox items
		self.refreshItems=function(){
			// clear items
			self.clear();
			// add empty item (if defined)
			if (self.o.empty && !self.o.multiple && !self.o.del) self.e.empty=self.add(null, null);
			// add items from array
			if (self.o.items) for (index in self.o.items) {
				var item=self.o.items[index];
				var nextindex=self.count();
				if (self.visibleItem(item, nextindex)) self.add(item, nextindex);
			}
			// add AJAX items
			if (self.o.ajaxdata && self.o.ajaxdata.data) {
				for (index in self.o.ajaxdata.data) {
					var item=self.o.ajaxdata.data[index];
					var nextindex=self.count();
					self.add(item, null);
				}
			}
			// select keys again
			if (self.o.key && self.o.selectedkeys) self.keys(array_keys(self.o.selectedkeys));
		};

		// natural search
		self.naturalSearch=function(text){
			return text
				.toLowerCase()
				.replace(/á/g,"a")
				.replace(/é/g,"e")
				.replace(/í/g,"i")
				.replace(/ó/g,"o")
				.replace(/ú/g,"u")
				.replace(/Á/g,"a")
				.replace(/É/g,"e")
				.replace(/Í/g,"i")
				.replace(/Ó/g,"o")
				.replace(/Ú/g,"u")
			;
		};

		// check if a value has all words inside
		self.hasText=function(haystack, needle) {
			var haystack=self.naturalSearch(haystack);
			var search=self.naturalSearch(needle).split(" ");
			var found=true;
			for (var i in search)
				if (haystack.search(new RegExp(search[i], "i")) == -1)
					return false;
			return true;
		};

		// refresh combo elements
		self.refresh=function(){

			// main element
			if (self.o.editable) {

				// caption input
				self.e.cmb_input=newElement("input", {
					"class":"cmb_input",
					"attributes":{
						"type":"text",
						"placeholder":"",
						"value":(isset(self.o.value)?self.o.value:"")
					},
					"events":{
						"focus":function(){
							classAdd(self.e.cmb_group, "cmb_focus");
						},
						"input":function(){
							// combo items
							if (!self.o.multiple) self.unselect();
							self.open();
							self.refreshItems();
							self.updateTimed();
						},
						"blur":function(){
							classDel(self.e.cmb_group, "cmb_focus");
						}
					}
				});

			} else {

				// caption container
				self.e.cmb_caption=newElement("span", {
					"class":"cmb_caption"
				});

				// combo container
				self.e.cmb_combo=newElement("div", {
					"class":"cmb_combo",
					"html":"&nbsp;",
					"childs":[self.e.cmb_caption]
				});

			}

			// search input
			self.e.cmb_search=(self.o.search?newElement("input", {
				"class":"cmb_search",
				"attributes":{
					"type":"text",
					"placeholder":(typeof(self.o.search) == "object" && self.o.search.placeholder?self.o.search.placeholder:"..."),
					"value":""
				},
				"events":{
					"click":function(e){
						e.preventDefault();
					},
					"dblclick":function(e){
						this.value="";
						self.refreshItems();
						self.updateTimed();
					},
					"focus":function(e){
						this.select();
						e.preventDefault();
					},
					"input":function(){
						self.refreshItems();
						self.updateTimed();
					},
					"blur":function(e){
						//e.open();
					},
					"keydown":function(e){
						switch (e.keyCode) {
						case 9: // tab
							self.open();
							self.focusSelectedItem();
							e.preventDefault();
							break;

						case 13: // enter
							if (self.count()) {
								self.select(self.indexFirst());
							}
							// no break
						case 27: // escape
							self.focus();
							self.close();
							break;

						case 33: // page up
						case 38: // up
							if (self.o.valignTop) {
								self.open();
								if (!self.focusSelectedItem()) self.focusLastItem();
							} else {
								self.focus();
								self.close();
							}
							e.stopPropagation();
							e.preventDefault();
							break;

						case 34: // page down
						case 40: // down
							if (self.o.valignTop) {
								self.focus();
								self.close();
							} else {
								self.open();
								if (!self.focusSelectedItem()) self.focusFirstItem();
							}
							e.stopPropagation();
							e.preventDefault();
							break;

						}
					}
				}
			}):null);

			// combobox
			self.e.cmb=newElement("div", {
				"class":"cmb",
				"childs":[(self.e.cmb_input?self.e.cmb_input:self.e.cmb_combo)],
				"events":{
					"mousedown":function(e){
						if (self.e.cmb_input) {
							if (!self.opened) {
								self.update();
								self.open();
							}
						} else {
							self.swap(true);
							if (self.opened) {
								self.update();
								if (!self.focusSearch())
									self.focus();
							}
							e.preventDefault();
						}
					}
				}
			});

			// set disabled attribute
			if (self.o.disabled) self.e.cmb.setAttribute("disabled", "");
			else self.e.cmb.removeAttribute("disabled");

			// set readonly attribute
			if (self.o.readonly) self.e.cmb.setAttribute("readonly", "");
			else self.e.cmb.removeAttribute("readonly");

			// items
			self.e.cmb_items=newElement("div", {
				"class":"cmb_items",
				"html":self.o.html,
				"events":{
					"mousedown":function(e){
						e.preventDefault();
					}
				}
			});

			// options
			self.e.cmb_options=newElement("div", {
				"class":"cmb_options",
				"childs":[
					self.e.cmb_search,
					self.e.cmb_items
				],
				"events":{
					"mousedown":function(e){
						//e.preventDefault();
					}
				}
			});

			// dropdown
			self.e.cmb_dropdown=newElement("div", {
				"class":"cmb_dropdown",
				"childs":[self.e.cmb_options],
				"events":{
					"mousedown":function(e){
						//e.preventDefault();
					}
				}
			});

			// fill items
			self.refreshItems();

			// general group for elements
			self.e.group=newElement("div", {
				"class":"group",
				"childs":[self.e.cmb]
			});

			// actions
			self.e.actions=[];
			if (!self.o.actions) self.o.actions=[];

			// add action
			if (self.o.add) self.o.actions.push(array_merge({
				"class":"cmd cmd_add",
				"html":"+",
				"action":function(self, action, index){
					if (self.o.disabled || self.o.readonly) return;
					if (isset(self.o.onadd)) self.o.onadd(self, action, index);
					else if (typeof(self.o.add) == "function") self.o.add(self, action, index);
				}
			}, (typeof(self.o.add) !== "object"?{}:self.o.add)));

			// delete action
			if (self.o.del) self.o.actions.push(array_merge({
				"class":"cmd cmd_del",
				"html":"⨯",
				"action":function(self, action, index){
					if (self.o.disabled || self.o.readonly) return;
					self.unselect();
					self.refreshCaption();
					self.focus();
					if (isset(self.o.ondel)) self.o.ondel(self, action, index);
					else if (typeof(self.o.del) == "function") self.o.del(self, action, index);
				}
			}, (typeof(self.o.del) !== "object"?{}:self.o.del)));

			// create actions
			if (self.o.actions) for (var i in self.o.actions) {
				(function(action, index){
					var a=array_copy(action);
					a["class"]=(isset(action["class"])?action["class"]:"cmd");
					delete a["action"];
					self.e.actions[index]=newElement("button", array_merge({
						"attributes":{
							"tabindex":(self.o.tabindex?self.o.tabindex+index+1:0),
						},
						"properties":{
							"onclick":function(){
								if (action.action) action.action(self, action, index);
							}
						}
					}, a));
					self.e.group.appendChild(self.e.actions[index]);
				})(self.o.actions[i], i);
			}

			// combobox group
			self.e.cmb_group=newElement("div", {
				"class":"cmb_group"
					+(self.o.checkboxes?" cmb_checkboxes":"")
					+(self.o.radios?" cmb_radios":"")
					+(self.o.class?" "+self.o.class:"")
				,
				"attributes":{
					"tabindex":(self.o.editable?"-1":(self.o.tabindex?self.o.tabindex:"0"))
				},
				"events":{
					"focus":function(e){
						//console.log("focus");
					},
					"focusin":function(e){
						//console.log("focusin "+e.target);
						if (self.opened) self.open();
					},
					"focusout":function(e){
						//console.log("focusout "+e.target);
						self.close();
					},
					"mousedown":function(e){
					},
					"keydown":function(e){
						var index=self.focusedItem();
						switch (e.keyCode) {
						case 9: // tab
							if (self.opened) {
								self.focus();
								self.close();
								e.preventDefault();
							}
							break;

						case 27: // escape
							self.focus();
							self.close();
							break;

						case 38: // up
							if (self.o.valignTop) {
								self.open();
								if (index === false) {
									if (!self.focusSearch()) // if search enabled, focus search
										if (!self.focusSelectedItem()) // else, focus selected item
											self.focusLastItem(); // else, first item
								} else self.focusPrevItem(index);
							} else {
								if (index === false) {
									self.open();
									if (!self.focusSelectedItem())
										self.focusFirstItem();
								} else {
									if (index !== self.indexFirst()) {
										self.focusPrevItem(index);
									} else {
										if (self.e.cmb_search) {
											self.focusSearch();
										} else {
											self.close();
											self.focus();
										}
									}
								}
							}
							e.preventDefault();
							break;

						case 40: // down
							if (self.o.valignTop) {
								if (index === false) {
									self.open();
									if (!self.focusSelectedItem())
										self.focusLastItem();
								} else {
									if (index != self.indexLast()) {
										self.focusNextItem(index);
									} else {
										if (self.e.cmb_search) {
											self.focusSearch();
										} else {
											self.close();
											self.focus();
										}
									}
								}
							} else {
								if (!self.opened) self.open();
								if (index === false) {
									if (!self.focusSearch()) // if search enabled, focus search
										if (!self.focusSelectedItem()) // else, focus selected item
											self.focusFirstItem(); // else, first item
								} else self.focusNextItem(index);
							}
							e.preventDefault();
							break;

						case 36: // home 
							if (index !== false) self.focusFirstItem();
							break;

						case 35: // end
							if (index !== false) self.focusLastItem();
							break;

						case 33: // page up
							if (index !== false) for (var i=0; i<self.pageSize(); i++) self.focusPrevItem();
							e.preventDefault();
							break;

						case 34: // page down
							if (index !== false) for (var i=0; i<self.pageSize(); i++) self.focusNextItem();
							e.preventDefault();
							break;

						}
					}
				},
				"childs":[self.e.group, self.e.cmb_dropdown]
			});

			// window/combo resize event
			if (typeof(ResizeObserver) == "function") self.resizeobserver=new ResizeObserver(self.resize).observe(self.e.cmb);
			if (typeof(window.addEventListener) == "function") window.addEventListener("resize", self.resize);

			// add all to the combo container
			classAdd(self.o.id, "cmb_container");
			gidset(self.o.id, "");
			gid(self.o.id).appendChild(self.e.cmb_group);

			// refresh initial caption
			self.refreshCaption();

		};

		// scroll top relative to parent
		self.parentScrollTop=function(){
			return (self.o.parent && gid(self.o.parent)?gid(self.o.parent).scrollTop:scrollTop());
		};

		// resize event
		self.resize=function(){

			// checks
			if (!gid(self.o.id)) return false;
			var parent=(self.o.parent && gid(self.o.parent)?self.o.parent:false);

			// initial setup
			var maxWidth=(parent?getLeft(parent)+getWidth(parent):windowWidth());
			var maxHeight=(parent?getTop(parent)+getHeight(parent):windowHeight());
			if (maxWidth > windowWidth()) maxWidth=windowWidth();
			if (maxHeight > windowHeight()) maxHeight=windowHeight();

			// calculate best alignment
			var alignRight, valignTop;
			switch (self.o.align) {
			case "left": alignRight=false; break;
			case "right": alignRight=true; break;
			default: alignRight=(getLeft(self.o.id) > (maxWidth / 2)); // calcular
			}
			switch (self.o.valign) {
			case "top": valignTop=true; break;
			case "bottom": valignTop=false; break;
			default: valignTop=((getTop(self.o.id) - self.parentScrollTop()) > (maxHeight / 2)); // calcular
			}

			// set class alignments
			classEnable(self.o.id, "cmb_align_left",    !alignRight);
			classEnable(self.o.id, "cmb_align_right",    alignRight);
			classEnable(self.o.id, "cmb_valign_top",     valignTop);
			classEnable(self.o.id, "cmb_valign_bottom", !valignTop);

			// calculate dimensions to optimize selection
			var margin=(isset(self.o.margin)?parseInt(self.o.margin):20);
			var inputWidth=getWidth(self.e.cmb);
			var inputHeight=getHeight(self.e.cmb);
			var resultsWidth=(alignRight
				?getLeft(self.e.cmb)+inputWidth-margin
				:maxWidth-margin-getLeft(self.e.cmb)
			);
			var resultsHeight=-inputHeight-margin;
			if (valignTop) {
				var bigger=(!parent || self.parentScrollTop() > getTop(parent)?self.parentScrollTop():getTop(parent));
				resultsHeight+=(getTop(self.e.cmb)-bigger);
			} else {
				resultsHeight+=maxHeight-(getTop(self.e.cmb)-self.parentScrollTop());
			}

			// set sizes
			self.e.cmb_options.style.minWidth=inputWidth+"px";
			self.e.cmb_options.style.maxWidth =parseInt(resultsWidth  < inputWidth ?inputWidth :resultsWidth )+"px";
			self.e.cmb_options.style.maxHeight=parseInt(resultsHeight < inputHeight?inputHeight*2:resultsHeight)+"px";

			// save alignments
			self.o.alignRight=alignRight;
			self.o.valignTop=valignTop;

			// ok
			return true;

		};

		// get/set key name
		self.key=function(key){
			if (isset(key)) self.o.key=key;
			return self.o.key;
		}

		// get/set selected keys
		self.keys=function(keys){
			if (!self.o.key) return false;
			if (isset(keys)) {
				if (keys instanceof Array) {
					for (var i in keys)
						self.keys(keys[i]);
				} else if (isset(keys) || (keys === null && self.o.empty)) {
					var index=self.o.keyindex[keys];
					if (!self.isselected(index)) self.select(index);
				}
			}
			var a=[];
			if (self.o.selected)
				for (var index in self.o.selected)
					if (self.o.selected[index])
						a.push(self.o.selected[index][self.o.key]);
			return a;
		};

		// get/set selected index/indexes
		self.index=function(indexes){
			if (isset(indexes)) {
				if (indexes instanceof Array) {
					for (var i in indexes)
						if (!isNaN(indexes[i]))
							self.select(indexes[i]);
				} else if (!isNaN(indexes)) {
					if (!self.isselected(indexes)) self.select(indexes);
				}
			}
			var a=[];
			if (self.o.multiple) {
				if (self.o.selected)
					for (var index in self.o.selected)
						if (self.o.selected[index])
							a.push(index);
			} else {
				a=self.o.selectedindex;
			}
			return a;
		};

		// get/set single/multiple
		self.multiple=function(multiple){
			if (isset(multiple)) {
				if (self.o.multiple != multiple) {
					self.o.multiple=multiple;
					if (!multiple) {
						var lastindex=self.o.selectedindex;
						var lastselected=array_count(self.o.selected);
						self.unselect();
						self.refreshItems();
						if (lastselected && isset(lastindex)) self.select(1+parseInt(lastindex));
						else if (self.o.empty) self.select(0);
					} else {
						self.refreshItems();
					}
					self.refreshCaption();
				}
			}
			return self.o.multiple;
		};

		// get available options
		self.options=function(){
			return self.o.options;
		};

		// get selected options
		self.selected=function() {
			return (self.o.multiple
				?(self.o.key?self.o.selecteditems:self.o.selected)
				:self.o.selecteditem
			);
		};

		// get/set item
		self.item=function(item, _noevents){
			if (isset(item)) { //  && item !== null
				self.o.selecteditem=item;
				self.o.selected=[item];
				self.o.selectedkeys={};
				if (self.o.key && item !== null) self.o.selectedkeys[item[self.o.key]]=item;
				self.refreshCaption();
				if (self.o.onchange && !_noevents) self.o.onchange(self, item);
				if (self.o.input) gidval(self.o.input, self.value());
			}
			return self.o.selecteditem;
		};

		// get/set input value or get selected keys/indexes
		self.value=function(value){
			if (self.e.cmb_input) {
				if (isset(value)) self.e.cmb_input.value=value;
				return self.e.cmb_input.value;
			} else {
				if (isset(value)) self.keys(value);
				var iks=self.keys();
				if (iks) {
					if (self.o.multiple) {
						return iks;
					} else {
						if (iks[0]) return iks[0];
					}
				}
			}
			return null;
		};

		// get selected items with its key/set with its keys/indexes
		self.values=function(values) {
			if (isset(values)) {
				if (self.o.key) self.keys(values);
				else self.index(values);
			}
			var a={};
			var selected=(self.o.key?self.o.selecteditems:self.o.selected);
			for (var index in selected) a[(self.o.key?selected[index][self.o.key]:index)]=selected[index];
			return a;
		};

		// timed update clear timer
		self.updateTimerClear=function(){
			if (self.updateTimer) {
				clearTimeout(self.updateTimer);
				delete self.updateTimer;
			}
		};

		// timed update
		self.updateTimed=function(noothercheck){
			self.updateTimerClear();
			self.updateTimer=setTimeout(function(){
				self.update();
			}, 250);
		};

		// get/set additional AJAX data
		self.data=function(d){
			if (d) self.o.data=d;
			return self.o.data;
		};

		// get/set search value
		self.search=function(s){
			var search_input=(self.e.cmb_search?self.e.cmb_search:(self.e.cmb_input?self.e.cmb_input:false));
			if (isset(s)) {
				search_input.value=s;
				self.refreshItems();
				self.update();
			}
			return search_input.value;
		};

		// update AJAX data
		self.update=function(o){
			var o=o||{};
			self.updateTimerClear();
			if (self.o.ajax) {
				var first_request=(self.o.requested?false:true);
				self.o.requested=true;
				var r={
					"search":(self.e.cmb_search?self.e.cmb_search.value:(self.e.cmb_input?self.e.cmb_input.value:"")),
					"visible":(isset(self.o.visible)?self.o.visible:null)
				};
				var er=self.data();
				if (er) r=array_merge(r, er);
				if (self.o.ajaxrequest) r=self.o.ajaxrequest(self, r);
				ajax(self.o.ajax, r, function(){}, function(r){
					if (r.data.err) newerror(r.data.err);
					if (r.data.ok) {
						// update ajax data
						self.o.ajaxdata=r.data;
						// refresh item list
						self.refreshItems();
						// if first request, autoselect item
						if (first_request) self.firstselect();
						// focus search, if requested
						if (o.focusSearch) self.focusSearch();
					}
				});
			}
		};

		// destroy
		self.destroy=function(){
			window.removeEventListener("resize", self.resize);
			if (self.resizeobserver) {
				self.resizeobserver.disconnect();
				delete self.resizeobserver;
			}
		};

		// aux: first item/index/key/value selection (called twice, on refresh and on update)
		self.firstselect=function(){
			if (self.o.item) {
				self.item(self.o.item, true);
				//if (self.e.cmb_caption) gidset(self.e.cmb_caption, self.renderItem(self.o.item));
			} else {
				if (isset(self.o.index)) self.index(self.o.index); else if (!self.o.editable && !self.o.multiple) self.index(0);
				if (isset(self.o.keys)) self.keys(self.o.keys);
				if (isset(self.o.value)) self.value(self.o.value);
			}
		};

		// startup
		self.init=function(o){
			if (self.o) self.destroy();
			self.e={"items":{}};
			self.o=array_copy(o);
			if (self.o.ajaxitem) self.o.ajaxitems=[self.o.ajaxitem];
			if (self.o.ajaxitems) self.o.ajaxdata={"data":self.o.ajaxitems};
			self.o.html=(isset(self.o.html)?self.o.html:gidget(self.o.id));
			self.unselect();
			self.o.multiple=self.o.multiple || false;
			self.o.emptyCaption="<div class='cmb_caption_empty'>"+(typeof(self.o.empty) == "string"?self.o.empty:"- Seleccione -")+"</div>";
			self.refresh();
			if (!self.o.item && (isset(self.o.index) || isset(self.o.keys) || isset(self.o.value))) self.update(); // if no item or no index/key/value is provided, no update is needed
			else self.o.requested=true; // if not, mark as requested, to prevent automatic item selection
			self.firstselect();
			// register self
			xwidgets.widgets[self.o.id]=self;
			if (o.focus) self.focus();
		};

		// automatic startup
		self.init(o);

	}

};
