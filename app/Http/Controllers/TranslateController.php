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

    public function saveApproved(Request $request)
    {
        $jobId = $request->get('jobId');
        $lines = $request->get('lines');
        $download = $request->get('download');

        if (!$jobId) {
            throw new \Exception('jobId is missing');
        }

        if (!$lines) {
            throw new \Exception('lines is missing');
        }

        $jobXml = $this->loadAndCache('https://visualdata.sferalabs.com/webservice/jobs/' . $jobId);

        $job = new \DomDocument('1.0', 'utf-8');
        $job->loadXML($jobXml);
        $xpath1 = new \DOMXpath($job);
        if ($node = $xpath1->query('//target_subtitle/url')) {
            $rusSubsUrl = $node->item(0)->nodeValue;
        } else {
            throw new \Exception('cannot find //target_subtitle/url');
        }
        $rusSubs = $this->loadAndCache($rusSubsUrl);

        $doc = new \DomDocument();
        $doc->loadXML($rusSubs);
        foreach ($doc->getElementsByTagName('p') as $i => $node) {
            /** @var \DOMElement $node */
            $line = array_get($lines, $i);
            if (!$line) {
                continue;
            }
            if (array_get($line, 'approveGoogle')) {
                $translation = array_get($line, 'translationGoogle');
            } else {
                $translation = array_get($line, 'translationYandex');
            }
            if (!trim($translation)) {
                continue;
            }
            while ($node->hasChildNodes()) {
                $node->removeChild($node->firstChild);
            }
            $translationLines = explode(PHP_EOL, $translation);
            if (count($translationLines) > 2) {
                $translationLines = [
                    $translationLines[0],
                    implode(' ', array_slice($translationLines, 1))
                ];
            }

            if ($line['isItalic']) {
                $subNode = $doc->createElement("span");
                $subNode->setAttribute('tts:fontStyle', 'italic');
                foreach ($translationLines as $j => $translationLine) {
                    $subNode->appendChild($doc->createTextNode($translationLine));
                    if ($j + 1 != count($translationLines)) {
                        $subNode->appendChild($doc->createElement('br'));
                    }
                }
                $node->appendChild($subNode);
            } else {
                foreach ($translationLines as $j => $translationLine) {
                    $node->appendChild($doc->createTextNode($translationLine));
                    if ($j + 1 != count($translationLines)) {
                        $node->appendChild($doc->createElement('br'));
                    }
                }
            }
        }
        $newXml = $doc->saveXML();
        $newXml = preg_replace('~<p ([^>]*)/>(\s*)$~m', '<p $1></p>$2', $newXml);

        if ($download) {
            $filename = 'translations-' . $request->get('jobId') . '.xml';
            $doc->formatOutput = true;
            $doc->preserveWhiteSpace = false;

            $response = response($newXml, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Filename' => $filename,
            ]);
        } else {

            $this->submitTranslations($jobId, $newXml);

            $response = response()->json([
                'success' => true,
            ]);
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Exception
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

        if ($node = $xpath->query('//source_subtitle/url')) {
            $engSubsUrl = $node->item(0)->nodeValue;
            $rusSubsUrl = $xpath->query('//target_subtitle/url')->item(0)->nodeValue;
        } else {
            throw new \Exception('cannot find //source_subtitle/url');
        }

        $engSubs = $this->loadAndCache($engSubsUrl);
        $rusSubs = $this->loadAndCache($rusSubsUrl);
        $translations = $this->getReadyTranslations($rusSubs);

        $dom = new Dom;
        $dom->load($engSubs);

        $lines = [];
        foreach ($dom->find('body div p') as $i => $node) {
            /** @var Dom\HtmlNode $node */
            $line = [];
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
        $key = 'yulia7.' . md5($url); // FIXME - never recaches :-/
//        return \Cache::remember($key, $minutes, function () use ($url) {
            return $this->loadUrl($url);
//        });
    }

    private function loadUrl($url)
    {
        $sessionId = $this->sessionId;
        $opts = [
            'http' => [
                'method' => "GET",
                'header' => "Accept-language: en\r\n" .
                    "Cookie: PHPSESSID=$sessionId; ReleaseNotification_sessionID=$sessionId;\r\n",
            ],
        ];

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

    public function test(Request $request) {
        echo "<pre>very good!</pre>";
//        print_r($_SERVER);
//        print_r($_POST);
        die();
    }

    private function submitTranslations($jobId, $xml)
    {
        $debug = 0;

        \Log::info('Saving...', ['debug' => $debug]);

        $data = [
            'userJobId' => $jobId,
            'subtitleContent' => $xml,
            'subtitleType' => 'target',
        ];

        $ch = curl_init();
        $url1 = 'https://visualdata.sferalabs.com/webservice/simple/save';
        if ($debug) {
            $url1 = 'http://yulia-trans.app/test';
        }

        curl_setopt($ch, CURLOPT_URL, $url1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, 'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf/[[DYNAMIC]]/4'); // FIXME what is 4?
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Cookie: PHPSESSID={$this->sessionId}; ReleaseNotification_sessionID={$this->sessionId}; __utma=121129999.1513241323.1496301115.1502567661.1502607103.111; __utmb=121129999.8.10.1502607103; __utmc=121129999; __utmz=121129999.1496301115.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)",
            "Origin: https://visualdata.sferalabs.com",
            // "Accept-Encoding: gzip, deflate, br",
            "Accept-Language: en-US,en;q=0.8,ru;q=0.6,uk;q=0.4",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: */*",
            "X-Requested-With: ShockwaveFlash/26.0.0.151",
            "Connection: keep-alive",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $html = curl_exec($ch);
        curl_close($ch);

        \Log::info('Save1 is done', [
            'response' => $html,
        ]);

        $data = [
            'subtitleContent' => $xml,
            'userJobId' => $jobId,
            'current_words_number' => 0,
            'subtitleFormat' => 'dfxp',
            'current_box_time' => '155.675', // FIXME randomize?
            'demoMode' => 0,
            'progress' => '0.22002200220022', // FIXME calculate?
            'activeTime' => '0',
            'background' => '0',
            'subtitleType' => 'target',
            'current_minute' => '2.5945833333333335', // FIXME randomize?
        ];

        $ch = curl_init();
        $lentgh = strlen($xml);
        $url2 = 'https://visualdata.sferalabs.com/webservice/jobs/save?contentLength=' . $lentgh . '&secondaryContentLength=0';
        if ($debug) {
            $url2 = 'http://yulia-trans.app/test?contentLength=' . $lentgh;
        }
        curl_setopt($ch, CURLOPT_URL, $url2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, 'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Cookie: PHPSESSID={$this->sessionId}; ReleaseNotification_sessionID={$this->sessionId}; __utma=121129999.1513241323.1496301115.1502567661.1502607103.111; __utmb=121129999.8.10.1502607103; __utmc=121129999; __utmz=121129999.1496301115.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)",
            "Origin: https://visualdata.sferalabs.com",
            // "Accept-Encoding: gzip, deflate, br",
            "Accept-Language: en-US,en;q=0.8,ru;q=0.6,uk;q=0.4",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: */*",
            "X-Requested-With: ShockwaveFlash/26.0.0.151",
            "Connection: keep-alive",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $html = curl_exec($ch);
        curl_close($ch);

        \Log::info('Save2 is done', [
            'response' => $html,
        ]);

//        https://visualdata.sferalabs.com/webservice/simple/save
//        userJobId=158351&subtitleContent=%3C%3Fxml%20version
//        &subtitleType=target
//        -H
// 'Cookie: PHPSESSID=h98ufcbd8f3nkoelgjj7pq7im4; ReleaseNotification_sessionID=h98ufcbd8f3nkoelgjj7pq7im4; __utma=121129999.1513241323.1496301115.1502567661.1502607103.111; __utmb=121129999.8.10.1502607103; __utmc=121129999; __utmz=121129999.1496301115.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)'
// -H 'Origin: https://visualdata.sferalabs.com'
// -H 'Accept-Encoding: gzip, deflate, br'
// -H 'Accept-Language: en-US,en;q=0.8,ru;q=0.6,uk;q=0.4'
//    -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36'
//     -H 'Content-Type: application/x-www-form-urlencoded'
//     -H 'Accept: */*'
//    -H 'Referer: https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf/[[DYNAMIC]]/4'
//    -H 'X-Requested-With: ShockwaveFlash/26.0.0.151'
//    -H 'Connection: keep-alive'

//        -H 'Cookie: PHPSESSID=h98ufcbd8f3nkoelgjj7pq7im4; ReleaseNotification_sessionID=h98ufcbd8f3nkoelgjj7pq7im4; __utma=121129999.1513241323.1496301115.1502567661.1502607103.111; __utmb=121129999.8.10.1502607103; __utmc=121129999; __utmz=121129999.1496301115.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)'
// -H 'Origin: https://visualdata.sferalabs.com'
// -H 'Accept-Encoding: gzip, deflate, br' -H 'Accept-Language: en-US,en;q=0.8,ru;q=0.6,uk;q=0.4'
// -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36'
// -H 'Content-Type: application/x-www-form-urlencoded' -H 'Accept: */*' -H 'Referer: https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf'
// -H 'X-Requested-With: ShockwaveFlash/26.0.0.151'
// -H 'Connection: keep-alive'
//        https://visualdata.sferalabs.com/webservice/jobs/save?contentLength=336979&secondaryContentLength=0
//        subtitleContent=%3C%3Fxml%20version
//        &userJobId=158351
//&current%5Fwords%5Fnumber=0
//&subtitleFormat=dfxp
//&current%5Fbox%5Ftime=155%2E675
//&demoMode=0
//&progress=0%2E22002200220022
//&activeTime=0
//&background=0
//&subtitleType=target
//&current%5Fminute=2%2E5945833333333335
    }
}
