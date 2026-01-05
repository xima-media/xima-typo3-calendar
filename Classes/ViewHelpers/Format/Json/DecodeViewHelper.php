<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\ViewHelpers\Format\Json;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

/**
 * @link https://github.com/FluidTYPO3/vhs/blob/development/Classes/ViewHelpers/Format/Json/DecodeViewHelper.php
 */
class DecodeViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    public function initializeArguments(): void
    {
        $this->registerArgument('json', 'string', 'JSON string to decode');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     * @throws Exception
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): mixed
    {
        $json = $renderChildrenClosure();
        if (true === empty($json)) {
            return null;
        }
        $value = json_decode($json, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception('The provided argument is invalid JSON.', 1657018416);
        }

        return $value;
    }
}
