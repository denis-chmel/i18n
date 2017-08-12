<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PHPHtmlParser\Dom;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * Class HomepageController
 * @package App\Http\Controllers\Website
 */
class TranslateController extends Controller
{
    public function google(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $text = $request->get('text');
        $text = str_replace(PHP_EOL, '<br>', $text);
        $encodedText = urlencode($text);
        $response = json_decode(file_get_contents("http://translate.google.com/translate_a/single?client=gtx&ie=UTF-8&oe=UTF-8&sl=$from&tl=$to&dt=t&q=$encodedText&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at"));
        $translation = $response[0][0][0];
        $translation = preg_replace('~<br>\s*~', PHP_EOL, $translation);
        return response()->json([
            'translation' => $translation,
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'translations-' . $request->get('jobId') . '.xml';
        $lines = $request->get('lines');

        $root = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root/>');
        $root->addAttribute('movie', 'movie');
        $root->addAttribute('language', 'English');
        $reel = $root->addChild('reel');

        foreach ($lines as $i => $line) {
            if (array_get($line, 'approveGoogle')) {
                $translation = array_get($line, 'translationGoogle');
            } else {
                $translation = array_get($line, 'translationYandex');
            }
            $translation = str_replace(PHP_EOL, '|', $translation);
            $title = $reel->addChild('title', $translation);
            $title->addAttribute('start', $line['begin']);
            $title->addAttribute('end', $line['end']);
        }

        $dom = dom_import_simplexml($root)->ownerDocument;
        $dom->formatOutput = true;

        $response = response($dom->saveXML(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Filename' => $filename,
        ]);

        return $response;
      }

    /**
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $jobId = $request->get('jobId');
        if (!$jobId) {
            abort(404, 'must pass ?jobId=');
        }

        $jobXml = $this->loadAndCache('https://visualdata.sferalabs.com/webservice/jobs/' . $jobId);

        $xml = new \DomDocument('1.0', 'utf-8');
        $xml->loadXML($jobXml);
        $xpath = new \DOMXpath($xml);

        $engSubsUrl = $xpath->query('//source_subtitle/url')->item(0)->nodeValue;

        $engSubs = $this->loadAndCache($engSubsUrl);

        $dom = new Dom;
        $dom->load($engSubs);

        $lines = [];
        foreach ($dom->find('body div p') as $node) {
            /** @var Dom\HtmlNode $node */
            $line = [];
            $line['begin'] = $node->getAttribute('begin');
            $line['end'] = $node->getAttribute('end');
            $line['html'] = $node->innerHtml();
            $text = strip_tags(str_replace('<br />', PHP_EOL, $line['html']));
            $line['text'] = trim($text);
            $line['original'] = $line['text'];
            $line['translationYandex'] = '';
            $line['translationGoogle'] = '';
            $line['loadingYandex'] = false;
            $line['loadingGoogle'] = false;
            $line['approveYandex'] = false;
            $line['approveGoogle'] = false;
            $line['translation'] = '';
            $lines[] = $line;
        }

        return view('translate', [
            'lines' => $lines,
            'jobId' => $jobId,
            'bannedWords' => config('bannedWords.list'),
        ]);
    }

    private function loadAndCache($url, $minutes = 120)
    {
        $key = 'yulia.' . md5($url);
        return \Cache::remember($key, $minutes, function () use ($url) {
            return $this->loadUrl($url);
        });
    }

    private function loadUrl($url)
    {
        $sessionId = 'm6tu40vff81dfcqjvss40kiej2';
        $opts = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>"Accept-language: en\r\n" .
                    "Cookie: PHPSESSID=$sessionId; ReleaseNotification_sessionID=$sessionId;\r\n"
            )
        );

        $context = stream_context_create($opts);
        $content = file_get_contents($url, false, $context);

        return $content;
    }

}
