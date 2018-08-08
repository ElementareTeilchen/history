<?php
namespace AE\History\ViewHelpers;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Diff\Diff;
use Neos\Diff\Renderer\Html\HtmlArrayRenderer;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Neos\EventLog\Domain\Model\NodeEvent;

/**
 *
 */
class DiffViewHelper extends AbstractViewHelper
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


    /**
     * Tries to determine a label for the specified property
     *
     * @param string $propertyName
     * @param NodeType $nodeType
     *
     * @return string
     */
    protected function getPropertyLabel(string $propertyName, NodeType $nodeType) : string
    {
        $properties = $nodeType->getProperties();
        if (!isset($properties[$propertyName], $properties[$propertyName]['ui']['label'])) {
            return $propertyName;
        }

        return $properties[$propertyName]['ui']['label'];
    }

    /**
     * Renders a slimmed down representation of a property of the given node. The output will be HTML, but does not
     * contain any markup from the original content.
     *
     * Note: It's clear that this method needs to be extracted and moved to a more universal service at some point.
     * However, since we only implemented diff-view support for this particular controller at the moment, it stays
     * here for the time being. Once we start displaying diffs elsewhere, we should refactor the diff rendering part.
     *
     * @param mixed $propertyValue
     *
     * @return string
     */
    protected function renderSlimmedDownContent($propertyValue) : string
    {
        $content = '';
        if (\is_string($propertyValue)) {
            $contentSnippet = \preg_replace('/<br[^>]*>/', "\n", $propertyValue);
            $contentSnippet = \preg_replace('/<[^>]*>/', ' ', $contentSnippet);
            $contentSnippet = \str_replace('&nbsp;', ' ', $contentSnippet);
            $content = \trim(\preg_replace('/ {2,}/', ' ', $contentSnippet));
        }

        return $content;
    }

    /**
     * A workaround for some missing functionality in the Diff Renderer:
     *
     * This method will check if content in the given diff array is either completely new or has been completely
     * removed and wraps the respective part in <ins> or <del> tags, because the Diff Renderer currently does not
     * do that in these cases.
     *
     * @param array $diffArray
     *
     * @return void
     */
    protected function postProcessDiffArray(array &$diffArray) : void
    {
        foreach ($diffArray as $index => $blocks) {
            foreach ($blocks as $blockIndex => $block) {
                $baseLines = \trim(\implode('', $block['base']['lines']), " \t\n\r\0\xC2\xA0");
                $changedLines = \trim(\implode('', $block['changed']['lines']), " \t\n\r\0\xC2\xA0");
                if ($baseLines === '') {
                    foreach ($block['changed']['lines'] as $lineIndex => $line) {
                        $diffArray[$index][$blockIndex]['changed']['lines'][$lineIndex] = '<ins>' . $line . '</ins>';
                    }
                }
                if ($changedLines === '') {
                    foreach ($block['base']['lines'] as $lineIndex => $line) {
                        $diffArray[$index][$blockIndex]['base']['lines'][$lineIndex] = '<del>' . $line . '</del>';
                    }
                }
            }
        }
    }


    /**
     * Renders the difference between the original and the changed content of the given node and returns it, along
     * with meta information, in an array.
     *
     * @param NodeEvent $nodeEvent
     *
     * @return string
     */
    public function render(NodeEvent $nodeEvent) : string
    {
        $data = $nodeEvent->getData();
        $old = $data['old'];
        $new = $data['new'];
        /** @noinspection PhpUnhandledExceptionInspection */
        $nodeType = $this->nodeTypeManager->getNodeType($data['nodeType']);
        $changeNodePropertiesDefaults = $nodeType->getDefaultValuesForProperties();

        $renderer = new HtmlArrayRenderer();
        $changes = [];
        foreach ($new as $propertyName => $changedPropertyValue) {
            if (($old === null && empty($changedPropertyValue))
                || (isset($changeNodePropertiesDefaults[$propertyName])
                    && $changedPropertyValue === $changeNodePropertiesDefaults[$propertyName]
                )
            ) {
                continue;
            }

            $originalPropertyValue = ($old === null ? null : $old[$propertyName]);

            if (!\is_object($originalPropertyValue) && !\is_object($changedPropertyValue)) {
                $originalSlimmedDownContent = $this->renderSlimmedDownContent($originalPropertyValue);
                $changedSlimmedDownContent = $this->renderSlimmedDownContent($changedPropertyValue);

                $diff = new Diff(
                    \explode("\n", $originalSlimmedDownContent),
                    \explode("\n", $changedSlimmedDownContent),
                    ['context' => 1]
                );
                $diffArray = $diff->render($renderer);
                $this->postProcessDiffArray($diffArray);
                if (\count($diffArray) > 0) {
                    $changes[$propertyName] = [
                        'type' => 'text',
                        'propertyLabel' => $this->getPropertyLabel($propertyName, $nodeType),
                        'diff' => $diffArray,
                    ];
                }
            } elseif ($originalPropertyValue instanceof ImageInterface
                || $changedPropertyValue instanceof ImageInterface
            ) {
                $changes[$propertyName] = [
                    'type' => 'image',
                    'propertyLabel' => $this->getPropertyLabel($propertyName, $nodeType),
                    'original' => $originalPropertyValue,
                    'changed' => $changedPropertyValue,
                ];
            } elseif ($originalPropertyValue instanceof AssetInterface
                || $changedPropertyValue instanceof AssetInterface
            ) {
                $changes[$propertyName] = [
                    'type' => 'asset',
                    'propertyLabel' => $this->getPropertyLabel($propertyName, $nodeType),
                    'original' => $originalPropertyValue,
                    'changed' => $changedPropertyValue,
                ];
            } elseif ($originalPropertyValue instanceof \DateTime && $changedPropertyValue instanceof \DateTime) {
                if ($changedPropertyValue->getTimestamp() !== $originalPropertyValue->getTimestamp()) {
                    $changes[$propertyName] = [
                        'type' => 'datetime',
                        'propertyLabel' => $this->getPropertyLabel($propertyName, $nodeType),
                        'original' => $originalPropertyValue,
                        'changed' => $changedPropertyValue,
                    ];
                }
            }
        }
        $this->templateVariableContainer->add('changes', $changes);
        $content = $this->renderChildren();
        $this->templateVariableContainer->remove('changes');

        return $content;
    }
}
