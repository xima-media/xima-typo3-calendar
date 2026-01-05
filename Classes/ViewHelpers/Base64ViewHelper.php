<?php

namespace Xima\XimaTypo3Calendar\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class Base64ViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function render(): string
    {
        $url = htmlspecialchars_decode($this->renderChildren() ?? '');
        if (!GeneralUtility::isValidUrl($url)) {
            return '';
        }

        $fileContent = GeneralUtility::getUrl($url);
        if (!$fileContent) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($fileContent);
    }
}
