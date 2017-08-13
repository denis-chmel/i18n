<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PHPHtmlParser\Dom;

/**
 * Class HomepageController
 * @package App\Http\Controllers\Website
 */
class TranslateController extends Controller
{
    protected $sessionId = 'h98ufcbd8f3nkoelgjj7pq7im4';

    public function google(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $origText = $request->get('text');
        $text = $origText;
//        $text = str_replace(PHP_EOL, '<br>', $text);
        $encodedText = urlencode($text);
//        $response = json_decode(file_get_contents("http://translate.google.com/translate_a/single?client=gtx&ie=UTF-8&oe=UTF-8&sl=$from&tl=$to&dt=t&q=$encodedText&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at"));
        $response = json_decode(file_get_contents("http://translate.google.com/translate_a/single?client=gtx&sl=$from&tl=$to&dt=t&q=$encodedText"));
        $translation = '';
        foreach ($response[0] as $a) {
            $translation .= $a[0];
        }

        $translation = preg_replace('~<br>\s*~', PHP_EOL, $translation);
        $translation = str_replace(' ...', '...', $translation);

        // if first original is lowercase make 1st translation letter lowercase
        $cleanOrigText = ltrim($origText, '\'');
        preg_match('/^[A-Za-z]/u', $cleanOrigText, $matches);
        $firstIsALetter = array_get($matches, 0);
        if ($firstIsALetter) {
            if ($firstIsALetter && $firstIsALetter === mb_strtolower($firstIsALetter)) {
                $translation = mb_lcfirst($translation);
            } else {
                $translation = mb_ucfirst($translation);
            }
        }
        $translation = preg_replace('/ -([А-Я])/', PHP_EOL . '-$1', $translation);
//        $translation = preg_replace('/\s*$/', '', $translation);
//        $translation = preg_replace('/^- /', '-', $translation);
        if ($request->get('debug')) {
            dd($origText, $text, $translation);
        }

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
//        dd($jobXml);

        $xml = new \DomDocument('1.0', 'utf-8');
        $xml->loadXML($jobXml);
        $xpath = new \DOMXpath($xml);

        $engSubsUrl = $xpath->query('//source_subtitle/url')->item(0)->nodeValue;
        $rusSubsUrl = $xpath->query('//target_subtitle/url')->item(0)->nodeValue;

        $engSubs = $this->loadAndCache($engSubsUrl);
        $rusSubs = $this->loadAndCache($rusSubsUrl);
        $translations = $this->getReadyTranslations($rusSubs);

        $dom = new Dom;
        $dom->load($engSubs);

        $lines = [];
        foreach ($dom->find('body div p') as $i => $node) {
            /** @var Dom\HtmlNode $node */
            $line = [];
            $line['begin'] = $node->getAttribute('begin');
            $line['end'] = $node->getAttribute('end');
            $line['editable'] = $node->getAttribute('ssroweditable') != 'false';
            $line['html'] = $node->innerHtml();
            $text = strip_tags(str_replace('<br />', PHP_EOL, $line['html']));
            $line['original'] = html_entity_decode(trim($text));
            $line['isItalic'] = str_contains($line['html'], 'tts:fontstyle="italic"');
            $line['translation'] = array_get($translations, $i, '');
            $line['translationYandex'] = '';
            $line['translationGoogle'] = '';
            $line['loadingYandex'] = false;
            $line['loadingGoogle'] = false;
            $line['approveYandex'] = false;
            $line['approveGoogle'] = false;
            $line['index'] = $i + 1;
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
        $sessionId = $this->sessionId;
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

    private function getReadyTranslations($targetXml)
    {
        $dom = new Dom;
        $dom->load($targetXml);
        $translations = [];
        foreach ($dom->find('body div p') as $i => $node) {
            /** @var Dom\HtmlNode $node */
            $text = $node->innerHtml();
            $text = str_replace('<br />', PHP_EOL, $text);
            $text = strip_tags($text);
            $text = html_entity_decode(trim($text));
            $translations[$i] = $text;
        }
        return $translations;
    }

}
