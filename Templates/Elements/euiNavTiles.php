<?php
namespace exface\JEasyUiTemplate\Templates\Elements;

use exface\Core\Widgets\NavTiles;

/**
 * 
 * @method NavTiles getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class euiNavTiles extends euiWidgetGrid 
{
    protected function buildHtmlGridWrapper(string $contentHtml) : string
    {
        $grid = parent::buildHtmlGridWrapper($contentHtml);
        return <<<HTML

    <div class="exf-navtiles">
         {$grid}
    </div>

HTML;
    }
         
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUiTemplate\Templates\Elements\euiWidgetGrid::getDefaultColumnNumber()
     */
    public function getDefaultColumnNumber()
    {
        return $this->getTemplate()->getConfig()->getOption("WIDGET.TILECONTAINER.COLUMNS_BY_DEFAULT");
    }
}