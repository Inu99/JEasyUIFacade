$( document ).ready(function() {
	
	contextBarInit();
	
});

function contextBarInit(){
	$(document).ajaxSuccess(function(event, jqXHR, ajaxOptions, data){
		var extras = {};
		if (jqXHR.responseJson){
			extras = jqXHR.responseJson.extras;
		} else {
			try {
				extras = $.parseJSON(jqXHR.responseText).extras;
			} catch (err) {
				extras = {};
			}
		}
		if (extras && extras.ContextBar){
			contextBarRefresh(extras.ContextBar);
		}
	});
	
	contextBarLoad();
}

function contextBarLoad(delay){
	if (delay == undefined) delay = 100;
	
	setTimeout(function(){
		if ($.active == 0 && $('#contextBar .panel-loading').length > 0){
			$.ajax({
				type: 'POST',
				url: 'exface/exface.php?exftpl=exface.JEasyUiTemplate',
				dataType: 'json',
				data: {
					action: 'exface.Core.ShowWidget',
					resource: getPageId(),
					element: 'ContextBar'
				},
				success: function(data, textStatus, jqXHR) {
					contextBarRefresh(data);
				},
				error: function(jqXHR, textStatus, errorThrown){
					contextBarRefresh({});
				}
			});
		} else {
			contextBarLoad(delay*3);
		}
	}, delay);
}

function contextBarRefresh(data){
	$('#contextBar').children().not('.login-logout').not('.user-info').remove();
	for (var id in data){
		var btn = $(' \
				<!-- '+data[id].bar_widget_id+' --> \
				<div class="toolbar-element" id="'+id+'"> \
					<div class="toolbar-button" title="'+data[id].hint+'" data-widget="'+data[id].bar_widget_id+'"> \
						<a href="#" class="easyui-linkbutton context-button" data-options="plain:true, iconCls:\''+data[id].icon+'\'">'+data[id].indicator+'</a> \
					</div> \
				</div>');
		$('#contextBar').prepend(btn);
	}
	$.parser.parse($('#contextBar'));
	
	$('#contextBar .context-button').tooltip({
        content: function(){return $('<div id="'+$(this).closest('.toolbar-element').attr('id')+'_tooltip"></div>')},
        showEvent: 'click',
        onUpdate: function(content){
        	content.panel({
                width: 200,
                height: 300,
                border: false,
                href: 'exface/exface.php?exftpl=exface.JEasyUiTemplate',
                method: 'POST',
                cache: false,
                queryParams: {
                    action: 'exface.Core.ShowContextPopup',
                    resource: getPageId(),
                    element: $(this).parent().data('widget')
                }
            });
        },
        onShow: function(){
            var t = $(this);
            t.tooltip('tip').unbind().bind('mouseenter', function(){
                t.tooltip('show');
            }).bind('mouseleave', function(){
                t.tooltip('hide');
            });
        },
        onHide: function(){
        	$(this).one('click', function(){
        		$(this).tooltip('update');
        	})
        	$('#'+$(this).closest('.toolbar-element').attr('id')+'_tooltip').panel('destroy');
		}
    });
}

function contextShowMenu(containerSelector){
	$(containerSelector).find('.toolbar-element').empty().append('<li class="header"><div class="overlay text-center"><i class="fa fa-refresh fa-spin"></i></div></li>');
	$.ajax({
		type: 'POST',
		url: 'exface/exface.php?exftpl=exface.JEasyUiTemplate',
		dataType: 'html',
		data: {
			action: 'exface.Core.ShowContextPopup',
			resource: getPageId(),
			element: $(containerSelector).data('widget')
		},
		success: function(data, textStatus, jqXHR) {
			var $data = $(data);
			$(containerSelector).find('.dropdown-menu').empty().append('<li></li>').children('li:first-of-type').append($data);
		},
		error: function(jqXHR, textStatus, errorThrown){
			adminLteCreateDialog($("body"), "error", jqXHR.responseText, jqXHR.status + " " + jqXHR.statusText);
		}
	});
}

function getPageId(){
	return $("meta[name='page_id']").attr("content");
}

/**
 * Creates an jEasyUI dialog
 */
function jeasyui_create_dialog(parentElement, id, options, content, parseContent){
	parseContent = parseContent ? true : false;
	var dialog = $('<div class="easyui-dialog" id="'+id+'"></div>');
	parentElement.append(dialog);
	$.parser.parse(content);
	dialog.append(content);
	if (parseContent){
		$.parser.parse(dialog);
	}
	dialog.dialog(options);
	// Lädt man eine Seite neu wenn man an alexa UI aber nicht an alexa RMS angemeldet ist,
	// erscheint in Firefox eine Fehlermeldung in der linken unteren Ecke, in WebView ist
	// die Fehlermeldung gar nicht zu sehen. Deshalb wird sie hier nochmal zentriert.
	setTimeout(function() { dialog.dialog("center"); }, 0);
}

/*$.extend($.fn.textbox.methods, {
	addClearBtn: function(jq, iconCls){
		return jq.each(function(){
			var t = $(this);
			var opts = t.textbox('options');
			opts.icons = opts.icons || [];
			opts.icons.unshift({
				iconCls: iconCls,
				handler: function(e){
					$(e.data.target).textbox('clear').textbox('textbox').focus();
					$(this).css('visibility','hidden');
				}
			});
			t.textbox();
			if (!t.textbox('getText')){
				t.textbox('getIcon',0).css('visibility','hidden');
			}
			t.textbox('textbox').bind('keyup', function(){
				var icon = t.textbox('getIcon',0);
				if ($(this).val()){
					icon.css('visibility','visible');
				} else {
					icon.css('visibility','hidden');
				}
			});
		});
	}
});*/

// compare arrays (http://stackoverflow.com/questions/7837456/how-to-compare-arrays-in-javascript)
// Warn if overriding existing method
if(Array.prototype.equals)
    console.warn("Overriding existing Array.prototype.equals. Possible causes: New API defines the method, there's a framework conflict or you've got double inclusions in your code.");
// attach the .equals method to Array's prototype to call it on any array
Array.prototype.equals = function (array) {
    // if the other array is a falsy value, return
    if (!array)
        return false;

    // compare lengths - can save a lot of time 
    if (this.length != array.length)
        return false;

    for (var i = 0, l=this.length; i < l; i++) {
        // Check if we have nested arrays
        if (this[i] instanceof Array && array[i] instanceof Array) {
            // recurse into the nested arrays
            if (!this[i].equals(array[i]))
                return false;       
        }           
        else if (this[i] != array[i]) { 
            // Warning - two different object instances will never be equal: {x:20} != {x:20}
            return false;   
        }           
    }       
    return true;
}
// Hide method from for-in loops
Object.defineProperty(Array.prototype, "equals", {enumerable: false});