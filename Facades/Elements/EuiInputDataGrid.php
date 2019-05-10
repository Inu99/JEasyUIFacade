<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Input;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDisableConditionTrait;

/**
 *
 * @method Input getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class EuiInputDataGrid extends EuiInput
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildHtml()
     */
    public function buildHtml()
    {
        /* @var $widget \exface\Core\Widgets\Input */
        $widget = $this->getWidget();
        
        $output = '	<input style="height: 100%; width: 100%;"
						name="' . $widget->getAttributeAlias() . '" 
						value="' . $this->getValueWithDefaults() . '" 
						id="' . $this->getId() . '"  
						' . ($widget->isRequired() ? 'required="true" ' : '') . '
						' . ($widget->isDisabled() ? 'disabled="disabled" ' : '') . '
						/>
					';
        return $this->buildHtmlLabelWrapper($output);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildJs()
     */
    public function buildJs()
    {
        $this->buildJsEventScripts();
        return <<<JS

var hotElement = document.querySelector('#hot');
var hotElementContainer = hotElement.parentNode;
var hotSettings = {
  data: dataObject,
  columns: [
    {
      data: 'id',
      type: 'numeric',
      width: 40
    },
    {
      data: 'flag',
			renderer: flagRenderer
    },
    {
      data: 'currencyCode',
      type: 'text'
    },
    {
      data: 'currency',
      type: 'text'
    },
    {
      data: 'level',
      type: 'numeric',
      numericFormat: {
        pattern: '0.0000'
      }
    },
    {
      data: 'units',
      type: 'text'
    },
    {
      data: 'asOf',
      type: 'date',
      dateFormat: 'MM/DD/YYYY'
    },
    {
      data: 'onedChng',
      type: 'numeric',
      numericFormat: {
        pattern: '0.00%'
      }
    }
  ],
  stretchH: 'all',
  width: 880,
  autoWrapRow: true,
  height: 487,
  maxRows: 22,
  manualRowResize: true,
  manualColumnResize: true,
  rowHeaders: true,
  colHeaders: [
    'ID',
    'Country',
    'Code',
    'Currency',
    'Level',
    'Units',
    'Date',
    'Change'
  ],
  manualRowMove: true,
  manualColumnMove: true,
  contextMenu: true,
  filters: true,
  dropdownMenu: true
};
var hot = new Handsontable(hotElement, hotSettings);

JS;
    }
    
    /**
     * Returns JS scripts for event handling like live references, onChange-handlers,
     * disable conditions, etc.
     * 
     * @return string
     */
    protected function buildJsEventScripts()
    {
        return <<<JS

    // Event scripts for {$this->getId()}
    $(function() { 
        try {
            {$this->buildJsLiveReference()}
        } catch (e) {
            console.warn('Failed to update live reference: ' + e);
        }
        {$this->buildJsOnChangeHandler()}
        {$this->buildJsDisableConditionInitializer()}
    });

JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiAbstractElement::buildJsInitOptions()
     */
    public function buildJsInitOptions()
    {
        return $this->buildJsDataOptions();
    }

    /**
     * 
     * @return string
     */
    protected function buildJsDataOptions()
    {
        $options = '';
        
        if ($this->getOnChangeScript()) {
            $options .= "\n" . 'onChange: function(newValue, oldValue) {$(this).trigger("change");}';
        }
        
        return $options;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        return $this->getElementType() . '("setValue", ' . $value . ').trigger("change")';
    }

    /**
     * 
     * @return string
     */
    protected function buildJsOnChangeHandler()
    {
        if ($this->getOnChangeScript()) {
            return "$('#" . $this->getId() . "').change(function(event){" . $this->getOnChangeScript() . "});";
        } else {
            return '';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter($action, $custom_body_js)
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if ($this->getWidget()->isDisplayOnly()) {
            return '{}';
        } else {
            return parent::buildJsDataGetter($action);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValidator()
     */
    function buildJsValidator()
    {
        $widget = $this->getWidget();
        
        $must_be_validated = ! ($widget->isHidden() || $widget->isReadonly() || $widget->isDisabled() || $widget->isDisplayOnly());
        if ($must_be_validated) {
            $output = "$('#{$this->getId()}').{$this->getElementType()}('isValid')";
        } elseif ($widget->isRequired()) {
            $output = '(' . $this->buildJsValueGetter() . ' === "" ? false : true)';
        } else {
            $output = 'true';
        }
        
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait::buildJsDisableCondition()
     */
    public function buildJsDisableCondition()
    {
        $output = '';
        $widget = $this->getWidget();
        
        if (($condition = $widget->getDisableCondition()) && $condition->hasProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            if ($linked_element) {
                switch ($condition->getProperty('comparator')) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $enable_widget_script = $widget->isDisabled() ? '' : $this->buildJsEnabler() . ';';
                        
                        $output = <<<JS

						if ({$linked_element->buildJsValueGetter($link->getTargetColumnId())} {$condition->getProperty('comparator')} "{$condition->getProperty('value')}") {
							{$this->buildJsDisabler()};
						} else {
							{$enable_widget_script}
						}
JS;
                        break;
                    case EXF_COMPARATOR_IN: // [
                    case EXF_COMPARATOR_NOT_IN: // ![
                    case EXF_COMPARATOR_IS: // =
                    default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
                }
            }
        }
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsEnabler()
     */
    public function buildJsEnabler()
    {
        return '$("#' . $this->getId() . '").' . $this->getElementType() . '("enable").' . $this->getElementType() . '("validate")';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDisabler()
     */
    public function buildJsDisabler()
    {
        return '$("#' . $this->getId() . '").' . $this->getElementType() . '("disable")';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiText::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 1) . 'px';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiValue::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-input';
    }
}
?>