<?php

namespace App\Services\Common;

use DOMElement;
use DOMNode;
use DOMXPath;

class HtmlParserService
{
    public function mapSiteHadithBlock(DOMXPath $xpath, DOMElement $container, array $options = []): array
    {
        $removeHtml = $options['removeHTML'] ?? true;
        $includeAlternate = $options['includeAlternate'] ?? true;
        $cleanRegex = $options['hadithCleanRegex'] ?? '/\d+\s+-/u';
        $hadithNode = $options['hadithNode'] ?? $this->firstElementChild($container);
        $infoNode = $options['infoNode'] ?? $this->secondElementChild($container) ?? $container;

        $hadithRaw = $hadithNode ? ($removeHtml ? trim($hadithNode->textContent) : trim($this->innerHtml($hadithNode))) : '';
        $hadith = trim((string) preg_replace($cleanRegex, '', $hadithRaw));

        $parsedInfo = $this->parseHadithInfo($xpath, $infoNode instanceof DOMElement ? $infoNode : $container);

        $similarHadithDorar = $this->findAttr($xpath, './/a[contains(@href,"?sims=1")]', $container, 'href');
        $alternateHadithSahihDorar = $includeAlternate
            ? $this->findAttr($xpath, './/a[contains(@href,"?alts=1")]', $container, 'href')
            : null;
        $usulHadithDorar = $this->findAttr($xpath, './/a[contains(@href,"?osoul=1")]', $container, 'href');
        $hadithId = $this->findAttr($xpath, './/a[@tag]', $container, 'tag');
        $categories = $this->parseHadithCategories($xpath, $container);

        return [
            'hadith' => $hadith,
            'rawi' => $parsedInfo['rawi'],
            'mohdith' => $parsedInfo['mohdith'],
            'mohdithId' => $parsedInfo['mohdithId'],
            'book' => $parsedInfo['book'],
            'bookId' => $parsedInfo['bookId'],
            'numberOrPage' => $parsedInfo['numberOrPage'],
            'grade' => $parsedInfo['grade'],
            'explainGrade' => $parsedInfo['explainGrade'],
            'takhrij' => $parsedInfo['takhrij'],
            'hadithId' => $hadithId,
            'categories' => $categories,
            'hasSimilarHadith' => (bool) $similarHadithDorar,
            'hasAlternateHadithSahih' => (bool) $alternateHadithSahihDorar,
            'hasUsulHadith' => (bool) $usulHadithDorar,
            'similarHadithDorar' => $similarHadithDorar,
            'alternateHadithSahihDorar' => $alternateHadithSahihDorar,
            'usulHadithDorar' => $usulHadithDorar,
            'urlToGetSimilarHadith' => $similarHadithDorar ? '/v1/site/hadith/similar/'.$hadithId : null,
            'urlToGetAlternateHadithSahih' => $alternateHadithSahihDorar ? '/v1/site/hadith/alternate/'.$hadithId : null,
            'urlToGetUsulHadith' => $usulHadithDorar ? '/v1/site/hadith/usul/'.$hadithId : null,
            'hasSharhMetadata' => (bool) $parsedInfo['sharhId'],
            'sharhMetadata' => $parsedInfo['sharhId'] ? [
                'id' => $parsedInfo['sharhId'],
                'isContainSharh' => false,
                'urlToGetSharh' => '/v1/site/sharh/'.$parsedInfo['sharhId'],
            ] : null,
        ];
    }

    public function mapApiHadithInfo(DOMXPath $xpath, DOMElement $info, bool $removeHtml): array
    {
        $hadithNode = $info->previousSibling;
        while ($hadithNode && !($hadithNode instanceof DOMElement)) {
            $hadithNode = $hadithNode->previousSibling;
        }

        $hadithRaw = $hadithNode
            ? ($removeHtml ? trim($hadithNode->textContent) : trim($this->innerHtml($hadithNode)))
            : '';

        $hadith = trim((string) preg_replace('/\d+\s*-/u', '', $hadithRaw));

        $subtitles = $xpath->query('.//*[contains(@class,"info-subtitle")]', $info) ?: [];
        $values = [];

        foreach ($subtitles as $subtitle) {
            $node = $subtitle->nextSibling;
            while ($node && trim((string) $node->textContent) === '') {
                $node = $node->nextSibling;
            }
            $values[] = $node ? trim((string) $node->textContent) : '';
        }

        return [
            'hadith' => $hadith,
            'rawi' => $values[0] ?? '',
            'mohdith' => $values[1] ?? '',
            'book' => $values[2] ?? '',
            'numberOrPage' => $values[3] ?? '',
            'grade' => $values[4] ?? '',
        ];
    }

