<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Widgets\DataColumnGroup;
use exface\Core\Widgets\Data;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryToolbarsTrait;
use exface\Core\Widgets\MenuButton;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\Tabs;
use exface\Core\Interfaces\Widgets\iHaveContextMenu;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryAlignmentTrait;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\TextStylesDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;

/**
 * Implementation of a basic grid.
 *
 * @method Data getWidget()
 *
 * @author Andrej Kabachnik
 *
 */
class EuiData extends EuiAbstractElement
{
    use JqueryToolbarsTrait;
    
    use JqueryAlignmentTrait;
    
    private $toolbar_id = null;
    
    private $show_footer = null;
    
    private $on_before_load = '';
    
    private $on_load_success = '';
    
    private $on_load_error = '';
    
    private $load_filter_script = '';
    
    private $headers_colspan = array();
    
    private $headers_rowspan = array();
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init();
        $widget = $this->getWidget();
        
        // Prepare the configurator widget
        $widget->getConfiguratorWidget()
        ->setTabPosition(Tabs::TAB_POSITION_RIGHT)
        ->setHideTabsCaptions(true);
    }
    
    /**
     * The Data element by itself does not generate anything - it just offers common utility methods.
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtml()
     */
    public function buildHtml()
    {
        return '';
    }
    
    /**
     * The Data element by itself does not generate anything - it just offers common utility methods.
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::generateJ()
     */
    public function buildJs()
    {
        return '';
    }
    
    /**
     *
     * @return boolean
     */
    protected function isLazyLoading()
    {
        return $this->getWidget()->getLazyLoading(true);
    }
    
    /**
     * Generates config-elements for the js grid instatiator, that define the data source for the grid.
     * By default the data source is remote and will be fetched via AJAX. Override this method for local data sources.
     *
     * @return string
     */
    public function buildJsDataSource()
    {
        $widget = $this->getWidget();
        
        if ($this->isLazyLoading()) {
            // Lazy loading via AJAX
            $params = array();
            $queryParams = array(
                'resource' => $widget->getPage()->getAliasWithNamespace(),
                'element' => $widget->getId(),
                'object' => $this->getWidget()->getMetaObject()->getId(),
                'action' => $widget->getLazyLoadingActionAlias()
            );
            foreach ($queryParams as $param => $val) {
                $params[] = $param . ': "' . $val . '"';
            }
            
            $result = '
				url: "' . $this->getAjaxUrl() . '"
				, queryParams: {' . implode("\n\t\t\t\t\t, ", $params) . '}';
        } else {
            // Data embedded in the code of the DataGrid
            $data = $widget->prepareDataSheetToRead($widget->getValuesDataSheet());
            if (! $data->isFresh()) {
                $data->dataRead();
            }
            $result = '
				remoteSort: false
				, loader: function(param, success, error) {' . $this->buildJsDataLoaderWithoutAjax($data) . '}';
        }
        
        return $result;
    }
    
    public function buildJsInitOptionsHead()
    {
        $widget = $this->getWidget();
        
        // add initial sorters
        $sort_by = [];
        $direction = [];
        if ($this->isLazyLoading() && count($widget->getSorters()) > 0) {
            foreach ($widget->getSorters() as $sort) {
                // Check if sorting over a column and use the column name in this case
                // to ensure, the sorting indicator lights up in the column header.
                if ($col = $widget->getColumnByAttributeAlias($sort->getProperty('attribute_alias'))){
                    $sort_by[] = urlencode($col->getDataColumnName());
                } else {
                    $sort_by[] = urlencode($sort->getProperty('attribute_alias'));
                }
                $direction[] = strcasecmp($sort->getProperty('direction'), SortingDirectionsDataType::ASC) === 0 ? 'asc' : 'desc';
            }
            $sortColumn = ", sortName: '" . implode(',', $sort_by) . "'";
            $sortOrder = ", sortOrder: '" . implode(',', $direction) . "'";
        }
        
        if (! $default_page_size = $widget->getPaginator()->getPageSize()) {
            try {
                $default_page_size = $this->getFacade()->getConfig()->getOption('WIDGET.' . $widget->getWidgetType() . '.PAGE_SIZE');
            } catch (ConfigOptionNotFoundError $e) {
                $default_page_size = $this->getFacade()->getConfig()->getOption('WIDGET.DATATABLE.PAGE_SIZE');
            }
        }
        
        $page_sizes = $this->getFacade()->getApp()->getConfig()->getOption('WIDGET.DATATABLE.PAGE_SIZES_SELECTABLE')->toArray();
        if (!in_array($default_page_size, $page_sizes)){
            $page_sizes[] = $default_page_size;
            sort($page_sizes);
        }
        
        // Make sure, all selections are cleared, when the data is loaded from the backend. This ensures, the selected rows are always visible to the user!
        if ($widget->getMultiSelect()) {
            // TODO: Gibt Probleme im Context einer InputComboTable. Dort muesste die Zeile folgendermassen
            // aussehen: $(this).combogrid("grid").' . $this->getElementType() . '("clearSelections");
            // Ist es fuer eine InputComboTable sinnvoll nach jedem Laden ihre Auswahl zu verlieren???
            // $this->addOnLoadSuccess('$(this).' . $this->getElementType() . '("clearSelections");');
            
            // Autoselect all rows if neccessary
            if ($widget->getMultiSelectAllSelected()){
                $this->addOnLoadSuccess("$('#" . $this->getId() . "')." . $this->getElementType() . "('selectAll');");
            }
        }
        
        $output = '
				, rownumbers: ' . ($widget->getShowRowNumbers() ? 'true' : 'false') . '
				, fitColumns: true
				, multiSort: ' . ($widget->getHeaderSortMultiple() ? 'true' : 'false') . '
				' . $sortColumn . $sortOrder . '
				' . ($widget->getUidColumnId() ? ', idField: "' . $widget->getUidColumn()->getDataColumnName() . '"' : '') . '
				, singleSelect: ' . ($widget->getMultiSelect() ? 'false' : 'true') . '
				' . ($this->getWidth() ? ', width: "' . $this->getWidth() . '"' : '') . '
				, pagination: ' . ($widget->isPaged() ? 'true' : 'false') . '
				' . ($widget->isPaged() ? ', pageList: ' . json_encode($page_sizes) : '') . '
				, showFooter: ' . ($widget->hasColumnFooters() ? 'true' : 'false') . '
                , pageSize: ' . $default_page_size . '
				, striped: ' . ($widget->getStriped() ? 'true' : 'false') . '
				, nowrap: ' . ($widget->getNowrap() ? 'true' : 'false') . '
				, toolbar: "#' . $this->getToolbarId() . '"
				' . ($this->buildJsOnBeforeLoadFunction() ? ', onBeforeLoad: ' . $this->buildJsOnBeforeLoadFunction() : '') . '
				' . $this->buildJsOnLoadSuccessOption() . '
				, onLoadError: function(response) {
					' . $this->buildJsShowError('response.responseText', 'response.status + " " + response.statusText') . '
					' . $this->getOnLoadError() . '
				}
				' . ($this->getLoadFilterScript() ? ', loadFilter: function(data) {
					' . $this->getLoadFilterScript() . '
					return data;
				}' : '') . '
				, columns: [ ' . implode(',', $this->buildJsInitOptionsColumns()) . ' ]';
        return $output;
    }
    
    protected function buildJsOnLoadSuccessOption() : string
    {
        $widget = $this->getWidget();
        
        // FIXME these are two different implementations for the selection fixer for
        // single- and multi-select. The multi-select version will not reselect rows,
        // that have the same UID, but different data (e.g. because it changed compared
        // to the last reload.
        if ($widget instanceof iSupportMultiSelect && $widget->getMultiSelect() === true) {
            // Add a script to remove selected but not present rows onLoadSuccess. getRowIndex returns
            // -1 for selected but not present rows. Selections outlive a reload but the selected row
            // may have been deleted in the meanwhile. An example is "offene Positionen stornieren" in
            // "Rueckstandsliste".
            $fixSelection = <<<JS

                    var rows = jqSelf.{$this->getElementType()}("getSelections");
                    var selectedRows = [];
                    for (var i = 0; i < rows.length; i++) {
                        var index = jqSelf.{$this->getElementType()}("getRowIndex", rows[i]);
                        if( index >= 0) {
                            selectedRows.push(index);
                        }
                    }
                    jqSelf.{$this->getElementType()}("clearSelections");
                    for (var i = 0; i < selectedRows.length; i++) {
                        jqSelf.{$this->getElementType()}("selectRow", selectedRows[i]);
                    }

JS;
        } else {
            $fixSelection = <<<JS

                    var prevSelection = jqSelf.data("_prevSelection");
                    if (prevSelection !== undefined) {
                        var curSelectedIdx = -1;
                        var curRows = jqSelf.{$this->getElementType()}('getRows');
                        for (var i in curRows) {
                            if ({$this->buildJsRowCompare('curRows[i]', 'prevSelection')}) {
                                curSelectedIdx = i;
                                break;
                            }
                        }
                        if (curSelectedIdx !== -1) {
                            jqSelf.{$this->getElementType()}('selectRow', curSelectedIdx);
                        } else {
                            {$this->buildJsValueResetter()}
                        }
                    }

JS;
        }
        
        return <<<JS

, onLoadSuccess: function(data) {
                    var jqSelf = $(this);
                    
                    {$fixSelection}
                    
					{$this->getOnLoadSuccess()}
				}

JS;
    }
    
    protected function buildJsOnChangeScript(string $rowJs = 'row', string $indexJs = 'index') : string
    {
        return <<<JS
                        var prevRow = $(this).data('_prevSelection');
                        $(this).data('_prevSelection', {$rowJs});
                        if (prevRow !== undefined && {$this->buildJsRowCompare($rowJs, 'prevRow')}) {
                            return;
                        } 
                        {$this->getOnChangeScript()}
                        
JS;
    }
    
    /**
     * Returns an inline JS snippet to compare two data rows represented by JS objects.
     * 
     * If this widget has a UID column, only the values of this column will be compared,
     * unless $trustUid is FALSE. This is handy if you need to compare if the rows represent
     * the same object (e.g. when selecting based on a row).
     * 
     * If this widget has no UID column or $trustUid is FALSE, the JSON-representations of
     * the rows will be compared.
     * 
     * @param string $leftRowJs
     * @param string $rightRowJs
     * @param bool $trustUid
     * @return string
     */
    protected function buildJsRowCompare(string $leftRowJs, string $rightRowJs, bool $trustUid = true) : string
    {
        if ($trustUid === true && $this->getWidget()->hasUidColumn()) {
            $uid = $this->getWidget()->getUidColumn()->getDataColumnName();
            return "{$leftRowJs}['{$uid}'] == {$rightRowJs}['{$uid}']";
        } else {
            return "(JSON.stringify({$leftRowJs}) == JSON.stringify({$rightRowJs}))";
        }
    }
    
    public function buildJsInitOptionsColumns(array $column_groups = null)
    {
        if (! $column_groups) {
            $column_groups = $this->getWidget()->getColumnGroups();
        }
        
        // render the columns
        $header_rows = array();
        $full_height_column_groups = array();
        if ($this->getWidget()->getMultiSelect()) {
            $header_rows[0][0] = '{field: "ck", checkbox: true}';
        }
        /* @var $column_group \exface\Core\Widgets\DataColumnGroup */
        // Set the rowspan for column groups with a caption and remember those without a caption to set the colspan later
        foreach ($column_groups as $column_group) {
            if (! $column_group->getCaption()) {
                $full_height_column_groups[] = $column_group;
            }
        }
        // Now set colspan = 2 for all full height columns, if there are two rows of columns
        if (count($full_height_column_groups) != count($column_groups)) {
            foreach ($full_height_column_groups as $column_group) {
                $this->setColumnHeaderRowspan($column_group, 2);
            }
            if ($this->getWidget()->getMultiSelect()) {
                $header_rows[0][0] = '{field: "ck", checkbox: true, rowspan: 2}';
            }
        }
        // Now loop through all column groups again and built the header definition
        foreach ($column_groups as $column_group) {
            if ($column_group->getCaption()) {
                $header_rows[0][] = '{title: "' . str_replace('"', '\"', $column_group->getCaption()) . '", colspan: ' . $column_group->countColumnsVisible() . '}';
                $put_into_header_row = 1;
            } else {
                $put_into_header_row = 0;
            }
            foreach ($column_group->getColumns() as $col) {
                $header_rows[$put_into_header_row][] = '{' . $this->buildJsInitOptionsColumn($col) . '}';
            }
        }
        
        foreach ($header_rows as $i => $row) {
            $header_rows[$i] = '[' . implode(',', $row) . ']';
        }
        
        return $header_rows;
    }
    
    protected function setColumnHeaderColspan(DataColumnGroup $column_group, $colspan)
    {
        foreach ($column_group->getColumns() as $col) {
            $this->headers_colspan[$col->getId()] = $colspan;
        }
        return $this;
    }
    
    protected function getColumnHeaderColspan($column_id)
    {
        return $this->headers_colspan[$column_id];
    }
    
    protected function setColumnHeaderRowspan(DataColumnGroup $column_group, $rowspan)
    {
        foreach ($column_group->getColumns() as $col) {
            $this->headers_rowspan[$col->getId()] = $rowspan;
        }
        return $this;
    }
    
    protected function getColumnHeaderRowspan($column_id)
    {
        return $this->headers_rowspan[$column_id];
    }
    
    protected function buildJsInitOptionsColumn(\exface\Core\Widgets\DataColumn $col)
    {
        $colspan = $this->getColumnHeaderColspan($col->getId());
        $rowspan = $this->getColumnHeaderRowspan($col->getId());
        
        // In datagrids with remote source sorting is allways performed remotely, so
        // it cannot be done for columns without attribute binding (the server cannot
        // sort those)
        $sortable = $col->isBoundToAttribute() ? ($col->isSortable() ? 'true' : 'false') : 'false';
        
        $output = '
                        title: "<span title=\"' . $this->buildHintText($col->getHint(), true) . '\">' . $col->getCaption() . '</span>"
                        , field: "' . ($col->getDataColumnName() ? $col->getDataColumnName() : $col->getId()) . '"
                        ' . ($col->isBoundToAttribute() ? ', _attributeAlias: "' . $col->getAttributeAlias() . '"' : '') . "
                        " . ($colspan ? ', colspan: ' . intval($colspan) : '') . ($rowspan ? ', rowspan: ' . intval($rowspan) : '') . "
                        " . ($col->isHidden() ? ', hidden: true' : '') . "
                        " . ($col->getWidth()->isFacadeSpecific() ? ', width: "' . $col->getWidth()->toString() . '"' : '') . "
                        " . (($format_options = $this->buildJsInitOptionsColumnFormatter($col, 'value', 'row', 'index')) ? ', ' . $format_options . '' : '') . "
                        " . ', align: "' . $this->buildCssTextAlignValue($col->getAlign()) . '"
                        ' . ', sortable: ' . $sortable . "
                        " . ($col->isSortable() ? ", order: '" . ($col->getDefaultSortingDirection() === SortingDirectionsDataType::ASC($this->getWorkbench()) ? 'asc' : 'desc') . "'" : '');
        
        return $output;
    }
    
    public function getToolbarId()
    {
        if (is_null($this->toolbar_id)) {
            $this->toolbar_id = $this->getId() . '_toolbar';
        }
        return $this->toolbar_id;
    }
    
    public function setToolbarId($value)
    {
        $this->toolbar_id = $value;
    }
    
    /**
     * Add JS code to be executed on the OnBeforeLoad event of jEasyUI datagrid.
     * The script will have access to the "param" variable
     * representing all XHR parameters to be sent to the server.
     *
     * @param string $script
     */
    public function addOnBeforeLoad($script)
    {
        $this->on_before_load .= $script;
    }
    
    /**
     * Set JS code to be executed on the OnBeforeLoad event of jEasyUI datagrid.
     * The script will have access to the "param" variable
     * representing all XHR parameters to be sent to the server.
     *
     * @param string $script
     */
    public function setOnBeforeLoad($script)
    {
        $this->on_before_load = $script;
    }
    
    /**
     * Binds a script to the onLoadSuccess event (available JS vars: jqSelf, data).
     * 
     * The script may use the following javascript variables available locally in the event handler:
     * 
     * - jqSelf - same as $('#{$this->getId()}'), but faster
     * - data - the data object just loaded
     *
     * @param string $script
     */
    public function addOnLoadSuccess($script)
    {
        $this->on_load_success .= $script;
    }
    
    protected function getOnLoadSuccess()
    {
        return $this->on_load_success;
    }
    
    /**
     * Binds a script to the onLoadError event.
     *
     * @param string $script
     */
    public function addOnLoadError($script)
    {
        $this->on_load_error .= $script;
    }
    
    protected function getOnLoadError()
    {
        return $this->on_load_error;
    }
    
    public function addOnChangeScript($string)
    {
        return parent::addOnChangeScript($string);
    }
    
    public function addLoadFilterScript($javascript)
    {
        $this->load_filter_script .= $javascript;
    }
    
    public function getLoadFilterScript()
    {
        return $this->load_filter_script;
    }
    
    public function buildJsDataLoaderWithoutAjax(DataSheetInterface $data)
    {
        $js = <<<JS
        
		try {
			var data = {$this->getFacade()->encodeData($this->getFacade()->buildResponseData($data, $this->getWidget()))};
		} catch (err){
            error();
			return;
		}
		
		var filter, value, total = data.rows.length;
        var filterPrefix = ("{$this->getFacade()->getUrlFilterPrefix()}").toLowerCase();
		for(var p in param){
			if (p.toLowerCase().startsWith(filterPrefix)){
				column = p.substring(filterPrefix.length);
				value = param[p];
			}
			
			if (value){
				var regexp = new RegExp(value, 'i');
				for (var row=0; row<total; row++){
					if (data.rows[row] && typeof data.rows[row][column] !== 'undefined'){
						if (!data.rows[row][column].match(regexp)){
							data.rows.splice(row, 1);
						}
					}
				}
			}
		}
		data.total = data.rows.length;
        success(data);
		return;
JS;
        
        // This is a strange fix for jEasyUI rendering wrong height in non-ajax
        // data widgets...
        if (! $this->getWidget()->getHideHeader()){
            $this->addOnLoadSuccess("setTimeout(function(){ $('#" . $this->getId() . "').datagrid('resize'); }, 0);");
        }
        
        return $js;
    }
    
    public function buildJsInitOptions()
    {
        return $this->buildJsDataSource() . $this->buildJsInitOptionsHead();
    }
    
    protected function buildHtmlContextMenu()
    {
        $widget = $this->getWidget();
        $context_menu_html = '';
        if ($widget->hasButtons()) {
            $main_toolbar = $widget->getToolbarMain();
            
            foreach ($main_toolbar->getButtonGroupFirst()->getButtons() as $button) {
                $context_menu_html .= $this->buildHtmlContextMenuItem($button);
            }
            
            foreach ($widget->getToolbars() as $toolbar){
                if ($toolbar->getIncludeSearchActions()){
                    $search_button_group = $toolbar->getButtonGroupForSearchActions();
                } else {
                    $search_button_group = null;
                }
                foreach ($toolbar->getButtonGroups() as $btn_group){
                    if ($btn_group !== $main_toolbar->getButtonGroupFirst() && $btn_group !== $search_button_group && $btn_group->hasButtons()){
                        $context_menu_html = $context_menu_html ? $context_menu_html . '<div class="menu-sep"></div>' : $context_menu_html;
                        foreach ($btn_group->getButtons() as $button){
                            $context_menu_html .= $this->buildHtmlContextMenuItem($button);
                        }
                    }
                }
            }
        }
        return $context_menu_html;
    }
    
    protected function buildHtmlContextMenuItem(Button $button)
    {
        $menu_item = '';
        if ($button instanceof MenuButton){
            if ($button->getParent() instanceof ButtonGroup && $button === $this->getFacade()->getElement($button->getParent())->getMoreButtonsMenu()){
                foreach ($button->getMenu()->getButtonGroups() as $grp){
                    $menu_item .= '<div class="menu-sep"></div>';
                    foreach ($grp->getButtons() as $btn){
                        $menu_item .= $this->buildHtmlContextMenuItem($btn);
                    }
                }
            } else {
                $menu_item .= '<div><span>' . $button->getCaption() . '</span><div>' . $this->getFacade()->getElement($button)->buildHtmlMenuItems(). '</div></div>';
            }
        } else {
            $menu_item .= $this->getFacade()->getElement($button)->buildHtmlButton();
        }
        $menu_item = str_replace(['<a id="', '</a>', 'easyui-linkbutton', ' href="#"'], ['<div id="' . $this->getId() . '_', '</div>', '', ''], $menu_item);
        return $menu_item;
    }
    
    protected function buildJsContextMenu()
    {
        // Prevent context menu on context menu. Otherwise the browser-menu keeps popping up
        // over the context menu from time to time.
        return '$("#' . $this->getId() . '_cmenu").contextmenu(function(e){e.stopPropagation(); e.preventDefault(); return false;})';
    }
    
    /**
     * Returns the base HTML element to construct the widget from: e.g. div, table, etc.
     *
     * @return string
     */
    protected function getBaseHtmlElement()
    {
        return 'table';
    }
    
    public function getDefaultButtonAlignment()
    {
        return $this->getFacade()->getConfig()->getOption('WIDGET.DATA.DEFAULT_BUTTON_ALIGNMENT');
    }
    
    /**
     * Creates the HTML for the header controls: filters, sorters, buttons, etc.
     * @return string
     */
    protected function buildHtmlTableHeader($panel_options = "border: false, width: '100%'")
    {
        $widget = $this->getWidget();
        $toolbar_style = '';
        
        // Prepare the header with the configurator and the toolbars
        $configurator_widget = $widget->getConfiguratorWidget();
        /* @var $configurator_element \exface\JEasyUIFacade\Facades\Elements\EuiDataConfigurator */
        $configurator_element = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->setFitOption(false)->setStyleAsPills(true);
        
        if ($configurator_widget->isEmpty()){
            $configurator_widget->setHidden(true);
            $configurator_panel_collapsed = ', collapsed: true';
        }
        
        // jEasyUI will not resize the configurator once the datagrid is resized
        // (don't know why), so we need to do it manually.
        // Wrapping the resize-call into a setTimeout( ,0) is another strange
        // workaround, but if not done so, the configurator will get resized to
        // the old size, not the new one.
        $this->addOnResizeScript("
            if(typeof $('#" . $configurator_element->getId() . "')." . $configurator_element->getElementType() . "() !== 'undefined') {
                setTimeout(function(){
                    $('#" . $configurator_element->getId() . "')." . $configurator_element->getElementType() . "('resize');
                }, 0);
            }
        ");
        
        // Build the HTML for the button toolbars.
        // IMPORTANT: do it BEFORE the context menu since buttons may be moved
        // between toolbars and hidden in menus when rendering.
        $toolbars_html = $this->buildHtmlToolbars();
        
        // Create a context menu if any items were found
        $context_menu_html = $this->buildHtmlContextMenu();
        if ($context_menu_html && ($widget instanceof iHaveContextMenu) && $widget->getContextMenuEnabled()) {
            $context_menu_html = '<div id="' . $this->getId() . '_cmenu" class="easyui-menu">' . $context_menu_html . '</div>';
        } else {
            $context_menu_html = '';
        }
        
        if ($widget->getHideHeader()){
            $panel_options .= ', collapsed: true';
            $toolbar_style .= 'display: none; height: 0;';
        } else {
            if ($widget->getConfiguratorWidget()->isCollapsed() === true) {
                $panel_options .= ', collapsed: true';
            }
        }
        
        return <<<HTML
        
                <div class="easyui-panel exf-data-header" data-options="footer: '#{$this->getToolbarId()}_footer', {$panel_options} {$configurator_panel_collapsed}">
                    {$configurator_element->buildHtml()}
                </div>
                <div id="{$this->getToolbarId()}_footer" class="datatable-toolbar" style="{$toolbar_style}">
                    {$toolbars_html}
                </div>
                {$context_menu_html}
                
HTML;
    }
    
    /**
     * Creates column options formatter:function(value,row,idx) and styler:function(value,row,idx) from the data
     * of a given column.
     *
     * The names of the JS variables "value", "row" and "index" must be passed along with with column widget.
     *
     * @param DataColumn $col
     * @param string $js_var_value
     * @param string $js_var_row
     * @param string $js_var_index
     * @return string
     */
    protected function buildJsInitOptionsColumnFormatter(DataColumn $col, $js_var_value, $js_var_row, $js_var_index)
    {
        $cellWidget = $col->getCellWidget();
        
        if (($cellWidget instanceof iDisplayValue) && $cellWidget->getDisableFormatting()) {
            return '';
        }
        
        $options = '';
        
        // Data type specific formatting
        $formatter_js = '';
        $cellTpl = $this->getFacade()->getElement($cellWidget);
        if (($cellTpl instanceof JsValueDecoratingInterface) && $cellTpl->hasDecorator()) {
            $formatter_js = $cellTpl->buildJsValueDecorator($js_var_value);
        }
        
        // Formatter option
        if ($formatter_js) {
            $options = <<<JS

                        formatter: function({$js_var_value},{$js_var_row},{$js_var_index}){

                            try {
                                return {$formatter_js};
                            } catch (e) {
                                console.warn('Cannot apply decorator to column {$col->getDataColumnName()} . ', e);
                                return {$js_var_value}; 
                            } 

                        }
JS;
        }
        
        // Styler option
        $styler = $col->getCellStylerScript();
        if (! $styler) {
            $stylerCss = '';
            if ($cellWidget instanceof iShowText){
                switch ($cellWidget->getStyle()) {
                    case TextStylesDataType::BOLD:
                        $stylerCss = "font-weight: bold;";
                        break;
                    case TextStylesDataType::ITALIC:
                        $stylerCss = "font-style: italic;";
                        break;
                    case TextStylesDataType::UNDERLINE:
                        $stylerCss = "text-decoration: underline;";
                        break;
                    case TextStylesDataType::UNDERLINE:
                        $stylerCss = "text-decoration: line-through;";
                        break;
                }
            }
            $maxWidth = $col->getWidthMax();
            if (! $maxWidth->isUndefined()) {
                if ($maxWidth->isRelative()) {
                    // TODO
                } else {
                    $stylerCss .= 'max-width: ' . $maxWidth->getValue() . '; text-overflow: ellipsis;';
                }
            }
            
            if ($stylerCss !== '') {
                $styler = "return '" . $stylerCss . "';";
            }
        }
        
        if ($styler) {
            $options = ($options ?  $options . ', ' : '') . "styler: function({$js_var_value},{$js_var_row},{$js_var_index}){" . $styler . "}";
        }
        
        return $options;
    }
    
    protected function buildJsOnBeforeLoadScript($js_var_param = 'param')
    {
        // Abort loading if _skipNextLoad is set - don't forget to trigger
        // resize, just as a regular load would do. Otherwise the table would
        // not fit exaclty in containers like splits.
        return <<<JS
                    // Abort immediately if next loading should be skipped
                    var jqself = $(this);
                    if (jqself.data("_skipNextLoad") == true) {
    					jqself.data("_skipNextLoad", false);
                        jqself.trigger('resize');
    					return false;
    				}

                    // Scripts added programmatically
				    {$this->on_before_load}

JS;
    }
    
    protected function buildJsOnBeforeLoadFunction()
    {
        if (! $script = $this->buildJsOnBeforeLoadScript('param')) {
            return '';
        }
        
        return <<<JS

                function(param) {
    				{$script}
				}

JS;
    }
    				
    protected function buildJsValueResetter() : string
    {
        return <<<JS

                            // Reset selection
                            var jqSelf = $('#{$this->getId()}');
                            jqSelf.{$this->getElementType()}('clearSelections').{$this->getElementType()}('clearChecked');
                            if (jqSelf.data('_prevSelection') !== undefined) {
                                jqSelf.removeData('_prevSelection');
                                {$this->getOnChangeScript()}
                            }

JS;
    }
                                
    protected function buildJsOnBeforeLoadAddConfiguratorData(string $paramJs = 'param') : string
    {
        $configurator_element = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
        
        return <<<JS
        
                try {
                    if (! {$configurator_element->buildJsValidator()}) {
                        {$this->buildJsDataResetter()}
                        return false;
                    }
                } catch (e) {
                    console.warn('Could not check filter validity - ', e);
                }
                {$paramJs}['data'] = {$configurator_element->buildJsDataGetter()};
                
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsAutoloadDisabler() : string
    {
        $widget = $this->getWidget();
        $js = '';
        if (! $widget->getAutoloadData() && $widget->getLazyLoading()) {
            // Wrap in setTimeout() to allow the grid to be drawn before placing the message in the middle.
            $js .= <<<JS
            
            $("#{$this->getId()}").data("_skipNextLoad", true);
            setTimeout(function(){
                {$this->buildJsAutoloadDisabledMessageShow()}
            }, 0);

JS;
            
            // Dieses Skript wird nach dem erfolgreichen Laden ausgefuehrt, um die angezeigte
            // Nachricht (s.u.) zu entfernen. Das Skript muss vor $grid_head erzeugt werden.
            $this->addOnLoadSuccess($this->buildJsAutoloadDisabledMessageHide());        
            
        }
        return $js;
    }
    
    
    
    /**
     * Generates JS code to show a message if the initial load was skipped.
     *
     * @return string
     */
    protected function buildJsAutoloadDisabledMessageShow() : string
    {
        return <<<JS
        
            $("#{$this->getId()}").parent().append("\
                <div id='{$this->getId()}_no_initial_load_message'\
                     class='no-initial-load-message-overlay'>\
                    <table class='no-initial-load-message-overlay-table'>\
                        <tr>\
                            <td style='text-align:center;'>\
                                {$this->getWidget()->getAutoloadDisabledHint()}\
                            </td>\
                        </tr>\
                    </table>\
                </div>\
            ");
JS;
    }
    
    /**
     * Generates JS code to remove the message if the initial load was skipped.
     *
     * @return string
     */
    protected function buildJsAutoloadDisabledMessageHide() : string
    {
        $output = <<<JS
        
        $("#{$this->getId()}_no_initial_load_message").remove();
JS;
        
        return $output;
    }
    
    
    
    /**
     * Returns a JS snippet, that empties the table (removes all rows).
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return "";
    }
}