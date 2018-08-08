<?php
namespace AE\History\ViewHelpers;

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;

class NodeTypeIconViewHelper extends AbstractViewHelper
{
    /**
     * @var boolean
     */
    protected $escapeOutput = false;


    /**
     * @var NodeTypeManager
     *
     * @Flow\Inject
     */
    protected $nodeTypeManager;


    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param string $nodeType
     *
     * @return string
     */
    public function render(string $nodeType) : string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->nodeTypeManager->hasNodeType($nodeType)
            ? $this->nodeTypeManager->getNodeType($nodeType)->getConfiguration('ui.icon')
            : ''
        ;
    }
}