    public function extractUsulSources(DOMXPath $xpath): array
    {
        $result = [];
        $articles = $xpath->query('//article') ?: [];

        foreach ($articles as $index => $article) {
            if ($index === 0 || !($article instanceof DOMElement)) {
                continue;
            }

            $h5 = $this->findNode($xpath, './/h5', $article);
            if (!$h5 instanceof DOMElement) {
                continue;
            }

            $source = $this->findText($xpath, './/span[contains(@style,"color:maroon")]', $h5);
            $chain = $this->findText($xpath, './/span[contains(@style,"color:blue")]', $h5);
            $full = trim($h5->textContent);
            if ($source !== '') {
                $full = trim(str_replace($source, '', $full));
            }
            if ($chain !== '') {
                $full = trim(str_replace($chain, '', $full));
            }

            $hadithText = trim((string) preg_replace('/^[،,.\s]+/u', '', $full));

            $result[] = [
                'source' => $source,
                'chain' => $chain,
                'hadithText' => $hadithText,
            ];
        }

        return $result;
    }

    public function parseHadithInfo(DOMXPath $xpath, DOMElement $infoElement): array
    {
        $result = [
            'rawi' => '',
            'mohdith' => '',
            'book' => '',
            'numberOrPage' => '',
            'grade' => '',
            'explainGrade' => '',
            'takhrij' => '',
            'mohdithId' => null,
            'bookId' => null,
            'sharhId' => null,
        ];

        $labelsMap = [
            'rawi' => 'الراوي',
            'mohdith' => 'المحدث',
            'book' => 'المصدر',
            'numberOrPage' => 'الصفحة أو الرقم',
            'grade' => 'درجة الحديث',
            'explainGrade' => 'خلاصة حكم المحدث',
            'takhrij' => 'التخريج',
        ];

        $strongs = $xpath->query('.//strong', $infoElement) ?: [];
        foreach ($strongs as $strong) {
            if (!($strong instanceof DOMElement)) {
                continue;
            }

            $label = trim(str_replace('|', '', explode(':', (string) $strong->textContent)[0]));
            foreach ($labelsMap as $key => $expectedLabel) {
                if (str_contains($label, $expectedLabel)) {
                    $span = $this->findNode($xpath, './/span', $strong);
                    if ($span) {
                        $result[$key] = trim((string) $span->textContent);
                    }
                }
            }
        }

        $mohdithCardLink = $this->findAttr($xpath, './/a[@view-card="mhd"]', $infoElement, 'card-link');
        if ($mohdithCardLink && preg_match('/\d+/', $mohdithCardLink, $match)) {
            $result['mohdithId'] = $match[0];
        }

        $bookCardLink = $this->findAttr($xpath, './/a[@view-card="book"]', $infoElement, 'card-link');
        if ($bookCardLink && preg_match('/\d+/', $bookCardLink, $match)) {
            $result['bookId'] = $match[0];
        }

        $sharhId = $this->findAttr($xpath, './/a[@xplain]', $infoElement, 'xplain');
        if ($sharhId && $sharhId !== '0') {
            $result['sharhId'] = $sharhId;
        }

        if ($result['grade'] === '' && $result['explainGrade'] !== '') {
            $result['grade'] = $result['explainGrade'];
        }

        return $result;
    }

    public function parseHadithCategories(DOMXPath $xpath, DOMElement $container): array
    {
        $categories = [];
        $links = $xpath->query('.//a[contains(@href,"/hadith-category/cat/")]', $container) ?: [];

        foreach ($links as $link) {
            if (!($link instanceof DOMElement)) {
                continue;
            }

            $href = (string) $link->getAttribute('href');
            if (!preg_match('#/hadith-category/cat/([^/?\#]+)#', $href, $match)) {
                continue;
            }

            $id = trim($match[1]);
            $name = trim((string) $link->textContent);
            if ($id !== '' && $name !== '') {
                $categories[] = ['id' => $id, 'name' => $name];
            }
        }

        return $categories;
    }

    public function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private function firstElementChild(DOMElement $node): ?DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function secondElementChild(DOMElement $node): ?DOMElement
    {
        $found = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $found++;
                if ($found === 2) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function findNode(DOMXPath $xpath, string $query, DOMElement $scope): ?DOMElement
    {
        $node = $xpath->query($query, $scope)?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function findAttr(DOMXPath $xpath, string $query, DOMElement $scope, string $attr): ?string
    {
        $node = $this->findNode($xpath, $query, $scope);
        if (!$node) {
            return null;
        }

        $value = trim((string) $node->getAttribute($attr));

        return $value === '' ? null : $value;
    }

    private function findText(DOMXPath $xpath, string $query, DOMElement $scope): string
    {
        $node = $this->findNode($xpath, $query, $scope);

        return $node ? trim((string) $node->textContent) : '';
    }
}
